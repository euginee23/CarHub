<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Services\Payments\SimulatedGateway;
use RuntimeException;

class ResolvePaymentGateway
{
    public function __construct(protected PaymentGateway $configured) {}

    /**
     * The gateway a payment was made through. Usually the configured one, but a
     * payment opened before the driver was switched must still be checked with
     * the gateway that actually took it.
     */
    public function for(Payment $payment): PaymentGateway
    {
        if ($payment->provider === $this->configured->name()) {
            return $this->configured;
        }

        if ($payment->provider === 'simulated' && ! app()->isProduction()) {
            return new SimulatedGateway;
        }

        throw new RuntimeException("Payment [{$payment->reference}] was made through [{$payment->provider}], which is not configured.");
    }
}
