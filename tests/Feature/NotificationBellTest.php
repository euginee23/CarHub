<?php

use App\Enums\BookingStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Booking;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Notifications\BookingRequested;
use App\Notifications\BookingStatusUpdated;
use App\Notifications\IdentityDocumentReviewed;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00'));
});

test('a new request asks the rental company to answer, and stops asking once answered', function () {
    $booking = Booking::factory()->create(['pickup_at' => now()->addDays(3), 'return_at' => now()->addDays(5)]);
    $booking->company->notify(new BookingRequested($booking));

    $bell = Livewire::actingAs($booking->company)
        ->test('notifications.bell')
        ->assertSee('New booking request')
        ->assertSee('Review and answer')
        ->assertSee('Needs your answer');

    $bell->call('open', $booking->company->notifications()->sole()->id)
        ->assertRedirect(route('company.bookings.show', $booking));

    $booking->forceFill(['status' => BookingStatus::Approved, 'approved_at' => now()])->save();

    Livewire::actingAs($booking->company)
        ->test('notifications.bell')
        ->assertDontSee('Needs your answer')
        ->assertSee('See booking')
        ->assertSee(BookingStatus::Approved->label());
});

test('an approval takes the renter straight to checkout while there is something to finish', function () {
    $booking = Booking::factory()->approved()->create();
    $booking->renter->notify(new BookingStatusUpdated($booking, BookingStatus::Approved));
    $notification = $booking->renter->notifications()->sole();

    Livewire::actingAs($booking->renter)
        ->test('notifications.bell')
        ->assertSee('Continue to checkout')
        ->assertSee('Action needed')
        ->call('open', $notification->id)
        ->assertRedirect(route('trips.checkout', $booking));

    expect($notification->fresh()->read_at)->not->toBeNull();

    $booking->forceFill(['status' => BookingStatus::Confirmed])->save();

    Livewire::actingAs($booking->renter)
        ->test('notifications.bell')
        ->assertSee('See your trip')
        ->call('open', $notification->id)
        ->assertRedirect(route('trips.show', $booking));
});

test('a finished trip asks the renter for a rating', function () {
    $booking = Booking::factory()->status(BookingStatus::Completed)->create();
    $booking->renter->notify(new BookingStatusUpdated($booking, BookingStatus::Completed));

    Livewire::actingAs($booking->renter)
        ->test('notifications.bell')
        ->assertSee('Leave a rating');
});

test('once both IDs are approved the renter is sent to find a car', function () {
    $renter = User::factory()->withVerifiedIdentity()->create();
    $renter->notify(new IdentityDocumentReviewed($renter->identityDocuments()->first()));

    Livewire::actingAs($renter)
        ->test('notifications.bell')
        ->assertSee('Find a car to rent')
        ->call('open', $renter->notifications()->sole()->id)
        ->assertRedirect(route('vehicles.index'));
});

test('a rejected ID asks for a replacement', function () {
    $renter = User::factory()->create();
    $document = VerificationDocument::factory()->create([
        'documentable_type' => $renter->getMorphClass(),
        'documentable_id' => $renter->id,
        'user_id' => $renter->id,
        'type' => DocumentType::Passport,
        'status' => DocumentStatus::Rejected,
        'rejection_reason' => 'Blurry photo',
    ]);
    $renter->notify(new IdentityDocumentReviewed($document));

    Livewire::actingAs($renter)
        ->test('notifications.bell')
        ->assertSee('Upload a new ID')
        ->call('open', $renter->notifications()->sole()->id)
        ->assertRedirect(route('identity.edit'));
});

test('an empty bell says the user is all caught up', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('notifications.bell')
        ->assertSee('You are all caught up.');
});
