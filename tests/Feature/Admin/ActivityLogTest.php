<?php

use App\Actions\Bookings\TransitionBooking;
use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\OwnerApplication;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
});

test('booking status changes are written to the activity log', function () {
    $booking = Booking::factory()->create();

    app(TransitionBooking::class)->handle($booking, BookingStatus::Approved, $booking->owner, 'Looks good.');

    $entry = ActivityLog::where('action', 'booking.approved')->sole();

    expect($entry)
        ->actor_id->toBe($booking->owner_id)
        ->subject->is($booking)->toBeTrue()
        ->and($entry->description)->toContain($booking->reference)
        ->and($entry->properties)->toBe(['note' => 'Looks good.']);
});

test('reviews of owner applications are logged with the reviewer', function () {
    $application = OwnerApplication::factory()->create();

    Livewire::actingAs($this->admin)->test('pages::admin.owner-applications')->call('approve', $application->id);

    expect(ActivityLog::where('action', 'owner_application.approved')->sole()->actor_id)->toBe($this->admin->id);
});

test('sign-ins and failed sign-ins are logged', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);

    $this->post(route('login.store'), ['email' => 'ana@example.com', 'password' => 'wrong-password']);
    $this->post(route('login.store'), ['email' => 'ana@example.com', 'password' => 'password']);

    expect(ActivityLog::where('action', 'auth.failed')->sole()->description)->toContain('ana@example.com')
        ->and(ActivityLog::where('action', 'auth.login')->sole()->actor_id)->toBe($user->id);
});

test('administrators can browse and filter the activity log', function () {
    $booking = Booking::factory()->create();
    app(TransitionBooking::class)->handle($booking, BookingStatus::Approved, $booking->owner);
    ActivityLogger::record('auth.login', 'Someone signed in.');

    $this->actingAs($this->admin)->get(route('admin.activity'))->assertOk()->assertSee($booking->reference);

    $component = Livewire::actingAs($this->admin)->test('pages::admin.activity')->set('area', 'booking');

    expect($component->instance()->entries->pluck('action')->all())->toBe(['booking.approved']);

    $component->set('area', '')->set('search', 'Someone');

    expect($component->instance()->entries->pluck('action')->all())->toBe(['auth.login']);
});

test('old activity is pruned after a year', function () {
    $old = ActivityLogger::record('auth.login', 'Old');
    $old->forceFill(['created_at' => now()->subDays(400)])->save();
    $recent = ActivityLogger::record('auth.login', 'Recent');

    $this->artisan('model:prune', ['--model' => [ActivityLog::class]])->assertSuccessful();

    expect(ActivityLog::pluck('id')->all())->toBe([$recent->id]);
});

test('only administrators see the activity log', function () {
    $this->actingAs(User::factory()->verifiedOwner()->create())->get(route('admin.activity'))->assertForbidden();
});
