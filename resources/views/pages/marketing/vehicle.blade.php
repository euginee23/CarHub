@php
    $similar = collect(config('demo.vehicles'))
        ->where('slug', '!=', $vehicle['slug'])
        ->sortByDesc(fn (array $candidate) => ($candidate['type'] === $vehicle['type'] ? 10 : 0) + $candidate['rating'])
        ->take(3);

    $minDate = now()->addDay()->toDateString();
    $maxDate = now()->addDays(90)->toDateString();

    $specs = [
        ['label' => __('Body type'), 'value' => $vehicle['type']],
        ['label' => __('Seats'), 'value' => $vehicle['seats']],
        ['label' => __('Transmission'), 'value' => $vehicle['transmission']],
        ['label' => __('Fuel'), 'value' => $vehicle['fuel']],
        ['label' => __('Year'), 'value' => $vehicle['year']],
    ];
@endphp

<x-layouts::marketing
    :title="$vehicle['year'].' '.$vehicle['name']"
    :description="__('Rent a :year :name in :location from ₱:price per day on CarHub.', [
        'year' => $vehicle['year'],
        'name' => $vehicle['name'],
        'location' => $vehicle['location'],
        'price' => number_format($vehicle['price_per_day']),
    ])"
    :mobile-action-bar="true"
>
    {{-- pb-28 below lg clears the fixed booking bar at the foot of the viewport. --}}
    <div class="bg-zinc-50 pb-28 lg:pb-20">
        <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
            <nav aria-label="{{ __('Breadcrumb') }}" class="-mx-1.5 flex items-center gap-1 text-sm text-zinc-500">
                <a href="{{ route('vehicles.index') }}" class="rounded px-1.5 py-2 transition-colors hover:text-brand-700">{{ __('Browse') }}</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('vehicles.index', ['type' => $vehicle['type']]) }}" class="rounded px-1.5 py-2 transition-colors hover:text-brand-700">{{ $vehicle['type'] }}</a>
                <span aria-hidden="true">/</span>
                <span class="truncate px-1.5 text-zinc-800">{{ $vehicle['name'] }}</span>
            </nav>

            <div class="mt-6 lg:grid lg:grid-cols-[1fr_22rem] lg:items-start lg:gap-10">
                {{-- Main column --}}
                <div class="space-y-8">
                    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white">
                        <x-marketing.vehicle-image :vehicle="$vehicle" class="aspect-video" />
                    </div>

                    <div>
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h1 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                                    {{ $vehicle['year'] }} {{ $vehicle['name'] }}
                                </h1>
                                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-600">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="size-4 text-amber-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M12 2.5 15 9l7 .8-5.2 4.7 1.5 6.9L12 17.9 5.7 21.4l1.5-6.9L2 9.8 9 9l3-6.5Z" />
                                        </svg>
                                        <span class="font-semibold text-zinc-900">{{ number_format($vehicle['rating'], 1) }}</span>
                                        <span class="text-zinc-500">({{ trans_choice('{1} :count trip|[2,*] :count trips', $vehicle['trips'], ['count' => $vehicle['trips']]) }})</span>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <svg class="size-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" />
                                            <circle cx="12" cy="10" r="2.5" />
                                        </svg>
                                        {{ $vehicle['location'] }}
                                    </span>
                                </div>
                            </div>

                            @if ($vehicle['instant_book'])
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-200">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M13.5 2 4 13.5h6L9.5 22 20 9.5h-6.9L13.5 2Z" />
                                    </svg>
                                    {{ __('Instant book') }}
                                </span>
                            @endif
                        </div>

                        <p class="mt-5 text-base/7 text-zinc-600">{{ $vehicle['description'] }}</p>
                    </div>

                    {{-- Specs. Five items over two mobile columns leaves an odd cell, so the
                         last one spans the full width below sm. --}}
                    <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-200 sm:grid-cols-5">
                        @foreach ($specs as $spec)
                            <div @class([
                                'bg-white p-4 text-center',
                                'max-sm:col-span-2' => $loop->last && $loop->count % 2 !== 0,
                            ])>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $spec['label'] }}</dt>
                                <dd class="mt-1.5 font-semibold text-zinc-900">{{ $spec['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    {{-- Features --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-6">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('What this vehicle has') }}</h2>
                        <ul class="mt-5 grid gap-3 sm:grid-cols-2">
                            @foreach ($vehicle['features'] as $feature)
                                <li class="flex items-center gap-2.5 text-sm text-zinc-700">
                                    <svg class="size-4.5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Owner --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-6">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('Hosted by') }}</h2>

                        <div class="mt-5 flex flex-wrap items-center gap-4">
                            <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-600 to-brand-500 text-lg font-semibold text-white">
                                {{ \Illuminate\Support\Str::of($vehicle['owner']['name'])->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-2 font-semibold text-zinc-900">
                                    {{ $vehicle['owner']['name'] }}
                                    @if ($vehicle['owner']['verified'])
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                                            <svg class="size-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <path d="M12 2 4 5.5v6c0 4.6 3.4 8.9 8 10 4.6-1.1 8-5.4 8-10v-6L12 2Zm-1 13.4-3.2-3.2 1.4-1.4 1.8 1.8 4.3-4.3 1.4 1.4-5.7 5.7Z" />
                                            </svg>
                                            {{ __('Verified') }}
                                        </span>
                                    @endif
                                </p>
                                <p class="mt-1 text-sm text-zinc-500">
                                    {{ __('Hosting since :year', ['year' => $vehicle['owner']['joined']]) }}
                                    &middot;
                                    {{ trans_choice('{1} :count trip|[2,*] :count trips', $vehicle['owner']['trips'], ['count' => $vehicle['owner']['trips']]) }}
                                    &middot;
                                    {{ __('Replies in :time', ['time' => $vehicle['owner']['response_time']]) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Requirements --}}
                    <div class="rounded-2xl border border-zinc-200 bg-white p-6">
                        <h2 class="text-lg font-semibold text-zinc-900">{{ __('What you will need') }}</h2>
                        <p class="mt-2 text-sm/6 text-zinc-600">
                            {{ __('These checks run once, before your first booking is confirmed. Nothing is shared with the owner beyond your verification status.') }}
                        </p>

                        <div class="mt-6 space-y-5">
                            @foreach ([
                                [
                                    'title' => __('Two valid government-issued IDs'),
                                    'body' => __('Upload two independent IDs — for example a driver\'s licence plus a passport, UMID, or PhilSys card. Both are matched against the name on your account.'),
                                ],
                                [
                                    'title' => __('A valid driver\'s licence'),
                                    'body' => __('You must be at least 21 years old with a licence that stays valid for the full rental period.'),
                                ],
                                [
                                    'title' => __('A signed digital rental contract'),
                                    'body' => __('At pickup, you and the owner both sign a contract recording condition, fuel level, mileage, and the deposit held.'),
                                ],
                                [
                                    'title' => __('Consent to GPS tracking'),
                                    'body' => __('The owner can see the vehicle\'s location for the duration of your trip only. Tracking stops the moment the rental closes.'),
                                ],
                            ] as $requirement)
                                <div class="flex gap-3.5">
                                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-zinc-900">{{ $requirement['title'] }}</h3>
                                        <p class="mt-1 text-sm/6 text-zinc-600">{{ $requirement['body'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-6 border-t border-zinc-100 pt-5 text-sm text-zinc-500">
                            {{ __('Full details are in our') }}
                            <a href="{{ route('terms') }}" class="font-medium text-brand-600 underline underline-offset-2 hover:text-brand-700">{{ __('terms & conditions') }}</a>.
                        </p>
                    </div>
                </div>

                {{-- Booking panel --}}
                <div
                    id="book"
                    class="mt-8 scroll-mt-24 lg:sticky lg:top-24 lg:mt-0"
                    x-data="{
                        rate: {{ $vehicle['price_per_day'] }},
                        serviceFeeRate: 0.15,
                        pickup: '',
                        dropoff: '',
                        get days() {
                            if (! this.pickup || ! this.dropoff) return 0;
                            const diff = (new Date(this.dropoff) - new Date(this.pickup)) / 86400000;
                            return diff > 0 ? Math.round(diff) : 0;
                        },
                        get datesInvalid() {
                            return !! this.pickup && !! this.dropoff && this.days === 0;
                        },
                        get subtotal() { return this.days * this.rate; },
                        get serviceFee() { return Math.round(this.subtotal * this.serviceFeeRate); },
                        get total() { return this.subtotal + this.serviceFee; },
                        peso(amount) { return '₱' + amount.toLocaleString('en-PH'); },
                    }"
                >
                    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-lg shadow-zinc-900/5">
                        <p class="flex items-baseline gap-1.5">
                            <span class="text-3xl font-bold tracking-tight text-zinc-900">&#8369;{{ number_format($vehicle['price_per_day']) }}</span>
                            <span class="text-zinc-500">/ {{ __('day') }}</span>
                        </p>

                        <div class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-zinc-300 bg-zinc-300">
                            <label class="bg-white p-3">
                                <span class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Pickup') }}</span>
                                <input
                                    type="date"
                                    x-model="pickup"
                                    min="{{ $minDate }}"
                                    max="{{ $maxDate }}"
                                    class="mt-1 w-full border-0 bg-transparent p-0 text-sm text-zinc-900 outline-hidden"
                                />
                            </label>
                            <label class="bg-white p-3">
                                <span class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Return') }}</span>
                                <input
                                    type="date"
                                    x-model="dropoff"
                                    x-bind:min="pickup || '{{ $minDate }}'"
                                    max="{{ $maxDate }}"
                                    class="mt-1 w-full border-0 bg-transparent p-0 text-sm text-zinc-900 outline-hidden"
                                />
                            </label>
                        </div>

                        {{-- Scheduling validation --}}
                        <p x-show="datesInvalid" x-cloak class="mt-3 flex items-start gap-2 text-sm text-red-600">
                            <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" />
                                <path stroke-linecap="round" d="M12 8v4.5m0 3h.01" />
                            </svg>
                            {{ __('The return date must be at least one day after pickup.') }}
                        </p>

                        <div x-show="days > 0" x-cloak class="mt-6 space-y-3 border-t border-zinc-100 pt-5 text-sm">
                            <div class="flex items-center justify-between text-zinc-600">
                                <span x-text="peso(rate) + ' × ' + days + (days === 1 ? ' {{ __('day') }}' : ' {{ __('days') }}')"></span>
                                <span class="font-medium text-zinc-900" x-text="peso(subtotal)"></span>
                            </div>
                            <div class="flex items-center justify-between text-zinc-600">
                                <span>{{ __('Service fee (15%)') }}</span>
                                <span class="font-medium text-zinc-900" x-text="peso(serviceFee)"></span>
                            </div>
                            <div class="flex items-center justify-between border-t border-zinc-100 pt-3 text-base font-semibold text-zinc-900">
                                <span>{{ __('Total') }}</span>
                                <span x-text="peso(total)"></span>
                            </div>
                        </div>

                        <a
                            href="{{ route('register') }}"
                            class="mt-6 block rounded-xl bg-brand-600 px-5 py-3.5 text-center text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                        >
                            {{ $vehicle['instant_book'] ? __('Book instantly') : __('Request to book') }}
                        </a>

                        <p class="mt-3 text-center text-xs text-zinc-500">
                            {{ __('You will not be charged until the owner confirms.') }}
                        </p>

                        <ul class="mt-6 space-y-2.5 border-t border-zinc-100 pt-5 text-sm text-zinc-600">
                            @foreach ([
                                __('Free cancellation up to 24 hours before pickup'),
                                __('Third-party liability coverage included'),
                                __('Roadside assistance on every trip'),
                            ] as $assurance)
                                <li class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 size-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    {{ $assurance }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Similar vehicles --}}
            <section class="mt-16">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-zinc-900">{{ __('You might also like') }}</h2>
                        <p class="mt-2 text-sm text-zinc-600">{{ __('Matched on body type, rating, and price band.') }}</p>
                    </div>
                    <a href="{{ route('vehicles.index') }}" class="shrink-0 text-sm font-semibold text-brand-600 transition-colors hover:text-brand-700">
                        {{ __('See all') }} <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>

                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($similar as $candidate)
                        <x-marketing.vehicle-card :vehicle="$candidate" />
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Mobile action bar. On phones the booking panel sits far below the fold, so the
             rate and the primary action are pinned to the viewport instead. --}}
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white/95 backdrop-blur-lg lg:hidden">
            <div class="flex items-center gap-3 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                <p class="min-w-0 flex-1">
                    <span class="block text-lg font-bold leading-tight text-zinc-900">
                        &#8369;{{ number_format($vehicle['price_per_day']) }}
                        <span class="text-sm font-normal text-zinc-500">/{{ __('day') }}</span>
                    </span>
                    <span class="mt-0.5 flex items-center gap-1 text-xs text-zinc-500">
                        <svg class="size-3 text-amber-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2.5 15 9l7 .8-5.2 4.7 1.5 6.9L12 17.9 5.7 21.4l1.5-6.9L2 9.8 9 9l3-6.5Z" />
                        </svg>
                        {{ number_format($vehicle['rating'], 1) }} &middot; {{ $vehicle['location'] }}
                    </span>
                </p>

                <a
                    href="#book"
                    class="shrink-0 rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                >
                    {{ $vehicle['instant_book'] ? __('Book now') : __('Request') }}
                </a>
            </div>
        </div>
    </div>
</x-layouts::marketing>
