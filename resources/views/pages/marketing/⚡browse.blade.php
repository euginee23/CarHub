<?php

use App\Enums\Transmission;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use App\Services\Matching\VehicleSimilarity;
use App\Support\CompareList;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts::marketing')]
#[Title('Browse vehicles')]
class extends Component {
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $transmission = '';

    #[Url(except: '')]
    public string $seats = '';

    #[Url(except: '')]
    public string $maxPrice = '';

    #[Url(except: 'recommended')]
    public string $sort = 'recommended';

    /**
     * The trip window, as Y-m-d dates. When both are set, only vehicles free for
     * every day of the trip are shown.
     */
    #[Url(except: '')]
    public string $pickup = '';

    #[Url(except: '')]
    public string $return = '';

    /**
     * The search origin: the renter's GPS position, or the centre of a chosen area.
     */
    #[Url(except: '')]
    public string $lat = '';

    #[Url(except: '')]
    public string $lng = '';

    #[Url(except: '')]
    public string $area = '';

    /**
     * Maximum distance from the origin, in kilometres. Empty means no limit.
     */
    #[Url(except: '')]
    public string $radius = '';

    #[Url(except: 'list')]
    public string $view = 'list';

    /**
     * The number of vehicles listed on the marketplace, before any filtering.
     */
    #[Computed]
    public function listedCount(): int
    {
        return Vehicle::listed()->count();
    }

    /**
     * The vehicles matching the current filters, in the requested order. When a
     * search origin is set, each carries its `distance_km` from that origin.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): Collection
    {
        $origin = $this->origin;
        $trip = $this->trip;

        $query = Vehicle::listed()
            ->with('coverPhoto')
            ->when(filled($this->search), fn (Builder $query) => $query->search(trim($this->search)))
            ->when(VehicleType::tryFrom($this->type), fn (Builder $query, VehicleType $type) => $query->where('type', $type))
            ->when(Transmission::tryFrom($this->transmission), fn (Builder $query, Transmission $transmission) => $query->where('transmission', $transmission))
            ->when(filled($this->seats), fn (Builder $query) => $query->seatsAtLeast((int) $this->seats))
            ->when(filled($this->maxPrice), fn (Builder $query) => $query->maxPrice((int) $this->maxPrice))
            ->when($trip, fn (Builder $query) => $query->availableBetween($trip['pickup'], $trip['return']))
            ->when($origin && $this->radiusKm, fn (Builder $query) => $query->near($origin['lat'], $origin['lng'], (float) $this->radiusKm));

        match ($this->sort) {
            'price-asc' => $query->orderBy('price_per_day'),
            'price-desc' => $query->orderByDesc('price_per_day'),
            'rating' => $query->orderByDesc('rating'),
            default => $query->orderByDesc('featured')->orderByDesc('rating'),
        };

        $vehicles = $query->orderBy('id')->get();

        if (! $origin) {
            return $vehicles;
        }

        $vehicles->each(fn (Vehicle $vehicle) => $vehicle->setAttribute('distance_km', $vehicle->distanceFrom($origin['lat'], $origin['lng'])));

        if ($this->radiusKm) {
            $vehicles = $vehicles->filter(fn (Vehicle $vehicle) => $vehicle->distance_km !== null && $vehicle->distance_km <= $this->radiusKm);
        }

        return $this->sort === 'nearest'
            ? $vehicles->sortBy(fn (Vehicle $vehicle) => $vehicle->distance_km ?? PHP_FLOAT_MAX)->values()
            : $vehicles->values();
    }

    /**
     * Vehicles outside the results whose characteristics are closest to what the
     * renter filtered for — content-based filtering over the listed catalogue.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function closeMatches(): Collection
    {
        return app(VehicleSimilarity::class)->matchingPreferences(
            [
                'type' => VehicleType::tryFrom($this->type)?->value,
                'transmission' => Transmission::tryFrom($this->transmission)?->value,
                'seats' => filled($this->seats) ? (int) $this->seats : null,
                'maxPrice' => filled($this->maxPrice) ? (int) $this->maxPrice : null,
            ],
            excludeIds: $this->vehicles->modelKeys(),
        );
    }

    /**
     * The search origin, when one has been chosen.
     *
     * @return array{lat: float, lng: float}|null
     */
    #[Computed]
    public function origin(): ?array
    {
        if (! is_numeric($this->lat) || ! is_numeric($this->lng)) {
            return null;
        }

        $lat = (float) $this->lat;
        $lng = (float) $this->lng;

        return abs($lat) <= 90 && abs($lng) <= 180 ? ['lat' => $lat, 'lng' => $lng] : null;
    }

    /**
     * The radius filter in kilometres, when it is one of the offered options.
     */
    #[Computed]
    public function radiusKm(): ?int
    {
        return in_array((int) $this->radius, config('carhub.search_radii'), true) ? (int) $this->radius : null;
    }

    /**
     * The requested trip window, when both dates are valid and in order.
     *
     * @return array{pickup: CarbonImmutable, return: CarbonImmutable}|null
     */
    #[Computed]
    public function trip(): ?array
    {
        try {
            $pickup = CarbonImmutable::createFromFormat('!Y-m-d', $this->pickup);
            $return = CarbonImmutable::createFromFormat('!Y-m-d', $this->return);
        } catch (\Throwable) {
            return null;
        }

        if (! $pickup || ! $return || $return->lt($pickup)) {
            return null;
        }

        return ['pickup' => $pickup, 'return' => $return];
    }

    /**
     * The areas renters can search around: each listing location, centred on
     * the average position of the vehicles pinned there.
     *
     * @return \Illuminate\Support\Collection<int, array{name: string, lat: float, lng: float}>
     */
    #[Computed]
    public function areas(): \Illuminate\Support\Collection
    {
        return Vehicle::listed()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->toBase()
            ->selectRaw('location, avg(latitude) as lat, avg(longitude) as lng')
            ->groupBy('location')
            ->orderBy('location')
            ->get()
            ->map(fn (object $row) => ['name' => $row->location, 'lat' => round((float) $row->lat, 5), 'lng' => round((float) $row->lng, 5)]);
    }

    /**
     * The results as map pins. Coordinates are rounded to roughly 100 m so an
     * owner's exact address is never published.
     *
     * @return array<int, array{id: int, lat: float, lng: float, name: string, price: string, subtitle: string, url: string}>
     */
    #[Computed]
    public function mapMarkers(): array
    {
        return $this->vehicles
            ->filter(fn (Vehicle $vehicle) => $vehicle->latitude !== null && $vehicle->longitude !== null)
            ->map(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'lat' => round($vehicle->latitude, 3),
                'lng' => round($vehicle->longitude, 3),
                'name' => $vehicle->year.' '.$vehicle->name,
                'price' => '₱'.number_format($vehicle->price_per_day),
                'subtitle' => $vehicle->location.($vehicle->distance_km !== null ? ' · '.number_format($vehicle->distance_km, 1).' km' : ''),
                'url' => route('vehicles.show', $vehicle),
            ])
            ->values()
            ->all();
    }

    /**
     * The IDs of the vehicles lined up for comparison.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function compareIds(): array
    {
        return app(CompareList::class)->ids();
    }

    /**
     * Filter facets derived from the listed catalogue itself.
     *
     * @return array{types: \Illuminate\Support\Collection<int, string>, transmissions: \Illuminate\Support\Collection<int, string>}
     */
    #[Computed]
    public function facets(): array
    {
        return [
            'types' => Vehicle::listed()->distinct()->orderBy('type')->pluck('type')->map->value,
            'transmissions' => Vehicle::listed()->distinct()->orderBy('transmission')->pluck('transmission')->map->value,
        ];
    }

    /**
     * Daily-rate ceilings offered in the price filter.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function priceBands(): array
    {
        return [1500, 2500, 3500, 5000];
    }

    /**
     * The active filters, as removable chips.
     *
     * @return array<int, array{property: string, label: string}>
     */
    #[Computed]
    public function activeFilters(): array
    {
        $place = filled($this->area) ? $this->area : __('you');

        return collect([
            'search' => filled($this->search) ? __('Search: :term', ['term' => $this->search]) : null,
            'type' => filled($this->type) ? $this->type : null,
            'transmission' => filled($this->transmission) ? $this->transmission : null,
            'seats' => filled($this->seats) ? __(':count+ seats', ['count' => $this->seats]) : null,
            'maxPrice' => filled($this->maxPrice)
                ? __('Under :price/day', ['price' => '₱'.number_format((int) $this->maxPrice)])
                : null,
            'location' => match (true) {
                $this->origin && $this->radiusKm !== null => __('Within :km km of :place', ['km' => $this->radiusKm, 'place' => $place]),
                $this->origin !== null => __('Near :place', ['place' => $place]),
                default => null,
            },
            'dates' => $this->tripWindow ? __('Free :window', ['window' => $this->tripWindow]) : null,
        ])
            ->filter()
            ->map(fn (string $label, string $property) => ['property' => $property, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * The trip window for display, when both dates are valid.
     */
    #[Computed]
    public function tripWindow(): ?string
    {
        if (! $this->trip) {
            return null;
        }

        return $this->trip['pickup']->format('M j').' — '.$this->trip['return']->format('M j, Y');
    }

    /**
     * Centre the search on the chosen area.
     */
    public function updatedArea(string $area): void
    {
        $match = $this->areas->firstWhere('name', $area);

        [$this->lat, $this->lng] = $match ? [(string) $match['lat'], (string) $match['lng']] : ['', ''];
    }

    /**
     * Centre the search on the renter's GPS position and sort nearest first.
     */
    public function useLocation(float $latitude, float $longitude): void
    {
        $this->lat = (string) round($latitude, 5);
        $this->lng = (string) round($longitude, 5);
        $this->area = '';
        $this->sort = 'nearest';
    }

    /**
     * Add a vehicle to the comparison list, or take it off.
     */
    public function toggleCompare(int $vehicleId): void
    {
        if (Vehicle::listed()->whereKey($vehicleId)->exists()) {
            app(CompareList::class)->toggle($vehicleId);
        }

        unset($this->compareIds);
    }

    /**
     * Empty the comparison list.
     */
    public function clearCompare(): void
    {
        app(CompareList::class)->clear();

        unset($this->compareIds);
    }

    /**
     * Clear a single filter.
     */
    public function clearFilter(string $property): void
    {
        match ($property) {
            'search', 'type', 'transmission', 'seats', 'maxPrice' => $this->{$property} = '',
            'location' => $this->reset(['lat', 'lng', 'area', 'radius']),
            'dates' => $this->reset(['pickup', 'return']),
            default => null,
        };

        if ($property === 'location' && $this->sort === 'nearest') {
            $this->reset('sort');
        }
    }

    /**
     * Clear every filter and return to the default ordering.
     */
    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'transmission', 'seats', 'maxPrice', 'sort', 'lat', 'lng', 'area', 'radius', 'pickup', 'return']);
    }
}; ?>

<div class="bg-zinc-50">
    {{-- Page header --}}
    <div class="border-b border-zinc-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">{{ __('Browse vehicles') }}</h1>
            <p class="mt-3 max-w-2xl text-lg/8 text-zinc-600">
                {{ __('Every listing below is from a verified owner. Filter by what matters to your trip — the matching engine handles the rest.') }}
            </p>

            @if ($this->tripWindow)
                <p class="mt-4 inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3.5 py-1.5 text-sm font-medium text-brand-700">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M4.5 5.25h15a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75h-15a.75.75 0 0 1-.75-.75V6a.75.75 0 0 1 .75-.75Z" />
                    </svg>
                    {{ $this->tripWindow }}
                </p>
            @endif
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
        <div class="lg:grid lg:grid-cols-[17rem_1fr] lg:gap-10">
            {{-- Filters --}}
            <aside x-data="{ open: false }" class="lg:sticky lg:top-24 lg:h-fit">
                <button
                    type="button"
                    x-on:click="open = ! open"
                    x-bind:aria-expanded="open ? 'true' : 'false'"
                    aria-controls="vehicle-filters"
                    class="flex w-full items-center justify-between gap-3 rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm font-semibold text-zinc-700 lg:hidden"
                >
                    <span class="flex items-center gap-2">
                        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5L14.25 12v6.75l-4.5 2.25V12L3.75 5.25Z" />
                        </svg>
                        {{ __('Filters') }}
                    </span>
                    @if (count($this->activeFilters) > 0)
                        <span class="rounded-full bg-brand-600 px-2 py-0.5 text-xs font-bold text-white">{{ count($this->activeFilters) }}</span>
                    @endif
                </button>

                {{-- `hidden`/`block` are toggled for mobile; `lg:block` always wins at desktop
                     because variant utilities are emitted after their unprefixed counterparts. --}}
                <div
                    id="vehicle-filters"
                    x-bind:class="open ? 'block' : 'hidden'"
                    class="mt-3 hidden space-y-6 rounded-2xl border border-zinc-200 bg-white p-5 lg:mt-0 lg:block"
                >
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        :label="__('Search')"
                        :placeholder="__('Model, type, or city')"
                        icon="magnifying-glass"
                        clearable
                    />

                    <flux:select wire:model.live="type" :label="__('Body type')">
                        <flux:select.option value="">{{ __('Any type') }}</flux:select.option>
                        @foreach ($this->facets['types'] as $option)
                            <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="transmission" :label="__('Transmission')">
                        <flux:select.option value="">{{ __('Any transmission') }}</flux:select.option>
                        @foreach ($this->facets['transmissions'] as $option)
                            <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="seats" :label="__('Minimum seats')">
                        <flux:select.option value="">{{ __('Any size') }}</flux:select.option>
                        <flux:select.option value="5">{{ __('5+ seats') }}</flux:select.option>
                        <flux:select.option value="7">{{ __('7+ seats') }}</flux:select.option>
                        <flux:select.option value="8">{{ __('8+ seats') }}</flux:select.option>
                        <flux:select.option value="15">{{ __('15+ seats') }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model.live="maxPrice" :label="__('Daily rate')">
                        <flux:select.option value="">{{ __('Any price') }}</flux:select.option>
                        @foreach ($this->priceBands as $band)
                            <flux:select.option :value="$band">{{ __('Under :price', ['price' => '₱'.number_format($band)]) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="space-y-3 border-t border-zinc-100 pt-6">
                        <p class="text-sm font-medium text-zinc-800">{{ __('Trip dates') }}</p>
                        <div class="grid grid-cols-2 gap-3">
                            <flux:input type="date" wire:model.live="pickup" :aria-label="__('Pickup date')" min="{{ now()->toDateString() }}" />
                            <flux:input type="date" wire:model.live="return" :aria-label="__('Return date')" min="{{ $pickup ?: now()->toDateString() }}" />
                        </div>
                        <p class="text-xs/5 text-zinc-500">{{ __('Only vehicles free for every day of your trip are shown.') }}</p>
                    </div>

                    {{-- GPS search: the browser shares the renter's position, or they pick an area. --}}
                    <div
                        class="space-y-3 border-t border-zinc-100 pt-6"
                        x-data="{
                            locating: false,
                            failed: false,
                            locate() {
                                if (! navigator.geolocation) { this.failed = true; return; }
                                this.locating = true;
                                this.failed = false;
                                navigator.geolocation.getCurrentPosition(
                                    ({ coords }) => { this.locating = false; $wire.useLocation(coords.latitude, coords.longitude); },
                                    () => { this.locating = false; this.failed = true; },
                                    { enableHighAccuracy: true, timeout: 10000 },
                                );
                            },
                        }"
                    >
                        <p class="text-sm font-medium text-zinc-800">{{ __('Location') }}</p>

                        <button
                            type="button"
                            x-on:click="locate()"
                            x-bind:disabled="locating"
                            class="flex w-full items-center justify-center gap-2 rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50 disabled:opacity-60"
                        >
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="12" cy="12" r="3" />
                                <path stroke-linecap="round" d="M12 2v3m0 14v3M2 12h3m14 0h3m-2.9-7.1-2.1 2.1M7 17l-2.1 2.1m0-14.2L7 7m10 10 2.1 2.1" />
                            </svg>
                            <span x-show="! locating">{{ __('Use my location') }}</span>
                            <span x-show="locating" x-cloak>{{ __('Locating…') }}</span>
                        </button>
                        <p x-show="failed" x-cloak class="text-xs text-red-600">{{ __('We could not get your location. Pick an area instead.') }}</p>

                        <flux:select wire:model.live="area" :aria-label="__('Area')">
                            <flux:select.option value="">{{ $this->origin && blank($area) ? __('Your current location') : __('Choose an area') }}</flux:select.option>
                            @foreach ($this->areas as $option)
                                <flux:select.option :value="$option['name']">{{ $option['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="radius" :aria-label="__('Distance')" :disabled="! $this->origin">
                            <flux:select.option value="">{{ __('Any distance') }}</flux:select.option>
                            @foreach (config('carhub.search_radii') as $km)
                                <flux:select.option :value="$km">{{ __('Within :km km', ['km' => $km]) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    @if (count($this->activeFilters) > 0)
                        <button
                            type="button"
                            wire:click="clearFilters"
                            class="w-full rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50"
                        >
                            {{ __('Clear all filters') }}
                        </button>
                    @endif
                </div>
            </aside>

            {{-- Results --}}
            <div class="mt-8 lg:mt-0">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-zinc-600">
                        {{ trans_choice('{0} No vehicles match|{1} :count vehicle available|[2,*] :count vehicles available', $this->vehicles->count(), ['count' => $this->vehicles->count()]) }}
                    </p>

                    <div class="flex items-center gap-3">
                        <div class="inline-flex shrink-0 rounded-lg border border-zinc-300 bg-white p-0.5" role="group" aria-label="{{ __('Results view') }}">
                            @foreach (['list' => __('List'), 'map' => __('Map')] as $option => $label)
                                <button
                                    type="button"
                                    wire:click="$set('view', '{{ $option }}')"
                                    aria-pressed="{{ $view === $option ? 'true' : 'false' }}"
                                    @class([
                                        'rounded-md px-3 py-1.5 text-sm font-semibold transition',
                                        'bg-brand-600 text-white' => $view === $option,
                                        'text-zinc-600 hover:text-zinc-900' => $view !== $option,
                                    ])
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        <label for="sort" class="shrink-0 text-sm text-zinc-500">{{ __('Sort') }}</label>
                        <flux:select id="sort" wire:model.live="sort" class="sm:w-48">
                            <flux:select.option value="recommended">{{ __('Recommended') }}</flux:select.option>
                            @if ($this->origin)
                                <flux:select.option value="nearest">{{ __('Nearest first') }}</flux:select.option>
                            @endif
                            <flux:select.option value="price-asc">{{ __('Price: low to high') }}</flux:select.option>
                            <flux:select.option value="price-desc">{{ __('Price: high to low') }}</flux:select.option>
                            <flux:select.option value="rating">{{ __('Highest rated') }}</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                @if (count($this->activeFilters) > 0)
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @foreach ($this->activeFilters as $filter)
                            <button
                                type="button"
                                wire:click="clearFilter('{{ $filter['property'] }}')"
                                class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 py-1 pe-2 ps-3 text-sm font-medium text-brand-700 transition hover:bg-brand-100"
                            >
                                {{ $filter['label'] }}
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                <span class="sr-only">{{ __('Remove filter') }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                @if (count($this->compareIds) > 0)
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3">
                        <p class="text-sm font-medium text-brand-800">
                            {{ trans_choice('{1} :count vehicle selected to compare|[2,*] :count vehicles selected to compare', count($this->compareIds), ['count' => count($this->compareIds)]) }}
                            <span class="text-brand-600">({{ __('up to :count', ['count' => config('carhub.compare_limit')]) }})</span>
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="clearCompare" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-100">{{ __('Clear') }}</button>
                            @if (count($this->compareIds) >= 2)
                                <a href="{{ route('vehicles.compare') }}" class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Compare now') }} &rarr;</a>
                            @else
                                <span class="text-xs text-brand-700">{{ __('Pick one more to compare') }}</span>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($this->vehicles->isEmpty())
                    <div class="mt-8 rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
                        <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-zinc-100">
                            <svg class="size-6 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                            </svg>
                        </span>
                        <h2 class="mt-5 text-lg font-semibold text-zinc-900">{{ __('No vehicles match those filters') }}</h2>
                        <p class="mx-auto mt-2 max-w-sm text-sm/6 text-zinc-600">
                            {{ __('Try widening the price range or clearing a filter — there are :count vehicles listed in total.', ['count' => $this->listedCount]) }}
                        </p>
                        <button
                            type="button"
                            wire:click="clearFilters"
                            class="mt-6 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700"
                        >
                            {{ __('Clear all filters') }}
                        </button>
                    </div>
                @elseif ($view === 'map')
                    {{-- Keyed on the pins so the map is rebuilt whenever the results change. --}}
                    <div
                        wire:key="results-map-{{ md5(json_encode([$this->mapMarkers, $this->origin])) }}"
                        x-data="vehicleMap({ markers: @js($this->mapMarkers), origin: @js($this->origin) })"
                        class="mt-6"
                    >
                        <div wire:ignore>
                            <div x-ref="map" class="z-0 h-[32rem] w-full overflow-hidden rounded-2xl border border-zinc-200"></div>
                        </div>
                        @if (count($this->mapMarkers) < $this->vehicles->count())
                            <p class="mt-3 text-xs text-zinc-500">{{ __('Some matching vehicles have no pickup pin yet and are only shown in the list view.') }}</p>
                        @endif
                    </div>
                @else
                    <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60">
                        @foreach ($this->vehicles as $vehicle)
                            <x-marketing.vehicle-card
                                wire:key="vehicle-{{ $vehicle->id }}"
                                :vehicle="$vehicle"
                                :compare="in_array($vehicle->id, $this->compareIds, true) ? 'on' : (count($this->compareIds) >= config('carhub.compare_limit') ? 'full' : 'off')"
                            />
                        @endforeach
                    </div>
                @endif

                @if ($this->closeMatches->isNotEmpty())
                    <section class="mt-14">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('Close matches') }}</h2>
                        <p class="mt-1 text-sm text-zinc-600">{{ __('Outside your filters, but the most similar in type, size, price, and features.') }}</p>

                        <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($this->closeMatches as $vehicle)
                                <x-marketing.vehicle-card
                                    wire:key="match-{{ $vehicle->id }}"
                                    :vehicle="$vehicle"
                                    :compare="in_array($vehicle->id, $this->compareIds, true) ? 'on' : (count($this->compareIds) >= config('carhub.compare_limit') ? 'full' : 'off')"
                                />
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
</div>
