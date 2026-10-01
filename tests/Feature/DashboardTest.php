<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\OwnerApplication;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerificationDocument;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('the dashboard sends each user to the overview for their role', function (string $state, string $expectedRoute) {
    $user = $state === 'renter' ? User::factory()->create() : User::factory()->{$state}()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($expectedRoute));
})->with([
    'renter' => ['renter', 'renter.dashboard'],
    'verified owner' => ['verifiedOwner', 'owner.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
]);

test('the email verification flag survives the redirect', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', ['verified' => 1]))
        ->assertRedirect(route('renter.dashboard', ['verified' => 1]));
});

test('the renter overview shows trips, next steps, and identity status', function () {
    $renter = User::factory()->create(['name' => 'Ana Reyes']);
    $booking = Booking::factory()->approved()->for($renter, 'renter')->create();

    $this->actingAs($renter)
        ->get(route('renter.dashboard'))
        ->assertOk()
        ->assertSee('Ana')
        ->assertSee('Finish checkout for your '.$booking->vehicle->name)
        ->assertSee('Verify your identity')
        ->assertSee($booking->vehicle->name)
        ->assertDontSee('Become an owner');
});

test('the renter overview totals only confirmed spending', function () {
    $renter = User::factory()->withVerifiedIdentity()->create();
    Booking::factory()->confirmed()->for($renter, 'renter')->create(['total' => 5000]);
    Booking::factory()->status(BookingStatus::Completed)->for($renter, 'renter')->create(['total' => 3000]);
    Booking::factory()->for($renter, 'renter')->create(['total' => 9999]);

    $this->actingAs($renter)
        ->get(route('renter.dashboard'))
        ->assertOk()
        ->assertSee('₱8,000')
        ->assertDontSee('Verify your identity');
});

test('the owner overview shows requests, schedule, fleet, and earnings', function () {
    $this->travelTo(now()->startOfMonth()->addDays(2));

    $owner = User::factory()->verifiedOwner()->create();
    $vehicle = Vehicle::factory()->for($owner, 'owner')->create(['brand' => 'Toyota', 'model' => 'Vios']);
    $request = Booking::factory()->forVehicle($vehicle)->create();
    Booking::factory()->forVehicle($vehicle)->confirmed()->create(['subtotal' => 4000, 'pickup_at' => now()->addDay(), 'return_at' => now()->addDays(2)]);

    $this->actingAs($owner)
        ->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee('Requests to answer')
        ->assertSee($request->renter->name)
        ->assertSee('Toyota Vios')
        ->assertSee('₱4,000');
});

test('only verified owners can open the owner overview', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('owner.dashboard'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('owner.dashboard'))
        ->assertRedirect(route('dashboard'));
});

test('owners who are not verified yet land on their verification', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('owner.apply'));
});

test('the admin overview shows platform numbers and review queues', function () {
    $admin = User::factory()->admin()->create();
    OwnerApplication::factory()->count(2)->create();
    VerificationDocument::factory()->create([
        'documentable_type' => $admin->getMorphClass(),
        'documentable_id' => $admin->id,
        'user_id' => $admin->id,
    ]);
    $booking = Booking::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Owner applications to review')
        ->assertSee('Renter IDs to review')
        ->assertSee($booking->reference)
        ->assertSee('Bookings by status');
});

test('only administrators can open the admin overview', function () {
    $this->actingAs(User::factory()->verifiedOwner()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('signed-in pages use the same shell as the public site', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('renter.dashboard'))
        ->assertOk()
        ->assertDontSee('<html lang="en" class="dark"', escape: false)
        ->assertDontSee('fluxAppearance', escape: false)
        ->assertDontSee('data-flux-sidebar', escape: false)
        ->assertSee('How it works')
        ->assertSee('account-menu-button', escape: false)
        ->assertSee('<meta name="robots" content="noindex" />', escape: false);
});

test('renters and owners each get only their own area', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('renter.dashboard'))
        ->assertSee('Renting')
        ->assertSee('My trips')
        ->assertDontSee('Hosting')
        ->assertDontSee('Booking requests')
        ->assertDontSee('Administration');

    $this->actingAs(User::factory()->verifiedOwner()->create())
        ->get(route('owner.dashboard'))
        ->assertSee('Hosting')
        ->assertSee('Booking requests')
        ->assertDontSee('My trips')
        ->assertDontSee('ID verification')
        ->assertDontSee('Administration');
});

test('administrators only get the administration and account areas', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Administration')
        ->assertSee('Owner applications')
        ->assertSee('ID reviews')
        ->assertDontSee('Renting')
        ->assertDontSee('My trips')
        ->assertDontSee('ID verification')
        ->assertDontSee('Become an owner')
        ->assertDontSee('Booking requests');
});

test('administrators are sent back to their own dashboard from renting and hosting pages', function (string $route) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route($route))
        ->assertRedirect(route('dashboard'));
})->with(['renter.dashboard', 'trips.index', 'identity.edit', 'owner.apply', 'owner.vehicles.index']);

test('each kind of account is kept out of the others\' areas', function (string $state, string $route) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route($route))
        ->assertRedirect(route('dashboard'));
})->with([
    'owner on renting' => ['verifiedOwner', 'trips.index'],
    'owner on identity' => ['verifiedOwner', 'identity.edit'],
    'renter on hosting' => ['renter', 'owner.apply'],
]);

test('each page shows the tabs for its area', function (string $state, string $route, string $tab) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route($route))
        ->assertOk()
        ->assertSeeInOrder(['aria-current="page"', $tab], escape: false);
})->with([
    'renting' => ['renter', 'trips.index', 'My trips'],
    'hosting' => ['verifiedOwner', 'owner.vehicles.index', 'My vehicles'],
    'admin' => ['admin', 'admin.id-reviews', 'ID reviews'],
    'account' => ['admin', 'profile.edit', 'Profile'],
]);

test('from public pages an administrator can always get back to administration', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('vehicles.index'))
        ->assertOk()
        ->assertSee('href="'.route('admin.dashboard').'"', escape: false)
        ->assertSee('Administration')
        ->assertDontSee('Renting');
});

test('from public pages a member can always get back to their areas', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('vehicles.index'))
        ->assertOk()
        ->assertSee('href="'.route('renter.dashboard').'"', escape: false)
        ->assertSee('Renting')
        ->assertDontSee('Hosting')
        ->assertDontSee('Administration');

    $this->actingAs(User::factory()->verifiedOwner()->create())
        ->get(route('home'))
        ->assertSee('href="'.route('owner.dashboard').'"', escape: false)
        ->assertSee('Hosting')
        ->assertDontSee('href="'.route('renter.dashboard').'"', escape: false);
});

test('the header highlights the area the current page belongs to', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.id-reviews'))
        ->assertSeeInOrder(['bg-brand-50 text-brand-700', 'Administration'], escape: false);
});

test('guests still get the marketing links', function () {
    $this->get(route('home'))
        ->assertSee('How it works')
        ->assertSee('Get started')
        ->assertDontSee('account-menu-button', escape: false);
});
