<?php

use App\Actions\Companies\SubmitCompanyApplication;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Livewire\Forms\CompanyApplicationForm;
use App\Models\CompanyApplication;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Company verification')] class extends Component {
    use WithFileUploads;

    public CompanyApplicationForm $form;

    /**
     * The user's most recent rental company application, if any.
     */
    #[Computed]
    public function application(): ?CompanyApplication
    {
        return Auth::user()->latestCompanyApplication()->with('documents')->first();
    }

    /**
     * Whether the form should be shown: no application yet, or the last one was rejected.
     */
    #[Computed]
    public function canApply(): bool
    {
        return ! Auth::user()->isVerifiedCompany()
            && ($this->application === null || $this->application->status === ApplicationStatus::Rejected);
    }

    /**
     * Start a resubmission from the details of the rejected application.
     */
    public function mount(): void
    {
        if ($this->application?->status === ApplicationStatus::Rejected) {
            $this->form->company_name = (string) $this->application->company_name;
            $this->form->business_address = (string) $this->application->business_address;
        }
    }

    /**
     * Submit the rental company application for review.
     */
    public function submit(SubmitCompanyApplication $submitCompanyApplication): void
    {
        abort_unless($this->canApply, 403);

        $this->form->validate();

        $submitCompanyApplication->handle(Auth::user(), [
            'company_name' => $this->form->company_name,
            'business_address' => $this->form->business_address,
            'notes' => filled($this->form->notes) ? $this->form->notes : null,
        ], [
            ['type' => DocumentType::BusinessPermit, 'file' => $this->form->business_permit],
            ['type' => DocumentType::from($this->form->representative_id_type), 'file' => $this->form->representative_id],
        ]);

        $this->form->reset();
        unset($this->application, $this->canApply);

        Flux::toast(variant: 'success', text: __('Application submitted. We will review it shortly.'));
    }
}; ?>

<div>
    <x-app.page-header
        :title="__('Company verification')"
        :description="__('Every rental company on CarHub is verified once by an administrator. After approval, your company can post vehicles right away.')"
    />

    <x-app.content width="3xl" class="space-y-8">
        @if (auth()->user()->isVerifiedCompany())
            <flux:callout variant="success" icon="check-badge" :heading="__(':company is a verified rental company', ['company' => auth()->user()->displayName()])">
                <flux:callout.text>{{ __('Your company is trusted to post vehicles. New listings go live as soon as you publish them.') }}</flux:callout.text>
                <x-slot name="actions">
                    <flux:button :href="route('company.vehicles.index')" variant="primary" wire:navigate>{{ __('Manage my vehicles') }}</flux:button>
                </x-slot>
            </flux:callout>
        @elseif ($this->application?->isPending())
            <flux:callout variant="warning" icon="clock" :heading="__('Your application is under review')">
                <flux:callout.text>
                    {{ __('Submitted :date. We will notify you by email once an administrator has reviewed your documents.', ['date' => $this->application->created_at->format('M j, Y')]) }}
                </flux:callout.text>
            </flux:callout>

            <flux:card class="space-y-3">
                <flux:heading>{{ $this->application->company_name }}</flux:heading>
                <flux:text>{{ $this->application->business_address }}</flux:text>
                <flux:heading size="sm" class="pt-2">{{ __('Submitted documents') }}</flux:heading>
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
                    <flux:heading>{{ __('Company details') }}</flux:heading>

                    <flux:input wire:model="form.company_name" :label="__('Company name')" :placeholder="__('e.g. Nemaria Car Rental')" />

                    <flux:input wire:model="form.business_address" :label="__('Business address')" :placeholder="__('Street, barangay, city')" />

                    <flux:separator variant="subtle" />

                    <flux:heading>{{ __('Documents') }}</flux:heading>

                    <flux:input
                        type="file"
                        wire:model="form.business_permit"
                        :label="__('Mayor\'s / Business permit')"
                        :description="__('The current permit issued by your city or municipality.')"
                        accept="image/*,application/pdf"
                    />

                    <div class="grid gap-6 sm:grid-cols-2">
                        <flux:select wire:model="form.representative_id_type" :label="__('Representative\'s ID type')">
                            @foreach (DocumentType::governmentIds() as $type)
                                <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input
                            type="file"
                            wire:model="form.representative_id"
                            :label="__('Representative\'s valid ID')"
                            :description="__('Of the person registering the company.')"
                            accept="image/*,application/pdf"
                        />
                    </div>

                    <flux:textarea wire:model="form.notes" :label="__('Notes for the reviewer (optional)')" rows="3" />

                    <flux:text class="text-xs">{{ __('JPG, PNG, or PDF up to 5 MB each. Documents are stored privately and only visible to CarHub administrators.') }}</flux:text>
                </flux:card>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" data-test="submit-company-application">
                        {{ __('Submit for review') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </x-app.content>
</div>
