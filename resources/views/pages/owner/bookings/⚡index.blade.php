<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Booking requests')] class extends Component {
    #[Url(except: 'requests')]
    public string $tab = 'requests';

    /**
     * The statuses shown on each tab.
     *
     * @return array<string, array{label: string, statuses: array<int, BookingStatus>}>
     */
    #[Computed]
    public function tabs(): array
    {
        return [
            'requests' => ['label' => __('Requests'), 'statuses' => [BookingStatus::Requested]],
            'upcoming' => ['label' => __('Upcoming'), 'statuses' => [BookingStatus::Approved, BookingStatus::AwaitingPayment, BookingStatus::Confirmed]],
            'active' => ['label' => __('On trip'), 'statuses' => [BookingStatus::Ongoing]],
            'past' => ['label' => __('Completed'), 'statuses' => [BookingStatus::Completed]],
            'closed' => ['label' => __('Closed'), 'statuses' => [BookingStatus::Declined, BookingStatus::Cancelled, BookingStatus::Expired]],
        ];
    }

    /**
     * The number of requests waiting for an answer.
     */
    #[Computed]
    public function pendingCount(): int
    {
        return Auth::user()->ownerBookings()->where('status', BookingStatus::Requested)->count();
    }

    /**
     * Bookings on the owner's vehicles for the current tab.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function bookings(): Collection
    {
        return Auth::user()->ownerBookings()
            ->with(['vehicle.coverPhoto', 'renter'])
            ->whereIn('status', ($this->tabs[$this->tab] ?? $this->tabs['requests'])['statuses'])
            ->orderBy('pickup_at', in_array($this->tab, ['past', 'closed'], true) ? 'desc' : 'asc')
            ->get();
    }
}; ?>

<div>
    <x-app.page-header :title="__('Booking requests')" :description="__('Answer requests and follow each rental from approval to return.')" />

    <x-app.content class="space-y-6">
        <div class="flex flex-wrap gap-2">
            @foreach ($this->tabs as $key => $option)
                <flux:button wire:key="tab-{{ $key }}" size="sm" :variant="$tab === $key ? 'primary' : 'outline'" wire:click="$set('tab', '{{ $key }}')">
                    {{ $option['label'] }}
                    @if ($key === 'requests' && $this->pendingCount > 0)
                        <flux:badge size="sm" color="amber" class="ms-1">{{ $this->pendingCount }}</flux:badge>
                    @endif
                </flux:button>
            @endforeach
        </div>

        @if ($this->bookings->isEmpty())
            <flux:card class="text-center">
                <flux:text>{{ __('Nothing here right now.') }}</flux:text>
            </flux:card>
        @else
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white px-4 sm:px-6">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                        <flux:table.column>{{ __('Renter') }}</flux:table.column>
                        <flux:table.column>{{ __('Dates') }}</flux:table.column>
                        <flux:table.column>{{ __('Earnings') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->bookings as $booking)
                            <flux:table.row :key="$booking->id">
                                <flux:table.cell class="font-medium">{{ $booking->vehicle->name }}</flux:table.cell>
                                <flux:table.cell>{{ $booking->renter->name }}</flux:table.cell>
                                <flux:table.cell>{{ $booking->pickup_at->format('M j') }} &ndash; {{ $booking->return_at->format('M j') }}</flux:table.cell>
                                <flux:table.cell>&#8369;{{ number_format($booking->subtotal) }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" :color="$booking->status->color()">{{ $booking->status->label() }}</flux:badge></flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="sm" :href="route('owner.bookings.show', $booking)" wire:navigate>{{ $booking->status === BookingStatus::Requested ? __('Review') : __('View') }}</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </x-app.content>
</div>
