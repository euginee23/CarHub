<?php

use App\Actions\Bookings\ApproveBooking;
use App\Actions\Bookings\DeclineBooking;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Booking')] class extends Component {
    #[Locked]
    public Booking $booking;

    public string $declineReason = '';

    /**
     * Load a booking on one of the owner's vehicles.
     */
    public function mount(Booking $booking): void
    {
        Gate::authorize('manage', $booking);

        $this->booking = $booking;
        $this->loadRelations();
    }

    /**
     * Other requests that would be declined automatically if this one is approved.
     */
    #[Computed]
    public function competingRequests(): int
    {
        if ($this->booking->status !== BookingStatus::Requested) {
            return 0;
        }

        return Booking::whereBelongsTo($this->booking->vehicle)
            ->whereKeyNot($this->booking->id)
            ->where('status', BookingStatus::Requested)
            ->overlapping($this->booking->pickup_at, $this->booking->return_at)
            ->count();
    }

    /**
     * Accept the request.
     */
    public function approve(ApproveBooking $approveBooking): void
    {
        Gate::authorize('manage', $this->booking);

        $approveBooking->handle($this->booking, Auth::user());

        $this->loadRelations();
        unset($this->competingRequests);

        Flux::toast(variant: 'success', text: __('Request approved. The renter can now complete checkout.'));
    }

    /**
     * Turn down the request.
     */
    public function decline(DeclineBooking $declineBooking): void
    {
        Gate::authorize('manage', $this->booking);

        $this->validate(['declineReason' => ['required', 'string', 'min:5', 'max:500']]);

        $declineBooking->handle($this->booking, Auth::user(), $this->declineReason);

        $this->reset('declineReason');
        $this->loadRelations();

        Flux::modal('decline-booking')->close();
        Flux::toast(variant: 'success', text: __('Request declined.'));
    }

    /**
     * Refresh the booking and everything shown alongside it.
     */
    protected function loadRelations(): void
    {
        $this->booking->refresh()->load(['vehicle.coverPhoto', 'renter', 'statusChanges.actor', 'contract']);
    }
}; ?>

<div class="mx-auto w-full max-w-4xl space-y-6">
    <div>
        <flux:link :href="route('owner.bookings.index')" wire:navigate class="text-sm">&larr; {{ __('Booking requests') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ __('Booking :reference', ['reference' => $booking->reference]) }}</flux:heading>
    </div>

    <flux:error name="booking" />

    @if ($booking->status === BookingStatus::Requested)
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __(':name wants to rent your :vehicle', ['name' => $booking->renter->name, 'vehicle' => $booking->vehicle->name]) }}</flux:heading>
            @if ($booking->renter_notes)
                <flux:text>&ldquo;{{ $booking->renter_notes }}&rdquo;</flux:text>
            @endif
            @if ($this->competingRequests > 0)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.text>{{ trans_choice('{1} Approving will decline :count other request for overlapping dates.|[2,*] Approving will decline :count other requests for overlapping dates.', $this->competingRequests, ['count' => $this->competingRequests]) }}</flux:callout.text>
                </flux:callout>
            @endif
            <div class="flex gap-2">
                <flux:button variant="primary" wire:click="approve" data-test="approve-booking">{{ __('Approve') }}</flux:button>
                <flux:modal.trigger name="decline-booking">
                    <flux:button variant="danger">{{ __('Decline') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </flux:card>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            <x-booking.summary :booking="$booking" />

            @if ($booking->contract)
                <flux:card class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <flux:heading>{{ __('Rental contract :number', ['number' => $booking->contract->contract_number]) }}</flux:heading>
                        <flux:text class="mt-1">{{ $booking->contract->isSigned() ? __('Signed by the renter') : __('Waiting for the renter to sign') }}</flux:text>
                    </div>
                    <flux:button :href="route('bookings.contract', $booking)" target="_blank" icon="document-text">{{ __('View contract') }}</flux:button>
                </flux:card>
            @endif
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-2">
                <flux:heading>{{ __('Renter') }}</flux:heading>
                <flux:text>{{ $booking->renter->name }}</flux:text>
                @if ($booking->renter->hasVerifiedIdentity())
                    <flux:badge size="sm" color="green" icon="check-badge">{{ __('Two IDs verified') }}</flux:badge>
                @else
                    <flux:badge size="sm" color="zinc">{{ __('IDs not yet verified') }}</flux:badge>
                @endif
                @if (in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Ongoing], true))
                    <flux:text>{{ $booking->renter->phone }}</flux:text>
                @endif
            </flux:card>

            <x-booking.timeline :booking="$booking" />
        </div>
    </div>

    <flux:modal name="decline-booking" class="md:w-md">
        <form wire:submit="decline" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Decline this request?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The renter will see your reason.') }}</flux:text>
            </div>
            <flux:textarea wire:model="declineReason" :label="__('Reason')" rows="3" :placeholder="__('e.g. The vehicle is in for repairs that week.')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Decline') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
