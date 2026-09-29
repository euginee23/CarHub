<?php

use App\Enums\BookingStatus;
use App\Enums\VehicleStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Owner overview')] class extends Component {
    /**
     * The signed-in owner.
     */
    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    /**
     * Headline numbers for the owner's fleet and bookings.
     *
     * @return array{listed: int, vehicles: int, pending: int, upcoming: int, earned: int, earnedThisMonth: int}
     */
    #[Computed]
    public function stats(): array
    {
        $earning = [BookingStatus::Confirmed, BookingStatus::Ongoing, BookingStatus::Completed];

        return [
            'listed' => $this->user->vehicles()->where('status', VehicleStatus::Listed)->count(),
            'vehicles' => $this->user->vehicles()->count(),
            'pending' => $this->user->ownerBookings()->where('status', BookingStatus::Requested)->count(),
            'upcoming' => $this->user->ownerBookings()->whereIn('status', [BookingStatus::Approved, BookingStatus::AwaitingPayment, BookingStatus::Confirmed])->count(),
            'earned' => (int) $this->user->ownerBookings()->whereIn('status', $earning)->sum('subtotal'),
            'earnedThisMonth' => (int) $this->user->ownerBookings()
                ->whereIn('status', $earning)
                ->whereBetween('pickup_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('subtotal'),
        ];
    }

    /**
     * Requests waiting for the owner's answer, soonest pickup first.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function pendingRequests(): Collection
    {
        return $this->user->ownerBookings()
            ->with(['vehicle', 'renter'])
            ->where('status', BookingStatus::Requested)
            ->orderBy('pickup_at')
            ->take(5)
            ->get();
    }

    /**
     * Upcoming handovers and vehicles currently out on a trip.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function schedule(): Collection
    {
        return $this->user->ownerBookings()
            ->with(['vehicle', 'renter'])
            ->whereIn('status', [BookingStatus::Approved, BookingStatus::AwaitingPayment, BookingStatus::Confirmed, BookingStatus::Ongoing])
            ->orderBy('pickup_at')
            ->take(5)
            ->get();
    }

    /**
     * The owner's vehicles with how many active bookings each has.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function fleet(): Collection
    {
        return $this->user->vehicles()
            ->with('coverPhoto')
            ->withCount(['bookings as active_bookings_count' => fn ($query) => $query->whereIn('status', BookingStatus::holding())])
            ->orderByRaw('case when status = ? then 0 else 1 end', [VehicleStatus::Listed->value])
            ->latest()
            ->get();
    }
}; ?>

<div>
    <x-dashboard.greeting :subtitle="__('Your fleet, requests, and earnings at a glance.')">
        <x-slot:actions>
            <flux:button :href="route('owner.vehicles.create')" variant="primary" icon="plus" wire:navigate>{{ __('List a vehicle') }}</flux:button>
        </x-slot:actions>
    </x-dashboard.greeting>

    <x-app.content class="space-y-8">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-dashboard.stat
                :label="__('Requests to answer')"
                :value="$this->stats['pending']"
                :href="route('owner.bookings.index')"
                :tone="$this->stats['pending'] > 0 ? 'attention' : 'default'"
            />
            <x-dashboard.stat :label="__('Upcoming rentals')" :value="$this->stats['upcoming']" :href="route('owner.bookings.index', ['tab' => 'upcoming'])" />
            <x-dashboard.stat :label="__('Listed vehicles')" :value="$this->stats['listed']" :hint="__(':count in your fleet', ['count' => $this->stats['vehicles']])" :href="route('owner.vehicles.index')" />
            <x-dashboard.stat
                :label="__('Earnings this month')"
                :value="'₱'.number_format($this->stats['earnedThisMonth'])"
                :hint="__('₱:total all time, before fees', ['total' => number_format($this->stats['earned'])])"
            />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-dashboard.panel :title="__('Needs your answer')" :href="route('owner.bookings.index')">
                @forelse ($this->pendingRequests as $booking)
                    <a wire:key="request-{{ $booking->id }}" href="{{ route('owner.bookings.show', $booking) }}" wire:navigate class="-mx-2 flex items-center justify-between gap-4 rounded-xl px-2 py-3 transition hover:bg-zinc-50">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-zinc-900">{{ $booking->renter->name }} &middot; {{ $booking->vehicle->name }}</p>
                            <p class="text-sm text-zinc-500">{{ $booking->pickup_at->format('M j') }} &ndash; {{ $booking->return_at->format('M j') }} &middot; &#8369;{{ number_format($booking->subtotal) }}</p>
                        </div>
                        <span class="shrink-0 text-sm font-semibold text-brand-600">{{ __('Review') }} &rarr;</span>
                    </a>
                @empty
                    <p class="py-4 text-center text-sm text-zinc-500">{{ __('You are all caught up.') }}</p>
                @endforelse
            </x-dashboard.panel>

            <x-dashboard.panel :title="__('Coming up')" :description="__('Approved, confirmed, and ongoing rentals')" :href="route('owner.bookings.index', ['tab' => 'upcoming'])">
                @forelse ($this->schedule as $booking)
                    <a wire:key="schedule-{{ $booking->id }}" href="{{ route('owner.bookings.show', $booking) }}" wire:navigate class="-mx-2 flex items-center justify-between gap-4 rounded-xl px-2 py-3 transition hover:bg-zinc-50">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-zinc-900">{{ $booking->vehicle->name }}</p>
                            <p class="text-sm text-zinc-500">{{ $booking->renter->name }} &middot; {{ $booking->pickup_at->format('D, M j · g:i A') }}</p>
                        </div>
                        <flux:badge size="sm" :color="$booking->status->color()">{{ $booking->status->label() }}</flux:badge>
                    </a>
                @empty
                    <p class="py-4 text-center text-sm text-zinc-500">{{ __('No upcoming rentals yet.') }}</p>
                @endforelse
            </x-dashboard.panel>
        </div>

        <x-dashboard.panel :title="__('Your fleet')" :href="route('owner.vehicles.index')" :link-label="__('Manage vehicles')">
            @if ($this->fleet->isEmpty())
                <div class="py-6 text-center">
                    <p class="font-medium text-zinc-900">{{ __('No vehicles yet') }}</p>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('List your first vehicle to start receiving booking requests.') }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($this->fleet as $vehicle)
                        <a wire:key="fleet-{{ $vehicle->id }}" href="{{ route('owner.vehicles.edit', $vehicle) }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-brand-200">
                            <x-marketing.vehicle-image :vehicle="$vehicle" :caption="false" class="aspect-[4/3] !w-16 shrink-0 rounded-lg" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-zinc-900">{{ $vehicle->name }}</p>
                                <p class="text-xs text-zinc-500">&#8369;{{ number_format($vehicle->price_per_day) }}/{{ __('day') }} &middot; {{ trans_choice('{0} no active bookings|{1} :count active booking|[2,*] :count active bookings', $vehicle->active_bookings_count, ['count' => $vehicle->active_bookings_count]) }}</p>
                            </div>
                            <flux:badge size="sm" :color="$vehicle->status->color()">{{ $vehicle->status->label() }}</flux:badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-dashboard.panel>
    </x-app.content>
</div>
