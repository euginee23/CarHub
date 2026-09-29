<?php

use App\Models\Vehicle;
use App\Models\VehicleBlackout;
use App\Services\Availability\AvailabilityChecker;
use App\Services\Pricing\RentalQuote;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00'));

    $this->vehicle = Vehicle::factory()->create(['price_per_day' => 2000]);

    VehicleBlackout::factory()->for($this->vehicle)->create([
        'starts_on' => '2026-10-10',
        'ends_on' => '2026-10-12',
        'reason' => 'Personal use',
    ]);
});

test('a vehicle is unavailable when the rental touches a blocked day', function (string $pickup, string $return, bool $available) {
    $checker = app(AvailabilityChecker::class);

    expect($checker->isAvailable($this->vehicle, CarbonImmutable::parse($pickup), CarbonImmutable::parse($return)))->toBe($available);
})->with([
    'well before' => ['2026-10-05 09:00', '2026-10-07 09:00', true],
    'ending on the first blocked day' => ['2026-10-08 09:00', '2026-10-10 09:00', false],
    'spanning the block' => ['2026-10-09 09:00', '2026-10-13 09:00', false],
    'starting on the last blocked day' => ['2026-10-12 09:00', '2026-10-14 09:00', false],
    'the day after the block' => ['2026-10-13 09:00', '2026-10-15 09:00', true],
]);

test('unlisted vehicles are never available', function () {
    $this->vehicle->update(['status' => 'unlisted']);

    expect(app(AvailabilityChecker::class)->isAvailable($this->vehicle, CarbonImmutable::parse('2026-10-05 09:00'), CarbonImmutable::parse('2026-10-07 09:00')))->toBeFalse();
});

test('schedules are checked against the booking rules', function (string $pickup, string $return, string $message) {
    $errors = app(AvailabilityChecker::class)->scheduleErrors(CarbonImmutable::parse($pickup), CarbonImmutable::parse($return));

    expect($errors)->toContain($message);
})->with([
    'too soon' => ['2026-10-01 12:00', '2026-10-03 12:00', 'Pickup must be at least 12 hours from now.'],
    'too far ahead' => ['2027-02-01 09:00', '2027-02-03 09:00', 'Bookings open up to 90 days ahead.'],
    'return before pickup' => ['2026-10-05 09:00', '2026-10-04 09:00', 'The return must be after pickup.'],
    'too short' => ['2026-10-05 09:00', '2026-10-05 18:00', 'The minimum rental is 24 hours.'],
    'too long' => ['2026-10-05 09:00', '2026-11-20 09:00', 'The maximum rental is 30 days.'],
]);

test('a valid schedule has no errors', function () {
    expect(app(AvailabilityChecker::class)->scheduleErrors(CarbonImmutable::parse('2026-10-05 09:00'), CarbonImmutable::parse('2026-10-07 09:00')))->toBe([]);
});

test('the blocked days in a range are listed for the calendar', function () {
    $dates = app(AvailabilityChecker::class)->unavailableDates($this->vehicle, CarbonImmutable::parse('2026-10-11'), CarbonImmutable::parse('2026-10-31'));

    expect(array_keys($dates))->toBe(['2026-10-11', '2026-10-12']);
});

test('rentals are charged per started day plus the service fee', function () {
    $quote = RentalQuote::for($this->vehicle, CarbonImmutable::parse('2026-10-05 09:00'), CarbonImmutable::parse('2026-10-07 11:00'));

    expect($quote)
        ->days->toBe(3)
        ->subtotal->toBe(6000)
        ->serviceFee->toBe(900)
        ->total->toBe(6900);
});

test('the booking panel confirms availability and prices the trip', function () {
    Livewire::test('vehicle.booking-panel', ['vehicle' => $this->vehicle])
        ->set('pickupDate', '2026-10-05')
        ->set('returnDate', '2026-10-07')
        ->assertSee('Available for your dates')
        ->assertSee('4,000')
        ->assertSee('4,600');
});

test('the booking panel flags blocked dates and broken rules', function () {
    Livewire::test('vehicle.booking-panel', ['vehicle' => $this->vehicle])
        ->set('pickupDate', '2026-10-09')
        ->set('returnDate', '2026-10-11')
        ->assertSee('This vehicle is not available for all of those dates.')
        ->assertDontSee('Available for your dates')
        ->set('pickupDate', '2026-10-05')
        ->set('returnDate', '2026-10-05')
        ->assertSee('The return must be after pickup.');
});

test('clicking calendar days fills in pickup then return', function () {
    Livewire::test('vehicle.booking-panel', ['vehicle' => $this->vehicle])
        ->dispatch('calendar-date-selected', date: '2026-10-05')
        ->assertSet('pickupDate', '2026-10-05')
        ->assertSet('returnDate', '')
        ->dispatch('calendar-date-selected', date: '2026-10-07')
        ->assertSet('returnDate', '2026-10-07')
        ->dispatch('calendar-date-selected', date: '2026-10-20')
        ->assertSet('pickupDate', '2026-10-20')
        ->assertSet('returnDate', '');
});

test('the calendar marks blocked days and only passes bookable ones on', function () {
    $component = Livewire::test('vehicle.availability-calendar', ['vehicle' => $this->vehicle])
        ->assertSee('October 2026')
        ->assertSeeHtml('October 10, unavailable');

    $component->call('select', '2026-10-10')->assertNotDispatched('calendar-date-selected');
    $component->call('select', '2026-09-30')->assertNotDispatched('calendar-date-selected');
    $component->call('select', '2026-10-05')->assertDispatched('calendar-date-selected', date: '2026-10-05');
});

test('the calendar only pages through the bookable window', function () {
    Livewire::test('vehicle.availability-calendar', ['vehicle' => $this->vehicle])
        ->call('shiftMonth', -1)
        ->assertSet('month', '2026-10')
        ->call('shiftMonth', 5)
        ->assertSet('month', '2026-12');
});

test('browsing with trip dates hides vehicles blocked during the trip', function () {
    $free = Vehicle::factory()->create();

    $component = Livewire::test('pages::marketing.browse')
        ->set('pickup', '2026-10-11')
        ->set('return', '2026-10-13')
        ->assertSee('Free Oct 11 — Oct 13, 2026');

    expect($component->instance()->vehicles->pluck('id')->all())->toBe([$free->id]);

    $component->call('clearFilter', 'dates');

    expect($component->instance()->vehicles)->toHaveCount(2);
});

test('owners can block and unblock dates on their vehicle', function () {
    $component = Livewire::actingAs($this->vehicle->owner)
        ->test('pages::owner.vehicles.form', ['vehicle' => $this->vehicle])
        ->set('blackoutStart', '2026-10-20')
        ->set('blackoutEnd', '2026-10-22')
        ->set('blackoutReason', 'Maintenance')
        ->call('addBlackout')
        ->assertHasNoErrors()
        ->assertSee('Maintenance');

    $blackout = $this->vehicle->blackouts()->where('reason', 'Maintenance')->sole();

    expect($blackout->starts_on->toDateString())->toBe('2026-10-20');

    $component->call('deleteBlackout', $blackout->id);

    expect($this->vehicle->blackouts()->count())->toBe(1);
});

test('blocked date ranges are validated', function () {
    Livewire::actingAs($this->vehicle->owner)
        ->test('pages::owner.vehicles.form', ['vehicle' => $this->vehicle])
        ->set('blackoutStart', '2026-10-22')
        ->set('blackoutEnd', '2026-10-20')
        ->call('addBlackout')
        ->assertHasErrors(['blackoutEnd' => 'after_or_equal']);
});

test('owners cannot remove another vehicle\'s blocked dates', function () {
    $otherBlackout = VehicleBlackout::factory()->create();

    Livewire::actingAs($this->vehicle->owner)
        ->test('pages::owner.vehicles.form', ['vehicle' => $this->vehicle])
        ->call('deleteBlackout', $otherBlackout->id);

    expect($otherBlackout->fresh())->not->toBeNull();
});
