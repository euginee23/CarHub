<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TermsVersion;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pickup = now()->addDays(fake()->numberBetween(3, 20))->setTime(9, 0);
        $days = fake()->numberBetween(1, 4);
        $rate = fake()->numberBetween(15, 45) * 100;
        $subtotal = $rate * $days;
        $fee = (int) round($subtotal * (float) config('carhub.service_fee_rate'));

        return [
            'renter_id' => User::factory(),
            'vehicle_id' => Vehicle::factory(),
            'owner_id' => fn (array $attributes) => Vehicle::whereKey($attributes['vehicle_id'])->value('owner_id'),
            'pickup_at' => $pickup,
            'return_at' => $pickup->addDays($days),
            'pickup_location' => 'Cebu City',
            'daily_rate' => $rate,
            'days' => $days,
            'subtotal' => $subtotal,
            'service_fee' => $fee,
            'total' => $subtotal + $fee,
            'status' => BookingStatus::Requested,
        ];
    }

    /**
     * Book the given vehicle, copying its owner, rate, and pickup area.
     */
    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(function (array $attributes) use ($vehicle) {
            $subtotal = $vehicle->price_per_day * $attributes['days'];
            $fee = (int) round($subtotal * (float) config('carhub.service_fee_rate'));

            return [
                'vehicle_id' => $vehicle->id,
                'owner_id' => $vehicle->owner_id,
                'pickup_location' => $vehicle->location,
                'daily_rate' => $vehicle->price_per_day,
                'subtotal' => $subtotal,
                'service_fee' => $fee,
                'total' => $subtotal + $fee,
            ];
        });
    }

    /**
     * Schedule the booking for an exact window.
     */
    public function between(string $pickup, string $return): static
    {
        return $this->state(fn (array $attributes) => [
            'pickup_at' => $pickup,
            'return_at' => $return,
        ]);
    }

    /**
     * Put the booking in the given status.
     */
    public function status(BookingStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'approved_at' => $status === BookingStatus::Requested ? null : now(),
        ]);
    }

    /**
     * Indicate that the owner has approved the request.
     */
    public function approved(): static
    {
        return $this->status(BookingStatus::Approved);
    }

    /**
     * Indicate that the booking is paid for and confirmed.
     */
    public function confirmed(): static
    {
        return $this->status(BookingStatus::Confirmed);
    }

    /**
     * Indicate that the renter has accepted the current rental terms.
     */
    public function withAcceptedTerms(): static
    {
        return $this->state(fn (array $attributes) => [
            'terms_version_id' => TermsVersion::current()->id ?? TermsVersion::factory(),
            'terms_accepted_at' => now(),
            'terms_accepted_ip' => '127.0.0.1',
        ]);
    }
}
