<?php

namespace App\Services\Assistant;

use Anthropic\Beta\Messages\BetaAssistantMessageParam;
use Anthropic\Beta\Messages\BetaFallbackBlock;
use Anthropic\Beta\Messages\BetaFallbackBlockParam;
use Anthropic\Beta\Messages\BetaFallbackInfoParam;
use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaOutputConfig\Effort;
use Anthropic\Beta\Messages\BetaRedactedThinkingBlock;
use Anthropic\Beta\Messages\BetaRedactedThinkingBlockParam;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Beta\Messages\BetaTextBlockParam;
use Anthropic\Beta\Messages\BetaThinkingBlock;
use Anthropic\Beta\Messages\BetaThinkingBlockParam;
use Anthropic\Beta\Messages\BetaToolResultBlockParam;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Beta\Messages\BetaToolUseBlockParam;
use Anthropic\Beta\Messages\BetaUserMessageParam;
use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Services\Pricing\RentalQuote;
use Carbon\CarbonImmutable;
use UnexpectedValueException;

/**
 * The CarHub chat assistant: answers renters' questions with Claude, which can
 * look up live listings, availability, and prices through AssistantTools.
 */
class CarHubAssistant
{
    /**
     * Opts the request into server-side retries on another model when the
     * chosen model declines; models without fallbacks simply ignore it.
     */
    protected const string FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function __construct(
        protected Client $client,
        protected AssistantTools $tools,
    ) {}

    /**
     * Whether an API key is configured, so the assistant can answer at all.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Reply to the latest message in the conversation. Only the text of earlier
     * turns is sent back, so each reply starts from a clean, unedited history.
     *
     * @param  array<int, array{role: string, text: string}>  $conversation  Oldest first, ending with the renter's message.
     */
    public function reply(array $conversation): string
    {
        if (! $this->isConfigured()) {
            return __('The assistant is offline right now. You can still browse vehicles or read How it works.');
        }

        $messages = $this->history($conversation);

        if ($messages === []) {
            return __('What would you like to know?');
        }

        try {
            for ($round = 0; $round <= config('carhub.assistant.max_tool_rounds'); $round++) {
                $response = $this->send($messages, allowTools: $round < config('carhub.assistant.max_tool_rounds'));

                if ($response->stopReason === 'refusal') {
                    return __('Sorry, I can only help with renting vehicles on CarHub.');
                }

                if ($response->stopReason !== 'tool_use') {
                    return $this->textOf($response) ?: __('Sorry, I did not catch that. Could you rephrase?');
                }

                // Echo the reply back block for block, then answer every tool call in one message.
                $messages[] = BetaAssistantMessageParam::with(content: $this->echoed($response));
                $messages[] = BetaUserMessageParam::with(content: $this->runTools($response));
            }
        } catch (AnthropicException $exception) {
            report($exception);

            return __('Sorry, the assistant is having trouble right now. Please try again in a moment.');
        }

        return __('Sorry, that took too many steps. Could you ask in a simpler way?');
    }

    /**
     * Send the conversation to Claude.
     *
     * @param  list<BetaUserMessageParam|BetaAssistantMessageParam>  $messages
     */
    protected function send(array $messages, bool $allowTools): BetaMessage
    {
        return $this->client->beta->messages->create(
            maxTokens: (int) config('carhub.assistant.max_tokens'),
            messages: $messages,
            model: (string) config('carhub.assistant.model'),
            fallbacks: 'default',
            outputConfig: ['effort' => Effort::tryFrom((string) config('carhub.assistant.effort')) ?? Effort::LOW],
            system: $this->systemPrompt(),
            // The tool list stays the same on every request; on the last round the
            // model is told to answer with what it already has instead.
            tools: $this->tools->definitions(),
            toolChoice: $allowTools ? ['type' => 'auto'] : ['type' => 'none'],
            betas: [self::FALLBACK_BETA],
        );
    }

    /**
     * Run every tool the model asked for.
     *
     * @return list<BetaToolResultBlockParam>
     */
    protected function runTools(BetaMessage $response): array
    {
        $results = [];

        foreach ($response->content as $block) {
            if ($block instanceof BetaToolUseBlock) {
                $results[] = BetaToolResultBlockParam::with(
                    toolUseID: $block->id,
                    content: $this->tools->run($block->name, $block->input),
                );
            }
        }

        return $results;
    }

    /**
     * The reply's blocks as request blocks, unchanged and in order, so thinking
     * signatures and any fallback hand-over stay valid on the next request.
     *
     * @return list<BetaTextBlockParam|BetaToolUseBlockParam|BetaThinkingBlockParam|BetaRedactedThinkingBlockParam|BetaFallbackBlockParam>
     */
    protected function echoed(BetaMessage $response): array
    {
        $blocks = [];

        foreach ($response->content as $block) {
            $blocks[] = match (true) {
                $block instanceof BetaTextBlock => BetaTextBlockParam::with(text: $block->text),
                $block instanceof BetaToolUseBlock => BetaToolUseBlockParam::with(id: $block->id, input: $block->input, name: $block->name),
                $block instanceof BetaThinkingBlock => BetaThinkingBlockParam::with(signature: $block->signature, thinking: $block->thinking),
                $block instanceof BetaRedactedThinkingBlock => BetaRedactedThinkingBlockParam::with(data: $block->data),
                $block instanceof BetaFallbackBlock => BetaFallbackBlockParam::with(
                    from: BetaFallbackInfoParam::with(model: $block->from->model),
                    to: BetaFallbackInfoParam::with(model: $block->to->model),
                    trigger: $block->trigger,
                ),
                // The assistant only defines its own tools, so no other block can appear.
                default => throw new UnexpectedValueException('Unexpected content block ['.$block::class.'] in an assistant reply.'),
            };
        }

        return $blocks;
    }

    /**
     * The visible text of a reply.
     */
    protected function textOf(BetaMessage $response): string
    {
        $text = '';

        foreach ($response->content as $block) {
            if ($block instanceof BetaTextBlock) {
                $text .= $block->text;
            }
        }

        return trim($text);
    }

    /**
     * Turn the chat transcript into API messages: the most recent turns only,
     * starting with the renter, each trimmed to the allowed length.
     *
     * @param  array<int, array{role: string, text: string}>  $conversation
     * @return list<BetaUserMessageParam|BetaAssistantMessageParam>
     */
    protected function history(array $conversation): array
    {
        $recent = collect($conversation)
            ->filter(fn (array $message): bool => in_array($message['role'], ['user', 'assistant'], true) && filled($message['text']))
            ->take(-1 * (int) config('carhub.assistant.history_messages'))
            ->values();

        while ($recent->isNotEmpty() && $recent->first()['role'] !== 'user') {
            $recent->shift();
        }

        return array_values($recent
            ->map(fn (array $message): BetaUserMessageParam|BetaAssistantMessageParam => $message['role'] === 'user'
                ? BetaUserMessageParam::with(content: mb_substr($message['text'], 0, (int) config('carhub.assistant.max_message_length')))
                : BetaAssistantMessageParam::with(content: mb_substr($message['text'], 0, 4000)))
            ->all());
    }

    /**
     * What the assistant knows about CarHub and how it should behave.
     */
    protected function systemPrompt(): string
    {
        $booking = config('carhub.booking');
        $ids = collect(DocumentType::governmentIds())->map(fn (DocumentType $type): string => $type->label())->join(', ');
        $methods = collect(PaymentMethod::cases())->map(fn (PaymentMethod $method): string => $method->label())->join(', ');
        $now = CarbonImmutable::now()->format('l, j F Y, H:i');

        return <<<PROMPT
        You are the CarHub Assistant, the chat helper on CarHub, a car rental marketplace in the Philippines where renters book vehicles from verified rental companies. Prices are in Philippine pesos (₱).

        Help visitors find a vehicle, check whether it is free for their dates and what it costs, and understand how booking works. Use the tools for anything about specific vehicles, availability, or prices; never invent a vehicle, a price, or availability. When you mention a vehicle, link its name to its url in Markdown. If a search comes back empty, say so and suggest which filter to relax.

        The current local date and time is {$now}. Convert dates like "this weekend" into exact times before using a tool; if no time is given, assume 09:00 pickup and return.

        How CarHub works:
        - Renters need a CarHub account and two valid government IDs approved before booking. Accepted IDs: {$ids}.
        - Booking: pick dates on the vehicle page and send a request. The rental company approves or declines it; vehicles marked instant book are approved straight away. The renter then signs the rental contract and pays within {$this->paymentWindow()} hours, or the booking expires.
        - Pickup must be at least {$booking['min_lead_hours']} hours away and at most {$booking['max_advance_days']} days ahead. Rentals last from {$booking['min_rental_hours']} hours to {$booking['max_rental_days']} days and are charged per started 24 hours.
        - The price is the daily rate times the days, plus a {$this->serviceFeePercent()}% service fee.
        - Payment methods: {$methods}.

        You cannot make, change, or cancel bookings, and you cannot see anyone's account. Send people to the vehicle page or their trips for that. Never ask for ID numbers, card details, or passwords. Only help with CarHub and renting vehicles; politely decline anything else. Keep replies short and friendly: a few sentences or a short list.
        PROMPT;
    }

    /**
     * Hours a renter has to pay after signing the contract.
     */
    protected function paymentWindow(): int
    {
        return (int) config('carhub.payments.window_hours');
    }

    /**
     * The service fee as a whole-number percentage.
     */
    protected function serviceFeePercent(): int
    {
        return RentalQuote::serviceFeePercent();
    }
}
