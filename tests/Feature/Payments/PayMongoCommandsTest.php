<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.paymongo.secret_key' => 'sk_test_123',
        'services.paymongo.webhook_secret' => null,
        'services.paymongo.methods' => ['qrph', 'gcash'],
    ]);
    Http::preventStrayRequests();
});

test('paymongo:check opens a throwaway checkout for the enabled methods', function () {
    Http::fake(['api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => [
        'id' => 'cs_test_probe',
        'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_test_probe'],
    ]])]);

    $this->artisan('paymongo:check')
        ->expectsOutputToContain('qrph, gcash')
        ->expectsOutputToContain('https://checkout.paymongo.com/cs_test_probe')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['data']['attributes']['payment_method_types'] === ['qrph', 'gcash']
        && $request['data']['attributes']['line_items'][0]['amount'] === 10000
        && ! isset($request['data']['attributes']['reference_number']));
});

test('paymongo:check reports what PayMongo refused', function () {
    Http::fake(['api.paymongo.com/v1/checkout_sessions' => Http::response(['errors' => [['detail' => 'payment_method_types contains an unavailable method.']]], 400)]);

    $this->artisan('paymongo:check', ['--methods' => 'grab_pay'])
        ->expectsOutputToContain('payment_method_types contains an unavailable method.')
        ->assertFailed();
});

test('paymongo:check needs a secret key', function () {
    config(['services.paymongo.secret_key' => null]);

    $this->artisan('paymongo:check')->expectsOutputToContain('PAYMONGO_SECRET_KEY is not set.')->assertFailed();
});

test('paymongo:webhook --create registers the endpoint and prints the signing secret', function () {
    Http::fake(['api.paymongo.com/v1/webhooks' => Http::response(['data' => [
        'id' => 'hook_test_1',
        'attributes' => ['secret_key' => 'whsk_test_new', 'url' => 'https://carhub.test/webhooks/paymongo'],
    ]])]);

    $this->artisan('paymongo:webhook', ['--create' => true, '--url' => 'https://carhub.test/webhooks/paymongo'])
        ->expectsOutputToContain('PAYMONGO_WEBHOOK_SECRET=whsk_test_new')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['data']['attributes']['url'] === 'https://carhub.test/webhooks/paymongo'
        && $request['data']['attributes']['events'] === ['checkout_session.payment.paid', 'payment.paid']);
});

test('paymongo:webhook refuses a non-HTTPS endpoint', function () {
    $this->artisan('paymongo:webhook', ['--create' => true, '--url' => 'http://localhost:8000/webhooks/paymongo'])
        ->expectsOutputToContain('HTTPS')
        ->assertFailed();
});

test('paymongo:webhook lists the registered webhooks', function () {
    Http::fake(['api.paymongo.com/v1/webhooks' => Http::response(['data' => [[
        'id' => 'hook_test_1',
        'attributes' => ['url' => 'https://carhub.test/webhooks/paymongo', 'status' => 'enabled', 'events' => ['checkout_session.payment.paid']],
    ]]])]);

    $this->artisan('paymongo:webhook')
        ->expectsOutputToContain('hook_test_1')
        ->assertSuccessful();
});
