<?php

use App\Enums\DocumentType;
use App\Enums\IdentityStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerificationDocument;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('administrators can list and filter users', function () {
    // Fixed emails: random ones could also match the search term.
    $renter = User::factory()->create(['name' => 'Ana Renter', 'email' => 'ana@example.com']);
    $company = User::factory()->company()->create(['name' => 'Ben Nemaria', 'email' => 'ben@example.com']);

    $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()->assertSee('Ana Renter')->assertSee('Ben Nemaria');

    $component = Livewire::actingAs($this->admin)->test('pages::admin.users')->set('role', 'company');

    expect($component->instance()->users->pluck('id')->all())->toBe([$company->id]);

    $component->set('role', '')->set('search', 'Ana Renter');

    expect($component->instance()->users->pluck('id')->all())->toBe([$renter->id]);
});

test('administrators see each renter\'s ID status and can filter by it', function () {
    $unverified = User::factory()->create(['name' => 'Cora Uploadless']);
    $pending = User::factory()->create(['name' => 'Dan Waiting']);
    $verified = User::factory()->withVerifiedIdentity()->create(['name' => 'Eve Approved']);

    foreach ([DocumentType::Passport, DocumentType::Umid] as $type) {
        VerificationDocument::factory()->create([
            'documentable_type' => $pending->getMorphClass(),
            'documentable_id' => $pending->id,
            'user_id' => $pending->id,
            'type' => $type,
        ]);
    }

    $this->actingAs($this->admin)->get(route('admin.users'))
        ->assertOk()
        ->assertSee(['Cora Uploadless', 'Dan Waiting', 'Eve Approved'])
        ->assertSee('data-test="identity-status"', escape: false)
        ->assertSee(['No IDs uploaded', 'Pending', 'Approved']);

    $component = Livewire::actingAs($this->admin)->test('pages::admin.users');

    foreach ([[IdentityStatus::NotUploaded, $unverified], [IdentityStatus::Pending, $pending], [IdentityStatus::Approved, $verified]] as [$status, $user]) {
        expect($component->set('identity', $status->value)->instance()->users->pluck('id')->all())->toBe([$user->id]);
    }
});

test('a renter who signed up by mistake can be made a rental company', function () {
    $user = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('changeAccountType', $user->id, 'company')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->role->toBe(UserRole::Company)
        ->company_verified_at->toBeNull();

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('company.apply'));
});

test('a rental company without listings can be made a renter', function () {
    $user = User::factory()->verifiedCompany()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('changeAccountType', $user->id, 'renter')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->role->toBe(UserRole::Renter)
        ->company_verified_at->toBeNull();
});

test('accounts with activity of their type cannot be switched', function () {
    $renter = Booking::factory()->create()->renter;
    $company = Vehicle::factory()->create()->company;

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('changeAccountType', $renter->id, 'company')
        ->assertHasErrors('role')
        ->call('changeAccountType', $company->id, 'renter')
        ->assertHasErrors('role');

    expect($renter->fresh()->role)->toBe(UserRole::Renter)
        ->and($company->fresh()->role)->toBe(UserRole::Company);
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
    $this->actingAs(User::factory()->verifiedCompany()->create())->get(route('admin.users'))->assertForbidden();
});
