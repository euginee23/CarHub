<?php

namespace Database\Factories;

use App\Models\TermsVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TermsVersion>
 */
class TermsVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => fake()->unique()->numerify('2026.##.##'),
            'body' => (string) file_get_contents(resource_path('markdown/rental-terms.md')),
            'published_at' => now()->subDay(),
        ];
    }

    /**
     * Indicate that the version has been drafted but not yet published.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }
}
