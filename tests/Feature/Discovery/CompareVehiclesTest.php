<?php

use App\Models\Vehicle;
use App\Support\CompareList;
use Livewire\Livewire;

test('vehicles can be added to and removed from the comparison from browse', function () {
    $vehicle = Vehicle::factory()->create();

    $component = Livewire::test('pages::marketing.browse')
        ->call('toggleCompare', $vehicle->id)
        ->assertSee('1 vehicle selected to compare');

    expect(session(CompareList::SESSION_KEY))->toBe([$vehicle->id]);

    $component->call('toggleCompare', $vehicle->id);

    expect(session(CompareList::SESSION_KEY))->toBe([]);
});

test('no more than the configured number of vehicles can be compared', function () {
    $vehicles = Vehicle::factory()->count(4)->create();

    $component = Livewire::test('pages::marketing.browse');

    $vehicles->each(fn (Vehicle $vehicle) => $component->call('toggleCompare', $vehicle->id));

    expect(session(CompareList::SESSION_KEY))->toBe($vehicles->take(3)->pluck('id')->all());
    $component->assertSee('Compare now');
});

test('unlisted vehicles cannot be added to the comparison', function () {
    $vehicle = Vehicle::factory()->draft()->create();

    Livewire::test('pages::marketing.browse')->call('toggleCompare', $vehicle->id);

    expect(session(CompareList::SESSION_KEY, []))->toBe([]);
});

test('the compare page lines vehicles up side by side and highlights the cheapest', function () {
    $cheap = Vehicle::factory()->create(['brand' => 'Cheap', 'model' => 'One', 'price_per_day' => 1500, 'features' => ['Dashcam']]);
    $pricey = Vehicle::factory()->create(['brand' => 'Pricey', 'model' => 'Two', 'price_per_day' => 4500, 'features' => ['Roof rack']]);

    session([CompareList::SESSION_KEY => [$cheap->id, $pricey->id]]);

    $component = Livewire::test('pages::marketing.compare')
        ->assertSee('Cheap One')
        ->assertSee('Pricey Two')
        ->assertSee('Dashcam')
        ->assertSee('Roof rack');

    $priceRow = collect($component->instance()->rows)->firstWhere('label', 'Daily rate');

    expect($priceRow['values'])->toBe(['₱1,500', '₱4,500'])
        ->and($priceRow['best'])->toBe([true, false]);
});

test('removing a vehicle from the compare page updates the list', function () {
    [$first, $second] = Vehicle::factory()->count(2)->create();
    session([CompareList::SESSION_KEY => [$first->id, $second->id]]);

    Livewire::test('pages::marketing.compare')
        ->call('remove', $first->id)
        ->assertSee('Pick at least two vehicles to compare');

    expect(session(CompareList::SESSION_KEY))->toBe([$second->id]);
});

test('the compare page is reachable and prompts when empty', function () {
    $this->get(route('vehicles.compare'))
        ->assertOk()
        ->assertSee('Pick at least two vehicles to compare');
});
