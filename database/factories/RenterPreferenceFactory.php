<?php

namespace Database\Factories;

use App\Models\RenterPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RenterPreference>
 */
class RenterPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'budget_per_day' => null,
            'vehicle_types' => null,
            'seats_min' => null,
            'transmission' => null,
        ];
    }
}
