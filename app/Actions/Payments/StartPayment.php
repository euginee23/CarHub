<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class StartPayment
{
    public function __construct(protected PaymentGateway $gateway) {}

    /**
     * Open a hosted checkout for the booking total with the chosen method. Any
     * earlier unfinished attempt is abandoned so only one checkout is live.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, PaymentMethod $method): Payment
    {
        if ($booking->status !== BookingStatus::AwaitingPayment) {
            throw ValidationException::withMessages(['payment' => __('This booking is not waiting for payment.')]);
        }

        if ($booking->payment_due_at?->isPast()) {
            throw ValidationException::withMessages(['payment' => __('The payment window for this booking has closed.')]);
        }

        $booking->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Expired]);

        $payment = new Payment([
            'method' => $method,
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
}
