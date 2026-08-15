<?php

use App\Models\User;

test('guest auth screens render', function (string $routeName, string $expected) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee($expected);
})->with([
    'login' => ['login', 'Log in to your account'],
    'register' => ['register', 'Create your account'],
    'forgot password' => ['password.request', 'Forgot your password?'],
]);

test('the reset password screen renders for a token', function () {
    $this->get(route('password.reset', 'a-reset-token'))
        ->assertOk()
        ->assertSee('Reset your password');
});

test('authenticated auth screens render', function (string $routeName, string $expected) {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route($routeName))
        ->assertOk()
        ->assertSee($expected);
})->with([
    'verify email' => ['verification.notice', 'Verify your email address'],
    'confirm password' => ['password.confirm', 'Confirm your password'],
]);

test('auth screens render light, never dark', function () {
    // The auth shell must not carry `dark` on <html> and must not emit @fluxAppearance,
    // which would restore a persisted dark preference from the authenticated app.
    foreach (['login', 'register', 'password.request'] as $routeName) {
        $this->get(route($routeName))
            ->assertOk()
            ->assertDontSee('<html lang="en" class="dark"', escape: false)
            ->assertDontSee('fluxAppearance', escape: false);
    }
});

test('the login and register screens show the brand panel', function () {
    foreach (['login', 'register'] as $routeName) {
        $this->get(route($routeName))
            ->assertOk()
            ->assertSee('Every renter verified with two government-issued IDs')
            ->assertSee('Trips GPS-tracked from pickup to return');
    }
});

test('auth screens are excluded from search indexing', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="robots" content="noindex"', escape: false);
});

test('the login form keeps the field names and action Fortify expects', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('action="'.route('login.store').'"', escape: false)
        ->assertSee('name="email"', escape: false)
        ->assertSee('name="password"', escape: false)
        ->assertSee('name="remember"', escape: false);
});

test('the register form keeps the field names and action Fortify expects', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('action="'.route('register.store').'"', escape: false)
        ->assertSee('name="name"', escape: false)
        ->assertSee('name="email"', escape: false)
        ->assertSee('name="password"', escape: false)
        ->assertSee('name="password_confirmation"', escape: false);
});
