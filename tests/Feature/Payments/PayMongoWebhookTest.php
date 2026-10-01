<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Notification::fake();
    config([
        'carhub.payments.driver' => 'paymongo',
        'services.paymongo.secret_key' => 'sk_test_123',
        'services.paymongo.webhook_secret' => 'whsk_test_secret',
        'services.paymongo.live' => false,
    ]);

    $this->booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create(['total' => 4600, 'payment_due_at' => now()->addDay()]);
    $this->payment = Payment::factory()->for($this->booking)->create([
        'provider_checkout_id' => 'cs_test_abc',
        'provider_payment_intent_id' => 'pi_test_abc',
    ]);

});

/**
 * Make the PayMongo API report the checkout session as paid in full.
 */
function fakePaidCheckoutSession(Booking $booking): void
{
    Http::fake([
        'api.paymongo.com/v1/checkout_sessions/cs_test_abc' => Http::response(['data' => [
            'id' => 'cs_test_abc',
            'attributes' => [
                'reference_number' => $booking->reference,
                'payments' => [['id' => 'pay_test_123', 'attributes' => ['amount' => 460000, 'currency' => 'PHP', 'status' => 'paid']]],
            ],
        ]]),
    ]);
}

/**
 * Send a webhook event to CarHub signed the way PayMongo signs it.
 *
 * @param  array<string, mixed>  $event
 */
function sendWebhook(array $event, ?string $secret = 'whsk_test_secret', ?int $timestamp = null, string $mode = 'te'): TestResponse
{
    $body = json_encode($event);
    $timestamp ??= now()->getTimestamp();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) $secret);

    return test()->call('POST', route('webhooks.paymongo'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},{$mode}={$signature},".($mode === 'te' ? 'li=' : 'te='),
    ], $body);
}

/**
 * A webhook event of the given type wrapping the given resource.
 *
 * @param  array<string, mixed>  $resource
 * @return array<string, mixed>
 */
function webhookEvent(string $type, array $resource): array
{
    return ['data' => ['id' => 'evt_test', 'attributes' => ['type' => $type, 'data' => $resource]]];
}

test('a signed checkout paid event confirms the booking', function () {
    fakePaidCheckoutSession($this->booking);

    sendWebhook(webhookEvent('checkout_session.payment.paid', ['id' => 'cs_test_abc']))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('a signed payment paid event is matched through its payment intent', function () {
    fakePaidCheckoutSession($this->booking);

    sendWebhook(webhookEvent('payment.paid', ['id' => 'pay_test_123', 'attributes' => ['payment_intent_id' => 'pi_test_abc']]))->assertOk();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('the webhook body is not trusted on its own', function () {
    // The event claims success, but the API is the source of truth.
    Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['id' => 'cs_test_abc', 'attributes' => ['payments' => []]]])]);

    sendWebhook(webhookEvent('checkout_session.payment.paid', ['id' => 'cs_test_abc', 'attributes' => ['status' => 'paid']]))->assertOk();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});

test('events with a bad signature are rejected', function (?string $secret, ?int $age, string $mode) {
    fakePaidCheckoutSession($this->booking);

    sendWebhook(
        webhookEvent('checkout_session.payment.paid', ['id' => 'cs_test_abc']),
        secret: $secret,
        timestamp: $age === null ? null : now()->getTimestamp() - $age,
        mode: $mode,
    )->assertUnauthorized();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
    Http::assertNothingSent();
})->with([
    'wrong secret' => ['someone-elses-secret', null, 'te'],
    'replayed after ten minutes' => ['whsk_test_secret', 600, 'te'],
    'live signature in test mode' => ['whsk_test_secret', null, 'li'],
]);

test('events without any signature are rejected', function () {
    $this->postJson(route('webhooks.paymongo'), webhookEvent('checkout_session.payment.paid', ['id' => 'cs_test_abc']))->assertUnauthorized();
});

test('delivering the same event twice is harmless', function () {
    fakePaidCheckoutSession($this->booking);

    $event = webhookEvent('checkout_session.payment.paid', ['id' => 'cs_test_abc']);

    sendWebhook($event)->assertOk();
    sendWebhook($event)->assertOk();

    expect($this->booking->statusChanges()->where('to_status', BookingStatus::Confirmed)->count())->toBe(1);
});

test('events for unknown payments and other event types are acknowledged and ignored', function () {
    sendWebhook(webhookEvent('checkout_session.payment.paid', ['id' => 'cs_not_ours']))->assertOk()->assertJson(['message' => 'Ignored.']);
    sendWebhook(webhookEvent('payout.deposited', ['id' => 'po_123']))->assertOk()->assertJson(['message' => 'Ignored.']);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});
