<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('an administrator suspends an account with a reason', function () {
    $user = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('startSuspending', $user->id)
        ->set('suspensionReason', 'Fraudulent ID documents.')
        ->call('suspend')
        ->assertHasNoErrors()
        ->assertSee('Suspended')
        ->assertSee('Fraudulent ID documents.');

    expect($user->fresh())
        ->isSuspended()->toBeTrue()
        ->suspension_reason->toBe('Fraudulent ID documents.')
        ->and(ActivityLog::where('action', 'user.suspended')->sole()->actor_id)->toBe($this->admin->id);

    $this->actingAs($user->fresh())->get(route('renter.dashboard'))->assertRedirect(route('login'));
});

test('a suspension needs a reason', function () {
    $user = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('startSuspending', $user->id)
        ->call('suspend')
        ->assertHasErrors(['suspensionReason' => 'required']);

    expect($user->fresh()->isSuspended())->toBeFalse();
});

test('a suspended owner\'s listings leave the marketplace until reinstated', function () {
    $vehicle = Vehicle::factory()->create(['brand' => 'Hidden', 'model' => 'Car']);
    $owner = $vehicle->owner;

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('startSuspending', $owner->id)
        ->set('suspensionReason', 'Repeated no-shows.')
        ->call('suspend');

    expect(Vehicle::listed()->pluck('id')->all())->not->toContain($vehicle->id);
    $this->get(route('vehicles.show', $vehicle))->assertNotFound();

    Livewire::actingAs($this->admin)->test('pages::admin.users')->call('reinstate', $owner->id);

    expect($owner->fresh()->isSuspended())->toBeFalse()
        ->and(Vehicle::listed()->pluck('id')->all())->toContain($vehicle->id);
});

test('administrators cannot be suspended', function () {
    $other = User::factory()->admin()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('startSuspending', $other->id)
        ->set('suspensionReason', 'Testing the rules.')
        ->call('suspend')
        ->assertHasErrors('suspension');

    expect($other->fresh()->isSuspended())->toBeFalse();
});

test('the user list can show only suspended accounts', function () {
    $suspended = User::factory()->suspended()->create();
    User::factory()->create();

    $component = Livewire::actingAs($this->admin)->test('pages::admin.users')->set('suspendedOnly', true);

    expect($component->instance()->users->pluck('id')->all())->toBe([$suspended->id]);
});
