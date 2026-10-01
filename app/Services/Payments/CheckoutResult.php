<?php

namespace App\Services\Payments;

/**
 * What the gateway reports about a checkout when asked directly.
 */
final readonly class CheckoutResult
{
    /**
     * `failed` means the checkout is over and can never be paid. Only report it when
     * that is certain: a renter can retry a declined card inside a still-open
     * PayMongo checkout, so a declined attempt there is not a failed checkout.
     *
     * @param  array<string, mixed>  $raw  The gateway's response, kept for the audit trail.
     */
    public function __construct(
        public bool $paid,
        public ?string $paymentId,
        public ?int $amount,
        public ?string $currency,
        public ?string $referenceNumber,
        public array $raw = [],
        public bool $failed = false,
    ) {}
}
