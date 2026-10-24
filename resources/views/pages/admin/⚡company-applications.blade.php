<?php

use App\Actions\Companies\ReviewCompanyApplication;
use App\Enums\ApplicationStatus;
use App\Models\CompanyApplication;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Company applications')] class extends Component {
    use WithPagination;

    #[Url(except: 'pending')]
    public string $status = 'pending';

    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    /**
     * The applications in the selected status, oldest first so the queue is fair.
     *
     * @return LengthAwarePaginator<int, CompanyApplication>
     */
    #[Computed]
    public function applications(): LengthAwarePaginator
    {
        return CompanyApplication::query()
            ->with(['user', 'documents', 'reviewer'])
            ->where('status', ApplicationStatus::tryFrom($this->status) ?? ApplicationStatus::Pending)
            ->oldest()
            ->paginate(10);
    }

    /**
     * Reset pagination whenever the status tab changes.
     */
    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Approve an application and verify the applicant as a rental company.
     */
    public function approve(int $applicationId, ReviewCompanyApplication $reviewCompanyApplication): void
    {
        $reviewCompanyApplication->approve($this->pendingApplication($applicationId), Auth::user());

        unset($this->applications);

        Flux::toast(variant: 'success', text: __('Application approved.'));
    }

    /**
     * Open the rejection modal for an application.
     */
    public function startRejecting(int $applicationId): void
    {
        $this->rejectingId = $applicationId;
        $this->rejectionReason = '';

        Flux::modal('reject-application')->show();
    }

    /**
     * Reject the application currently open in the modal.
     */
    public function reject(ReviewCompanyApplication $reviewCompanyApplication): void
    {
        $this->validate(['rejectionReason' => ['required', 'string', 'min:10', 'max:1000']]);

        $reviewCompanyApplication->reject($this->pendingApplication((int) $this->rejectingId), Auth::user(), $this->rejectionReason);

        $this->reset(['rejectingId', 'rejectionReason']);
        unset($this->applications);

        Flux::modal('reject-application')->close();
        Flux::toast(variant: 'success', text: __('Application rejected. The applicant has been notified.'));
    }

    /**
     * Find a pending application, failing if it has already been reviewed.
     */
    protected function pendingApplication(int $applicationId): CompanyApplication
    {
        return CompanyApplication::pending()->findOrFail($applicationId);
    }
}; ?>

<div>
    <x-app.page-header :title="__('Company applications')" :description="__('Check each rental company\'s business permit and representative ID. Approved companies post vehicles without further review.')" />

    <x-app.content class="space-y-6">
        <div class="flex flex-wrap gap-2">
            @foreach (ApplicationStatus::cases() as $option)
                <flux:button
                    wire:key="status-{{ $option->value }}"
                    size="sm"
                    :variant="$status === $option->value ? 'primary' : 'outline'"
                    wire:click="$set('status', '{{ $option->value }}')"
                >
                    {{ $option->label() }}
                </flux:button>
            @endforeach
        </div>

        @if ($this->applications->isEmpty())
            <flux:card>
                <flux:text>{{ __('No applications here.') }}</flux:text>
            </flux:card>
        @else
            <div class="space-y-4">
                @foreach ($this->applications as $application)
                    <flux:card wire:key="application-{{ $application->id }}" class="space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <flux:heading>{{ $application->company_name ?? $application->user->name }}</flux:heading>
                                @if ($application->business_address)
                                    <flux:text class="mt-1">{{ $application->business_address }}</flux:text>
                                @endif
                                <flux:text class="mt-1">
                                    {{ __('Representative: :name', ['name' => $application->user->name]) }} &middot; {{ $application->user->email }} &middot; {{ $application->user->phone ?? __('No phone') }}
                                </flux:text>
                                <flux:text class="mt-1 text-xs">
                                    {{ __('Submitted :date', ['date' => $application->created_at->format('M j, Y g:i A')]) }}
                                </flux:text>
                            </div>
                            <flux:badge :color="$application->status->color()">{{ $application->status->label() }}</flux:badge>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach ($application->documents as $document)
                                <flux:button
                                    wire:key="document-{{ $document->id }}"
                                    size="sm"
                                    icon="document-text"
                                    :href="route('documents.show', $document)"
                                    target="_blank"
                                >
                                    {{ $document->type->label() }}
                                </flux:button>
                            @endforeach
                        </div>

                        @if ($application->notes)
                            <flux:text><span class="font-medium">{{ __('Applicant notes:') }}</span> {{ $application->notes }}</flux:text>
                        @endif

                        @if ($application->isPending())
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="primary" wire:click="approve({{ $application->id }})" wire:confirm="{{ __('Approve this rental company?') }}">
                                    {{ __('Approve') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="startRejecting({{ $application->id }})">
                                    {{ __('Reject') }}
                                </flux:button>
                            </div>
                        @else
                            <flux:text class="text-xs">
                                {{ __('Reviewed by :name on :date', ['name' => $application->reviewer?->name ?? __('an administrator'), 'date' => $application->reviewed_at?->format('M j, Y')]) }}
                                @if ($application->rejection_reason)
                                    &middot; {{ $application->rejection_reason }}
                                @endif
                            </flux:text>
                        @endif
                    </flux:card>
                @endforeach
            </div>

            {{ $this->applications->links() }}
        @endif

        <flux:modal name="reject-application" class="md:w-md">
            <form wire:submit="reject" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Reject application') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('The applicant will see this reason and can resubmit.') }}</flux:text>
                </div>

                <flux:textarea wire:model="rejectionReason" :label="__('Reason')" rows="4" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Reject') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </x-app.content>
</div>
