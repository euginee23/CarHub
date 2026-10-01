<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\RentalContract;
use App\Models\TermsVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00'));

    $this->renter = User::factory()->withVerifiedIdentity()->create(['name' => 'Ana Reyes']);
    $this->booking = Booking::factory()
        ->status(BookingStatus::AwaitingPayment)
        ->for($this->renter, 'renter')
        ->between('2026-10-10 09:00', '2026-10-12 09:00')
        ->create(['total' => 4600, 'payment_due_at' => now()->addDay()]);
});

test('signing the contract gives the renter a payment deadline', function () {
    TermsVersion::factory()->create();
    $booking = Booking::factory()->approved()->withAcceptedTerms()->for($this->renter, 'renter')
        ->between('2026-10-01 20:00', '2026-10-03 20:00')->create();
    RentalContract::factory()->for($booking)->create();

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $booking])
        ->set('signature', 'Ana Reyes')
        ->set('agreeToContract', true)
        ->call('signContract');

    // 24 hours from now would pass pickup, so the deadline is pickup itself.
    expect($booking->fresh()->payment_due_at->format('Y-m-d H:i'))->toBe('2026-10-01 20:00');
});

test('with the simulated gateway, paying opens the local test checkout', function () {
    config(['carhub.payments.driver' => 'simulated']);

    $component = Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->set('paymentMethod', 'maya')
        ->call('pay')
        ->assertHasNoErrors();

    $payment = Payment::sole();

    expect($payment)
        ->status->toBe(PaymentStatus::Pending)
        ->method->toBe(PaymentMethod::Maya)
        ->amount->toBe(460000)
        ->provider->toBe('simulated')
        ->and($payment->checkout_url)->toContain('/payments/'.$payment->reference.'/simulated');

    $component->assertRedirect($payment->checkout_url);
});

test('with PayMongo, paying opens a hosted checkout session', function () {
    config(['carhub.payments.driver' => 'paymongo', 'services.paymongo.secret_key' => 'sk_test_123']);

    Http::fake([
        'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => [
            'id' => 'cs_test_abc',
            'attributes' => [
                'checkout_url' => 'https://checkout.paymongo.com/cs_test_abc',
                'payment_intent' => ['id' => 'pi_test_abc'],
            ],
        ]]),
    ]);

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->set('paymentMethod', 'gcash')
        ->call('pay')
        ->assertRedirect('https://checkout.paymongo.com/cs_test_abc');

    expect(Payment::sole())
        ->provider->toBe('paymongo')
        ->provider_checkout_id->toBe('cs_test_abc')
        ->provider_payment_intent_id->toBe('pi_test_abc');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_123:'))
        && $request['data']['attributes']['line_items'][0]['amount'] === 460000
        && $request['data']['attributes']['line_items'][0]['currency'] === 'PHP'
        && $request['data']['attributes']['payment_method_types'] === ['gcash']
        && $request['data']['attributes']['reference_number'] === $this->booking->reference);
});

test('a gateway outage is reported and the attempt marked failed', function () {
    config(['carhub.payments.driver' => 'paymongo', 'services.paymongo.secret_key' => 'sk_test_123']);
    Http::fake(['api.paymongo.com/*' => Http::response(['errors' => [['detail' => 'down']]], 500)]);

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->call('pay')
        ->assertHasErrors('payment');

    expect(Payment::sole()->status)->toBe(PaymentStatus::Failed)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});

test('starting again abandons the earlier unfinished attempt', function () {
    $earlier = Payment::factory()->for($this->booking)->create(['provider' => 'simulated']);

    Livewire::actingAs($this->renter)->test('pages::trips.checkout', ['booking' => $this->booking])->call('pay');

    expect($earlier->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($this->booking->payments()->where('status', PaymentStatus::Pending)->count())->toBe(1);
});

test('payment cannot start once the deadline has passed', function () {
    $this->booking->forceFill(['payment_due_at' => now()->subMinute()])->save();

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->call('pay')
        ->assertHasErrors('payment');

    expect(Payment::count())->toBe(0);
});

test('payment cannot start before the contract is signed', function () {
    $booking = Booking::factory()->approved()->for($this->renter, 'renter')->create();

    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $booking])
        ->call('pay')
        ->assertHasErrors('payment');
});

test('payment methods are limited to the configured list', function () {
    Livewire::actingAs($this->renter)
        ->test('pages::trips.checkout', ['booking' => $this->booking])
        ->set('paymentMethod', 'bitcoin')
        ->call('pay')
        ->assertHasErrors(['paymentMethod' => 'in']);
});
