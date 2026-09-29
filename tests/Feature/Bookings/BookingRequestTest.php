<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookingRequested;
use App\Notifications\BookingStatusUpdated;
use App\Services\Availability\AvailabilityChecker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00'));

    $this->vehicle = Vehicle::factory()->create(['price_per_day' => 2000, 'instant_book' => false, 'location' => 'Cebu City']);
    $this->renter = User::factory()->create();
});

/**
 * Pick dates on a vehicle's booking panel and press the request button.
 */
function requestVehicle(Vehicle $vehicle, ?User $renter, string $pickup = '2026-10-05', string $return = '2026-10-07'): Testable
{
    if ($renter) {
        Livewire::actingAs($renter);
    }

    return Livewire::test('vehicle.booking-panel', ['vehicle' => $vehicle])
        ->set('pickupDate', $pickup)
        ->set('returnDate', $return)
        ->call('requestBooking');
}

test('a renter can request to book an available vehicle', function () {
    $component = requestVehicle($this->vehicle, $this->renter)->assertHasNoErrors();

    $booking = Booking::sole();

    $component->assertRedirect(route('trips.show', $booking));

    expect($booking)
        ->status->toBe(BookingStatus::Requested)
        ->renter_id->toBe($this->renter->id)
        ->owner_id->toBe($this->vehicle->owner_id)
        ->pickup_location->toBe('Cebu City')
        ->days->toBe(2)
        ->subtotal->toBe(4000)
        ->service_fee->toBe(600)
        ->total->toBe(4600)
        ->and($booking->pickup_at->format('Y-m-d H:i'))->toBe('2026-10-05 09:00')
        ->and($booking->reference)->toStartWith('BK-')
        ->and($booking->statusChanges)->toHaveCount(1);

    Notification::assertSentTo($this->vehicle->owner, BookingRequested::class);
});

test('instant book vehicles are approved straight away', function () {
    $this->vehicle->update(['instant_book' => true]);

    requestVehicle($this->vehicle, $this->renter)->assertHasNoErrors();

    $booking = Booking::sole();

    expect($booking->status)->toBe(BookingStatus::Approved)
        ->and($booking->approved_at)->not->toBeNull()
        ->and($booking->statusChanges->pluck('to_status')->all())->toBe([BookingStatus::Requested, BookingStatus::Approved]);

    Notification::assertSentTo($this->renter, BookingStatusUpdated::class);
    Notification::assertNotSentTo($this->vehicle->owner, BookingRequested::class);
});

test('guests are sent to sign in and brought back to the vehicle', function () {
    requestVehicle($this->vehicle, null)->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('vehicles.show', $this->vehicle))
        ->and(Booking::count())->toBe(0);
});

test('owners cannot book their own vehicle', function () {
    requestVehicle($this->vehicle, $this->vehicle->owner)->assertHasErrors('schedule');

    expect(Booking::count())->toBe(0);
});

test('requests that break the scheduling rules are rejected', function () {
    requestVehicle($this->vehicle, $this->renter, '2026-10-05', '2026-10-05')->assertHasErrors('schedule');

    expect(Booking::count())->toBe(0);
});

test('a request cannot overlap a booking that holds the vehicle', function (BookingStatus $status) {
    Booking::factory()->forVehicle($this->vehicle)->status($status)->between('2026-10-06 09:00', '2026-10-08 09:00')->create();

    requestVehicle($this->vehicle, $this->renter)->assertHasErrors('schedule');

    expect(Booking::count())->toBe(1);
})->with([
    'approved' => BookingStatus::Approved,
    'awaiting payment' => BookingStatus::AwaitingPayment,
    'confirmed' => BookingStatus::Confirmed,
    'ongoing' => BookingStatus::Ongoing,
]);

test('requests only compete with each other until one is approved', function () {
    Booking::factory()->forVehicle($this->vehicle)->between('2026-10-05 09:00', '2026-10-07 09:00')->create();

    requestVehicle($this->vehicle, $this->renter)->assertHasNoErrors();

    expect(Booking::where('status', BookingStatus::Requested)->count())->toBe(2);
});

test('closed bookings free the vehicle again', function (BookingStatus $status) {
    Booking::factory()->forVehicle($this->vehicle)->status($status)->between('2026-10-05 09:00', '2026-10-07 09:00')->create();

    requestVehicle($this->vehicle, $this->renter)->assertHasNoErrors();
})->with([
    'declined' => BookingStatus::Declined,
    'cancelled' => BookingStatus::Cancelled,
    'expired' => BookingStatus::Expired,
]);

test('back-to-back rentals do not overlap', function () {
    Booking::factory()->forVehicle($this->vehicle)->confirmed()->between('2026-10-03 09:00', '2026-10-05 09:00')->create();

    requestVehicle($this->vehicle, $this->renter)->assertHasNoErrors();
});

test('a renter cannot request the same vehicle twice for the same dates', function () {
    requestVehicle($this->vehicle, $this->renter)->assertHasNoErrors();
    requestVehicle($this->vehicle, $this->renter)->assertHasErrors('schedule');

    expect(Booking::count())->toBe(1);
});

test('booked days show as unavailable on the calendar and in browse', function () {
    Booking::factory()->forVehicle($this->vehicle)->confirmed()->between('2026-10-10 09:00', '2026-10-12 09:00')->create();

    $dates = app(AvailabilityChecker::class)->unavailableDates($this->vehicle, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

    expect(array_keys($dates))->toBe(['2026-10-10', '2026-10-11', '2026-10-12']);

    $browse = Livewire::test('pages::marketing.browse')->set('pickup', '2026-10-11')->set('return', '2026-10-11');

    expect($browse->instance()->vehicles)->toBeEmpty();
});

test('the booking panel offers the request button once dates are available', function () {
    Livewire::actingAs($this->renter)
        ->test('vehicle.booking-panel', ['vehicle' => $this->vehicle])
        ->assertSee('Request to book')
        ->set('pickupDate', '2026-10-05')
        ->set('returnDate', '2026-10-07')
        ->assertSee('Message to the owner');
});

test('administrators cannot book vehicles', function () {
    requestVehicle($this->vehicle, User::factory()->admin()->create())->assertHasErrors('schedule');

    expect(Booking::count())->toBe(0);
});

test('administrators see a notice instead of the booking button', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test('vehicle.booking-panel', ['vehicle' => $this->vehicle])
        ->assertSee('Admin accounts manage the marketplace and cannot book vehicles.')
        ->assertDontSee('Request to book');
});
