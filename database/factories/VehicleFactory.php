<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$brand, $model, $type] = fake()->randomElement([
            ['Toyota', 'Vios', VehicleType::Sedan],
            ['Toyota', 'Fortuner', VehicleType::Suv],
            ['Honda', 'Civic', VehicleType::Sedan],
            ['Mitsubishi', 'Montero Sport', VehicleType::Suv],
            ['Toyota', 'Hiace', VehicleType::Van],
            ['Ford', 'Ranger', VehicleType::Pickup],
            ['Suzuki', 'Swift', VehicleType::Hatchback],
            ['Toyota', 'Innova', VehicleType::Mpv],
        ]);

        return [
            'owner_id' => User::factory()->verifiedOwner(),
            'brand' => $brand,
            'model' => $model,
            'year' => fake()->numberBetween(2018, 2025),
            'type' => $type,
            'transmission' => fake()->randomElement(Transmission::cases()),
            'fuel' => fake()->randomElement([FuelType::Gasoline, FuelType::Diesel]),
            'seats' => match ($type) {
                VehicleType::Van => 12,
                VehicleType::Suv, VehicleType::Mpv => 7,
                default => 5,
            },
            'price_per_day' => fake()->numberBetween(12, 60) * 100,
            'description' => fake()->paragraph(),
            'features' => fake()->randomElements(['Air conditioning', 'Bluetooth audio', 'Dashcam', 'GPS tracker', 'USB charging', 'Reverse camera'], 3),
            'location' => 'Cebu City',
            'latitude' => fake()->latitude(10.25, 10.40),
            'longitude' => fake()->longitude(123.85, 123.96),
            'status' => VehicleStatus::Listed,
            'instant_book' => false,
            'featured' => false,
            'rating' => fake()->randomFloat(1, 4, 5),
            'trips_count' => fake()->numberBetween(0, 150),
        ];
    }

    /**
     * Indicate that the vehicle is an unpublished draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VehicleStatus::Draft,
        ]);
    }

    /**
     * Indicate that the owner has taken the vehicle off the marketplace.
     */
    public function unlisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VehicleStatus::Unlisted,
        ]);
    }

    /**
     * Indicate that the vehicle is featured on the home page.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }
}
