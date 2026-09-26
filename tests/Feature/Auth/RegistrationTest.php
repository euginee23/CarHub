<?php

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
});
