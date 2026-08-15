<?php

test('every public page declares a responsive viewport', function (string $routeName) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('name="viewport" content="width=device-width, initial-scale=1.0"', escape: false);
})->with(['home', 'vehicles.index', 'how-it-works', 'about', 'contact', 'faq', 'terms']);

test('the auth and error shells declare a responsive viewport too', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="viewport" content="width=device-width, initial-scale=1.0"', escape: false);

    $this->view('errors.404')
        ->assertSee('name="viewport" content="width=device-width, initial-scale=1.0"', escape: false);
});

test('the vehicle page pins a booking bar for phones', function () {
    $vehicle = collect(config('demo.vehicles'))->first();

    $this->get(route('vehicles.show', $vehicle['slug']))
        ->assertOk()
        // Fixed to the viewport foot, hidden once the sticky desktop panel is available.
        ->assertSee('fixed inset-x-0 bottom-0', escape: false)
        ->assertSee('lg:hidden', escape: false)
        // Jumps to the full booking panel further down the page.
        ->assertSee('href="#book"', escape: false)
        ->assertSee('id="book"', escape: false);
});

test('only pages with a mobile action bar raise the floating assistant', function () {
    // The assistant is positioned from --fab-offset, which this class overrides below lg.
    $vehicle = collect(config('demo.vehicles'))->first();

    $this->get(route('vehicles.show', $vehicle['slug']))
        ->assertOk()
        ->assertSee('has-mobile-actionbar', escape: false);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('has-mobile-actionbar', escape: false);
});

test('the browse filters collapse behind a toggle on small screens', function () {
    $this->get(route('vehicles.index'))
        ->assertOk()
        ->assertSee('aria-controls="vehicle-filters"', escape: false)
        ->assertSee('lg:hidden', escape: false);
});

test('the marketing nav ships a mobile menu toggle', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('aria-controls="mobile-nav"', escape: false)
        ->assertSee('Toggle navigation');
});
