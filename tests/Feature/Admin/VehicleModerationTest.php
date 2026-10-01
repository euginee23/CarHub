<?php

use App\Enums\VehicleStatus;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\VehicleTakenDown;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->vehicle = Vehicle::factory()->create();
});

test('administrators see every listing', function () {
    $draft = Vehicle::factory()->draft()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.vehicles.index'))
        ->assertOk()
        ->assertSee($this->vehicle->name)
        ->assertSee($draft->owner->name);
});

test('an administrator takes a listing down and the owner is told why', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.vehicles.index')
        ->call('startTakingDown', $this->vehicle->id)
        ->set('moderationReason', 'Photos do not match the vehicle.')
        ->call('takeDown')
        ->assertHasNoErrors();

    $vehicle = $this->vehicle->fresh();

    expect($vehicle)
        ->status->toBe(VehicleStatus::Unlisted)
        ->isTakenDown()->toBeTrue()
        ->moderation_reason->toBe('Photos do not match the vehicle.')
        ->and(ActivityLog::where('action', 'vehicle.taken_down')->exists())->toBeTrue();

    Notification::assertSentTo($vehicle->owner, VehicleTakenDown::class);
    $this->get(route('vehicles.show', $vehicle))->assertNotFound();
});

test('the owner cannot relist a taken-down vehicle until it is allowed', function () {
    $this->vehicle->forceFill(['status' => VehicleStatus::Unlisted, 'moderated_at' => now(), 'moderation_reason' => 'Fake photos.'])->save();

    Livewire::actingAs($this->vehicle->owner)
        ->test('pages::owner.vehicles.form', ['vehicle' => $this->vehicle])
        ->assertSee('This listing was taken down by an administrator.')
        ->assertSee('Fake photos.')
        ->set('form.status', 'listed')
        ->call('save')
        ->assertHasErrors('form.status');

    expect($this->vehicle->fresh()->status)->toBe(VehicleStatus::Unlisted);

    Livewire::actingAs($this->admin)->test('pages::admin.vehicles.index')->call('allowRelisting', $this->vehicle->id);

    Livewire::actingAs($this->vehicle->owner)
        ->test('pages::owner.vehicles.form', ['vehicle' => $this->vehicle->fresh()])
        ->assertDontSee('This listing was taken down by an administrator.')
        ->set('form.status', 'listed')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->vehicle->fresh())
        ->status->toBe(VehicleStatus::Listed)
        ->isTakenDown()->toBeFalse();
});

test('taken-down listings can be filtered', function () {
    Vehicle::factory()->create(['moderated_at' => now(), 'status' => VehicleStatus::Unlisted, 'moderation_reason' => 'x']);

    $component = Livewire::actingAs($this->admin)->test('pages::admin.vehicles.index')->set('status', 'taken_down');

    expect($component->instance()->vehicles->total())->toBe(1);
});

test('a take-down needs a reason', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.vehicles.index')
        ->call('startTakingDown', $this->vehicle->id)
        ->call('takeDown')
        ->assertHasErrors(['moderationReason' => 'required']);
});

test('only administrators can moderate listings', function () {
    $this->actingAs($this->vehicle->owner)->get(route('admin.vehicles.index'))->assertForbidden();
});
