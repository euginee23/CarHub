<?php

use App\Enums\ApplicationStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\OwnerApplication;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Notifications\OwnerApplicationReviewed;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(VerificationDocument::DISK);
});

/**
 * Fill in and submit the owner application form as the given user.
 */
function submitOwnerApplication(User $user): void
{
    Livewire::actingAs($user)
        ->test('pages::owner.apply')
        ->set('governmentIdType', DocumentType::Passport->value)
        ->set('governmentId', UploadedFile::fake()->image('passport.jpg'))
        ->set('driversLicense', UploadedFile::fake()->image('license.jpg'))
        ->set('vehicleRegistration', UploadedFile::fake()->create('orcr.pdf', 200, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors();
}

test('the owner application page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('owner.apply'))
        ->assertOk()
        ->assertSee('Become a vehicle owner');
});

test('a user can submit an owner application with their documents', function () {
    $user = User::factory()->create();

    submitOwnerApplication($user);

    $application = $user->latestOwnerApplication;

    expect($application->status)->toBe(ApplicationStatus::Pending)
        ->and($application->documents)->toHaveCount(3)
        ->and($application->documents->pluck('type')->all())->toContain(DocumentType::Passport, DocumentType::DriversLicense, DocumentType::VehicleRegistration);

    $application->documents->each(fn (VerificationDocument $document) => Storage::disk(VerificationDocument::DISK)->assertExists($document->path));

    expect($user->fresh()->isVerifiedOwner())->toBeFalse();
});

test('every document is required', function (string $field) {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::owner.apply')
        ->set('governmentId', UploadedFile::fake()->image('id.jpg'))
        ->set('driversLicense', UploadedFile::fake()->image('license.jpg'))
        ->set('vehicleRegistration', UploadedFile::fake()->image('orcr.jpg'))
        ->set($field, null)
        ->call('submit')
        ->assertHasErrors([$field => 'required']);

    expect(OwnerApplication::count())->toBe(0);
})->with(['governmentId', 'driversLicense', 'vehicleRegistration']);

test('documents must be images or pdfs', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::owner.apply')
        ->set('governmentId', UploadedFile::fake()->create('id.exe', 10))
        ->set('driversLicense', UploadedFile::fake()->image('license.jpg'))
        ->set('vehicleRegistration', UploadedFile::fake()->image('orcr.jpg'))
        ->call('submit')
        ->assertHasErrors(['governmentId' => 'mimes']);
});

test('a user cannot submit a second application while one is pending', function () {
    $user = User::factory()->create();
    OwnerApplication::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::owner.apply')
        ->set('governmentId', UploadedFile::fake()->image('id.jpg'))
        ->set('driversLicense', UploadedFile::fake()->image('license.jpg'))
        ->set('vehicleRegistration', UploadedFile::fake()->image('orcr.jpg'))
        ->call('submit')
        ->assertForbidden();

    expect($user->ownerApplications()->count())->toBe(1);
});

test('a rejected applicant can apply again', function () {
    $user = User::factory()->create();
    OwnerApplication::factory()->for($user)->rejected()->create(['rejection_reason' => 'The OR/CR photo is blurry.']);

    $this->actingAs($user)->get(route('owner.apply'))->assertSee('The OR/CR photo is blurry.');

    submitOwnerApplication($user);

    expect($user->ownerApplications()->count())->toBe(2)
        ->and($user->fresh()->latestOwnerApplication->status)->toBe(ApplicationStatus::Pending);
});

test('an administrator can approve an application', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $application = OwnerApplication::factory()->create();
    VerificationDocument::factory()->for($application, 'documentable')->create(['user_id' => $application->user_id]);

    Livewire::actingAs($admin)
        ->test('pages::admin.owner-applications')
        ->assertSee($application->user->name)
        ->call('approve', $application->id)
        ->assertHasNoErrors();

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Approved)
        ->and($application->reviewed_by)->toBe($admin->id)
        ->and($application->documents->first()->status)->toBe(DocumentStatus::Approved)
        ->and($application->user->isVerifiedOwner())->toBeTrue();

    Notification::assertSentTo($application->user, OwnerApplicationReviewed::class);
});

test('an administrator can reject an application with a reason', function () {
    Notification::fake();

    $application = OwnerApplication::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.owner-applications')
        ->call('startRejecting', $application->id)
        ->set('rejectionReason', 'The driver\'s license has expired.')
        ->call('reject')
        ->assertHasNoErrors();

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Rejected)
        ->and($application->rejection_reason)->toBe('The driver\'s license has expired.')
        ->and($application->user->isVerifiedOwner())->toBeFalse();

    Notification::assertSentTo($application->user, OwnerApplicationReviewed::class);
});

test('a rejection requires a reason', function () {
    $application = OwnerApplication::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.owner-applications')
        ->call('startRejecting', $application->id)
        ->call('reject')
        ->assertHasErrors(['rejectionReason' => 'required']);

    expect($application->fresh()->isPending())->toBeTrue();
});

test('an application that was already reviewed cannot be reviewed again', function () {
    $application = OwnerApplication::factory()->rejected()->create();

    $component = Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.owner-applications');

    expect(fn () => $component->call('approve', $application->id))->toThrow(ModelNotFoundException::class);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Rejected);
});

test('non-administrators cannot access the review queue', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.owner-applications'))
        ->assertForbidden();
});

test('documents are only visible to their owner and administrators', function () {
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
