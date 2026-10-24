<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\CompanyApplication;
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
            'documentable_type' => (new CompanyApplication)->getMorphClass(),
            'documentable_id' => CompanyApplication::factory(),
            'user_id' => fn (array $attributes) => CompanyApplication::whereKey($attributes['documentable_id'])->value('user_id'),
            'type' => DocumentType::DriversLicense,
            'path' => 'documents/'.fake()->uuid().'.jpg',
            'original_name' => 'license.jpg',
            'status' => DocumentStatus::Pending,
        ];
    }
}
