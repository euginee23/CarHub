<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PayMongo hosted checkout (https://docs.paymongo.com). Amounts are in centavos;
 * requests authenticate with the secret key as the Basic auth username.
 */
class PayMongoGateway implements PaymentGateway
{
    public function __construct(
        protected ?string $secretKey,
        protected string $baseUrl,
    ) {}

    /**
     * The name stored on payments made through this gateway.
     */
    public function name(): string
    {
        return 'paymongo';
    }

    /**
     * Open a PayMongo checkout session for the payment.
     */
    public function createCheckout(Payment $payment): CheckoutSession
    {
        $booking = $payment->booking->loadMissing('vehicle');

        $response = $this->client()->post('checkout_sessions', [
            'data' => [
                'attributes' => [
                    'line_items' => [[
                        'name' => __(':vehicle rental (:days days)', ['vehicle' => $booking->vehicle->name, 'days' => $booking->days]),
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => [$payment->method->payMongoType()],
                    'reference_number' => $booking->reference,
                    'description' => __('CarHub booking :reference', ['reference' => $booking->reference]),
                    'success_url' => route('payments.return', $payment),
                    'cancel_url' => route('trips.checkout', $booking),
                    'send_email_receipt' => true,
                    'show_description' => true,
                    'show_line_items' => true,
                    'metadata' => [
                        'payment_reference' => $payment->reference,
                        'booking_reference' => $booking->reference,
                    ],
                ],
            ],
        ])->throw()->json('data');

        return new CheckoutSession(
            id: $response['id'],
            url: $response['attributes']['checkout_url'],
            paymentIntentId: data_get($response, 'attributes.payment_intent.id'),
        );
    }

    /**
     * Fetch the checkout session and report whether it was paid.
     */
    public function retrieveCheckout(Payment $payment): CheckoutResult
    {
        $session = $this->client()->get('checkout_sessions/'.$payment->provider_checkout_id)->throw()->json('data');

        $payments = data_get($session, 'attributes.payments');

        $paid = Arr::first(
            is_array($payments) ? $payments : [],
            fn (mixed $candidate) => is_array($candidate) && data_get($candidate, 'attributes.status') === 'paid',
        );

        return new CheckoutResult(
            paid: $paid !== null,
            paymentId: $paid['id'] ?? null,
            amount: data_get($paid, 'attributes.amount'),
            currency: data_get($paid, 'attributes.currency'),
            referenceNumber: data_get($session, 'attributes.reference_number'),
            raw: $session,
        );
    }

    /**
     * An authenticated client for the PayMongo API.
     */
    protected function client(): PendingRequest
    {
        if (blank($this->secretKey)) {
            throw new RuntimeException('PayMongo is not configured: set PAYMONGO_SECRET_KEY.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
