<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->status(BookingStatus::Completed),
            'vehicle_id' => fn (array $attributes) => Booking::whereKey($attributes['booking_id'])->value('vehicle_id'),
            'reviewer_id' => fn (array $attributes) => Booking::whereKey($attributes['booking_id'])->value('renter_id'),
            'owner_id' => fn (array $attributes) => Booking::whereKey($attributes['booking_id'])->value('owner_id'),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->sentence(),
        ];
    }
}
