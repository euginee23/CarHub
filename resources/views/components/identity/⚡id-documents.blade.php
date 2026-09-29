<?php

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $type = '';

    public ?TemporaryUploadedFile $file = null;

    /**
     * The renter's IDs on file, newest first.
     *
     * @return Collection<int, VerificationDocument>
     */
    #[Computed]
    public function documents(): Collection
    {
        return Auth::user()->identityDocuments()->latest()->get();
    }

    /**
     * The IDs that count towards the two-ID requirement.
     *
     * @return Collection<int, VerificationDocument>
     */
    #[Computed]
    public function activeDocuments(): Collection
    {
        return $this->documents->filter(fn (VerificationDocument $document) => $document->status !== DocumentStatus::Rejected)->values();
    }

    /**
     * ID types the renter can still add: government IDs they do not already have on file.
     *
     * @return array<int, DocumentType>
     */
    #[Computed]
    public function availableTypes(): array
    {
        $taken = $this->activeDocuments->map(fn (VerificationDocument $document) => $document->type)->all();

        return array_values(array_filter(DocumentType::governmentIds(), fn (DocumentType $type) => ! in_array($type, $taken, true)));
    }

    /**
     * Upload one ID for review.
     */
    public function upload(SubmitIdentityDocument $submitIdentityDocument): void
    {
        $this->validate([
            'type' => ['required', Rule::in(array_map(fn (DocumentType $type) => $type->value, $this->availableTypes))],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], attributes: ['type' => __('ID type'), 'file' => __('ID photo')]);

        $submitIdentityDocument->handle(Auth::user(), DocumentType::from($this->type), $this->file);

        $this->reset(['type', 'file']);
        $this->refreshDocuments();
    }

    /**
     * Withdraw an ID that has not been reviewed yet.
     */
    public function remove(int $documentId): void
    {
        $document = Auth::user()->identityDocuments()->where('status', DocumentStatus::Pending)->findOrFail($documentId);

        Storage::disk(VerificationDocument::DISK)->delete($document->path);
        $document->delete();

        $this->refreshDocuments();
    }

    /**
     * Forget cached document lists and let the page know the renter's IDs changed.
     */
    protected function refreshDocuments(): void
    {
        unset($this->documents, $this->activeDocuments, $this->availableTypes);

        $this->dispatch('identity-documents-updated');
    }
}; ?>

<div class="space-y-6">
    @php($approvedCount = $this->documents->where('status', DocumentStatus::Approved)->count())

    @if (auth()->user()->hasVerifiedIdentity())
        <flux:callout variant="success" icon="check-badge" :heading="__('Your identity is verified')">
            <flux:callout.text>{{ __('Two government IDs have been approved. You can complete bookings.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:callout icon="identification" :heading="__('Two valid government IDs are required to rent')">
            <flux:callout.text>
                {{ __('Upload two different IDs — for example your driver\'s license plus a passport, UMID, or PhilSys ID. An administrator checks each one; owners only see that you are verified.') }}
            </flux:callout.text>
            <flux:callout.text>{{ __(':approved of :required approved.', ['approved' => $approvedCount, 'required' => User::REQUIRED_IDENTITY_DOCUMENTS]) }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->documents->isNotEmpty())
        <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->documents as $document)
                <li wire:key="id-document-{{ $document->id }}" class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                    <div class="min-w-0">
                        <flux:link :href="route('documents.show', $document)" target="_blank" class="font-medium">{{ $document->type->label() }}</flux:link>
                        <p class="text-xs text-zinc-500">
                            {{ __('Uploaded :date', ['date' => $document->created_at->format('M j, Y')]) }}
                            @if ($document->rejection_reason)
                                &middot; <span class="text-red-600">{{ $document->rejection_reason }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" :color="$document->status->color()">{{ $document->status->label() }}</flux:badge>
                        @if ($document->status === DocumentStatus::Pending)
                            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="remove({{ $document->id }})" wire:confirm="{{ __('Withdraw this ID?') }}" :aria-label="__('Withdraw')" />
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($this->activeDocuments->count() < User::REQUIRED_IDENTITY_DOCUMENTS)
        <form wire:submit="upload" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="type" :label="__('ID type')" :placeholder="__('Choose an ID…')">
                    @foreach ($this->availableTypes as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="file" wire:model="file" :label="__('Photo or scan')" accept="image/*,application/pdf" />
            </div>
            <flux:text class="text-xs">{{ __('JPG, PNG, or PDF up to 5 MB. Make sure your name and photo are readable.') }}</flux:text>
            <flux:button type="submit" variant="primary" size="sm" data-test="upload-id">{{ __('Upload ID') }}</flux:button>
        </form>
    @endif
</div>
