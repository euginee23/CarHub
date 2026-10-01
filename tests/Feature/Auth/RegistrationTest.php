<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'phone' => '09171234567',
        'account_type' => 'renter',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('registration stores the mobile number', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'phone' => '+639171234567',
        'account_type' => 'renter',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'test@example.com')->phone)->toBe('+639171234567');
});

test('registration requires a valid philippine mobile number', function (?string $phone) {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'phone' => $phone,
        'account_type' => 'renter',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('phone');

    $this->assertGuest();
})->with([
    'missing' => null,
    'landline' => '0322345678',
    'letters' => '09abc123456',
]);

test('users registering to list a vehicle are sent to the owner application', function () {
    $this->post(route('register.store'), [
        'name' => 'Jane Owner',
        'email' => 'owner@example.com',
        'phone' => '09171234567',
        'account_type' => 'owner',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('owner.apply'));

    $this->assertAuthenticated();

    expect(User::firstWhere('email', 'owner@example.com')->role)->toBe(UserRole::Owner);
});

test('renters are registered as renter accounts', function () {
    $this->post(route('register.store'), [
        'name' => 'Ana Renter',
        'email' => 'renter@example.com',
        'phone' => '09171234567',
        'account_type' => 'renter',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'renter@example.com')->role)->toBe(UserRole::Renter);
});

test('an account type must be chosen and cannot be admin', function (?string $accountType) {
    $this->post(route('register.store'), [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'phone' => '09171234567',
        'account_type' => $accountType,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('account_type');

    $this->assertGuest();
})->with(['missing' => null, 'admin' => 'admin']);

test('the list your vehicle links open owner sign-up', function () {
    $this->get(route('home'))->assertSee(route('register', ['as' => 'owner']), escape: false);

    $this->get(route('register', ['as' => 'owner']))
        ->assertOk()
        ->assertSee('Create your owner account')
        ->assertSee('value="owner"'."\n".'                                checked', escape: false);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your account')
        ->assertDontSee('Create your owner account');
});
