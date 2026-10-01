<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\VehicleLocation;
use App\Support\Geo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    /**
     * The most points sent to the map; longer trips are thinned out evenly.
     */
    public const int MAX_POINTS = 1500;

    #[Locked]
    public Booking $booking;

    /**
     * A smaller map with a link to the full tracking page, for booking pages.
     */
    #[Locked]
    public bool $compact = false;

    /**
     * Only people allowed to track the rental get a map.
     */
    public function mount(): void
    {
        Gate::authorize('track', $this->booking);

        $this->booking->loadMissing(['vehicle.gpsDevice']);
    }

    /**
     * Every fix recorded during the rental, oldest first.
     *
     * @return Collection<int, VehicleLocation>
     */
    #[Computed]
    public function locations(): Collection
    {
        return $this->booking->locations()->get(['latitude', 'longitude', 'speed_kph', 'recorded_at']);
    }

    /**
     * The route for the map as [lat, lng] pairs, thinned out for long trips.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    #[Computed]
    public function points(): array
    {
        $locations = $this->locations;
        $step = max(1, (int) ceil($locations->count() / self::MAX_POINTS));

        return $locations
            ->filter(fn (VehicleLocation $location, int $index) => $index % $step === 0 || $index === $locations->count() - 1)
            ->map(fn (VehicleLocation $location) => [$location->latitude, $location->longitude])
            ->values()
            ->all();
    }

    /**
     * The most recent fix, for the "here now" marker and the stats.
     *
     * @return array{lat: float, lng: float, speed: float|null, at: string, label: string}|null
     */
    #[Computed]
    public function latest(): ?array
    {
        $location = $this->locations->last();

        if ($location === null) {
            return null;
        }

        return [
            'lat' => $location->latitude,
            'lng' => $location->longitude,
            'speed' => $location->speed_kph,
            'at' => $location->recorded_at->diffForHumans(),
            'label' => __('Here at :time', ['time' => $location->recorded_at->format('g:i A')]),
        ];
    }

    /**
     * Where the trip started, shown until the tracker reports a position.
     *
     * @return array{lat: float, lng: float, label: string}|null
     */
    #[Computed]
    public function origin(): ?array
    {
        $vehicle = $this->booking->vehicle;

        if ($vehicle->latitude === null || $vehicle->longitude === null) {
            return null;
        }

        return ['lat' => $vehicle->latitude, 'lng' => $vehicle->longitude, 'label' => __('Pickup point')];
    }

    /**
     * Distance travelled along the recorded route, in kilometres.
     */
    #[Computed]
    public function distanceKm(): float
    {
        return $this->locations->sliding(2)->sum(fn (Collection $pair) => Geo::distanceInKm(
            $pair->first()->latitude,
            $pair->first()->longitude,
            $pair->last()->latitude,
            $pair->last()->longitude,
        ));
    }

    /**
     * Whether the vehicle is still out, so the map keeps updating.
     */
    #[Computed]
    public function isLive(): bool
    {
        return $this->booking->status === BookingStatus::Ongoing;
    }

    /**
     * Polled while the trip is live: push the latest route to this map.
     */
    public function refreshTracking(): void
    {
        $this->booking->refresh()->load('vehicle.gpsDevice');
        unset($this->locations, $this->points, $this->latest, $this->distanceKm, $this->isLive);

        $this->dispatch('tracking-updated', booking: $this->booking->reference, points: $this->points, latest: $this->latest);
    }
}; ?>

<div class="space-y-4" @if ($this->isLive) wire:poll.10s="refreshTracking" @endif>
    <div @class(['grid gap-3', 'grid-cols-2 lg:grid-cols-4' => ! $compact, 'grid-cols-3' => $compact])>
        @unless ($compact)
            <x-dashboard.stat :label="__('Status')" :value="$this->isLive ? __('On the road') : __('Returned')" />
        @endunless
        <x-dashboard.stat :label="__('Last update')" :value="$this->latest['at'] ?? __('Waiting')" :size="$compact ? 'sm' : 'lg'" />
        <x-dashboard.stat :label="__('Speed')" :value="isset($this->latest['speed']) ? number_format($this->latest['speed']).' km/h' : '—'" :size="$compact ? 'sm' : 'lg'" />
        <x-dashboard.stat :label="__('Distance')" :value="number_format($this->distanceKm, 1).' km'" :size="$compact ? 'sm' : 'lg'" />
    </div>

    @if (! $booking->vehicle->gpsDevice)
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('No GPS tracker is connected to this vehicle.')">
            <flux:callout.text>{{ __('The owner can connect one from the vehicle\'s page under My vehicles.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->isLive && $this->latest === null)
        <flux:callout icon="signal" :heading="__('Waiting for the tracker\'s first position.')">
            <flux:callout.text>{{ __('The map is centred on the pickup point. The route will appear as soon as the tracker reports in.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->isLive && ! $booking->vehicle->gpsDevice->isOnline())
        <flux:callout icon="signal-slash" :heading="__('The tracker has not reported in the last 5 minutes.')">
            <flux:callout.text>{{ __('It may be out of signal. Buffered positions will appear when it reconnects.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div
        wire:ignore
        x-data="trackingMap({ points: @js($this->points), latest: @js($this->latest), origin: @js($this->origin) })"
        x-on:tracking-updated.window="$event.detail.booking === @js($booking->reference) && update($event.detail)"
        class="overflow-hidden rounded-2xl border border-zinc-200 bg-white"
    >
        <div x-ref="map" @class(['z-0 w-full', 'h-[32rem]' => ! $compact, 'h-80' => $compact])></div>
    </div>

    @if ($compact)
        <div class="flex justify-end">
            <flux:link :href="route('bookings.tracking', $booking)" wire:navigate class="text-sm">{{ __('Open full map') }} &rarr;</flux:link>
        </div>
    @endif
</div>
