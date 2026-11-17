<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\BookingStatusUpdated;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    config(['carhub.payments.driver' => 'simulated']);
    $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00'));
});

test('unpaid bookings past their deadline expire and release the vehicle', function () {
    $booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create(['payment_due_at' => now()->subMinute()]);
    $payment = Payment::factory()->for($booking)->create(['provider' => 'simulated']);

    $this->artisan('bookings:expire-stale')->expectsOutputToContain('Expired 1 booking.')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($booking->statusChanges()->latest('id')->first()->note)->toBe('Payment was not completed before the deadline.');

    Notification::assertSentTo($booking->renter, BookingStatusUpdated::class, fn ($n) => $n->status === BookingStatus::Expired);
});

test('expiring a booking closes its PayMongo checkout so it can no longer be paid', function () {
    config(['carhub.payments.driver' => 'paymongo', 'services.paymongo.secret_key' => 'sk_test_123']);
    $booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create(['payment_due_at' => now()->subMinute()]);
    $payment = Payment::factory()->for($booking)->create(['provider_checkout_id' => 'cs_test_abc']);

    Http::preventStrayRequests();
    Http::fake([
        'api.paymongo.com/v1/checkout_sessions/cs_test_abc/expire' => Http::response(['data' => ['id' => 'cs_test_abc']]),
        'api.paymongo.com/v1/checkout_sessions/cs_test_abc' => Http::response(['data' => ['id' => 'cs_test_abc', 'attributes' => ['reference_number' => $booking->reference, 'payments' => []]]]),
    ]);

    $this->artisan('bookings:expire-stale')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === 'https://api.paymongo.com/v1/checkout_sessions/cs_test_abc/expire');
});

test('a booking still expires when PayMongo refuses to close the checkout', function () {
    config(['carhub.payments.driver' => 'paymongo', 'services.paymongo.secret_key' => 'sk_test_123']);
    $booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create(['payment_due_at' => now()->subMinute()]);
    $payment = Payment::factory()->for($booking)->create(['provider_checkout_id' => 'cs_test_abc']);

    Http::fake([
        'api.paymongo.com/v1/checkout_sessions/cs_test_abc/expire' => Http::response(['errors' => [['detail' => 'Checkout session is already expired.']]], 400),
        'api.paymongo.com/v1/checkout_sessions/cs_test_abc' => Http::response(['data' => ['id' => 'cs_test_abc', 'attributes' => ['reference_number' => $booking->reference, 'payments' => []]]]),
    ]);

    $this->artisan('bookings:expire-stale')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired);
});

test('a payment that went through without its webhook is caught before expiring', function () {
    $booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create(['total' => 3000, 'payment_due_at' => now()->subMinute()]);
    Payment::factory()->for($booking)->create(['provider' => 'simulated', 'payload' => ['simulated_outcome' => 'paid']]);

    $this->artisan('bookings:expire-stale')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('requests and checkouts still unfinished at pickup time expire', function (BookingStatus $status) {
    $booking = Booking::factory()->status($status)->between('2026-10-05 09:00', '2026-10-07 09:00')->create();

    $this->artisan('bookings:expire-stale')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired);
})->with([
    'never answered' => BookingStatus::Requested,
    'never checked out' => BookingStatus::Approved,
]);

test('bookings still in time are left alone', function () {
    $waiting = Booking::factory()->status(BookingStatus::AwaitingPayment)->create(['payment_due_at' => now()->addHour()]);
    $upcoming = Booking::factory()->between('2026-10-08 09:00', '2026-10-09 09:00')->create();
    $confirmed = Booking::factory()->confirmed()->between('2026-10-05 09:00', '2026-10-07 09:00')->create();

    $this->artisan('bookings:expire-stale')->expectsOutputToContain('No bookings expired.');

    expect($waiting->fresh()->status)->toBe(BookingStatus::AwaitingPayment)
        ->and($upcoming->fresh()->status)->toBe(BookingStatus::Requested)
        ->and($confirmed->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('the sweep is scheduled', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('bookings:expire-stale');
});
