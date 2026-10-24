<?php

use App\Enums\ApplicationStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\CompanyApplication;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Notifications\CompanyApplicationReviewed;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(VerificationDocument::DISK);
});

/**
 * Open the company application form as the given user with every field filled in.
 */
function filledCompanyApplication(User $user): Testable
{
    return Livewire::actingAs($user)
        ->test('pages::company.apply')
        ->set('form.company_name', 'Nemaria Car Rental')
        ->set('form.business_address', 'Rizal Avenue, San Francisco District, Pagadian City')
        ->set('form.business_permit', UploadedFile::fake()->create('permit.pdf', 200, 'application/pdf'))
        ->set('form.representative_id_type', DocumentType::Passport->value)
        ->set('form.representative_id', UploadedFile::fake()->image('passport.jpg'));
}

/**
 * Fill in and submit the rental company application form as the given user.
 */
function submitCompanyApplication(User $user): void
{
    filledCompanyApplication($user)->call('submit')->assertHasNoErrors();
}

test('the rental company application page is displayed', function () {
    $this->actingAs(User::factory()->company()->create())
        ->get(route('company.apply'))
        ->assertOk()
        ->assertSee('Company verification')
        ->assertSee('Business permit');
});

test('a rental company can submit its details, business permit, and representative ID', function () {
    $user = User::factory()->company()->create();

    submitCompanyApplication($user);

    $application = $user->latestCompanyApplication;

    expect($application->status)->toBe(ApplicationStatus::Pending)
        ->and($application->company_name)->toBe('Nemaria Car Rental')
        ->and($application->business_address)->toBe('Rizal Avenue, San Francisco District, Pagadian City')
        ->and($application->documents)->toHaveCount(2)
        ->and($application->documents->pluck('type')->all())->toContain(DocumentType::BusinessPermit, DocumentType::Passport);

    $application->documents->each(fn (VerificationDocument $document) => Storage::disk(VerificationDocument::DISK)->assertExists($document->path));

    expect($user->fresh()->isVerifiedCompany())->toBeFalse();
});

test('every company detail and document is required', function (string $field, mixed $empty) {
    filledCompanyApplication(User::factory()->company()->create())
        ->set('form.'.$field, $empty)
        ->call('submit')
        ->assertHasErrors(['form.'.$field => 'required']);

    expect(CompanyApplication::count())->toBe(0);
})->with([
    'company name' => ['company_name', ''],
    'business address' => ['business_address', ''],
    'business permit' => ['business_permit', null],
    'representative ID' => ['representative_id', null],
]);

test('documents must be images or pdfs', function () {
    filledCompanyApplication(User::factory()->company()->create())
        ->set('form.business_permit', UploadedFile::fake()->create('permit.exe', 10))
        ->call('submit')
        ->assertHasErrors(['form.business_permit' => 'mimes']);
});

test('a user cannot submit a second application while one is pending', function () {
    $user = User::factory()->company()->create();
    CompanyApplication::factory()->for($user)->create();

    filledCompanyApplication($user)
        ->call('submit')
        ->assertForbidden();

    expect($user->companyApplications()->count())->toBe(1);
});

test('a rejected applicant can apply again', function () {
    $user = User::factory()->company()->create();
    CompanyApplication::factory()->for($user)->rejected()->create(['company_name' => 'Nemaria Rentals', 'rejection_reason' => 'The business permit has expired.']);

    $this->actingAs($user)->get(route('company.apply'))->assertSee('The business permit has expired.');

    Livewire::actingAs($user)->test('pages::company.apply')->assertSet('form.company_name', 'Nemaria Rentals');

    submitCompanyApplication($user);

    expect($user->companyApplications()->count())->toBe(2)
        ->and($user->fresh()->latestCompanyApplication->status)->toBe(ApplicationStatus::Pending);
});

test('an administrator can approve an application, publishing the company name and address', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $application = CompanyApplication::factory()->create(['company_name' => 'Nemaria Car Rental', 'business_address' => 'Pagadian City']);
    VerificationDocument::factory()->for($application, 'documentable')->create(['user_id' => $application->user_id, 'type' => DocumentType::BusinessPermit]);

    Livewire::actingAs($admin)
        ->test('pages::admin.company-applications')
        ->assertSee('Nemaria Car Rental')
        ->assertSee($application->user->name)
        ->assertSee('Mayor\'s / Business permit')
        ->call('approve', $application->id)
        ->assertHasNoErrors();

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Approved)
        ->and($application->reviewed_by)->toBe($admin->id)
        ->and($application->documents->first()->status)->toBe(DocumentStatus::Approved)
        ->and($application->user->isVerifiedCompany())->toBeTrue()
        ->and($application->user->company_name)->toBe('Nemaria Car Rental')
        ->and($application->user->company_address)->toBe('Pagadian City')
        ->and($application->user->displayName())->toBe('Nemaria Car Rental');

    Notification::assertSentTo($application->user, CompanyApplicationReviewed::class);
});

test('an administrator can reject an application with a reason', function () {
    Notification::fake();

    $application = CompanyApplication::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.company-applications')
        ->call('startRejecting', $application->id)
        ->set('rejectionReason', 'The business permit has expired.')
        ->call('reject')
        ->assertHasNoErrors();

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Rejected)
        ->and($application->rejection_reason)->toBe('The business permit has expired.')
        ->and($application->user->isVerifiedCompany())->toBeFalse();

    Notification::assertSentTo($application->user, CompanyApplicationReviewed::class);
});

test('a rejection requires a reason', function () {
    $application = CompanyApplication::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.company-applications')
        ->call('startRejecting', $application->id)
        ->call('reject')
        ->assertHasErrors(['rejectionReason' => 'required']);

    expect($application->fresh()->isPending())->toBeTrue();
});

test('an application that was already reviewed cannot be reviewed again', function () {
    $application = CompanyApplication::factory()->rejected()->create();

    $component = Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.company-applications');

    expect(fn () => $component->call('approve', $application->id))->toThrow(ModelNotFoundException::class);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Rejected);
});

test('non-administrators cannot access the review queue', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.company-applications'))
        ->assertForbidden();
});

test('documents are only visible to their rental company and administrators', function () {
    $document = VerificationDocument::factory()->create();
    Storage::disk(VerificationDocument::DISK)->put($document->path, 'contents');

    $this->actingAs($document->user)->get(route('documents.show', $document))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('documents.show', $document))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('documents.show', $document))->assertForbidden();
});

test('guests cannot open documents', function () {
    $document = VerificationDocument::factory()->create();

    $this->get(route('documents.show', $document))->assertRedirect(route('login'));
});

test('only rental company accounts can open rental company verification', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('company.apply'))
        ->assertRedirect(route('dashboard'));
});

test('links to the old owner pages redirect to the company pages', function (string $from, string $to) {
    $this->get($from)->assertRedirect($to)->assertStatus(301);
})->with([
    'overview' => ['/owner', '/company'],
    'verification' => ['/owner/apply', '/company/apply'],
    'a booking' => ['/owner/bookings/BK-ABC123', '/company/bookings/BK-ABC123'],
    'a vehicle' => ['/owner/vehicles/12/edit', '/company/vehicles/12/edit'],
    'admin review queue' => ['/admin/owner-applications', '/admin/company-applications'],
]);
