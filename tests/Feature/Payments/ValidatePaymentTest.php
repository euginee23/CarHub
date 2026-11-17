<?php

use App\Actions\Payments\ValidatePayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\BookingStatusUpdated;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    config(['carhub.payments.driver' => 'paymongo', 'services.paymongo.secret_key' => 'sk_test_123']);

    $this->booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create(['total' => 4600, 'payment_due_at' => now()->addDay()]);
    $this->payment = Payment::factory()->for($this->booking)->create(['provider_checkout_id' => 'cs_test_abc']);
});

/**
 * Make the PayMongo API report the checkout session in the given state.
 *
 * @param  array<string, mixed>  $payment
 */
function payMongoReports(?array $payment, string $reference): void
{
    Http::fake([
        'api.paymongo.com/v1/checkout_sessions/*' => Http::response(['data' => [
            'id' => 'cs_test_abc',
            'attributes' => [
                'reference_number' => $reference,
                'payments' => $payment ? [$payment] : [],
            ],
        ]]),
    ]);
}

/**
 * A PayMongo payment resource.
 *
 * @return array<string, mixed>
 */
function payMongoPayment(int $amount, string $status = 'paid', string $currency = 'PHP', ?string $source = null): array
{
    return ['id' => 'pay_test_123', 'attributes' => array_filter([
        'amount' => $amount,
        'currency' => $currency,
        'status' => $status,
        'source' => $source ? ['type' => $source] : null,
    ])];
}

test('a payment of the full amount confirms the booking', function () {
    payMongoReports(payMongoPayment(460000), $this->booking->reference);

    app(ValidatePayment::class)->handle($this->payment);

    expect($this->payment->fresh())
        ->status->toBe(PaymentStatus::Paid)
        ->provider_payment_id->toBe('pay_test_123')
        ->paid_at->not->toBeNull()
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($this->booking->statusChanges()->latest('id')->first()->note)->toContain($this->payment->reference);

    Notification::assertSentTo($this->booking->renter, BookingStatusUpdated::class, fn ($n) => $n->status === BookingStatus::Confirmed);
    Notification::assertSentTo($this->booking->company, BookingStatusUpdated::class, fn ($n) => $n->status === BookingStatus::Confirmed);
});

test('an unpaid checkout leaves everything waiting', function () {
    payMongoReports(null, $this->booking->reference);

    app(ValidatePayment::class)->handle($this->payment);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});

test('a declined attempt inside an open PayMongo checkout is not treated as final', function () {
    payMongoReports(payMongoPayment(460000, 'failed'), $this->booking->reference);

    app(ValidatePayment::class)->handle($this->payment);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('payments that do not match the booking are rejected', function (array $payment, string $reference, string $reason) {
    payMongoReports($payment, $reference === 'own' ? $this->booking->reference : $reference);

    app(ValidatePayment::class)->handle($this->payment);

    expect($this->payment->fresh())
        ->status->toBe(PaymentStatus::Failed)
        ->failure_reason->toContain($reason)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
})->with([
    'short by one peso' => [payMongoPayment(459900), 'own', 'Amount mismatch'],
    'wrong currency' => [payMongoPayment(460000, currency: 'USD'), 'own', 'Currency mismatch'],
    'another booking' => [payMongoPayment(460000), 'BK-SOMEONEELSE', 'Reference mismatch'],
]);

test('validating twice confirms the booking only once', function () {
    payMongoReports(payMongoPayment(460000), $this->booking->reference);

    app(ValidatePayment::class)->handle($this->payment);
    app(ValidatePayment::class)->handle($this->payment->fresh());

    expect($this->booking->statusChanges()->where('to_status', BookingStatus::Confirmed)->count())->toBe(1);
});

test('money received after the booking expired is flagged for a refund', function () {
    $this->booking->forceFill(['status' => BookingStatus::Expired])->save();
    payMongoReports(payMongoPayment(460000), $this->booking->reference);

    app(ValidatePayment::class)->handle($this->payment);

    expect($this->payment->fresh())
        ->status->toBe(PaymentStatus::RefundDue)
        ->failure_reason->toContain('refunded')
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Expired);
});

test('the method the renter chose on PayMongo is recorded', function (string $source, PaymentMethod $method) {
    payMongoReports(payMongoPayment(460000, source: $source), $this->booking->reference);

    app(ValidatePayment::class)->handle($this->payment);

    expect($this->payment->fresh()->method)->toBe($method)
        ->and($this->booking->statusChanges()->latest('id')->first()->note)->toContain('via '.$method->label());
})->with([
    'QR Ph' => ['qrph', PaymentMethod::QrPh],
    'GCash' => ['gcash', PaymentMethod::Gcash],
    'Maya' => ['paymaya', PaymentMethod::Maya],
    'card' => ['card', PaymentMethod::Card],
]);

test('a checkout CarHub gave up on that was paid anyway still confirms a booking awaiting payment', function () {
    $this->payment->forceFill(['status' => PaymentStatus::Expired])->save();
    payMongoReports(payMongoPayment(460000), $this->booking->reference);

    app(ValidatePayment::class)->handle($this->payment);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('money paid into an abandoned checkout after the booking expired is flagged for a refund, not lost', function () {
    $this->booking->forceFill(['status' => BookingStatus::Expired])->save();
    $this->payment->forceFill(['status' => PaymentStatus::Expired])->save();
    payMongoReports(payMongoPayment(460000), $this->booking->reference);

    app(ValidatePayment::class)->handle($this->payment);

    expect($this->payment->fresh())
        ->status->toBe(PaymentStatus::RefundDue)
        ->paid_at->not->toBeNull()
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Expired);
});

test('an abandoned checkout that was never paid stays expired', function () {
    $this->payment->forceFill(['status' => PaymentStatus::Expired])->save();
    payMongoReports(null, $this->booking->reference);

    app(ValidatePayment::class)->handle($this->payment);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Expired);
});

test('confirming a booking closes its other open checkouts', function () {
    $other = Payment::factory()->for($this->booking)->create(['provider_checkout_id' => 'cs_test_other']);
    payMongoReports(payMongoPayment(460000), $this->booking->reference);

    app(ValidatePayment::class)->handle($this->payment);

    expect($other->fresh()->status)->toBe(PaymentStatus::Expired);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === 'https://api.paymongo.com/v1/checkout_sessions/cs_test_other/expire');
});
