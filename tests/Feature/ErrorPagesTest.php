<?php

use Symfony\Component\HttpKernel\Exception\HttpException;

test('an unknown url renders the branded 404 page', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('404')
        ->assertSee('We took a wrong turn')
        ->assertSee('Browse vehicles');
});

test('every error view renders with its code and heading', function (string $code, string $heading) {
    $this->view("errors.{$code}", ['exception' => new HttpException((int) $code)])
        ->assertSee($code)
        ->assertSee($heading)
        ->assertSee('CarHub');
})->with([
    '401' => ['401', 'You need to be signed in'],
    '402' => ['402', 'Payment required'],
    '403' => ['403', 'That page is not yours to open'],
    '404' => ['404', 'We took a wrong turn'],
    '419' => ['419', 'Your session expired'],
    '429' => ['429', 'Too many attempts'],
    '500' => ['500', 'Something went wrong on our end'],
    '503' => ['503', 'Down for maintenance'],
]);

test('error pages are excluded from search indexing', function () {
    $this->view('errors.404')->assertSee('name="robots" content="noindex"', escape: false);
});

test('the 403 page surfaces an authorization message when one is given', function () {
    $this->view('errors.403', ['exception' => new HttpException(403, 'You do not own this listing.')])
        ->assertSee('You do not own this listing.');
});

test('the 403 page falls back to its default copy when the exception carries no message', function () {
    $this->view('errors.403', ['exception' => new HttpException(403)])
        ->assertSee('this page belongs to someone else', escape: false);
});

test('the 500 and 503 pages avoid named routes so a routing fault cannot cascade', function (string $code) {
    // Rendered without route helpers on purpose: these views must survive an
    // application that is broken or only partially booted.
    $source = file_get_contents(resource_path("views/errors/{$code}.blade.php"));
    $markup = preg_replace('/\{\{--.*?--\}\}/s', '', $source);

    expect($markup)->not->toContain('route(');
})->with(['500', '503']);
