<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\ValidatePayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives PayMongo webhook events. Configure the webhook in the PayMongo
 * dashboard to POST to /webhooks/paymongo for `checkout_session.payment.paid`
 * and `payment.paid`, and put its secret in PAYMONGO_WEBHOOK_SECRET.
 *
 * The event body is only used to find the payment; the outcome is always
 * re-fetched from the PayMongo API by ValidatePayment.
 */
class PayMongoWebhookController extends Controller
{
    /**
     * How old a signed event may be before it is treated as a replay, in seconds.
     */
    public const int TOLERANCE_SECONDS = 300;

    /**
     * Handle an incoming webhook event.
     */
    public function __invoke(Request $request, ValidatePayment $validatePayment): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('Rejected a PayMongo webhook with an invalid signature.');

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $type = $request->input('data.attributes.type');
        $resource = (array) $request->input('data.attributes.data', []);

        $payment = match ($type) {
            'checkout_session.payment.paid' => Payment::firstWhere('provider_checkout_id', $resource['id'] ?? null),
            'payment.paid' => filled($intent = data_get($resource, 'attributes.payment_intent_id'))
                ? Payment::firstWhere('provider_payment_intent_id', $intent)
                : null,
            default => null,
        };

        // Unknown events and payments that are not ours are acknowledged so PayMongo stops retrying.
        if ($payment === null) {
            return response()->json(['message' => 'Ignored.']);
        }

        $validatePayment->handle($payment);

        return response()->json(['message' => 'Processed.']);
    }

    /**
     * Verify the Paymongo-Signature header: "t=<timestamp>,te=<test sig>,li=<live sig>",
     * where each signature is HMAC-SHA256 of "<timestamp>.<raw body>" with the webhook secret.
     */
    protected function hasValidSignature(Request $request): bool
    {
        $secret = config('services.paymongo.webhook_secret');

        if (blank($secret)) {
            return false;
        }

        $parts = collect(explode(',', (string) $request->header('Paymongo-Signature')))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

                return [$key => $value];
            });

        $timestamp = (int) $parts->get('t');
        $signature = (string) $parts->get(config('services.paymongo.live') ? 'li' : 'te');

        if ($timestamp === 0 || $signature === '' || abs(now()->getTimestamp() - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
