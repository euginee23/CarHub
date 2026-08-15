<?php

use Illuminate\Support\Collection;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The filtered vehicles a browse component is currently rendering.
 *
 * @return Collection<int, array<string, mixed>>
 */
function renderedVehicles(Testable $component): Collection
{
    return $component->instance()->vehicles;
}

/**
 * Every demo vehicle, for building expectations.
 *
 * @return Collection<int, array<string, mixed>>
 */
function demoVehicles(): Collection
{
    return collect(config('demo.vehicles'));
}

test('the browse page lists every vehicle by default', function () {
    $component = Livewire::test('pages::marketing.browse')->assertOk();

    expect(renderedVehicles($component))->toHaveCount(demoVehicles()->count());
});

test('filtering by body type narrows the results', function () {
    $expected = demoVehicles()->where('type', 'SUV');

    $component = Livewire::test('pages::marketing.browse')
        ->set('type', 'SUV')
        ->assertSee($expected->first()['name']);

    expect(renderedVehicles($component)->pluck('type')->unique()->all())->toBe(['SUV']);
    expect(renderedVehicles($component))->toHaveCount($expected->count());
});

test('searching matches on model name', function () {
    $component = Livewire::test('pages::marketing.browse')
        ->set('search', 'Fortuner')
        ->assertSee('Toyota Fortuner')
        ->assertDontSee('Suzuki Swift');

    expect(renderedVehicles($component))->toHaveCount(1);
});

test('searching matches on location', function () {
    $expected = demoVehicles()->where('location', 'Mandaue City');

    $component = Livewire::test('pages::marketing.browse')->set('search', 'mandaue');

    expect(renderedVehicles($component))->toHaveCount($expected->count());
});

test('the maximum daily rate excludes pricier vehicles', function () {
    $component = Livewire::test('pages::marketing.browse')->set('maxPrice', '2500');

    expect(renderedVehicles($component)->max('price_per_day'))->toBeLessThanOrEqual(2500);
    expect(renderedVehicles($component))->toHaveCount(demoVehicles()->where('price_per_day', '<=', 2500)->count());
});

test('the minimum seats filter only keeps larger vehicles', function () {
    $component = Livewire::test('pages::marketing.browse')->set('seats', '7');

    expect(renderedVehicles($component)->min('seats'))->toBeGreaterThanOrEqual(7);
    expect(renderedVehicles($component))->toHaveCount(demoVehicles()->where('seats', '>=', 7)->count());
});

test('sorting by price orders the results cheapest first', function () {
    $component = Livewire::test('pages::marketing.browse')->set('sort', 'price-asc');

    expect(renderedVehicles($component)->first()['slug'])
        ->toBe(demoVehicles()->sortBy('price_per_day')->first()['slug']);
});

test('a single filter can be cleared', function () {
    $component = Livewire::test('pages::marketing.browse')
        ->set('type', 'SUV')
        ->call('clearFilter', 'type')
        ->assertSet('type', '');

    expect(renderedVehicles($component))->toHaveCount(demoVehicles()->count());
});

test('all filters can be cleared at once', function () {
    $component = Livewire::test('pages::marketing.browse')
        ->set('type', 'Van')
        ->set('search', 'Hiace')
        ->set('sort', 'rating')
        ->call('clearFilters')
        ->assertSet('type', '')
        ->assertSet('search', '')
        ->assertSet('sort', 'recommended');

    expect(renderedVehicles($component))->toHaveCount(demoVehicles()->count());
});

test('over-filtering shows the empty state', function () {
    $component = Livewire::test('pages::marketing.browse')
        ->set('type', 'Van')
        ->set('maxPrice', '1500')
        ->assertSee('No vehicles match those filters');

    expect(renderedVehicles($component))->toBeEmpty();
});

test('search terms from the home page search bar are applied on mount', function () {
    $this->get(route('vehicles.index', ['search' => 'Fortuner']))
        ->assertOk()
        ->assertSee('Toyota Fortuner')
        ->assertDontSee('Suzuki Swift');
});

test('trip dates carried over from the home page are shown for context', function () {
    $this->get(route('vehicles.index', ['pickup' => '2026-09-01', 'return' => '2026-09-04']))
        ->assertOk()
        ->assertSee('Sep 1')
        ->assertSee('Sep 4, 2026');
});
