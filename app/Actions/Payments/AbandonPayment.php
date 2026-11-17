<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Throwable;

class AbandonPayment
{
    public function __construct(protected ResolvePaymentGateway $gateways) {}

    /**
     * Give up on an unfinished payment: close its hosted checkout at the gateway so
     * the renter cannot pay into it any more, then mark it expired. If money still
     * slips through, ValidatePayment re-checks expired payments and catches it.
     */
    public function handle(Payment $payment): Payment
    {
        if (! $payment->isPending()) {
            return $payment;
        }

        try {
            $this->gateways->for($payment)->expireCheckout($payment);
        } catch (Throwable $exception) {
            Log::warning('Could not close a checkout at the payment gateway.', ['payment' => $payment->reference, 'exception' => $exception->getMessage()]);
        }

        Payment::whereKey($payment->getKey())->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Expired]);

        return $payment->refresh();
    }
}
