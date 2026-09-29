<?php

use App\Models\Vehicle;
use App\Support\Geo;
use Livewire\Livewire;

/**
 * Cebu City centre, the origin for these searches.
 */
const CEBU = ['lat' => 10.3157, 'lng' => 123.8854];

beforeEach(function () {
    $this->near = Vehicle::factory()->create(['brand' => 'Near', 'model' => 'Car', 'location' => 'Cebu City', 'latitude' => 10.3200, 'longitude' => 123.8900]);
    $this->mid = Vehicle::factory()->create(['brand' => 'Mid', 'model' => 'Car', 'location' => 'Mandaue City', 'latitude' => 10.3800, 'longitude' => 123.9300]);
    $this->far = Vehicle::factory()->create(['brand' => 'Far', 'model' => 'Car', 'location' => 'Toledo City', 'latitude' => 10.3773, 'longitude' => 123.6386]);
});

test('the haversine distance between two known points is accurate', function () {
    // Cebu City to Toledo City is roughly 27 km as the crow flies.
    expect(Geo::distanceInKm(CEBU['lat'], CEBU['lng'], 10.3773, 123.6386))->toBeGreaterThan(26)->toBeLessThan(28);
    expect(Geo::distanceInKm(CEBU['lat'], CEBU['lng'], CEBU['lat'], CEBU['lng']))->toBe(0.0);
});

test('using the renter\'s location sorts vehicles nearest first', function () {
    $component = Livewire::test('pages::marketing.browse')
        ->call('useLocation', CEBU['lat'], CEBU['lng'])
        ->assertSet('sort', 'nearest')
        ->assertSee('km away');

    expect($component->instance()->vehicles->pluck('id')->all())->toBe([$this->near->id, $this->mid->id, $this->far->id]);
});

test('a radius keeps only vehicles within that distance', function () {
    $component = Livewire::test('pages::marketing.browse')
        ->call('useLocation', CEBU['lat'], CEBU['lng'])
        ->set('radius', '10');

    expect($component->instance()->vehicles->pluck('id')->all())->toBe([$this->near->id, $this->mid->id]);

    $component->set('radius', '5');

    expect($component->instance()->vehicles->pluck('id')->all())->toBe([$this->near->id]);
});

test('a radius outside the offered options is ignored', function () {
    $component = Livewire::test('pages::marketing.browse')
        ->call('useLocation', CEBU['lat'], CEBU['lng'])
        ->set('radius', '1');

    expect($component->instance()->vehicles)->toHaveCount(3);
});

test('choosing an area centres the search on its vehicles', function () {
    $component = Livewire::test('pages::marketing.browse')
        ->set('area', 'Toledo City')
        ->assertSet('lat', '10.3773')
        ->assertSet('lng', '123.6386')
        ->set('radius', '5')
        ->assertSee('Within 5 km of Toledo City');

    expect($component->instance()->vehicles->pluck('id')->all())->toBe([$this->far->id]);
});

test('clearing the location filter resets the origin and nearest sort', function () {
    Livewire::test('pages::marketing.browse')
        ->call('useLocation', CEBU['lat'], CEBU['lng'])
        ->set('radius', '5')
        ->call('clearFilter', 'location')
        ->assertSet('lat', '')
        ->assertSet('radius', '')
        ->assertSet('sort', 'recommended');
});

test('the map view plots results with coordinates rounded for privacy', function () {
    $component = Livewire::test('pages::marketing.browse')
        ->set('view', 'map')
        ->assertSeeHtml('vehicleMap(');

    $marker = collect($component->instance()->mapMarkers)->firstWhere('id', $this->near->id);

    expect($marker)
        ->lat->toBe(10.32)
        ->lng->toBe(123.89)
        ->price->toBe('₱'.number_format($this->near->price_per_day))
        ->url->toBe(route('vehicles.show', $this->near));
});

test('unpinned vehicles are left off the map', function () {
    Vehicle::factory()->create(['latitude' => null, 'longitude' => null]);

    $component = Livewire::test('pages::marketing.browse')->set('view', 'map');

    expect($component->instance()->mapMarkers)->toHaveCount(3)
        ->and($component->instance()->vehicles)->toHaveCount(4);
});

test('the vehicle page shows an approximate pickup area map', function () {
    $this->get(route('vehicles.show', $this->near))
        ->assertOk()
        ->assertSee('Pickup area')
        ->assertSeeHtml('pickupAreaMap({ latitude: 10.32, longitude: 123.89 })');
});
