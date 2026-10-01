<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\VehicleLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleLocation>
 */
class VehicleLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'vehicle_id' => fn (array $attributes) => Booking::whereKey($attributes['booking_id'])->value('vehicle_id'),
            'latitude' => fake()->latitude(10.25, 10.40),
            'longitude' => fake()->longitude(123.85, 123.96),
            'speed_kph' => fake()->randomFloat(1, 0, 80),
            'heading' => fake()->numberBetween(0, 359),
            'recorded_at' => now(),
        ];
    }
}
