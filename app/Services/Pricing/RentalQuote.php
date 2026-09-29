<?php

namespace App\Services\Pricing;

use App\Models\Vehicle;
use Carbon\CarbonInterface;

/**
 * The price of renting a vehicle for a given window: the owner's daily rate for
 * every started 24-hour period, plus the platform service fee.
 */
final readonly class RentalQuote
{
    public function __construct(
        public int $dailyRate,
        public int $days,
        public int $subtotal,
        public int $serviceFee,
        public int $total,
    ) {}

    /**
     * Price the vehicle for the given rental window.
     */
    public static function for(Vehicle $vehicle, CarbonInterface $pickup, CarbonInterface $return): self
    {
        $days = max(1, (int) ceil($pickup->diffInMinutes($return) / (60 * 24)));
        $subtotal = $vehicle->price_per_day * $days;
        $serviceFee = (int) round($subtotal * (float) config('carhub.service_fee_rate'));

        return new self($vehicle->price_per_day, $days, $subtotal, $serviceFee, $subtotal + $serviceFee);
    }

    /**
     * The service fee rate as a whole-number percentage, for display.
     */
    public static function serviceFeePercent(): int
    {
        return (int) round((float) config('carhub.service_fee_rate') * 100);
    }
}
