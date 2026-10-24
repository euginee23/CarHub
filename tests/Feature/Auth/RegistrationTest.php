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

test('new renters can register and are sent to upload their IDs', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'phone' => '09171234567',
        'account_type' => 'renter',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('identity.edit', absolute: false));

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

test('rental companies registering are sent to the company application', function () {
    $this->post(route('register.store'), [
        'name' => 'Jane Nemaria',
        'email' => 'company@example.com',
        'phone' => '09171234567',
        'account_type' => 'company',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('company.apply'));

    $this->assertAuthenticated();

    expect(User::firstWhere('email', 'company@example.com')->role)->toBe(UserRole::Company);
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

test('the list your vehicle links open rental company sign-up', function () {
    $this->get(route('home'))->assertSee(route('register', ['as' => 'company']), escape: false);

    $this->get(route('register', ['as' => 'company']))
        ->assertOk()
        ->assertSee('Create your rental company account')
        ->assertSee('value="company"'."\n".'                                checked', escape: false);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your account')
        ->assertDontSee('Create your rental company account');
});
