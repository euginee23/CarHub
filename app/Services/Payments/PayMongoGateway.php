<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * PayMongo hosted checkout (https://docs.paymongo.com). Amounts are in centavos;
 * requests authenticate with the secret key as the Basic auth username. The
 * renter chooses how to pay on PayMongo's page, from the methods in PAYMONGO_METHODS.
 */
class PayMongoGateway implements PaymentGateway
{
    /**
     * The webhook events CarHub listens for. Both only trigger a re-check.
     *
     * @var array<int, string>
     */
    public const array WEBHOOK_EVENTS = ['checkout_session.payment.paid', 'payment.paid'];

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
        $booking = $payment->booking->loadMissing(['vehicle', 'renter']);

        return $this->openCheckout([
            'billing' => array_filter([
                'name' => $booking->renter->name,
                'email' => $booking->renter->email,
                'phone' => $booking->renter->phone,
            ], filled(...)),
            'line_items' => [[
                'name' => __(':vehicle rental (:days days)', ['vehicle' => $booking->vehicle->name, 'days' => $booking->days]),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'quantity' => 1,
            ]],
            'payment_method_types' => $this->methodTypes(),
            'reference_number' => $booking->reference,
            'description' => __('CarHub booking :reference', ['reference' => $booking->reference]),
            'success_url' => route('payments.return', $payment),
            'cancel_url' => route('payments.cancel', $payment),
            'send_email_receipt' => true,
            'show_description' => true,
            'show_line_items' => true,
            'metadata' => [
                'payment_reference' => $payment->reference,
                'booking_reference' => $booking->reference,
            ],
        ]);
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
            method: $paid === null ? null : PaymentMethod::fromPayMongo(
                data_get($paid, 'attributes.source.type') ?? data_get($session, 'attributes.payment_method_used'),
            ),
        );
    }

    /**
     * Expire the checkout session so it can no longer be paid. PayMongo refuses
     * sessions that are already paid or expired; those are only logged.
     */
    public function expireCheckout(Payment $payment): void
    {
        if (blank($payment->provider_checkout_id)) {
            return;
        }

        try {
            $this->client()->post('checkout_sessions/'.$payment->provider_checkout_id.'/expire')->throw();
        } catch (Throwable $exception) {
            Log::warning('Could not expire a PayMongo checkout session.', ['payment' => $payment->reference, 'exception' => $exception->getMessage()]);
        }
    }

    /**
     * Open a checkout session that is not tied to a booking, to see which
     * methods the account really offers (`php artisan paymongo:check`).
     *
     * @param  array<int, string>  $methodTypes  PayMongo `payment_method_types`.
     */
    public function openProbeCheckout(array $methodTypes, int $amount): CheckoutSession
    {
        return $this->openCheckout([
            'line_items' => [[
                'name' => 'Payment method check — not a booking',
                'amount' => $amount,
                'currency' => 'PHP',
                'quantity' => 1,
            ]],
            'payment_method_types' => array_values($methodTypes),
            'description' => 'CarHub payment method check — not a booking',
            'success_url' => url('/'),
            'cancel_url' => url('/'),
        ]);
    }

    /**
     * The webhooks registered on the PayMongo account.
     *
     * @return array<int, array<string, mixed>>
     */
    public function webhooks(): array
    {
        return (array) $this->client()->get('webhooks')->throw()->json('data', []);
    }

    /**
     * Register a webhook for the events CarHub listens for. The response holds the
     * signing secret, which PayMongo only shows this once.
     *
     * @return array<string, mixed>
     */
    public function createWebhook(string $url): array
    {
        return (array) $this->client()->post('webhooks', [
            'data' => ['attributes' => ['url' => $url, 'events' => self::WEBHOOK_EVENTS]],
        ])->throw()->json('data');
    }

    /**
     * Whether the secret key is a sandbox or a live one.
     */
    public function keyMode(): string
    {
        return match (true) {
            str_starts_with((string) $this->secretKey, 'sk_test_') => 'test',
            str_starts_with((string) $this->secretKey, 'sk_live_') => 'live',
            default => 'unknown',
        };
    }

    /**
     * The PayMongo `payment_method_types` to offer on the checkout page.
     *
     * @return array<int, string>
     */
    protected function methodTypes(): array
    {
        $types = array_map(fn (PaymentMethod $method): string => $method->payMongoType(), PaymentMethod::enabled());

        if ($types === []) {
            throw new RuntimeException('No payment methods are enabled: set PAYMONGO_METHODS, e.g. "qrph".');
        }

        return $types;
    }

    /**
     * Create a checkout session from its attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function openCheckout(array $attributes): CheckoutSession
    {
        $response = $this->client()->post('checkout_sessions', [
            'data' => ['attributes' => array_filter($attributes, fn (mixed $value): bool => $value !== [] && $value !== null)],
        ])->throw()->json('data');

        return new CheckoutSession(
            id: $response['id'],
            url: $response['attributes']['checkout_url'],
            paymentIntentId: data_get($response, 'attributes.payment_intent.id'),
        );
    }

    /**
     * An authenticated client for the PayMongo API. Dropped connections are
     * retried; error responses are not.
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
            ->timeout(15)
            ->retry(2, 250, fn (Throwable $exception): bool => $exception instanceof ConnectionException, throw: false);
    }
}
