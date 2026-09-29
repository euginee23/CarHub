<?php

namespace Database\Seeders;

use App\Models\TermsVersion;
use Illuminate\Database\Seeder;

class RentalTermsSeeder extends Seeder
{
    /**
     * Publish the initial rental terms renters accept at checkout.
     */
    public function run(): void
    {
        TermsVersion::firstOrCreate(
            ['version' => '2026.1'],
            [
                'body' => (string) file_get_contents(resource_path('markdown/rental-terms.md')),
                'published_at' => now(),
            ],
        );
    }
}
