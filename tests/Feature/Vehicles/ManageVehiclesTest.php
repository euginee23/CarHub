<?php

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(VehiclePhoto::DISK);
});

/**
 * A complete, valid set of listing fields, keyed for the component's form object.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validListing(array $overrides = []): array
{
    return collect([
        'brand' => 'Toyota',
        'model' => 'Vios',
        'year' => '2022',
        'type' => 'Sedan',
        'transmission' => 'Automatic',
        'fuel' => 'Gasoline',
        'seats' => '5',
        'price_per_day' => '1800',
        'description' => 'A dependable, fuel-sipping sedan for city and provincial trips.',
        'features' => ['Air conditioning', 'Dashcam'],
        'location' => 'Cebu City',
        'latitude' => 10.3157,
        'longitude' => 123.8854,
        'status' => 'listed',
        'instant_book' => true,
        ...$overrides,
    ])->mapWithKeys(fn (mixed $value, string $field) => ['form.'.$field => $value])->all();
}

test('rental companies who are not verified yet cannot manage vehicles', function (string $routeName) {
    $this->actingAs(User::factory()->company()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with(['company.vehicles.index', 'company.vehicles.create']);

test('renter accounts are sent back to their own dashboard from the rental company area', function (string $routeName) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName))
        ->assertRedirect(route('dashboard'));
})->with(['company.vehicles.index', 'company.vehicles.create']);

test('verified rental companies see their own vehicles only', function () {
    $company = User::factory()->verifiedCompany()->create();
    Vehicle::factory()->for($company, 'company')->create(['brand' => 'Honda', 'model' => 'Civic']);
    Vehicle::factory()->create(['brand' => 'Nissan', 'model' => 'Navara']);

    $this->actingAs($company)
        ->get(route('company.vehicles.index'))
        ->assertOk()
        ->assertSee('Honda Civic')
        ->assertDontSee('Nissan Navara');
});

test('a verified rental company can list a vehicle with photos', function () {
    $company = User::factory()->verifiedCompany()->create();

    Livewire::actingAs($company)
        ->test('pages::company.vehicles.form')
        ->fill(validListing())
        ->set('newPhotos', [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('side.jpg')])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $vehicle = $company->vehicles()->sole();

    expect($vehicle->name)->toBe('Toyota Vios')
        ->and($vehicle->slug)->toBe('toyota-vios-2022')
        ->and($vehicle->type)->toBe(VehicleType::Sedan)
        ->and($vehicle->status)->toBe(VehicleStatus::Listed)
        ->and($vehicle->price_per_day)->toBe(1800)
        ->and($vehicle->features)->toBe(['Air conditioning', 'Dashcam'])
        ->and($vehicle->instant_book)->toBeTrue()
        ->and($vehicle->photos)->toHaveCount(2);

    Storage::disk(VehiclePhoto::DISK)->assertExists($vehicle->photos->pluck('path')->all());

    $this->get(route('vehicles.show', $vehicle))->assertOk()->assertSee('Toyota Vios');
});

test('slugs stay unique when the same model is listed twice', function () {
    Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Vios', 'year' => 2022]);

    Livewire::actingAs(User::factory()->verifiedCompany()->create())
        ->test('pages::company.vehicles.form')
        ->fill(validListing())
        ->call('save')
        ->assertHasNoErrors();

    expect(Vehicle::pluck('slug')->all())->toContain('toyota-vios-2022', 'toyota-vios-2022-2');
});

test('listing fields are validated', function (string $field, mixed $value, string $rule) {
    Livewire::actingAs(User::factory()->verifiedCompany()->create())
        ->test('pages::company.vehicles.form')
        ->fill(validListing([$field => $value]))
        ->call('save')
        ->assertHasErrors(['form.'.$field => $rule]);

    expect(Vehicle::count())->toBe(0);
})->with([
    'missing brand' => ['brand', '', 'required'],
    'unknown body type' => ['type', 'Tank', 'Illuminate\Validation\Rules\Enum'],
    'price too low' => ['price_per_day', '100', 'min'],
    'missing map pin' => ['latitude', null, 'required'],
    'short description' => ['description', 'Nice car.', 'min'],
]);

test('a rental company can update their vehicle', function () {
    $vehicle = Vehicle::factory()->create(['price_per_day' => 2000]);

    Livewire::actingAs($vehicle->company)
        ->test('pages::company.vehicles.form', ['vehicle' => $vehicle])
        ->assertSet('form.price_per_day', '2000')
        ->set('form.price_per_day', '2400')
        ->set('form.status', 'unlisted')
        ->call('save')
        ->assertHasNoErrors();

    expect($vehicle->fresh())
        ->price_per_day->toBe(2400)
        ->status->toBe(VehicleStatus::Unlisted);
});

test('a rental company cannot edit another rental company\'s vehicle', function () {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->verifiedCompany()->create())
        ->get(route('company.vehicles.edit', $vehicle))
        ->assertForbidden();
});

test('photos can be reordered and deleted', function () {
    $vehicle = Vehicle::factory()->create();
    $first = VehiclePhoto::factory()->for($vehicle)->create(['position' => 0]);
    $second = VehiclePhoto::factory()->for($vehicle)->create(['position' => 1]);
    Storage::disk(VehiclePhoto::DISK)->put($first->path, 'image');

    $component = Livewire::actingAs($vehicle->company)
        ->test('pages::company.vehicles.form', ['vehicle' => $vehicle])
        ->call('movePhoto', $second->id, 'up');

    expect($vehicle->photos()->pluck('id')->all())->toBe([$second->id, $first->id]);

    $component->call('deletePhoto', $first->id);

    expect($vehicle->photos()->pluck('id')->all())->toBe([$second->id]);
    Storage::disk(VehiclePhoto::DISK)->assertMissing($first->path);
});

test('the uploaded cover photo is shown on the public listing', function () {
    $vehicle = Vehicle::factory()->create();
    $photo = VehiclePhoto::factory()->for($vehicle)->create();

    $this->get(route('vehicles.show', $vehicle))
        ->assertOk()
        ->assertSee($photo->url(), escape: false);
});

test('suspended users are signed out', function () {
    $this->actingAs(User::factory()->suspended()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
