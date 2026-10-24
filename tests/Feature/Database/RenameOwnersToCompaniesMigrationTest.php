<?php

use App\Enums\UserRole;
use App\Models\CompanyApplication;
use App\Models\User;
use App\Notifications\CompanyApplicationReviewed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('owners and their stored references become rental companies', function () {
    $migration = require database_path('migrations/2026_10_07_042027_rename_owners_to_companies.php');
    $migration->down();

    $ownerId = DB::table('users')->insertGetId([
        'name' => 'Nemaria Owner',
        'email' => 'owner@example.com',
        'password' => 'secret',
        'role' => 'owner',
        'owner_verified_at' => now(),
    ]);
    $applicationId = DB::table('owner_applications')->insertGetId(['user_id' => $ownerId, 'status' => 'approved']);
    DB::table('verification_documents')->insert([
        'documentable_type' => 'App\\Models\\OwnerApplication',
        'documentable_id' => $applicationId,
        'user_id' => $ownerId,
        'type' => 'business_permit',
        'path' => 'documents/permit.pdf',
        'original_name' => 'permit.pdf',
    ]);
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\OwnerApplicationReviewed',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $ownerId,
        'data' => json_encode(['url' => 'http://localhost/owner/vehicles', 'owner_application_id' => $applicationId]),
    ]);
    DB::table('activity_logs')->insert([
        'action' => 'owner_application.approved',
        'subject_type' => 'App\\Models\\OwnerApplication',
        'subject_id' => $applicationId,
        'description' => 'Approved.',
    ]);

    $migration->up();

    $company = User::find($ownerId);
    $notification = $company->notifications()->first();

    expect($company->role)->toBe(UserRole::Company)
        ->and($company->isVerifiedCompany())->toBeTrue()
        ->and(CompanyApplication::find($applicationId)->documents)->toHaveCount(1)
        ->and($notification->type)->toBe(CompanyApplicationReviewed::class)
        ->and($notification->data)->toBe(['url' => 'http://localhost/company/vehicles', 'company_application_id' => $applicationId])
        ->and(DB::table('activity_logs')->first())
        ->action->toBe('company_application.approved')
        ->subject_type->toBe(CompanyApplication::class);
});
