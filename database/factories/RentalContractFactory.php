<?php

namespace Database\Factories;

use App\Actions\Bookings\GenerateRentalContract;
use App\Models\Booking;
use App\Models\RentalContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalContract>
 */
class RentalContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->approved()->withAcceptedTerms(),
            'snapshot' => fn (array $attributes) => app(GenerateRentalContract::class)->snapshot(Booking::whereKey($attributes['booking_id'])->firstOrFail()),
        ];
    }
}
