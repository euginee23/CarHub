<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\GpsDevice;
use App\Models\User;
use App\Models\VehicleLocation;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00'));

    $this->booking = Booking::factory()->status(BookingStatus::Ongoing)->between('2026-10-05 09:00', '2026-10-07 09:00')
        ->create(['picked_up_at' => '2026-10-05 09:05']);
    $this->device = GpsDevice::factory()->withToken('gps_test_token')->for($this->booking->vehicle)->create();
});

/**
 * Send fixes to the tracking API as a device would.
 *
 * @param  array<string, mixed>  $payload
 */
function sendPings(array $payload, ?string $token = 'gps_test_token'): TestResponse
{
    return test()->withHeaders($token ? ['Authorization' => 'Bearer '.$token] : [])->postJson(route('api.tracking.pings'), $payload);
}

test('a fix from the tracker is stored against the ongoing rental', function () {
    sendPings(['lat' => 10.3157, 'lng' => 123.8854, 'speed' => 42.5, 'heading' => 90])
        ->assertAccepted()
        ->assertJson(['received' => 1, 'stored' => 1, 'tracking' => true]);

    $location = VehicleLocation::sole();

    expect($location)
        ->booking_id->toBe($this->booking->id)
        ->vehicle_id->toBe($this->booking->vehicle_id)
        ->latitude->toBe(10.3157)
        ->speed_kph->toBe(42.5)
        ->heading->toBe(90)
        ->and($this->device->fresh()->last_seen_at)->not->toBeNull()
        ->and($this->device->fresh()->last_latitude)->toBe(10.3157);
});

test('buffered fixes are accepted in a batch, in time order', function () {
    sendPings(['pings' => [
        ['lat' => 10.32, 'lng' => 123.89, 'recorded_at' => '2026-10-05T09:40:00+00:00'],
        ['lat' => 10.31, 'lng' => 123.88, 'recorded_at' => '2026-10-05T09:20:00+00:00'],
    ]])->assertAccepted()->assertJson(['received' => 2, 'stored' => 2]);

    expect($this->booking->locations()->pluck('latitude')->all())->toBe([10.31, 10.32]);
});

test('fixes from before the vehicle was handed over are not part of the trip', function () {
    sendPings(['pings' => [['lat' => 10.3, 'lng' => 123.8, 'recorded_at' => '2026-10-04T12:00:00+00:00']]])->assertAccepted();

    expect(VehicleLocation::sole()->recorded_at->format('Y-m-d H:i'))->toBe('2026-10-05 09:05');
});

test('positions are not kept when the vehicle is not out on a rental', function () {
    $this->booking->forceFill(['status' => BookingStatus::Completed])->save();

    sendPings(['lat' => 10.3157, 'lng' => 123.8854])->assertAccepted()->assertJson(['stored' => 0, 'tracking' => false]);

    expect(VehicleLocation::count())->toBe(0)
        ->and($this->device->fresh()->last_seen_at)->not->toBeNull();
});

test('devices must present a valid token', function (?string $token) {
    sendPings(['lat' => 10.3157, 'lng' => 123.8854], $token)->assertUnauthorized();

    expect(VehicleLocation::count())->toBe(0);
})->with(['missing' => null, 'unknown' => 'gps_not_a_real_token']);

test('fixes are validated', function (array $payload, string $field) {
    sendPings($payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'latitude off the globe' => [['lat' => 95, 'lng' => 123.8], 'lat'],
    'missing longitude' => [['lat' => 10.3], 'lng'],
    'heading out of range' => [['lat' => 10.3, 'lng' => 123.8, 'heading' => 400], 'heading'],
    'empty batch' => [['pings' => []], 'pings'],
    'bad fix in a batch' => [['pings' => [['lat' => 'north', 'lng' => 123.8]]], 'pings.0.lat'],
]);

test('each device is rate limited', function () {
    foreach (range(1, 60) as $attempt) {
        sendPings(['lat' => 10.3157, 'lng' => 123.8854])->assertAccepted();
    }

    sendPings(['lat' => 10.3157, 'lng' => 123.8854])->assertTooManyRequests();
});

test('an owner can connect a tracker and gets its token once', function () {
    $this->device->delete();
    $vehicle = $this->booking->vehicle;

    $component = Livewire::actingAs($vehicle->owner)
        ->test('pages::owner.vehicles.form', ['vehicle' => $vehicle])
        ->call('connectTracker');

    $token = $component->get('issuedTrackerToken');

    expect($token)->toStartWith('gps_')
        ->and(GpsDevice::findByToken($token)?->vehicle_id)->toBe($vehicle->id)
        ->and($vehicle->gpsDevice()->first()->token_hash)->not->toBe($token);

    $component->assertSee($token)->assertSee(route('api.tracking.pings'));

    sendPings(['lat' => 10.3157, 'lng' => 123.8854], $token)->assertAccepted();
});

test('issuing a new token stops the old one working, and disconnecting stops both', function () {
    $component = Livewire::actingAs($this->booking->owner)
        ->test('pages::owner.vehicles.form', ['vehicle' => $this->booking->vehicle])
        ->call('connectTracker');

    sendPings(['lat' => 10.3157, 'lng' => 123.8854])->assertUnauthorized();
    sendPings(['lat' => 10.3157, 'lng' => 123.8854], $component->get('issuedTrackerToken'))->assertAccepted();

    $component->call('disconnectTracker');

    expect(GpsDevice::count())->toBe(0);
});

test('the tracking page is open to the renter, owner, and admins during the trip', function (string $who, bool $allowed) {
    $user = match ($who) {
        'renter' => $this->booking->renter,
        'owner' => $this->booking->owner,
        'admin' => User::factory()->admin()->create(),
        'stranger' => User::factory()->create(),
    };

    $response = $this->actingAs($user)->get(route('bookings.tracking', $this->booking));

    $allowed ? $response->assertOk()->assertSee('trackingMap(', escape: false) : $response->assertForbidden();
})->with([
    'renter' => ['renter', true],
    'owner' => ['owner', true],
    'admin' => ['admin', true],
    'stranger' => ['stranger', false],
]);

test('after the trip only the owner and admins can see the route', function () {
    $this->booking->forceFill(['status' => BookingStatus::Completed])->save();

    $this->actingAs($this->booking->renter)->get(route('bookings.tracking', $this->booking))->assertForbidden();
    $this->actingAs($this->booking->owner)->get(route('bookings.tracking', $this->booking))->assertOk()->assertSee('Route recorded during the rental');
});

test('trips that have not started cannot be tracked', function () {
    $this->booking->forceFill(['status' => BookingStatus::Confirmed])->save();

    $this->actingAs($this->booking->owner)->get(route('bookings.tracking', $this->booking))->assertForbidden();
});

test('the live map is refreshed with the latest route', function () {
    VehicleLocation::factory()->for($this->booking)->create(['latitude' => 10.30, 'longitude' => 123.88, 'recorded_at' => now()->subMinutes(2)]);
    VehicleLocation::factory()->for($this->booking)->create(['latitude' => 10.31, 'longitude' => 123.89, 'recorded_at' => now()->subMinute(), 'speed_kph' => 35]);

    Livewire::actingAs($this->booking->owner)
        ->test('tracking.live-map', ['booking' => $this->booking])
        ->assertSee('35 km/h')
        ->call('refreshTracking')
        ->assertDispatched('tracking-updated', fn (string $event, array $params) => $params['booking'] === $this->booking->reference
            && count($params['points']) === 2
            && $params['latest']['lat'] === 10.31);
});

test('before the first position the map waits at the pickup point', function () {
    Livewire::actingAs($this->booking->owner)
        ->test('tracking.live-map', ['booking' => $this->booking])
        ->assertSee('Waiting for the tracker')
        ->assertSee('The map is centred on the pickup point.')
        ->assertSee('Pickup point');
});

test('the live map is shown right on the renter and owner booking pages during the trip', function () {
    $this->actingAs($this->booking->renter)
        ->get(route('trips.show', $this->booking))
        ->assertSee('Live map')
        ->assertSee('trackingMap(', escape: false)
        ->assertSee('Open full map');

    $this->actingAs($this->booking->owner)
        ->get(route('owner.bookings.show', $this->booking))
        ->assertSee('Where the vehicle is now')
        ->assertSee('trackingMap(', escape: false);
});

test('the booking pages have no live map once the trip is over', function () {
    $this->booking->forceFill(['status' => BookingStatus::Completed])->save();

    $this->actingAs($this->booking->owner)
        ->get(route('owner.bookings.show', $this->booking))
        ->assertDontSee('Where the vehicle is now');
});

test('the simulator drives the vehicle around for local testing', function () {
    $this->artisan('tracking:simulate', ['booking' => $this->booking->reference, '--steps' => 5, '--interval' => 0])->assertSuccessful();

    expect($this->booking->locations()->count())->toBe(5)
        ->and($this->booking->locations()->pluck('speed_kph')->every(fn ($speed) => $speed >= 20 && $speed <= 60))->toBeTrue();
});

test('the simulator pairs a stand-in tracker when the vehicle has none', function () {
    $this->device->delete();

    $this->artisan('tracking:simulate', ['booking' => $this->booking->reference, '--steps' => 2, '--interval' => 0])
        ->expectsOutputToContain('a simulated one was paired')
        ->assertSuccessful();

    expect($this->booking->vehicle->gpsDevice()->first()->label)->toBe('Simulated tracker');
});

test('the simulator only drives vehicles that are out on a trip', function () {
    $this->booking->forceFill(['status' => BookingStatus::Confirmed])->save();

    $this->artisan('tracking:simulate', ['booking' => $this->booking->reference, '--interval' => 0])->assertFailed();

    expect(VehicleLocation::count())->toBe(0);
});

test('trip location history is pruned after 30 days', function () {
    VehicleLocation::factory()->for($this->booking)->create(['recorded_at' => now()->subDays(31)]);
    $recent = VehicleLocation::factory()->for($this->booking)->create(['recorded_at' => now()->subDays(29)]);

    $this->artisan('model:prune', ['--model' => [VehicleLocation::class]])->assertSuccessful();

    expect(VehicleLocation::pluck('id')->all())->toBe([$recent->id]);
});
