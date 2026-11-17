<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Services\Payments\CheckoutResult;
use App\Services\Payments\CheckoutSession;

interface PaymentGateway
{
    /**
     * The name stored on payments made through this gateway.
     */
    public function name(): string;

    /**
     * Open a hosted checkout for the payment and return where to send the renter.
     */
    public function createCheckout(Payment $payment): CheckoutSession;

    /**
     * Ask the gateway what actually happened to a checkout. This is the source
     * of truth for validation — webhook bodies and redirects are never trusted.
     */
    public function retrieveCheckout(Payment $payment): CheckoutResult;

    /**
     * Close a hosted checkout CarHub has given up on, so the renter can no longer
     * pay into it. Best effort: it must not throw when the gateway refuses.
     */
    public function expireCheckout(Payment $payment): void;
}
