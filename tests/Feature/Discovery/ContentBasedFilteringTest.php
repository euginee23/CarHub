<?php

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use App\Services\Matching\VehicleFeatureVector;
use App\Services\Matching\VehicleSimilarity;
use Livewire\Livewire;

/**
 * Create a listed vehicle with fixed characteristics.
 *
 * @param  array<string, mixed>  $attributes
 */
function vehicleLike(string $name, VehicleType $type, int $seats, int $price, array $attributes = []): Vehicle
{
    return Vehicle::factory()->create([
        'brand' => $name,
        'model' => 'Test',
        'type' => $type,
        'seats' => $seats,
        'price_per_day' => $price,
        'transmission' => Transmission::Automatic,
        'fuel' => FuelType::Gasoline,
        'year' => 2022,
        'features' => ['Air conditioning'],
        ...$attributes,
    ]);
}

test('a vehicle is perfectly similar to itself and dissimilar to an unrelated one', function () {
    $sedan = vehicleLike('Sedan', VehicleType::Sedan, 5, 1800);
    $van = vehicleLike('Van', VehicleType::Van, 12, 5500, ['transmission' => Transmission::Manual, 'fuel' => FuelType::Diesel, 'year' => 2016, 'features' => ['Sliding doors']]);

    $sedanVector = VehicleFeatureVector::forVehicle($sedan);

    expect($sedanVector->cosineSimilarity($sedanVector))->toEqualWithDelta(1.0, 0.0001)
        ->and($sedanVector->cosineSimilarity(VehicleFeatureVector::forVehicle($van)))->toBeLessThan(0.1);
});

test('similar vehicles rank by body type, size, and price', function () {
    $reference = vehicleLike('Reference', VehicleType::Suv, 7, 4000);
    $closest = vehicleLike('Closest', VehicleType::Suv, 7, 4200);
    $sameTypeCheaper = vehicleLike('Cheaper', VehicleType::Suv, 7, 1400);
    $differentType = vehicleLike('Hatch', VehicleType::Hatchback, 5, 1300);

    $similar = app(VehicleSimilarity::class)->similarTo($reference, limit: 3);

    expect($similar->pluck('id')->all())->toBe([$closest->id, $sameTypeCheaper->id, $differentType->id])
        ->and($similar->first()->similarity)->toBeGreaterThan($similar->last()->similarity);
});

test('only listed vehicles are suggested', function () {
    $reference = vehicleLike('Reference', VehicleType::Sedan, 5, 1800);
    vehicleLike('Draft', VehicleType::Sedan, 5, 1800, ['status' => 'draft']);

    expect(app(VehicleSimilarity::class)->similarTo($reference))->toBeEmpty();
});

test('preference matching skips vehicles already shown and needs at least one preference', function () {
    $suv = vehicleLike('Suv', VehicleType::Suv, 7, 3000);
    $other = vehicleLike('OtherSuv', VehicleType::Suv, 7, 3200);
    vehicleLike('Sedan', VehicleType::Sedan, 5, 1500);

    $matches = app(VehicleSimilarity::class)->matchingPreferences(['type' => 'SUV', 'seats' => 7], excludeIds: [$suv->id], limit: 1);

    expect($matches->pluck('id')->all())->toBe([$other->id])
        ->and(app(VehicleSimilarity::class)->matchingPreferences([]))->toBeEmpty();
});

test('the browse page suggests close matches outside the active filters', function () {
    vehicleLike('Pricey', VehicleType::Suv, 7, 3200);
    vehicleLike('Budget', VehicleType::Sedan, 5, 1400);

    $component = Livewire::test('pages::marketing.browse')
        ->set('type', 'SUV')
        ->set('maxPrice', '2500')
        ->assertSee('No vehicles match those filters')
        ->assertSee('Close matches');

    expect($component->instance()->closeMatches->first()->brand)->toBe('Pricey');
});

test('no close matches are suggested without filters', function () {
    vehicleLike('Any', VehicleType::Suv, 7, 3200);

    Livewire::test('pages::marketing.browse')->assertDontSee('Close matches');
});

test('the vehicle page lists content-based similar vehicles', function () {
    $reference = vehicleLike('Reference', VehicleType::Suv, 7, 4000);
    vehicleLike('Lookalike', VehicleType::Suv, 7, 4100);

    $this->get(route('vehicles.show', $reference))
        ->assertOk()
        ->assertSee('You might also like')
        ->assertSee('Lookalike Test');
});
