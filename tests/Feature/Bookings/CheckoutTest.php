<?php

use App\Actions\Bookings\GenerateRentalContract;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\RentalContract;
use App\Models\TermsVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00'));

    $this->terms = TermsVersion::factory()->create(['version' => '2026.1']);
    $this->renter = User::factory()->withVerifiedIdentity()->create(['name' => 'Ana Reyes']);
    $this->booking = Booking::factory()->approved()->for($this->renter, 'renter')->create();
});

test('the renter can open checkout', function () {
    $this->actingAs($this->renter)
        ->get(route('trips.checkout', $this->booking))
        ->assertOk()
        ->assertSee('Rental terms')
        ->assertSee('Version 2026.1');
});

test('nobody else can open the renter\'s checkout', function () {
    $this->actingAs($this->booking->owner)->get(route('trips.checkout', $this->booking))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('trips.checkout', $this->booking))->assertForbidden();
});

test('checkout sends the renter back to the trip once it is over', function () {
    $this->booking->forceFill(['status' => BookingStatus::Confirmed])->save();

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->assertRedirect(route('trips.show', $this->booking));
});

test('accepting the terms records the version, time, and IP address', function () {
    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->set('agreeToTerms', true)
        ->call('acceptTerms')
        ->assertHasNoErrors();

    expect($this->booking->fresh())
        ->terms_version_id->toBe($this->terms->id)
        ->terms_accepted_at->not->toBeNull()
        ->terms_accepted_ip->toBe('127.0.0.1');
});

test('the terms must be ticked to be accepted', function () {
    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->call('acceptTerms')
        ->assertHasErrors(['agreeToTerms' => 'accepted']);

    expect($this->booking->fresh()->terms_accepted_at)->toBeNull();
});

test('renters can accept the terms while the owner is still deciding', function () {
    $this->booking->forceFill(['status' => BookingStatus::Requested, 'approved_at' => null])->save();

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->set('agreeToTerms', true)
        ->call('acceptTerms')
        ->assertHasNoErrors()
        ->assertSee('The contract is prepared once the owner approves your request.');

    expect($this->booking->fresh()->contract)->toBeNull();
});

test('the contract is only prepared once terms and IDs are done', function () {
    $unverified = User::factory()->create();
    $booking = Booking::factory()->approved()->withAcceptedTerms()->for($unverified, 'renter')->create();

    Livewire::actingAs($unverified)
        ->test('pages::trips.checkout', ['booking' => $booking])
        ->assertSee('get two IDs approved');

    expect($booking->fresh()->contract)->toBeNull();
});

test('the contract captures the booking as it stood when prepared', function () {
    $this->booking->forceFill(['terms_version_id' => $this->terms->id, 'terms_accepted_at' => now()])->save();

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->assertSee('Vehicle rental contract');

    $contract = $this->booking->fresh()->contract;

    expect($contract->contract_number)->toMatch('/^RC-2026-\d{5}$/')
        ->and($contract->snapshot['renter']['name'])->toBe('Ana Reyes')
        ->and($contract->snapshot['renter']['identity_verified'])->toBeTrue()
        ->and($contract->snapshot['pricing']['total'])->toBe($this->booking->total)
        ->and($contract->snapshot['terms']['version'])->toBe('2026.1')
        ->and($contract->snapshot['vehicle']['name'])->toBe($this->booking->vehicle->name);
});

test('signing the contract moves the booking on to payment', function () {
    $this->booking->forceFill(['terms_version_id' => $this->terms->id, 'terms_accepted_at' => now()])->save();

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->set('signature', 'ana reyes')
        ->set('agreeToContract', true)
        ->call('signContract')
        ->assertHasNoErrors()
        ->assertSee('Pay ₱'.number_format($this->booking->total));

    $booking = $this->booking->fresh();

    expect($booking->status)->toBe(BookingStatus::AwaitingPayment)
        ->and($booking->contract->isSigned())->toBeTrue()
        ->and($booking->contract->renter_signature)->toBe('ana reyes')
        ->and($booking->contract->renter_signed_ip)->toBe('127.0.0.1');
});

test('the signature must match the renter\'s name', function () {
    $this->booking->forceFill(['terms_version_id' => $this->terms->id, 'terms_accepted_at' => now()])->save();

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->set('signature', 'Somebody Else')
        ->set('agreeToContract', true)
        ->call('signContract')
        ->assertHasErrors('signature');

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Approved);
});

test('the contract cannot be signed without two approved IDs', function () {
    $unverified = User::factory()->create(['name' => 'Ben Cruz']);
    $booking = Booking::factory()->approved()->withAcceptedTerms()->for($unverified, 'renter')->create();
    RentalContract::factory()->for($booking)->create();

    Livewire::actingAs($unverified)
        ->test('pages::trips.checkout', ['booking' => $booking])
        ->set('signature', 'Ben Cruz')
        ->set('agreeToContract', true)
        ->call('signContract')
        ->assertHasErrors('signature');

    expect($booking->fresh()->status)->toBe(BookingStatus::Approved);
});

test('a signed contract does not change when the listing does', function () {
    $contract = RentalContract::factory()->for(
        Booking::factory()->approved()->withAcceptedTerms()->for($this->renter, 'renter'),
    )->create();
    $contract->forceFill(['renter_signed_at' => now(), 'renter_signature' => 'Ana Reyes'])->save();

    $contract->booking->vehicle->update(['brand' => 'Renamed']);

    app(GenerateRentalContract::class)->handle($contract->booking->fresh());

    expect($contract->fresh()->snapshot['vehicle']['name'])->not->toContain('Renamed');
});

test('the printable contract is visible to the renter, owner, and admins only', function () {
    $contract = RentalContract::factory()->for($this->booking)->create();

    $this->actingAs($this->renter)->get(route('bookings.contract', $this->booking))->assertOk()->assertSee($contract->contract_number);
    $this->actingAs($this->booking->owner)->get(route('bookings.contract', $this->booking))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('bookings.contract', $this->booking))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('bookings.contract', $this->booking))->assertForbidden();
});

test('there is no contract page before a contract exists', function () {
    $this->actingAs($this->renter)->get(route('bookings.contract', $this->booking))->assertNotFound();
});
