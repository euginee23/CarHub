<?php

use App\Actions\Bookings\CancelBooking;
use App\Actions\Reviews\SubmitReview;
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

    public int $rating = 0;

    public string $comment = '';

    /**
     * Load the renter's booking.
     */
    public function mount(Booking $booking): void
    {
        Gate::authorize('checkout', $booking);

        $this->booking = $booking->load(['vehicle.coverPhoto', 'owner', 'statusChanges.actor', 'contract', 'successfulPayment', 'latestPayment', 'review.reviewer']);
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

    /**
     * Rate the completed rental.
     */
    public function submitReview(SubmitReview $submitReview): void
    {
        Gate::authorize('review', $this->booking);

        $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], ['rating.between' => __('Choose between 1 and 5 stars.')]);

        $submitReview->handle($this->booking, Auth::user(), $this->rating, filled($this->comment) ? $this->comment : null);

        $this->booking->load('review.reviewer');
        $this->reset(['rating', 'comment']);

        Flux::toast(variant: 'success', text: __('Thanks for your review!'));
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
            @case(BookingStatus::Confirmed)
                <flux:callout variant="success" icon="check-badge" :heading="__('Your booking is confirmed')">
                    <flux:callout.text>{{ __('Pick up the :vehicle on :date at the pin below. Bring the two IDs you verified.', ['vehicle' => $booking->vehicle->name, 'date' => $booking->pickup_at->format('D, M j, g:i A')]) }}</flux:callout.text>
                </flux:callout>
                @break
            @case(BookingStatus::Ongoing)
                <flux:callout icon="map-pin" :heading="__('You are on your trip')">
                    <flux:callout.text>{{ __('Return the :vehicle by :date with the same fuel level.', ['vehicle' => $booking->vehicle->name, 'date' => $booking->return_at->format('D, M j, g:i A')]) }}</flux:callout.text>
                    <x-slot name="actions">
                        <flux:button :href="route('bookings.tracking', $booking)" wire:navigate>{{ __('Full-screen map') }}</flux:button>
                    </x-slot>
                </flux:callout>
                @break
            @case(BookingStatus::Completed)
                <flux:callout variant="success" icon="check-circle" :heading="__('Trip complete')">
                    <flux:callout.text>{{ __('Thanks for renting with CarHub.') }}</flux:callout.text>
                </flux:callout>
                @break
            @case(BookingStatus::Expired)
                <flux:callout variant="danger" icon="clock" :heading="__('This booking expired')">
                    <flux:callout.text>{{ $booking->statusChanges->last()?->note }}</flux:callout.text>
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
                <x-booking.progress :booking="$booking" />

                @if ($booking->status === BookingStatus::Ongoing)
                    <flux:card class="space-y-4">
                        <flux:heading size="lg">{{ __('Live map') }}</flux:heading>
                        <livewire:tracking.live-map :booking="$booking" :compact="true" />
                    </flux:card>
                @endif

                <x-booking.summary :booking="$booking" />

                <x-booking.handover :booking="$booking" />

                @if ($booking->review)
                    <x-booking.review :review="$booking->review" />
                @elseif (auth()->user()->can('review', $booking))
                    <flux:card class="space-y-4">
                        <div>
                            <flux:heading size="lg">{{ __('Rate your trip') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Your rating helps other renters choose and helps owners improve.') }}</flux:text>
                        </div>
                        <form wire:submit="submitReview" class="space-y-4">
                            <div class="flex items-center gap-1" role="radiogroup" aria-label="{{ __('Rating') }}">
                                @for ($star = 1; $star <= 5; $star++)
                                    <button
                                        type="button"
                                        wire:click="$set('rating', {{ $star }})"
                                        role="radio"
                                        aria-checked="{{ $rating === $star ? 'true' : 'false' }}"
                                        aria-label="{{ trans_choice('{1} :count star|[2,*] :count stars', $star, ['count' => $star]) }}"
                                        class="rounded p-0.5 transition hover:scale-110"
                                    >
                                        <svg @class(['size-8', 'text-amber-400' => $star <= $rating, 'text-zinc-300' => $star > $rating]) viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M12 2.5 15 9l7 .8-5.2 4.7 1.5 6.9L12 17.9 5.7 21.4l1.5-6.9L2 9.8 9 9l3-6.5Z" />
                                        </svg>
                                    </button>
                                @endfor
                            </div>
                            <flux:error name="rating" />
                            <flux:textarea wire:model="comment" :label="__('Feedback (optional)')" rows="3" :placeholder="__('How was the vehicle and the handover?')" />
                            <flux:button type="submit" variant="primary" data-test="submit-review">{{ __('Submit review') }}</flux:button>
                        </form>
                    </flux:card>
                @endif

                @if (in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Ongoing], true) && $booking->vehicle->latitude !== null)
                    <flux:card class="space-y-3">
                        <flux:heading>{{ __('Pickup point') }}</flux:heading>
                        <flux:text>{{ $booking->pickup_location }}</flux:text>
                        <div wire:ignore x-data="pickupAreaMap({ latitude: {{ $booking->vehicle->latitude }}, longitude: {{ $booking->vehicle->longitude }}, exact: true })">
                            <div x-ref="map" class="z-0 h-64 w-full overflow-hidden rounded-xl border border-zinc-200"></div>
                        </div>
                        <flux:link :href="'https://www.openstreetmap.org/?mlat='.$booking->vehicle->latitude.'&mlon='.$booking->vehicle->longitude.'#map=17/'.$booking->vehicle->latitude.'/'.$booking->vehicle->longitude" target="_blank" class="text-sm">
                            {{ __('Open in maps') }}
                        </flux:link>
                    </flux:card>
                @endif

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

                <x-booking.payment :booking="$booking" />

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
