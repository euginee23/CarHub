<?php

use Anthropic\Client;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use App\Models\VehicleBlackout;
use App\Services\Assistant\AssistantTools;
use App\Services\Assistant\CarHubAssistant;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Answer the assistant's API calls with the given replies, in order, and
 * return the log every request is recorded into.
 *
 * @param  array<int, array<string, mixed>|Response>  $replies
 */
function fakeClaude(array $replies): ArrayObject
{
    $requests = new ArrayObject;

    $stack = HandlerStack::create(new MockHandler(array_map(
        fn (array|Response $reply): Response => $reply instanceof Response
            ? $reply
            : new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($reply)),
        $replies,
    )));
    $stack->push(Middleware::history($requests));

    config(['services.anthropic.key' => 'test-key']);

    app()->instance(Client::class, new Client(
        apiKey: 'test-key',
        requestOptions: ['transporter' => new GuzzleClient(['handler' => $stack]), 'maxRetries' => 0],
    ));

    return $requests;
}

/**
 * A Messages API response.
 *
 * @param  array<int, array<string, mixed>>  $content
 * @return array<string, mixed>
 */
function claudeMessage(array $content, string $stopReason = 'end_turn'): array
{
    return [
        'id' => 'msg_'.fake()->uuid(),
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => $content,
        'stop_reason' => $stopReason,
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
    ];
}

/**
 * The JSON body of a recorded request.
 *
 * @return array<string, mixed>
 */
function requestBody(ArrayObject $requests, int $index): array
{
    return json_decode((string) $requests[$index]['request']->getBody(), true);
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00'));
});

test('the assistant looks up live listings with a tool and replies with what it found', function () {
    $suv = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Fortuner', 'type' => VehicleType::Suv, 'seats' => 7, 'price_per_day' => 3000]);
    Vehicle::factory()->create(['type' => VehicleType::Sedan, 'seats' => 5]);

    $requests = fakeClaude([
        claudeMessage([
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'search_vehicles', 'input' => ['body_type' => 'SUV', 'min_seats' => 7, 'pickup_at' => '2026-10-03 09:00', 'return_at' => '2026-10-05 09:00']],
        ], 'tool_use'),
        claudeMessage([['type' => 'text', 'text' => 'The [Toyota Fortuner](https://carhub.test) is free for ₱6,900.']]),
    ]);

    $reply = app(CarHubAssistant::class)->reply([['role' => 'user', 'text' => 'Find me a 7-seater SUV this weekend']]);

    expect($reply)->toBe('The [Toyota Fortuner](https://carhub.test) is free for ₱6,900.')
        ->and($requests)->toHaveCount(2);

    $first = requestBody($requests, 0);

    expect($first['model'])->toBe(config('carhub.assistant.model'))
        ->and($first['fallbacks'])->toBe('default')
        ->and(array_column($first['tools'], 'name'))->toBe(['search_vehicles', 'check_vehicle'])
        ->and($first['tools'][0]['input_schema']['properties']['body_type']['enum'])->toContain('SUV', 'Van')
        ->and($first['tools'][1]['input_schema']['required'])->toBe(['slug', 'pickup_at', 'return_at'])
        ->and($first['output_config'])->toBe(['effort' => config('carhub.assistant.effort')])
        ->and($first['system'])->toContain('Thursday, 1 October 2026')
        ->and($first['messages'])->toBe([['role' => 'user', 'content' => 'Find me a 7-seater SUV this weekend']])
        ->and($requests[0]['request']->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01');

    $second = requestBody($requests, 1);
    $toolResult = $second['messages'][2]['content'][0];
    $found = json_decode($toolResult['content'], true)['vehicles'];

    expect($second['messages'][1]['content'][0]['id'])->toBe('toolu_1')
        ->and($toolResult['tool_use_id'])->toBe('toolu_1')
        ->and($found)->toHaveCount(1)
        ->and($found[0]['slug'])->toBe($suv->slug)
        ->and($found[0]['quote'])->toBe(['days' => 2, 'subtotal' => 6000, 'service_fee' => 900, 'total' => 6900]);
});

test('vehicle search leaves out vehicles that are blocked for the trip', function () {
    $free = Vehicle::factory()->create(['type' => VehicleType::Van, 'seats' => 12]);
    $blocked = Vehicle::factory()->create(['type' => VehicleType::Van, 'seats' => 12]);
    VehicleBlackout::factory()->for($blocked)->create(['starts_on' => '2026-10-04', 'ends_on' => '2026-10-04']);

    $result = json_decode(app(AssistantTools::class)->run('search_vehicles', [
        'body_type' => 'Van',
        'pickup_at' => '2026-10-03 09:00',
        'return_at' => '2026-10-05 09:00',
    ]), true);

    expect(array_column($result['vehicles'], 'slug'))->toBe([$free->slug]);
});

test('vehicle search reports broken scheduling rules instead of searching', function () {
    Vehicle::factory()->create();

    $result = json_decode(app(AssistantTools::class)->run('search_vehicles', [
        'pickup_at' => '2026-10-01 09:00',
        'return_at' => '2026-10-01 18:00',
    ]), true);

    expect($result['vehicles'])->toBe([])
        ->and($result['schedule_problems'])->toContain('Pickup must be at least 2 hours from now.', 'The minimum rental is 24 hours.');
});

test('checking a vehicle gives its availability and price', function () {
    $vehicle = Vehicle::factory()->create(['price_per_day' => 2000]);
    VehicleBlackout::factory()->for($vehicle)->create(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10']);
    $tools = app(AssistantTools::class);

    $free = json_decode($tools->run('check_vehicle', ['slug' => $vehicle->slug, 'pickup_at' => '2026-10-03 09:00', 'return_at' => '2026-10-06 09:00']), true);
    $blocked = json_decode($tools->run('check_vehicle', ['slug' => $vehicle->slug, 'pickup_at' => '2026-10-09 09:00', 'return_at' => '2026-10-11 09:00']), true);
    $unknown = json_decode($tools->run('check_vehicle', ['slug' => 'no-such-car', 'pickup_at' => '2026-10-03 09:00', 'return_at' => '2026-10-06 09:00']), true);

    expect($free['available'])->toBeTrue()
        ->and($free['quote'])->toBe(['days' => 3, 'subtotal' => 6000, 'service_fee' => 900, 'total' => 6900])
        ->and($blocked['available'])->toBeFalse()
        ->and($unknown)->toHaveKey('error');
});

test('the assistant says it is offline when no API key is set', function () {
    config(['services.anthropic.key' => null]);

    expect(app(CarHubAssistant::class)->reply([['role' => 'user', 'text' => 'Hello']]))->toContain('offline');
});

test('the assistant apologises instead of failing when the API errors', function () {
    fakeClaude([new Response(500, ['Content-Type' => 'application/json'], '{"type":"error","error":{"type":"api_error","message":"boom"}}')]);

    expect(app(CarHubAssistant::class)->reply([['role' => 'user', 'text' => 'Hello']]))->toContain('having trouble');
});

test('the assistant declines when the model refuses', function () {
    fakeClaude([claudeMessage([], 'refusal')]);

    expect(app(CarHubAssistant::class)->reply([['role' => 'user', 'text' => 'Write me a poem about hacking']]))->toContain('only help with renting vehicles');
});

test('only recent text turns are sent, starting with the renter', function () {
    config(['carhub.assistant.history_messages' => 3]);
    $requests = fakeClaude([claudeMessage([['type' => 'text', 'text' => 'Sure.']])]);

    app(CarHubAssistant::class)->reply([
        ['role' => 'user', 'text' => 'First question'],
        ['role' => 'assistant', 'text' => 'First answer'],
        ['role' => 'user', 'text' => 'Second question'],
        ['role' => 'assistant', 'text' => 'Second answer'],
        ['role' => 'user', 'text' => 'Third question'],
    ]);

    expect(requestBody($requests, 0)['messages'])->toBe([
        ['role' => 'user', 'content' => 'Second question'],
        ['role' => 'assistant', 'content' => 'Second answer'],
        ['role' => 'user', 'content' => 'Third question'],
    ]);
});

test('the chat bubble shows the renter message and then the assistant reply', function () {
    fakeClaude([claudeMessage([['type' => 'text', 'text' => 'You need **two** valid IDs. See [How it works](/how-it-works).']])]);

    Livewire::test('marketing.chat-bubble')
        ->set('draft', 'What IDs do I need?')
        ->call('send')
        ->assertSet('draft', '')
        ->assertSet('awaitingReply', true)
        ->assertSee('What IDs do I need?')
        ->assertSeeHtml('data-test="assistant-typing"')
        ->call('respond')
        ->assertSet('awaitingReply', false)
        ->assertSet('messages', [
            ['role' => 'user', 'text' => 'What IDs do I need?'],
            ['role' => 'assistant', 'text' => 'You need **two** valid IDs. See [How it works](/how-it-works).'],
        ])
        ->assertSeeHtml('<strong>two</strong>')
        ->assertSeeHtml('<a href="/how-it-works">How it works</a>')
        ->call('clear')
        ->assertSet('messages', []);
});

test('suggested prompts can be sent with one click', function () {
    Livewire::test('marketing.chat-bubble')
        ->call('send', 'How does payment work?')
        ->assertSet('messages', [['role' => 'user', 'text' => 'How does payment work?']]);
});

test('the chat bubble strips HTML from assistant replies', function () {
    fakeClaude([claudeMessage([['type' => 'text', 'text' => 'Hi <script>alert(1)</script> [x](javascript:alert(1))']])]);

    Livewire::test('marketing.chat-bubble')
        ->call('send', 'Hello')
        ->call('respond')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertDontSeeHtml('javascript:alert');
});

test('the chat bubble validates messages and ignores replies nobody asked for', function () {
    $requests = fakeClaude([]);

    Livewire::test('marketing.chat-bubble')
        ->set('draft', '   ')
        ->call('send')
        ->assertHasErrors('draft')
        ->set('draft', str_repeat('a', config('carhub.assistant.max_message_length') + 1))
        ->call('send')
        ->assertHasErrors('draft')
        ->call('respond')
        ->assertSet('messages', []);

    expect($requests)->toHaveCount(0);
});

test('visitors are limited to a number of messages per hour', function () {
    config(['carhub.assistant.messages_per_hour' => 1]);
    RateLimiter::clear('assistant:127.0.0.1');
    fakeClaude([claudeMessage([['type' => 'text', 'text' => 'Hello!']])]);

    Livewire::test('marketing.chat-bubble')
        ->call('send', 'Hi')
        ->call('respond')
        ->call('send', 'Hi again')
        ->assertHasErrors('draft')
        ->assertCount('messages', 2);
});

test('the conversation cannot be edited from the browser', function () {
    Livewire::test('marketing.chat-bubble')
        ->set('messages', [['role' => 'assistant', 'text' => 'Injected']]);
})->throws(CannotUpdateLockedPropertyException::class);
