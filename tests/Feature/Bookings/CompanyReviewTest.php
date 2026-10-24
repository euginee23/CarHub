<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookingStatusUpdated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00'));

    $this->vehicle = Vehicle::factory()->create();
    $this->company = $this->vehicle->company;
    $this->booking = Booking::factory()->forVehicle($this->vehicle)->between('2026-10-05 09:00', '2026-10-07 09:00')->create();
});

test('rental companies see pending requests for their vehicles', function () {
    Booking::factory()->create(); // Someone else's vehicle.

    $this->actingAs($this->company)
        ->get(route('company.bookings.index'))
        ->assertOk()
        ->assertSee($this->booking->renter->name)
        ->assertSee('Review');

    expect(Livewire::actingAs($this->company)->test('pages::company.bookings.index')->instance()->bookings)->toHaveCount(1);
});

test('a rental company can approve a request', function () {
    Livewire::actingAs($this->company)
        ->test('pages::company.bookings.show', ['booking' => $this->booking])
        ->call('approve')
        ->assertHasNoErrors();

    $this->booking->refresh();

    expect($this->booking->status)->toBe(BookingStatus::Approved)
        ->and($this->booking->approved_at)->not->toBeNull()
        ->and($this->booking->statusChanges->last()->changed_by)->toBe($this->company->id);

    Notification::assertSentTo($this->booking->renter, BookingStatusUpdated::class, fn (BookingStatusUpdated $notification) => $notification->status === BookingStatus::Approved);
});

test('approving a request declines the others that overlap it', function () {
    $overlapping = Booking::factory()->forVehicle($this->vehicle)->between('2026-10-06 09:00', '2026-10-08 09:00')->create();
    $later = Booking::factory()->forVehicle($this->vehicle)->between('2026-10-10 09:00', '2026-10-12 09:00')->create();

    Livewire::actingAs($this->company)
        ->test('pages::company.bookings.show', ['booking' => $this->booking])
        ->assertSee('Approving will decline 1 other request')
        ->call('approve');

    expect($overlapping->fresh()->status)->toBe(BookingStatus::Declined)
        ->and($overlapping->fresh()->decline_reason)->toContain('booked by someone else')
        ->and($later->fresh()->status)->toBe(BookingStatus::Requested);

    Notification::assertSentTo($overlapping->renter, BookingStatusUpdated::class);
});

test('a request cannot be approved once the vehicle is taken for those dates', function () {
    Booking::factory()->forVehicle($this->vehicle)->confirmed()->between('2026-10-06 09:00', '2026-10-08 09:00')->create();

    Livewire::actingAs($this->company)
        ->test('pages::company.bookings.show', ['booking' => $this->booking])
        ->call('approve')
        ->assertHasErrors('booking');

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Requested);
});

test('a request cannot be approved after its pickup time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00'));

    Livewire::actingAs($this->company)
        ->test('pages::company.bookings.show', ['booking' => $this->booking])
        ->call('approve')
        ->assertHasErrors('booking');
});

test('a rental company can decline a request with a reason', function () {
    Livewire::actingAs($this->company)
        ->test('pages::company.bookings.show', ['booking' => $this->booking])
        ->set('declineReason', 'The car is in the shop that week.')
        ->call('decline')
        ->assertHasNoErrors();

    expect($this->booking->fresh())
        ->status->toBe(BookingStatus::Declined)
        ->decline_reason->toBe('The car is in the shop that week.');

    Notification::assertSentTo($this->booking->renter, BookingStatusUpdated::class);
});

test('declining requires a reason', function () {
    Livewire::actingAs($this->company)
        ->test('pages::company.bookings.show', ['booking' => $this->booking])
        ->call('decline')
        ->assertHasErrors(['declineReason' => 'required']);
});

test('a request that was already answered cannot be answered again', function () {
    $this->booking->forceFill(['status' => BookingStatus::Declined])->save();

    Livewire::actingAs($this->company)
        ->test('pages::company.bookings.show', ['booking' => $this->booking])
        ->call('approve')
        ->assertHasErrors('booking');
});

test('another rental company cannot manage the booking', function () {
    $this->actingAs(User::factory()->verifiedCompany()->create())
        ->get(route('company.bookings.show', $this->booking))
        ->assertForbidden();
});

test('renters have no access to the rental company booking pages', function () {
    $this->actingAs($this->booking->renter)
        ->get(route('company.bookings.show', $this->booking))
        ->assertRedirect(route('dashboard'));
});
