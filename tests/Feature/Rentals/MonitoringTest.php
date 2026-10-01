<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('administrators monitor every booking by stage', function () {
    $ongoing = Booking::factory()->status(BookingStatus::Ongoing)->create();
    $requested = Booking::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.bookings.index'))->assertOk()->assertSee($ongoing->reference)->assertSee($requested->reference);

    $component = Livewire::actingAs($this->admin)->test('pages::admin.bookings.index')->set('status', 'ongoing');

    expect($component->instance()->bookings->pluck('id')->all())->toBe([$ongoing->id])
        ->and($component->instance()->counts)->toMatchArray(['ongoing' => 1, 'requested' => 1]);
});

test('administrators can search bookings by reference or person', function () {
    $booking = Booking::factory()->create();
    Booking::factory()->create();

    $component = Livewire::actingAs($this->admin)->test('pages::admin.bookings.index');

    $component->set('search', $booking->reference);
    expect($component->instance()->bookings->pluck('id')->all())->toBe([$booking->id]);

    $component->set('search', $booking->renter->name);
    expect($component->instance()->bookings->pluck('id')->all())->toBe([$booking->id]);
});

test('the booking detail shows the whole transaction', function () {
    $booking = Booking::factory()->status(BookingStatus::Ongoing)->create(['pickup_odometer' => 1000, 'picked_up_at' => now()]);
    $payment = Payment::factory()->paid()->for($booking)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.bookings.show', $booking))
        ->assertOk()
        ->assertSee($payment->reference)
        ->assertSee('Payment attempts')
        ->assertSee('Handover record')
        ->assertSee($booking->renter->email)
        ->assertSee($booking->owner->email)
        ->assertSee(route('bookings.tracking', $booking));
});

test('only administrators can monitor all bookings', function () {
    $booking = Booking::factory()->create();

    $this->actingAs($booking->owner)->get(route('admin.bookings.index'))->assertForbidden();
    $this->actingAs($booking->owner)->get(route('admin.bookings.show', $booking))->assertForbidden();
});

test('booking pages show how far the rental has come', function () {
    $booking = Booking::factory()->status(BookingStatus::Ongoing)->create();

    $this->actingAs($booking->renter)
        ->get(route('trips.show', $booking))
        ->assertSeeInOrder(['Requested', 'Approved', 'Paid', 'On trip', 'Returned']);
});
