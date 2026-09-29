<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('My trips')] class extends Component {
    #[Url(except: 'upcoming')]
    public string $tab = 'upcoming';

    /**
     * The statuses shown on each tab.
     *
     * @return array<string, array<int, BookingStatus>>
     */
    #[Computed]
    public function tabs(): array
    {
        return [
            'upcoming' => [BookingStatus::Requested, BookingStatus::Approved, BookingStatus::AwaitingPayment, BookingStatus::Confirmed, BookingStatus::Ongoing],
            'past' => [BookingStatus::Completed],
            'cancelled' => [BookingStatus::Declined, BookingStatus::Cancelled, BookingStatus::Expired],
        ];
    }

    /**
     * The renter's bookings on the current tab.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function bookings(): Collection
    {
        return Auth::user()->bookings()
            ->with(['vehicle.coverPhoto'])
            ->whereIn('status', $this->tabs[$this->tab] ?? $this->tabs['upcoming'])
            ->orderBy('pickup_at', $this->tab === 'upcoming' ? 'asc' : 'desc')
            ->get();
    }
}; ?>

<div>
    <x-app.page-header :title="__('My trips')" :description="__('Your booking requests and rentals.')">
        <x-slot:actions>
            <flux:button :href="route('vehicles.index')" icon="magnifying-glass">{{ __('Find a vehicle') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <x-app.content class="space-y-6">
        <div class="flex flex-wrap gap-2">
            @foreach (['upcoming' => __('Upcoming'), 'past' => __('Past'), 'cancelled' => __('Cancelled')] as $key => $label)
                <flux:button wire:key="tab-{{ $key }}" size="sm" :variant="$tab === $key ? 'primary' : 'outline'" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</flux:button>
            @endforeach
        </div>

        @forelse ($this->bookings as $booking)
            <a wire:key="booking-{{ $booking->id }}" href="{{ route('trips.show', $booking) }}" wire:navigate class="block">
                <flux:card class="flex flex-wrap items-center gap-4 transition hover:border-zinc-300 dark:hover:border-zinc-500">
                    <x-marketing.vehicle-image :vehicle="$booking->vehicle" :caption="false" class="aspect-[4/3] !w-20 shrink-0 rounded-lg" />
                    <div class="min-w-0 flex-1">
                        <flux:heading>{{ $booking->vehicle->year }} {{ $booking->vehicle->name }}</flux:heading>
                        <flux:text class="mt-1">{{ $booking->pickup_at->format('M j, g:i A') }} &rarr; {{ $booking->return_at->format('M j, g:i A') }}</flux:text>
                    </div>
                    <div class="text-end">
                        <flux:badge size="sm" :color="$booking->status->color()">{{ $booking->status->label() }}</flux:badge>
                        <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">&#8369;{{ number_format($booking->total) }}</p>
                    </div>
                </flux:card>
            </a>
        @empty
            <flux:card class="text-center">
                <flux:heading>{{ __('No trips here yet') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Find a vehicle and request your dates — your bookings will show up here.') }}</flux:text>
            </flux:card>
        @endforelse
    </x-app.content>
</div>
