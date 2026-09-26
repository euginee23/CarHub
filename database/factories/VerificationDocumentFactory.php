<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\OwnerApplication;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationDocument>
 */
class VerificationDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'documentable_type' => (new OwnerApplication)->getMorphClass(),
            'documentable_id' => OwnerApplication::factory(),
            'user_id' => fn (array $attributes) => OwnerApplication::whereKey($attributes['documentable_id'])->value('user_id'),
            'type' => DocumentType::DriversLicense,
            'path' => 'documents/'.fake()->uuid().'.jpg',
            'original_name' => 'license.jpg',
            'status' => DocumentStatus::Pending,
        ];
    }
}
