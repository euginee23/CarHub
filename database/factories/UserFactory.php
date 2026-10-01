<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '09'.fake()->numerify('#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a platform administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Indicate that the account is a renter account (the default).
     */
    public function renter(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Renter,
        ]);
    }

    /**
     * Indicate that the account is an owner account that has not been verified yet.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Owner,
        ]);
    }

    /**
     * Indicate that the account is an owner account verified to list vehicles.
     */
    public function verifiedOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Owner,
            'owner_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the user's account has been suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended_at' => now(),
        ]);
    }

    /**
     * Indicate that the user has two approved government IDs, so they can rent.
     */
    public function withVerifiedIdentity(): static
    {
        return $this->afterCreating(function (User $user): void {
            foreach ([DocumentType::DriversLicense, DocumentType::Passport] as $type) {
                VerificationDocument::factory()->create([
                    'documentable_type' => $user->getMorphClass(),
                    'documentable_id' => $user->id,
                    'user_id' => $user->id,
                    'type' => $type,
                    'status' => DocumentStatus::Approved,
                ]);
            }
        });
    }
}
