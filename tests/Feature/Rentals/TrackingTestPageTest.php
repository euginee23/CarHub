<?php

use App\Actions\Tracking\StartDemoTrip;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\GpsDevice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use App\Support\TrackerRequestLog;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    config(['carhub.tracking.test_page' => true]);

    $this->admin = User::factory()->admin()->create();
    $this->vehicle = Vehicle::factory()->create(['latitude' => 10.3157, 'longitude' => 123.8854, 'trips_count' => 7]);
});

test('administrators open the test page by typing its address', function () {
    $this->actingAs($this->admin)
        ->get('/test-track-gps-map')
        ->assertOk()
        ->assertSee('GPS tracker test')
        ->assertSee('Choose the vehicle');
});

test('only administrators can use the test page', function () {
    $this->get('/test-track-gps-map')->assertRedirect(route('login'));
    $this->actingAs($this->vehicle->owner)->get('/test-track-gps-map')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/test-track-gps-map')->assertForbidden();
});

test('the test page does not exist when switched off', function () {
    config(['carhub.tracking.test_page' => false]);

    $this->actingAs($this->admin)->get('/test-track-gps-map')->assertNotFound();
});

test('choosing a vehicle shows the endpoint, the request format, and the phone sender', function () {
    $this->actingAs($this->admin)
        ->get('/test-track-gps-map?vehicle='.$this->vehicle->id)
        ->assertOk()
        ->assertSee(url('/api/v1/tracking/pings'))
        ->assertSee('cannot reach localhost')
        ->assertSee('ESP32')
        ->assertSee('gpsSender(', escape: false)
        ->assertSee('phones will refuse to share GPS')
        ->assertSee('Not on a trip');
});

test('assigning a tracker issues a working token', function () {
    $component = Livewire::actingAs($this->admin)
        ->test('pages::admin.tracking-test')
        ->set('vehicleId', (string) $this->vehicle->id)
        ->call('assignTracker')
        ->assertDispatched('tracker-token-issued');

    $token = $component->get('issuedToken');

    expect(GpsDevice::findByToken($token)?->vehicle_id)->toBe($this->vehicle->id);

    $component->assertSee($token);
});

test('pings show in the request log, without positions when there is no rental', function () {
    $device = GpsDevice::factory()->withToken('gps_test_page')->for($this->vehicle)->create();

    $this->withToken('gps_test_page')->postJson(route('api.tracking.pings'), ['lat' => 10.3157, 'lng' => 123.8854])->assertAccepted();
    $this->withToken('gps_test_page')->postJson(route('api.tracking.pings'), ['lat' => 99, 'lng' => 123.8854])->assertUnprocessable();

    $entries = TrackerRequestLog::for($device);

    expect($entries)->toHaveCount(2)
        ->and($entries[0])->toMatchArray(['status' => 422, 'stored' => 0])
        ->and($entries[0]['error'])->toContain('lat')
        ->and($entries[1])->toMatchArray(['status' => 202, 'received' => 1, 'stored' => 0, 'lat' => null, 'lng' => null]);

    Livewire::actingAs($this->admin)
        ->test('tracking.request-log', ['device' => $device])
        ->assertSee('not stored — no ongoing rental');
});

test('a demo trip puts the vehicle on a rental so pings are recorded and mapped', function () {
    $device = GpsDevice::factory()->withToken('gps_test_page')->for($this->vehicle)->create();

    $component = Livewire::actingAs($this->admin)
        ->test('pages::admin.tracking-test')
        ->set('vehicleId', (string) $this->vehicle->id)
        ->call('startDemoTrip')
        ->assertHasNoErrors()
        ->assertSee('positions are being recorded');

    $booking = Booking::sole();

    expect($booking->status)->toBe(BookingStatus::Ongoing)
        ->and($booking->renter->email)->toBe(StartDemoTrip::RENTER_EMAIL)
        ->and($booking->statusChanges()->count())->toBe(2);

    $this->withToken('gps_test_page')->postJson(route('api.tracking.pings'), ['lat' => 10.32, 'lng' => 123.89])
        ->assertAccepted()
        ->assertJson(['stored' => 1]);

    expect(VehicleLocation::sole()->booking_id)->toBe($booking->id)
        ->and(TrackerRequestLog::for($device)[0]['lat'])->toBe(10.32);

    $this->actingAs($this->admin)
        ->get('/test-track-gps-map?vehicle='.$this->vehicle->id)
        ->assertSee('trackingMap(', escape: false)
        ->assertSee($booking->reference);

    Notification::assertNothingSent();
});

test('ending a demo trip stops tracking without counting it as a real trip', function () {
    $booking = app(StartDemoTrip::class)->handle($this->vehicle, $this->admin);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.tracking-test')
        ->set('vehicleId', (string) $this->vehicle->id)
        ->call('endDemoTrip')
        ->assertSee('Not on a trip');

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed)
        ->and($this->vehicle->fresh()->trips_count)->toBe(7);

    Notification::assertNothingSent();
});

test('a demo trip cannot start when the vehicle is already booked', function () {
    Booking::factory()->forVehicle($this->vehicle)->confirmed()->between(now()->addHours(3)->toDateTimeString(), now()->addHours(30)->toDateTimeString())->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.tracking-test')
        ->set('vehicleId', (string) $this->vehicle->id)
        ->call('startDemoTrip')
        ->assertHasErrors('demo');

    expect(Booking::where('status', BookingStatus::Ongoing)->count())->toBe(0);
});

test('real rentals cannot be ended from the test page', function () {
    Booking::factory()->forVehicle($this->vehicle)->status(BookingStatus::Ongoing)->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.tracking-test')
        ->set('vehicleId', (string) $this->vehicle->id)
        ->assertDontSee('End demo trip')
        ->call('endDemoTrip')
        ->assertHasErrors('demo');
});

test('demo trips are refused when the test page is switched off', function () {
    config(['carhub.tracking.test_page' => false]);

    expect(fn () => app(StartDemoTrip::class)->handle($this->vehicle, $this->admin))->toThrow(LogicException::class);
});
