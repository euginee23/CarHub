<?php

use App\Services\Assistant\CarHubAssistant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    public string $draft = '';

    /**
     * The conversation so far, oldest first. Only the server adds to it.
     *
     * @var array<int, array{role: string, text: string}>
     */
    #[Locked]
    public array $messages = [];

    /**
     * Whether the latest message is waiting for the assistant's reply.
     */
    #[Locked]
    public bool $awaitingReply = false;

    /**
     * Add the renter's message, or a suggested prompt, to the conversation and
     * ask the browser to fetch the reply in a second request so the message
     * shows straight away.
     */
    public function send(?string $prompt = null): void
    {
        if ($this->awaitingReply) {
            return;
        }

        if ($prompt !== null) {
            $this->draft = $prompt;
        }

        $this->draft = trim($this->draft);

        $this->validate([
            'draft' => ['required', 'string', 'max:'.config('carhub.assistant.max_message_length')],
        ], attributes: ['draft' => __('message')]);

        $limiterKey = $this->limiterKey();

        if (RateLimiter::tooManyAttempts($limiterKey, (int) config('carhub.assistant.messages_per_hour'))) {
            $this->addError('draft', __('You have sent a lot of messages. Please try again in :minutes minutes.', [
                'minutes' => max(1, (int) ceil(RateLimiter::availableIn($limiterKey) / 60)),
            ]));

            return;
        }

        RateLimiter::hit($limiterKey, 3600);

        $this->messages[] = ['role' => 'user', 'text' => $this->draft];
        $this->draft = '';
        $this->awaitingReply = true;

        $this->js('$wire.respond()');
    }

    /**
     * Ask the assistant to reply to the latest message.
     */
    public function respond(CarHubAssistant $assistant): void
    {
        if (! $this->awaitingReply) {
            return;
        }

        $this->messages[] = ['role' => 'assistant', 'text' => $assistant->reply($this->messages)];
        $this->awaitingReply = false;
    }

    /**
     * Start a new conversation.
     */
    public function clear(): void
    {
        $this->reset('messages', 'draft', 'awaitingReply');
        $this->resetErrorBag();
    }

    /**
     * Who the hourly message limit applies to: the account when signed in,
     * otherwise the visitor's IP address.
     */
    protected function limiterKey(): string
    {
        return 'assistant:'.(Auth::id() ?? request()->ip());
    }
}; ?>

@php
    $prompts = [
        __('Find me a 7-seater for the weekend'),
        __('What IDs do I need to book?'),
        __('How does payment work?'),
    ];
@endphp

<div x-data="{ open: false }" class="fixed bottom-(--fab-offset) end-5 z-50 flex flex-col items-end gap-3 transition-[bottom]">
    <div
        x-show="open"
        x-cloak
        x-transition.origin.bottom.right
        class="flex max-h-[min(36rem,calc(100dvh-7rem))] w-[min(22rem,calc(100vw-2.5rem))] flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl shadow-zinc-900/10"
        role="dialog"
        aria-label="{{ __('CarHub assistant') }}"
    >
        <div class="flex shrink-0 items-center gap-3 bg-linear-to-r from-brand-700 to-brand-500 px-4 py-3.5">
            <span class="flex size-9 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25">
                <svg class="size-4.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.75h4.5M12 3.75V7.5m-4.5 0h9A2.25 2.25 0 0 1 18.75 9.75v6A2.25 2.25 0 0 1 16.5 18h-3l-3 3v-3h-3a2.25 2.25 0 0 1-2.25-2.25v-6A2.25 2.25 0 0 1 7.5 7.5Z" />
                    <circle cx="9.75" cy="12.75" r="1" fill="currentColor" stroke="none" />
                    <circle cx="14.25" cy="12.75" r="1" fill="currentColor" stroke="none" />
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-white">{{ __('CarHub Assistant') }}</p>
                <p class="flex items-center gap-1.5 text-xs text-brand-100">
                    <span class="size-1.5 rounded-full bg-spark-300"></span>
                    {{ __('Usually replies instantly') }}
                </p>
            </div>
            @if ($messages !== [])
                <button type="button" wire:click="clear" class="rounded-lg px-2 py-1 text-xs font-medium text-white/80 transition-colors hover:bg-white/15 hover:text-white" data-test="assistant-clear">
                    {{ __('New chat') }}
                </button>
            @endif
            <button type="button" x-on:click="open = false" class="rounded-lg p-1.5 text-white/80 transition-colors hover:bg-white/15 hover:text-white">
                <span class="sr-only">{{ __('Close') }}</span>
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-zinc-50 px-4 py-4" aria-live="polite">
            <div class="max-w-[85%] rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-sm/6 text-zinc-700 shadow-sm ring-1 ring-black/5">
                {{ __('Hi! Tell me your dates and how many seats you need, and I will find vehicles that are free and show you the price.') }}
            </div>

            @if ($messages === [])
                <div class="space-y-2 pt-1">
                    @foreach ($prompts as $prompt)
                        <button
                            type="button"
                            wire:click="send(@js($prompt))"
                            class="block w-full rounded-xl border border-zinc-200 bg-white px-3.5 py-2 text-start text-sm text-zinc-600 transition-colors hover:border-brand-300 hover:text-brand-700"
                        >
                            {{ $prompt }}
                        </button>
                    @endforeach
                </div>
            @endif

            @foreach ($messages as $index => $message)
                <div
                    wire:key="assistant-message-{{ $index }}"
                    x-init="$nextTick(() => $el.parentElement.scrollTop = $el.parentElement.scrollHeight)"
                    @class([
                        'flex',
                        'justify-end' => $message['role'] === 'user',
                    ])
                >
                    @if ($message['role'] === 'user')
                        <div class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-br-md bg-brand-600 px-3.5 py-2.5 text-sm/6 text-white">{{ $message['text'] }}</div>
                    @else
                        <div @class([
                            'max-w-[85%] rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-sm/6 text-zinc-700 shadow-sm ring-1 ring-black/5',
                            '[&_p+p]:mt-2 [&_ul]:mt-1 [&_ul]:list-disc [&_ul]:ps-4 [&_ol]:mt-1 [&_ol]:list-decimal [&_ol]:ps-4 [&_li]:mt-0.5',
                            '[&_strong]:font-semibold [&_strong]:text-zinc-900',
                            '[&_a]:font-medium [&_a]:text-brand-600 [&_a]:underline [&_a]:underline-offset-2 hover:[&_a]:text-brand-700',
                        ])>
                            {!! Str::markdown($message['text'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    @endif
                </div>
            @endforeach

            @if ($awaitingReply)
                <div x-init="$nextTick(() => $el.parentElement.scrollTop = $el.parentElement.scrollHeight)" class="flex" data-test="assistant-typing">
                    <div class="flex items-center gap-1 rounded-2xl rounded-bl-md bg-white px-3.5 py-3 shadow-sm ring-1 ring-black/5">
                        <span class="sr-only">{{ __('The assistant is typing…') }}</span>
                        <span class="size-1.5 animate-bounce rounded-full bg-zinc-400"></span>
                        <span class="size-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:150ms]"></span>
                        <span class="size-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:300ms]"></span>
                    </div>
                </div>
            @endif
        </div>

        <form wire:submit="send" class="shrink-0 border-t border-zinc-200 bg-white px-4 py-3">
            <div class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 focus-within:border-brand-400">
                <input
                    type="text"
                    wire:model="draft"
                    maxlength="{{ config('carhub.assistant.max_message_length') }}"
                    placeholder="{{ __('Ask about a vehicle…') }}"
                    aria-label="{{ __('Message the assistant') }}"
                    @disabled($awaitingReply)
                    class="min-w-0 flex-1 bg-transparent text-sm text-zinc-900 outline-hidden placeholder:text-zinc-400 disabled:cursor-wait"
                />
                <button
                    type="submit"
                    @disabled($awaitingReply)
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white transition-colors hover:bg-brand-700 disabled:cursor-wait disabled:opacity-50"
                >
                    <span class="sr-only">{{ __('Send') }}</span>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                </button>
            </div>
            @error('draft')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1.5 text-[0.6875rem] text-zinc-400">{{ __('Answers are generated and may be wrong. Check the vehicle page before you book.') }}</p>
        </form>
    </div>

    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open ? 'true' : 'false'"
        class="flex size-14 items-center justify-center rounded-full bg-linear-to-br from-brand-600 to-brand-500 text-white shadow-lg shadow-brand-600/35 transition hover:scale-105 hover:shadow-xl hover:shadow-brand-600/40"
    >
        <span class="sr-only">{{ __('Open the CarHub assistant') }}</span>
        <svg x-show="! open" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.75h4.5M12 3.75V7.5m-4.5 0h9A2.25 2.25 0 0 1 18.75 9.75v6A2.25 2.25 0 0 1 16.5 18h-3l-3 3v-3h-3a2.25 2.25 0 0 1-2.25-2.25v-6A2.25 2.25 0 0 1 7.5 7.5Z" />
            <circle cx="9.75" cy="12.75" r="1" fill="currentColor" stroke="none" />
            <circle cx="14.25" cy="12.75" r="1" fill="currentColor" stroke="none" />
        </svg>
        <svg x-show="open" x-cloak class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
    </button>
</div>
