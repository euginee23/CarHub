<?php

use App\Actions\Owners\SubmitOwnerApplication;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Models\OwnerApplication;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Become a vehicle owner')] class extends Component {
    use WithFileUploads;

    public string $governmentIdType = 'national_id';

    public ?TemporaryUploadedFile $governmentId = null;

    public ?TemporaryUploadedFile $driversLicense = null;

    public ?TemporaryUploadedFile $vehicleRegistration = null;

    public string $notes = '';

    /**
     * The user's most recent owner application, if any.
     */
    #[Computed]
    public function application(): ?OwnerApplication
    {
        return Auth::user()->latestOwnerApplication()->with('documents')->first();
    }

    /**
     * The government IDs accepted alongside the driver's license.
     *
     * @return array<int, DocumentType>
     */
    #[Computed]
    public function governmentIdTypes(): array
    {
        return array_values(array_filter(
            DocumentType::governmentIds(),
            fn (DocumentType $type): bool => $type !== DocumentType::DriversLicense,
        ));
    }

    /**
     * Whether the form should be shown: no application yet, or the last one was rejected.
     */
    #[Computed]
    public function canApply(): bool
    {
        return ! Auth::user()->isVerifiedOwner()
            && ($this->application === null || $this->application->status === ApplicationStatus::Rejected);
    }

    /**
     * Submit the owner application for review.
     */
    public function submit(SubmitOwnerApplication $submitOwnerApplication): void
    {
        abort_unless($this->canApply, 403);

        $documentRules = ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];

        $this->validate([
            'governmentIdType' => ['required', Rule::in(array_map(fn (DocumentType $type) => $type->value, $this->governmentIdTypes))],
            'governmentId' => $documentRules,
            'driversLicense' => $documentRules,
            'vehicleRegistration' => $documentRules,
            'notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'governmentId' => __('government ID'),
            'driversLicense' => __('driver\'s license'),
            'vehicleRegistration' => __('vehicle OR/CR'),
        ]);

        $submitOwnerApplication->handle(Auth::user(), [
            ['type' => DocumentType::from($this->governmentIdType), 'file' => $this->governmentId],
            ['type' => DocumentType::DriversLicense, 'file' => $this->driversLicense],
            ['type' => DocumentType::VehicleRegistration, 'file' => $this->vehicleRegistration],
        ], filled($this->notes) ? $this->notes : null);

        $this->reset(['governmentId', 'driversLicense', 'vehicleRegistration', 'notes']);
        unset($this->application, $this->canApply);

        Flux::toast(variant: 'success', text: __('Application submitted. We will review it shortly.'));
    }
}; ?>

<div class="mx-auto w-full max-w-3xl space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Become a vehicle owner') }}</flux:heading>
        <flux:subheading size="lg" class="mt-2">
            {{ __('Every owner on CarHub is verified before their first listing goes live. Upload your documents and an administrator will review them.') }}
        </flux:subheading>
    </div>

    @if (auth()->user()->isVerifiedOwner())
        <flux:callout variant="success" icon="check-badge" :heading="__('You are a verified owner')">
            <flux:callout.text>{{ __('You can list vehicles for rent on CarHub.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button :href="route('owner.vehicles.index')" variant="primary" wire:navigate>{{ __('Manage my vehicles') }}</flux:button>
            </x-slot>
        </flux:callout>
    @elseif ($this->application?->isPending())
        <flux:callout variant="warning" icon="clock" :heading="__('Your application is under review')">
            <flux:callout.text>
                {{ __('Submitted :date. We will notify you by email once an administrator has reviewed your documents.', ['date' => $this->application->created_at->format('M j, Y')]) }}
            </flux:callout.text>
        </flux:callout>

        <flux:card class="space-y-3">
            <flux:heading>{{ __('Submitted documents') }}</flux:heading>
            @foreach ($this->application->documents as $document)
                <div wire:key="document-{{ $document->id }}" class="flex items-center justify-between gap-4 text-sm">
                    <flux:link :href="route('documents.show', $document)" target="_blank">{{ $document->type->label() }}</flux:link>
                    <flux:badge size="sm" :color="$document->status->color()">{{ $document->status->label() }}</flux:badge>
                </div>
            @endforeach
        </flux:card>
    @endif

    @if ($this->canApply)
        @if ($this->application?->status === ApplicationStatus::Rejected)
            <flux:callout variant="danger" icon="x-circle" :heading="__('Your previous application was not approved')">
                <flux:callout.text>{{ $this->application->rejection_reason }}</flux:callout.text>
                <flux:callout.text>{{ __('Fix the issue above and submit your documents again.') }}</flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="submit" class="space-y-6">
            <flux:card class="space-y-6">
                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:select wire:model="governmentIdType" :label="__('Government ID type')">
                        @foreach ($this->governmentIdTypes as $type)
                            <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input type="file" wire:model="governmentId" :label="__('Government ID')" accept="image/*,application/pdf" />
                </div>

                <flux:input type="file" wire:model="driversLicense" :label="__('Driver\'s license')" accept="image/*,application/pdf" />

                <flux:input
                    type="file"
                    wire:model="vehicleRegistration"
                    :label="__('Vehicle OR/CR')"
                    :description="__('Official receipt and certificate of registration of a vehicle you intend to list.')"
                    accept="image/*,application/pdf"
                />

                <flux:textarea wire:model="notes" :label="__('Notes for the reviewer (optional)')" rows="3" />

                <flux:text class="text-xs">{{ __('JPG, PNG, or PDF up to 5 MB each. Documents are stored privately and only visible to CarHub administrators.') }}</flux:text>
            </flux:card>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" data-test="submit-owner-application">
                    {{ __('Submit for review') }}
                </flux:button>
            </div>
        </form>
    @endif
</div>
