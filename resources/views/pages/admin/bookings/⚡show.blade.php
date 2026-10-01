<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Booking')] class extends Component {
    #[Locked]
    public Booking $booking;

    /**
     * Load everything known about the booking.
     */
    public function mount(Booking $booking): void
    {
        $this->booking = $booking->load([
            'vehicle.coverPhoto', 'vehicle.gpsDevice', 'renter', 'owner', 'statusChanges.actor',
            'contract', 'payments', 'successfulPayment', 'latestPayment', 'review.reviewer',
        ]);
    }
}; ?>

<div>
    <x-app.page-header
        :title="$booking->vehicle->year.' '.$booking->vehicle->name"
        :description="__('Booking :reference', ['reference' => $booking->reference])"
        :back="route('admin.bookings.index')"
        :back-label="__('Bookings')"
    >
        <x-slot:actions>
            @if ($booking->contract)
                <flux:button :href="route('bookings.contract', $booking)" target="_blank" icon="document-text">{{ __('Contract') }}</flux:button>
            @endif
            @if (in_array($booking->status, [BookingStatus::Ongoing, BookingStatus::Completed], true))
                <flux:button :href="route('bookings.tracking', $booking)" icon="map-pin" wire:navigate>{{ __('Tracking') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-app.page-header>

    <x-app.content class="space-y-6">
        <x-booking.progress :booking="$booking" />

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                <x-booking.summary :booking="$booking" />
                <x-booking.handover :booking="$booking" />

                @if ($booking->payments->isNotEmpty())
                    <flux:card class="space-y-3">
                        <flux:heading>{{ __('Payment attempts') }}</flux:heading>
                        <ul class="divide-y divide-zinc-100 text-sm">
                            @foreach ($booking->payments->sortByDesc('created_at') as $payment)
                                <li wire:key="payment-{{ $payment->id }}" class="flex flex-wrap items-center justify-between gap-3 py-2">
                                    <span>
                                        <span class="font-medium text-zinc-900">{{ $payment->reference }}</span>
                                        <span class="text-zinc-500">&middot; {{ $payment->method->label() }} &middot; {{ $payment->provider }} &middot; {{ $payment->created_at?->format('M j, g:i A') }}</span>
                                        @if ($payment->failure_reason)
                                            <span class="block text-xs text-red-600">{{ $payment->failure_reason }}</span>
                                        @endif
                                    </span>
                                    <flux:badge size="sm" :color="$payment->status->color()">{{ $payment->status->label() }}</flux:badge>
                                </li>
                            @endforeach
                        </ul>
                    </flux:card>
                @endif

                @if ($booking->review)
                    <x-booking.review :review="$booking->review" />
                @endif
            </div>

            <div class="space-y-6">
                <flux:card class="space-y-3 text-sm">
                    <div>
                        <flux:heading>{{ __('Renter') }}</flux:heading>
                        <p class="mt-1 text-zinc-900">{{ $booking->renter->name }}</p>
                        <p class="text-zinc-500">{{ $booking->renter->email }} &middot; {{ $booking->renter->phone }}</p>
                        <p class="mt-1 text-xs {{ $booking->renter->hasVerifiedIdentity() ? 'text-emerald-700' : 'text-zinc-500' }}">
                            {{ $booking->renter->hasVerifiedIdentity() ? __('Two IDs verified') : __('IDs not verified') }}
                        </p>
                    </div>
                    <div class="border-t border-zinc-100 pt-3">
                        <flux:heading>{{ __('Owner') }}</flux:heading>
                        <p class="mt-1 text-zinc-900">{{ $booking->owner->name }}</p>
                        <p class="text-zinc-500">{{ $booking->owner->email }} &middot; {{ $booking->owner->phone }}</p>
                    </div>
                </flux:card>

                <x-booking.timeline :booking="$booking" />
            </div>
        </div>
    </x-app.content>
</div>
