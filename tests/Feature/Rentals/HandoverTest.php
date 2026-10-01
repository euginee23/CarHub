<?php

use App\Enums\BookingStatus;
use App\Enums\FuelLevel;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingStatusUpdated;
use App\Services\Availability\AvailabilityChecker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:30'));

    $this->booking = Booking::factory()->confirmed()->between('2026-10-05 09:00', '2026-10-07 09:00')->create();
    $this->booking->vehicle->forceFill(['trips_count' => 10])->save();
    $this->owner = $this->booking->owner;
});

/**
 * Release the vehicle from the owner's booking page.
 */
function releaseVehicle(Booking $booking, User $owner, string $odometer = '42000'): Testable
{
    return Livewire::actingAs($owner)
        ->test('pages::owner.bookings.show', ['booking' => $booking])
        ->set('pickupOdometer', $odometer)
        ->set('pickupFuel', 'full')
        ->set('pickupNotes', 'Small scratch on the rear bumper.')
        ->call('releaseVehicle');
}

test('the owner hands the vehicle over and the rental starts', function () {
    releaseVehicle($this->booking, $this->owner)->assertHasNoErrors();

    $booking = $this->booking->fresh();

    expect($booking)
        ->status->toBe(BookingStatus::Ongoing)
        ->pickup_odometer->toBe(42000)
        ->pickup_fuel->toBe(FuelLevel::Full)
        ->pickup_notes->toBe('Small scratch on the rear bumper.')
        ->and($booking->picked_up_at->format('Y-m-d H:i'))->toBe('2026-10-05 08:30')
        ->and($booking->statusChanges->last()->note)->toBe('Vehicle released at 42,000 km with a full tank.');

    Notification::assertSentTo($booking->renter, BookingStatusUpdated::class, fn ($n) => $n->status === BookingStatus::Ongoing);
});

test('the vehicle cannot be released long before pickup', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 06:00'));

    releaseVehicle($this->booking, $this->owner)->assertHasErrors('handover');

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('only paid bookings can be handed over', function () {
    $booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->forVehicle($this->booking->vehicle)
        ->between('2026-10-05 09:00', '2026-10-06 09:00')->create();

    releaseVehicle($booking, $this->owner)->assertHasErrors('handover');
});

test('the odometer reading is required', function () {
    releaseVehicle($this->booking, $this->owner, '')->assertHasErrors(['pickupOdometer' => 'required']);
});

test('the owner records the return and the rental completes', function () {
    releaseVehicle($this->booking, $this->owner);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:45'));

    Livewire::actingAs($this->owner)
        ->test('pages::owner.bookings.show', ['booking' => $this->booking->fresh()])
        ->set('returnOdometer', '42350')
        ->set('returnFuel', 'three_quarters')
        ->call('recordReturn')
        ->assertHasNoErrors();

    $booking = $this->booking->fresh();

    expect($booking)
        ->status->toBe(BookingStatus::Completed)
        ->return_odometer->toBe(42350)
        ->return_fuel->toBe(FuelLevel::ThreeQuarters)
        ->and($booking->distanceDriven())->toBe(350)
        ->and($booking->wasReturnedLate())->toBeFalse()
        ->and($booking->vehicle->fresh()->trips_count)->toBe(11)
        ->and($booking->statusChanges->last()->note)->toContain('350 km driven');

    Notification::assertSentTo($booking->renter, BookingStatusUpdated::class, fn ($n) => $n->status === BookingStatus::Completed);
});

test('a return more than an hour past the deadline is flagged late', function () {
    releaseVehicle($this->booking, $this->owner);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 11:00'));

    Livewire::actingAs($this->owner)
        ->test('pages::owner.bookings.show', ['booking' => $this->booking->fresh()])
        ->set('returnOdometer', '42100')
        ->call('recordReturn')
        ->assertSee('Late');

    expect($this->booking->fresh()->wasReturnedLate())->toBeTrue();
});

test('the return odometer cannot go backwards', function () {
    releaseVehicle($this->booking, $this->owner);

    Livewire::actingAs($this->owner)
        ->test('pages::owner.bookings.show', ['booking' => $this->booking->fresh()])
        ->set('returnOdometer', '41000')
        ->call('recordReturn')
        ->assertHasErrors('returnOdometer');

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Ongoing);
});

test('a completed rental frees the vehicle for new bookings', function () {
    $checker = app(AvailabilityChecker::class);
    $window = [CarbonImmutable::parse('2026-10-06 09:00'), CarbonImmutable::parse('2026-10-08 09:00')];

    expect($checker->isAvailable($this->booking->vehicle, ...$window))->toBeFalse();

    releaseVehicle($this->booking, $this->owner);
    Livewire::actingAs($this->owner)
        ->test('pages::owner.bookings.show', ['booking' => $this->booking->fresh()])
        ->set('returnOdometer', '42100')
        ->call('recordReturn');

    expect($checker->isAvailable($this->booking->vehicle->fresh(), ...$window))->toBeTrue();
});

test('the renter sees the trip in progress with a link to the live map', function () {
    releaseVehicle($this->booking, $this->owner);

    $this->actingAs($this->booking->renter)
        ->get(route('trips.show', $this->booking))
        ->assertOk()
        ->assertSee('You are on your trip')
        ->assertSee(route('bookings.tracking', $this->booking))
        ->assertSee('Handover record')
        ->assertSee('42,000 km');
});
