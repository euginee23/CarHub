<?php

test('every marketing page is reachable as a guest', function (string $routeName, string $expected) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee($expected, escape: false);
})->with([
    'home' => ['home', 'Rent the right car,'],
    'browse' => ['vehicles.index', 'Browse vehicles'],
    'how it works' => ['how-it-works', 'accountable process'],
    'about' => ['about', 'About CarHub'],
    'contact' => ['contact', 'Send us a message'],
    'faq' => ['faq', 'Frequently asked questions'],
    'terms' => ['terms', 'Terms &amp; conditions'],
]);

test('marketing pages render in light mode', function () {
    // The authenticated app layout hardcodes class="dark"; the marketing shell must not.
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('<html lang="en" class="dark"', escape: false);
});

test('no public page advertises AI capabilities', function (string $routeName) {
    // The recommendation engine, demand forecasting and chatbot are not implemented;
    // the marketing site must not claim them.
    $response = $this->get(route($routeName))->assertOk();

    foreach (['AI-powered', 'AI-suggested', 'Where the AI helps', 'The intelligence layer', 'LSTM'] as $claim) {
        $response->assertDontSee($claim);
    }
})->with(['home', 'vehicles.index', 'how-it-works', 'about', 'contact', 'faq', 'terms']);

test('the home page shows featured vehicles linking to their detail page', function () {
    $featured = collect(config('demo.vehicles'))->firstWhere('featured', true);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($featured['name'])
        ->assertSee(route('vehicles.show', $featured['slug']));
});

test('a vehicle detail page shows the listing, its owner, and the ID requirement', function () {
    $vehicle = collect(config('demo.vehicles'))->first();

    $this->get(route('vehicles.show', $vehicle['slug']))
        ->assertOk()
        ->assertSee($vehicle['name'])
        ->assertSee($vehicle['owner']['name'])
        ->assertSee(number_format($vehicle['price_per_day']))
        ->assertSee('Two valid government-issued IDs');
});

test('an unknown vehicle slug returns a 404', function () {
    $this->get(route('vehicles.show', 'not-a-real-vehicle'))->assertNotFound();
});

test('the terms page covers the panel-required policies', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('two valid government-issued identification documents')
        ->assertSee('The rental contract', escape: false)
        ->assertSee('GPS tracking and privacy', escape: false);
});
