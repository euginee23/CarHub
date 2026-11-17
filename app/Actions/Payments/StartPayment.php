<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class StartPayment
{
    public function __construct(
        protected PaymentGateway $gateway,
        protected ValidatePayment $validatePayment,
        protected AbandonPayment $abandonPayment,
    ) {}

    /**
     * Open a hosted checkout for the booking total. The renter picks how to pay on
     * the gateway's page. Any earlier unfinished attempt is checked first, in case
     * it was paid after all, and otherwise closed so only one checkout is live.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking): Payment
    {
        $this->ensureAwaitingPayment($booking);

        $this->settleEarlierAttempts($booking);

        $this->ensureAwaitingPayment($booking->refresh());

        $payment = new Payment([
            'amount' => $booking->totalInCentavos(),
            'currency' => 'PHP',
            'provider' => $this->gateway->name(),
        ]);
        $payment->booking()->associate($booking)->save();

        try {
            $session = $this->gateway->createCheckout($payment);
        } catch (Throwable $exception) {
            Log::error('Could not open a payment checkout.', ['payment' => $payment->reference, 'exception' => $exception->getMessage()]);

            $payment->forceFill(['status' => PaymentStatus::Failed, 'failure_reason' => __('The payment provider could not be reached.')])->save();

            throw ValidationException::withMessages(['payment' => __('We could not reach the payment provider. Please try again in a moment.')]);
        }

        $payment->forceFill([
            'provider_checkout_id' => $session->id,
            'provider_payment_intent_id' => $session->paymentIntentId,
            'checkout_url' => $session->url,
        ])->save();

        return $payment;
    }

    /**
     * Refuse to open a checkout for a booking that is not waiting on payment.
     *
     * @throws ValidationException
     */
    protected function ensureAwaitingPayment(Booking $booking): void
    {
        if ($booking->status === BookingStatus::Confirmed) {
            throw ValidationException::withMessages(['payment' => __('This booking is already paid and confirmed.')]);
        }

        if ($booking->status !== BookingStatus::AwaitingPayment) {
            throw ValidationException::withMessages(['payment' => __('This booking is not waiting for payment.')]);
        }

        if ($booking->payment_due_at?->isPast()) {
            throw ValidationException::withMessages(['payment' => __('The payment window for this booking has closed.')]);
        }
    }

    /**
     * Check unfinished attempts with the gateway, then close whatever is still open.
     */
    protected function settleEarlierAttempts(Booking $booking): void
    {
        foreach ($booking->payments()->where('status', PaymentStatus::Pending)->get() as $earlier) {
            try {
                $earlier = $this->validatePayment->handle($earlier);
            } catch (Throwable $exception) {
                Log::warning('Could not re-check an earlier payment attempt.', ['payment' => $earlier->reference, 'exception' => $exception->getMessage()]);
            }

            $this->abandonPayment->handle($earlier);
        }
    }
}
