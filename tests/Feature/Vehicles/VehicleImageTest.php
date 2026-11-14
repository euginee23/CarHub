<?php

use App\Models\Vehicle;
use App\Models\VehiclePhoto;
use Illuminate\Support\Facades\Blade;

test('a vehicle without photos shows the branded placeholder', function () {
    $vehicle = Vehicle::factory()->create();

    $html = Blade::render('<x-marketing.vehicle-image :vehicle="$vehicle" />', ['vehicle' => $vehicle]);

    expect($html)->toContain('data-test="vehicle-placeholder"')
        ->not->toContain('<img');
});

test('a vehicle photo falls back to the placeholder when its file fails to load', function () {
    $photo = VehiclePhoto::factory()->create();
    $vehicle = $photo->vehicle->load('coverPhoto');

    $html = Blade::render('<x-marketing.vehicle-image :vehicle="$vehicle" />', ['vehicle' => $vehicle]);

    expect($html)->toContain('src="'.$photo->url().'"')
        ->toContain('x-on:error="failed = true"')
        ->toContain('<template x-if="failed">')
        ->toContain('data-test="vehicle-placeholder"');
});
