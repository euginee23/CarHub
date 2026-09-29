<?php

use App\Actions\Bookings\CancelBooking;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Trip details')] class extends Component {
    #[Locked]
    public Booking $booking;

    public string $cancellationReason = '';

    /**
     * Load the renter's booking.
     */
    public function mount(Booking $booking): void
    {
        Gate::authorize('checkout', $booking);

        $this->booking = $booking->load(['vehicle.coverPhoto', 'owner', 'statusChanges.actor', 'contract']);
    }

    /**
     * Cancel the booking.
     */
    public function cancel(CancelBooking $cancelBooking): void
    {
        Gate::authorize('cancel', $this->booking);

        $this->validate(['cancellationReason' => ['nullable', 'string', 'max:500']]);

        $cancelBooking->handle($this->booking, Auth::user(), filled($this->cancellationReason) ? $this->cancellationReason : null);

        $this->booking->load('statusChanges.actor');

        Flux::modal('cancel-booking')->close();
        Flux::toast(variant: 'success', text: __('Booking cancelled.'));
    }
}; ?>

<div>
    <x-app.page-header
        :title="$booking->vehicle->year.' '.$booking->vehicle->name"
        :description="__('Trip :reference', ['reference' => $booking->reference])"
        :back="route('trips.index')"
        :back-label="__('My trips')"
    />

    <x-app.content width="5xl" class="space-y-6">
        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
        @endif

        @switch($booking->status)
            @case(BookingStatus::Requested)
                <flux:callout icon="clock" :heading="__('Waiting for the owner')">
                    <flux:callout.text>{{ __('The owner has been notified. Meanwhile you can accept the rental terms and verify your IDs so you are ready once they approve.') }}</flux:callout.text>
                    <x-slot name="actions">
                        <flux:button :href="route('trips.checkout', $booking)" wire:navigate>{{ __('Get ready') }}</flux:button>
                    </x-slot>
                </flux:callout>
                @break
            @case(BookingStatus::Approved)
                <flux:callout variant="success" icon="check-circle" :heading="__('Approved — finish checkout')">
                    <flux:callout.text>{{ __('Accept the terms, verify two IDs, and sign the rental contract to continue to payment.') }}</flux:callout.text>
                    <x-slot name="actions">
                        <flux:button variant="primary" :href="route('trips.checkout', $booking)" wire:navigate>{{ __('Continue checkout') }}</flux:button>
                    </x-slot>
                </flux:callout>
                @break
            @case(BookingStatus::AwaitingPayment)
                <flux:callout icon="credit-card" :heading="__('Contract signed — payment is next')">
                    <flux:callout.text>{{ __('Your booking is held for you. Complete payment to confirm it.') }}</flux:callout.text>
                    <x-slot name="actions">
                        <flux:button variant="primary" :href="route('trips.checkout', $booking)" wire:navigate>{{ __('Go to payment') }}</flux:button>
                    </x-slot>
                </flux:callout>
                @break
            @case(BookingStatus::Declined)
                <flux:callout variant="danger" icon="x-circle" :heading="__('The owner declined this request')">
                    <flux:callout.text>{{ $booking->decline_reason }}</flux:callout.text>
                </flux:callout>
                @break
        @endswitch

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-6">
                <x-booking.summary :booking="$booking" />

                @if ($booking->contract)
                    <flux:card class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <flux:heading>{{ __('Rental contract :number', ['number' => $booking->contract->contract_number]) }}</flux:heading>
                            <flux:text class="mt-1">
                                {{ $booking->contract->isSigned() ? __('Signed :date', ['date' => $booking->contract->renter_signed_at->format('M j, Y g:i A')]) : __('Not signed yet') }}
                            </flux:text>
                        </div>
                        <flux:button :href="route('bookings.contract', $booking)" target="_blank" icon="document-text">{{ __('View contract') }}</flux:button>
                    </flux:card>
                @endif
            </div>

            <div class="space-y-6">
                <flux:card>
                    <flux:heading>{{ __('Owner') }}</flux:heading>
                    <flux:text class="mt-2">{{ $booking->owner->name }}</flux:text>
                    @if (in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Ongoing], true))
                        <flux:text>{{ $booking->owner->phone }}</flux:text>
                    @endif
                </flux:card>

                <x-booking.timeline :booking="$booking" />

                @can('cancel', $booking)
                    <flux:modal.trigger name="cancel-booking">
                        <flux:button variant="danger" class="w-full">{{ __('Cancel booking') }}</flux:button>
                    </flux:modal.trigger>
                @endcan
            </div>
        </div>

        <flux:modal name="cancel-booking" class="md:w-md">
            <form wire:submit="cancel" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Cancel this booking?') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('The owner will be told. Nothing has been charged yet.') }}</flux:text>
                </div>
                <flux:textarea wire:model="cancellationReason" :label="__('Reason (optional)')" rows="3" />
                <flux:error name="booking" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Keep booking') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Cancel booking') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </x-app.content>
</div>
