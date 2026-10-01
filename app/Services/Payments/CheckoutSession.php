<?php

namespace App\Services\Payments;

/**
 * A hosted checkout opened with the payment gateway.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
        public ?string $paymentIntentId = null,
    ) {}
}
