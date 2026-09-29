<?php

use App\Actions\Identity\ReviewIdentityDocument;
use App\Enums\DocumentStatus;
use App\Models\User;
use App\Models\VerificationDocument;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('ID reviews')] class extends Component {
    use WithPagination;

    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    /**
     * Renters' government IDs awaiting review, oldest first.
     *
     * @return LengthAwarePaginator<int, VerificationDocument>
     */
    #[Computed]
    public function documents(): LengthAwarePaginator
    {
        return VerificationDocument::query()
            ->with('user')
            ->where('documentable_type', (new User)->getMorphClass())
            ->where('status', DocumentStatus::Pending)
            ->oldest()
            ->paginate(15);
    }

    /**
     * Approve an ID.
     */
    public function approve(int $documentId, ReviewIdentityDocument $reviewIdentityDocument): void
    {
        $reviewIdentityDocument->approve($this->pendingDocument($documentId), Auth::user());

        unset($this->documents);

        Flux::toast(variant: 'success', text: __('ID approved.'));
    }

    /**
     * Open the rejection modal for an ID.
     */
    public function startRejecting(int $documentId): void
    {
        $this->rejectingId = $documentId;
        $this->rejectionReason = '';

        Flux::modal('reject-id')->show();
    }

    /**
     * Reject the ID currently open in the modal.
     */
    public function reject(ReviewIdentityDocument $reviewIdentityDocument): void
    {
        $this->validate(['rejectionReason' => ['required', 'string', 'min:5', 'max:500']]);

        $reviewIdentityDocument->reject($this->pendingDocument((int) $this->rejectingId), Auth::user(), $this->rejectionReason);

        $this->reset(['rejectingId', 'rejectionReason']);
        unset($this->documents);

        Flux::modal('reject-id')->close();
        Flux::toast(variant: 'success', text: __('ID rejected. The renter has been notified.'));
    }

    /**
     * Find a renter ID that is still awaiting review.
     */
    protected function pendingDocument(int $documentId): VerificationDocument
    {
        return VerificationDocument::query()
            ->where('documentable_type', (new User)->getMorphClass())
            ->where('status', DocumentStatus::Pending)
            ->findOrFail($documentId);
    }
}; ?>

<div>
    <x-app.page-header :title="__('ID reviews')" :description="__('Check that each ID is genuine, readable, current, and matches the renter\'s name.')" />

    <x-app.content class="space-y-6">
        @if ($this->documents->isEmpty())
            <flux:card>
                <flux:text>{{ __('No IDs are waiting for review.') }}</flux:text>
            </flux:card>
        @else
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white px-4 sm:px-6">
                <flux:table :paginate="$this->documents">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Renter') }}</flux:table.column>
                        <flux:table.column>{{ __('ID type') }}</flux:table.column>
                        <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->documents as $document)
                            <flux:table.row :key="$document->id">
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $document->user->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $document->user->email }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:link :href="route('documents.show', $document)" target="_blank">{{ $document->type->label() }}</flux:link>
                                </flux:table.cell>
                                <flux:table.cell>{{ $document->created_at->diffForHumans() }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="primary" wire:click="approve({{ $document->id }})">{{ __('Approve') }}</flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="startRejecting({{ $document->id }})">{{ __('Reject') }}</flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif

        <flux:modal name="reject-id" class="md:w-md">
            <form wire:submit="reject" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Reject ID') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Tell the renter what was wrong so they can upload a replacement.') }}</flux:text>
                </div>
                <flux:textarea wire:model="rejectionReason" :label="__('Reason')" rows="3" :placeholder="__('e.g. The photo is blurry, or the ID has expired.')" />
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
