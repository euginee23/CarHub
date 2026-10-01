<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Notification::fake();
    config(['carhub.payments.driver' => 'simulated']);

    $this->booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create(['total' => 4600, 'payment_due_at' => now()->addDay()]);
    $this->payment = Payment::factory()->for($this->booking)->create(['provider' => 'simulated', 'provider_checkout_id' => null]);
    $this->renter = $this->booking->renter;
});

test('the renter can open the simulated checkout through its signed link', function () {
    $this->actingAs($this->renter)
        ->get(URL::temporarySignedRoute('payments.simulated.show', now()->addHour(), $this->payment))
        ->assertOk()
        ->assertSee('Test payment — no money moves')
        ->assertSee('4,600.00');
});

test('the simulated checkout needs a valid signature', function () {
    $this->actingAs($this->renter)->get(route('payments.simulated.show', $this->payment))->assertForbidden();
});

test('a successful test payment confirms the booking on return', function () {
    $this->actingAs($this->renter)
        ->post(route('payments.simulated.complete', $this->payment), ['outcome' => 'paid'])
        ->assertRedirect(route('payments.return', $this->payment));

    $this->actingAs($this->renter)
        ->get(route('payments.return', $this->payment))
        ->assertRedirect(route('trips.show', $this->booking))
        ->assertSessionHas('status', 'Payment received — your booking is confirmed!');

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('a failed test payment sends the renter back to try again', function () {
    $this->actingAs($this->renter)->post(route('payments.simulated.complete', $this->payment), ['outcome' => 'failed']);

    $this->actingAs($this->renter)
        ->get(route('payments.return', $this->payment))
        ->assertRedirect(route('trips.checkout', $this->booking))
        ->assertSessionHasErrors('payment');

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});

test('returning before the payment clears shows a waiting message', function () {
    $this->actingAs($this->renter)
        ->get(route('payments.return', $this->payment))
        ->assertRedirect(route('trips.show', $this->booking))
        ->assertSessionHas('status');

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('nobody else can use the renter\'s payment pages', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('payments.return', $this->payment))->assertForbidden();
    $this->actingAs($stranger)->post(route('payments.simulated.complete', $this->payment), ['outcome' => 'paid'])->assertForbidden();
});

test('the simulated checkout is closed for PayMongo payments', function () {
    $payment = Payment::factory()->for($this->booking)->create(['provider' => 'paymongo']);

    $this->actingAs($this->renter)
        ->get(URL::temporarySignedRoute('payments.simulated.show', now()->addHour(), $payment))
        ->assertNotFound();
});
