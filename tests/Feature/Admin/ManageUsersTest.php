<?php

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('administrators can list and filter users', function () {
    // Fixed emails: random ones could also match the search term.
    $renter = User::factory()->create(['name' => 'Ana Renter', 'email' => 'ana@example.com']);
    $owner = User::factory()->owner()->create(['name' => 'Ben Owner', 'email' => 'ben@example.com']);

    $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()->assertSee('Ana Renter')->assertSee('Ben Owner');

    $component = Livewire::actingAs($this->admin)->test('pages::admin.users')->set('role', 'owner');

    expect($component->instance()->users->pluck('id')->all())->toBe([$owner->id]);

    $component->set('role', '')->set('search', 'Ana Renter');

    expect($component->instance()->users->pluck('id')->all())->toBe([$renter->id]);
});

test('a renter who signed up by mistake can be made an owner', function () {
    $user = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('changeAccountType', $user->id, 'owner')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->role->toBe(UserRole::Owner)
        ->owner_verified_at->toBeNull();

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('owner.apply'));
});

test('an owner without listings can be made a renter', function () {
    $user = User::factory()->verifiedOwner()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('changeAccountType', $user->id, 'renter')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->role->toBe(UserRole::Renter)
        ->owner_verified_at->toBeNull();
});

test('accounts with activity of their type cannot be switched', function () {
    $renter = Booking::factory()->create()->renter;
    $owner = Vehicle::factory()->create()->owner;

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('changeAccountType', $renter->id, 'owner')
        ->assertHasErrors('role')
        ->call('changeAccountType', $owner->id, 'renter')
        ->assertHasErrors('role');

    expect($renter->fresh()->role)->toBe(UserRole::Renter)
        ->and($owner->fresh()->role)->toBe(UserRole::Owner);
});

test('administrator accounts cannot be switched or created here', function () {
    $otherAdmin = User::factory()->admin()->create();
    $renter = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('changeAccountType', $otherAdmin->id, 'renter')
        ->assertHasErrors('role')
        ->call('changeAccountType', $renter->id, 'admin')
        ->assertHasErrors('role');

    expect($otherAdmin->fresh()->role)->toBe(UserRole::Admin)
        ->and($renter->fresh()->role)->toBe(UserRole::Renter);
});

test('only administrators can manage users', function () {
    $this->actingAs(User::factory()->verifiedOwner()->create())->get(route('admin.users'))->assertForbidden();
});
