<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
     * Trip dates carried over from the home page search bar. Availability is not
     * modelled yet, so these are shown for context rather than filtered on.
     */
    #[Url(except: '')]
    public string $pickup = '';

    #[Url(except: '')]
    public string $return = '';

    /**
     * All demo vehicles, before any filtering.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function allVehicles(): Collection
    {
        return collect(config('demo.vehicles'));
    }

    /**
     * The vehicles matching the current filters, in the requested order.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function vehicles(): Collection
    {
        $vehicles = $this->allVehicles()
            ->when(filled($this->search), fn (Collection $vehicles) => $vehicles->filter(
                fn (array $vehicle) => Str::contains(
                    $vehicle['name'].' '.$vehicle['type'].' '.$vehicle['location'],
                    trim($this->search),
                    ignoreCase: true,
                )
            ))
            ->when(filled($this->type), fn (Collection $vehicles) => $vehicles->where('type', $this->type))
            ->when(filled($this->transmission), fn (Collection $vehicles) => $vehicles->where('transmission', $this->transmission))
            ->when(filled($this->seats), fn (Collection $vehicles) => $vehicles->where('seats', '>=', (int) $this->seats))
            ->when(filled($this->maxPrice), fn (Collection $vehicles) => $vehicles->where('price_per_day', '<=', (int) $this->maxPrice));

        return match ($this->sort) {
            'price-asc' => $vehicles->sortBy('price_per_day')->values(),
            'price-desc' => $vehicles->sortByDesc('price_per_day')->values(),
            'rating' => $vehicles->sortByDesc('rating')->values(),
            default => $vehicles->sortByDesc(fn (array $vehicle) => [$vehicle['featured'], $vehicle['rating']])->values(),
        };
    }

    /**
     * Filter facets derived from the catalogue itself.
     *
     * @return array{types: Collection<int, string>, transmissions: Collection<int, string>}
     */
    #[Computed]
    public function facets(): array
    {
        return [
            'types' => $this->allVehicles()->pluck('type')->unique()->sort()->values(),
            'transmissions' => $this->allVehicles()->pluck('transmission')->unique()->sort()->values(),
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
        return collect([
            'search' => filled($this->search) ? __('Search: :term', ['term' => $this->search]) : null,
            'type' => filled($this->type) ? $this->type : null,
            'transmission' => filled($this->transmission) ? $this->transmission : null,
            'seats' => filled($this->seats) ? __(':count+ seats', ['count' => $this->seats]) : null,
            'maxPrice' => filled($this->maxPrice)
                ? __('Under :price/day', ['price' => '₱'.number_format((int) $this->maxPrice)])
                : null,
        ])
            ->filter()
            ->map(fn (string $label, string $property) => ['property' => $property, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * The trip window carried over from the home page, when both dates are present.
     */
    #[Computed]
    public function tripWindow(): ?string
    {
        if (blank($this->pickup) || blank($this->return)) {
            return null;
        }

        return Carbon::parse($this->pickup)->format('M j').' — '.Carbon::parse($this->return)->format('M j, Y');
    }

    /**
     * Clear a single filter.
     */
    public function clearFilter(string $property): void
    {
        if (in_array($property, ['search', 'type', 'transmission', 'seats', 'maxPrice'], strict: true)) {
            $this->{$property} = '';
        }
    }

    /**
     * Clear every filter and return to the default ordering.
     */
    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'transmission', 'seats', 'maxPrice', 'sort']);
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

                    {{-- GPS map search is not implemented yet; the toggle marks where it lands. --}}
                    <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-sm font-medium text-zinc-600">
                                <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20.25 3 22.5v-15L9 5.25m0 15 6-2.25m-6 2.25V5.25m6 12.75 6 2.25V6l-6-2.25m0 14.25V3.75" />
                                </svg>
                                {{ __('Map view') }}
                            </span>
                            <span class="rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-zinc-600">
                                {{ __('Soon') }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs/5 text-zinc-500">{{ __('GPS-based search will let you find vehicles by distance from your pin.') }}</p>
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

                    <div class="flex items-center gap-2 sm:w-64">
                        <label for="sort" class="shrink-0 text-sm text-zinc-500">{{ __('Sort') }}</label>
                        <flux:select id="sort" wire:model.live="sort">
                            <flux:select.option value="recommended">{{ __('Recommended') }}</flux:select.option>
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

                @if ($this->vehicles->isEmpty())
                    <div class="mt-8 rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
                        <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-zinc-100">
                            <svg class="size-6 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                            </svg>
                        </span>
                        <h2 class="mt-5 text-lg font-semibold text-zinc-900">{{ __('No vehicles match those filters') }}</h2>
                        <p class="mx-auto mt-2 max-w-sm text-sm/6 text-zinc-600">
                            {{ __('Try widening the price range or clearing a filter — there are :count vehicles listed in total.', ['count' => $this->allVehicles->count()]) }}
                        </p>
                        <button
                            type="button"
                            wire:click="clearFilters"
                            class="mt-6 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700"
                        >
                            {{ __('Clear all filters') }}
                        </button>
                    </div>
                @else
                    <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60">
                        @foreach ($this->vehicles as $vehicle)
                            <x-marketing.vehicle-card :key="$vehicle['slug']" :vehicle="$vehicle" />
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
