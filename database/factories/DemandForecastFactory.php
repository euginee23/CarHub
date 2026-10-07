<?php

namespace Database\Factories;

use App\Enums\VehicleType;
use App\Models\DemandForecast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemandForecast>
 */
class DemandForecastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_type' => VehicleType::Sedan,
            'date' => now()->addDay()->toDateString(),
            'predicted_requests' => fake()->randomFloat(2, 0, 5),
            'method' => DemandForecast::METHOD_LSTM,
            'run_id' => 'run-test',
        ];
    }
}
