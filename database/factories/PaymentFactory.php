<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->status(BookingStatus::AwaitingPayment),
            'method' => PaymentMethod::Gcash,
            'amount' => fn (array $attributes) => Booking::whereKey($attributes['booking_id'])->value('total') * 100,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
            'provider' => 'paymongo',
            'provider_checkout_id' => 'cs_'.fake()->unique()->bothify('????????????????'),
            'checkout_url' => 'https://checkout.paymongo.com/cs_test',
        ];
    }

    /**
     * Indicate that the payment went through.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'provider_payment_id' => 'pay_'.fake()->bothify('????????????????'),
        ]);
    }
}
