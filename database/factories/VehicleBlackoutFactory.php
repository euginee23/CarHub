<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleBlackout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleBlackout>
 */
class VehicleBlackoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 30))->startOfDay();

        return [
            'vehicle_id' => Vehicle::factory(),
            'starts_on' => $start,
            'ends_on' => $start->addDays(fake()->numberBetween(0, 4)),
            'reason' => fake()->randomElement(['Personal use', 'Maintenance', null]),
        ];
    }
}
