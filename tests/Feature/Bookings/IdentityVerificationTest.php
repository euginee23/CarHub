<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Notifications\IdentityDocumentReviewed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(VerificationDocument::DISK);
    Notification::fake();
});

/**
 * Upload one ID through the renter's ID component.
 */
function uploadId(User $user, DocumentType $type): Testable
{
    return Livewire::actingAs($user)
        ->test('identity.id-documents')
        ->set('type', $type->value)
        ->set('file', UploadedFile::fake()->image($type->value.'.jpg'))
        ->call('upload');
}

/**
 * Store an ID on file for the user without going through the form.
 */
function idOnFile(User $user, DocumentType $type, DocumentStatus $status = DocumentStatus::Pending): VerificationDocument
{
    return VerificationDocument::factory()->create([
        'documentable_type' => $user->getMorphClass(),
        'documentable_id' => $user->id,
        'user_id' => $user->id,
        'type' => $type,
        'status' => $status,
    ]);
}

test('the ID verification settings page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('identity.edit'))
        ->assertOk()
        ->assertSee('Two valid government IDs are required to rent');
});

test('a renter can upload two different IDs for review', function () {
    $user = User::factory()->create();

    uploadId($user, DocumentType::DriversLicense)->assertHasNoErrors()->assertDispatched('identity-documents-updated');
    uploadId($user, DocumentType::Passport)->assertHasNoErrors();

    expect($user->identityDocuments()->pluck('type')->all())->toContain(DocumentType::DriversLicense, DocumentType::Passport)
        ->and($user->hasVerifiedIdentity())->toBeFalse();

    $user->identityDocuments->each(fn (VerificationDocument $document) => Storage::disk(VerificationDocument::DISK)->assertExists($document->path));
});

test('both IDs must be different kinds', function () {
    $user = User::factory()->create();
    idOnFile($user, DocumentType::Passport);

    uploadId($user, DocumentType::Passport)->assertHasErrors('type');

    expect($user->identityDocuments()->count())->toBe(1);
});

test('no more than two IDs can be on file at once', function () {
    $user = User::factory()->create();
    idOnFile($user, DocumentType::Passport);
    idOnFile($user, DocumentType::Umid, DocumentStatus::Approved);

    uploadId($user, DocumentType::NationalId)->assertHasErrors('type');
});

test('a rejected ID can be replaced', function () {
    $user = User::factory()->create();
    idOnFile($user, DocumentType::Passport, DocumentStatus::Rejected);
    idOnFile($user, DocumentType::Umid, DocumentStatus::Approved);

    uploadId($user, DocumentType::Passport)->assertHasNoErrors();

    expect($user->identityDocuments()->where('status', DocumentStatus::Pending)->count())->toBe(1);
});

test('vehicle papers do not count as an ID', function () {
    uploadId(User::factory()->create(), DocumentType::VehicleRegistration)->assertHasErrors('type');
});

test('a pending ID can be withdrawn', function () {
    $user = User::factory()->create();
    $document = idOnFile($user, DocumentType::Passport);

    Livewire::actingAs($user)->test('identity.id-documents')->call('remove', $document->id);

    expect($document->fresh())->toBeNull();
});

test('identity is verified only with two approved IDs of different types', function () {
    $user = User::factory()->create();
    idOnFile($user, DocumentType::Passport, DocumentStatus::Approved);

    expect($user->hasVerifiedIdentity())->toBeFalse();

    idOnFile($user, DocumentType::Umid);

    expect($user->hasVerifiedIdentity())->toBeFalse();

    $user->identityDocuments()->update(['status' => DocumentStatus::Approved]);

    expect($user->hasVerifiedIdentity())->toBeTrue();
});

test('administrators review renter IDs', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $approve = idOnFile($user, DocumentType::Passport);
    $reject = idOnFile($user, DocumentType::Umid);

    Livewire::actingAs($admin)
        ->test('pages::admin.id-reviews')
        ->assertSee($user->name)
        ->call('approve', $approve->id)
        ->call('startRejecting', $reject->id)
        ->set('rejectionReason', 'The photo is too blurry to read.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($approve->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and($approve->fresh()->reviewed_by)->toBe($admin->id)
        ->and($reject->fresh()->status)->toBe(DocumentStatus::Rejected)
        ->and($reject->fresh()->rejection_reason)->toBe('The photo is too blurry to read.');

    Notification::assertSentToTimes($user, IdentityDocumentReviewed::class, 2);
});

test('owner application documents are not in the renter ID queue', function () {
    VerificationDocument::factory()->create(); // Attached to an owner application.

    $component = Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.id-reviews');

    expect($component->instance()->documents)->toHaveCount(0);
});

test('only administrators can open the ID review queue', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.id-reviews'))->assertForbidden();
});
