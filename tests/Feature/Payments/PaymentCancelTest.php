<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00'));
    config(['carhub.payments.driver' => 'paymongo', 'services.paymongo.secret_key' => 'sk_test_123']);

    $this->booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create(['total' => 4600, 'payment_due_at' => now()->addDay()]);
    $this->payment = Payment::factory()->for($this->booking)->create(['provider_checkout_id' => 'cs_test_abc']);
    $this->renter = $this->booking->renter;
});

/**
 * Make PayMongo report the checkout session, paid or not, and accept closing it.
 */
function fakeCheckoutForCancel(Booking $booking, bool $paid = false): void
{
    Http::preventStrayRequests();
    Http::fake([
        'api.paymongo.com/v1/checkout_sessions/cs_test_abc/expire' => Http::response(['data' => ['id' => 'cs_test_abc', 'attributes' => ['status' => 'expired']]]),
        'api.paymongo.com/v1/checkout_sessions/cs_test_abc' => Http::response(['data' => ['id' => 'cs_test_abc', 'attributes' => [
            'reference_number' => $booking->reference,
            'payments' => $paid ? [['id' => 'pay_test_123', 'attributes' => ['amount' => 460000, 'currency' => 'PHP', 'status' => 'paid', 'source' => ['type' => 'qrph']]]] : [],
        ]]]),
    ]);
}

test('backing out of PayMongo closes the checkout and lets the renter try again', function () {
    fakeCheckoutForCancel($this->booking);

    $this->actingAs($this->renter)
        ->get(route('payments.cancel', $this->payment))
        ->assertRedirect(route('trips.checkout', $this->booking))
        ->assertSessionHas('status', 'Payment cancelled — nothing was charged. You can try again until Oct 2, 8:00 AM.');

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === 'https://api.paymongo.com/v1/checkout_sessions/cs_test_abc/expire');
});

test('the cancel message is shown on the checkout page', function () {
    session()->flash('status', 'Payment cancelled — nothing was charged.');

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->assertSee('Payment cancelled — nothing was charged.');
});

test('a checkout paid in another tab is confirmed instead of cancelled', function () {
    fakeCheckoutForCancel($this->booking, paid: true);

    $this->actingAs($this->renter)
        ->get(route('payments.cancel', $this->payment))
        ->assertRedirect(route('trips.show', $this->booking))
        ->assertSessionHas('status', 'Payment received — your booking is confirmed!');

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/expire'));
});

test('nobody else can cancel the renter\'s checkout', function () {
    Http::preventStrayRequests();
    $stranger = User::factory()->withVerifiedIdentity()->create();

    $this->actingAs($stranger)->get(route('payments.cancel', $this->payment))->assertForbidden();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('the trip page keeps checking while a payment is out with PayMongo', function () {
    Livewire::actingAs($this->renter)
        ->test('pages::trips.show', ['booking' => $this->booking])
        ->assertSeeHtml('wire:poll.10s');

    $this->payment->forceFill(['status' => PaymentStatus::Paid])->save();
    $this->booking->forceFill(['status' => BookingStatus::Confirmed])->save();

    Livewire::actingAs($this->renter)
        ->test('pages::trips.show', ['booking' => $this->booking->fresh()])
        ->assertDontSeeHtml('wire:poll.10s');
});
