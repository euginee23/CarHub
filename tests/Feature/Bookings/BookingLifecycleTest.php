<?php

use App\Actions\Bookings\TransitionBooking;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingStatusUpdated;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
});

test('bookings only move along the allowed lifecycle', function (BookingStatus $from, BookingStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [BookingStatus::Requested, BookingStatus::Approved, true],
    [BookingStatus::Requested, BookingStatus::Confirmed, false],
    [BookingStatus::Approved, BookingStatus::AwaitingPayment, true],
    [BookingStatus::AwaitingPayment, BookingStatus::Confirmed, true],
    [BookingStatus::Confirmed, BookingStatus::Ongoing, true],
    [BookingStatus::Ongoing, BookingStatus::Completed, true],
    [BookingStatus::Ongoing, BookingStatus::Cancelled, false],
    [BookingStatus::Completed, BookingStatus::Requested, false],
    [BookingStatus::Declined, BookingStatus::Approved, false],
]);

test('an invalid transition is refused and nothing is recorded', function () {
    $booking = Booking::factory()->create();

    expect(fn () => app(TransitionBooking::class)->handle($booking, BookingStatus::Completed))->toThrow(LogicException::class);

    expect($booking->fresh()->status)->toBe(BookingStatus::Requested)
        ->and($booking->statusChanges()->count())->toBe(0);
});

test('every transition is written to the booking history', function () {
    $booking = Booking::factory()->create();
    $owner = $booking->owner;

    app(TransitionBooking::class)->handle($booking, BookingStatus::Approved, $owner, 'Looks good.');

    $change = $booking->statusChanges()->sole();

    expect($change)
        ->from_status->toBe(BookingStatus::Requested)
        ->to_status->toBe(BookingStatus::Approved)
        ->changed_by->toBe($owner->id)
        ->note->toBe('Looks good.');
});

test('the renter can cancel before paying and the owner is told', function () {
    $booking = Booking::factory()->approved()->create();

    Livewire::actingAs($booking->renter)
        ->test('pages::trips.show', ['booking' => $booking])
        ->set('cancellationReason', 'Plans changed.')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($booking->fresh())
        ->status->toBe(BookingStatus::Cancelled)
        ->cancelled_by->toBe($booking->renter_id)
        ->cancellation_reason->toBe('Plans changed.');

    Notification::assertSentTo($booking->owner, BookingStatusUpdated::class);
    Notification::assertNotSentTo($booking->renter, BookingStatusUpdated::class);
});

test('a confirmed booking cannot be cancelled from the trip page', function () {
    $booking = Booking::factory()->confirmed()->create();

    Livewire::actingAs($booking->renter)
        ->test('pages::trips.show', ['booking' => $booking])
        ->call('cancel')
        ->assertForbidden();

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('renters see their trips grouped by stage', function () {
    $renter = User::factory()->create();
    $upcoming = Booking::factory()->for($renter, 'renter')->create();
    $cancelled = Booking::factory()->for($renter, 'renter')->status(BookingStatus::Cancelled)->create();
    Booking::factory()->create(); // Someone else's trip.

    $component = Livewire::actingAs($renter)->test('pages::trips.index');

    expect($component->instance()->bookings->pluck('id')->all())->toBe([$upcoming->id]);

    $component->set('tab', 'cancelled');

    expect($component->instance()->bookings->pluck('id')->all())->toBe([$cancelled->id]);
});

test('only the renter can open their trip page', function () {
    $booking = Booking::factory()->create();

    $this->actingAs($booking->renter)->get(route('trips.show', $booking))->assertOk()->assertSee($booking->reference);
    $this->actingAs(User::factory()->create())->get(route('trips.show', $booking))->assertForbidden();
});

test('bookings are addressed by their reference, not their ID', function () {
    $booking = Booking::factory()->create();

    expect(route('trips.show', $booking))->toEndWith('/trips/'.$booking->reference);
});
