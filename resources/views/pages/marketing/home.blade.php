@php
    $vehicles = collect(config('demo.vehicles'));
    $featured = $vehicles->where('featured', true)->take(6);
    $testimonials = config('demo.testimonials');
    $types = $vehicles->pluck('type')->unique()->sort()->values();
    $minDate = now()->addDay()->toDateString();
@endphp

<x-layouts::marketing
    :title="__('AI-powered vehicle rental')"
    :description="__('Rent a vehicle from verified local owners. CarHub matches you to the right car with smart recommendations, live GPS tracking, and secure, validated payments.')"
>
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-white">
        <x-marketing.glow />

        <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-16 sm:px-6 lg:px-8 lg:pb-24 lg:pt-24">
            <div class="mx-auto max-w-3xl text-center">
                <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/70 px-3.5 py-1.5 text-sm font-medium text-brand-700 shadow-sm backdrop-blur">
                    <span class="flex size-1.5 rounded-full bg-brand-500"></span>
                    {{ __('AI-powered vehicle rental') }}
                </span>

                <h1 class="mt-6 text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-6xl">
                    {{ __('Rent the right car,') }}
                    <span class="text-gradient">{{ __('matched to you') }}</span>
                </h1>

                <p class="mx-auto mt-6 max-w-2xl text-lg/8 text-pretty text-zinc-600">
                    {{ __('CarHub connects renters with verified local vehicle owners. Our recommendation engine learns what you actually need, forecasts demand so prices stay fair, and every trip is GPS-tracked from pickup to return.') }}
                </p>
            </div>

            {{-- Search bar --}}
            <form
                method="GET"
                action="{{ route('vehicles.index') }}"
                class="mx-auto mt-10 max-w-4xl rounded-2xl border border-zinc-200 bg-white/90 p-2 shadow-xl shadow-brand-900/5 backdrop-blur-xl sm:mt-12"
            >
                <div class="grid gap-2 md:grid-cols-[1.4fr_1fr_1fr_auto]">
                    <label class="group flex items-center gap-3 rounded-xl px-3.5 py-2.5 transition-colors focus-within:bg-brand-50/60 hover:bg-zinc-50">
                        <svg class="size-5 shrink-0 text-zinc-400 group-focus-within:text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" />
                            <circle cx="12" cy="10" r="2.5" />
                        </svg>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Where') }}</span>
                            <input
                                type="text"
                                name="search"
                                placeholder="{{ __('City or vehicle') }}"
                                class="w-full border-0 bg-transparent p-0 text-sm text-zinc-900 outline-hidden placeholder:text-zinc-400"
                            />
                        </span>
                    </label>

                    <label class="group flex items-center gap-3 rounded-xl px-3.5 py-2.5 transition-colors focus-within:bg-brand-50/60 hover:bg-zinc-50 md:border-s md:border-zinc-200">
                        <svg class="size-5 shrink-0 text-zinc-400 group-focus-within:text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M4.5 5.25h15a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75h-15a.75.75 0 0 1-.75-.75V6a.75.75 0 0 1 .75-.75Z" />
                        </svg>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Pickup') }}</span>
                            <input
                                type="date"
                                name="pickup"
                                min="{{ $minDate }}"
                                class="w-full border-0 bg-transparent p-0 text-sm text-zinc-900 outline-hidden"
                            />
                        </span>
                    </label>

                    <label class="group flex items-center gap-3 rounded-xl px-3.5 py-2.5 transition-colors focus-within:bg-brand-50/60 hover:bg-zinc-50 md:border-s md:border-zinc-200">
                        <svg class="size-5 shrink-0 text-zinc-400 group-focus-within:text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M4.5 5.25h15a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75h-15a.75.75 0 0 1-.75-.75V6a.75.75 0 0 1 .75-.75Zm5.25 8.25 1.5 1.5 3-3" />
                        </svg>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Return') }}</span>
                            <input
                                type="date"
                                name="return"
                                min="{{ $minDate }}"
                                class="w-full border-0 bg-transparent p-0 text-sm text-zinc-900 outline-hidden"
                            />
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                    >
                        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                        </svg>
                        {{ __('Search') }}
                    </button>
                </div>
            </form>

            <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                <span class="text-sm text-zinc-500">{{ __('Popular:') }}</span>
                @foreach ($types as $type)
                    <a
                        href="{{ route('vehicles.index', ['type' => $type]) }}"
                        class="rounded-full border border-zinc-200 bg-white px-3 py-1 text-sm text-zinc-600 transition-colors hover:border-brand-300 hover:text-brand-700"
                    >
                        {{ $type }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Trust strip --}}
    <section class="border-y border-zinc-200 bg-zinc-50">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 py-12 sm:px-6 lg:grid-cols-4 lg:px-8">
            <x-marketing.stat value="{{ $vehicles->count() }}+" :label="__('Vehicles listed')" />
            <x-marketing.stat value="100%" :label="__('ID-verified renters')" />
            <x-marketing.stat value="6" :label="__('Cities served')" />
            <x-marketing.stat value="4.8/5" :label="__('Average trip rating')" />
        </div>
    </section>

    {{-- AI capabilities --}}
    <section class="relative isolate overflow-hidden bg-zinc-950 py-20 lg:py-28">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <div class="absolute -left-24 top-0 size-[30rem] rounded-full bg-brand-600/20 blur-3xl"></div>
            <div class="absolute -right-24 bottom-0 size-[30rem] rounded-full bg-spark-500/15 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-spark-400">{{ __('The intelligence layer') }}</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">
                    {{ __('More than a listings page') }}
                </h2>
                <p class="mt-4 text-lg/8 text-pretty text-zinc-400">
                    {{ __('Three models run underneath CarHub — one to understand what you want, one to keep prices honest, and one to keep every trip accounted for.') }}
                </p>
            </div>

            <div class="mt-14 grid gap-6 lg:grid-cols-3">
                <x-marketing.feature-card tone="dark" :title="__('Personalised recommendations')" :badge="__('Content-based')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.36.39-1.59 1.59M21 12h-2.25m-.39 6.36-1.59-1.59M12 18.75V21m-4.77-2.23-1.59 1.59M5.25 12H3m2.64-6.36L7.23 7.23" />
                            <circle cx="12" cy="12" r="3.25" />
                        </svg>
                    </x-slot:icon>
                    {{ __('CarHub profiles every vehicle by body type, seats, transmission, features, and price band, then matches those attributes against what you have searched for and booked before. The more you use it, the closer the shortlist gets.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card tone="dark" :title="__('Demand forecasting')" :badge="__('LSTM')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 20.25h18M6.75 16.5V9.75M11.25 16.5V6.75M15.75 16.5v-4.5M20.25 16.5V4.5" />
                        </svg>
                    </x-slot:icon>
                    {{ __('A sequence model reads historical bookings, seasonality, and local events to project demand week by week. Owners get a suggested daily rate that reflects what the market will actually bear — no guesswork, no gouging.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card tone="dark" :title="__('GPS search & live tracking')" :badge="__('Real-time')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20.25 3 22.5v-15L9 5.25m0 15 6-2.25m-6 2.25V5.25m6 12.75 6 2.25V6l-6-2.25m0 14.25V3.75m0 0L9 5.25" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Search by map to find vehicles genuinely close to you, not just in the same city. Once a trip starts, owners see the vehicle live for the rental window only — tracking switches off the moment it ends.') }}
                </x-marketing.feature-card>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-14 lg:grid-cols-2 lg:gap-20">
                <div>
                    <x-marketing.section-heading
                        align="left"
                        :eyebrow="__('How it works')"
                        :title="__('Booked in four steps')"
                        :description="__('From search to keys in hand — every step validated so both sides of the trip are protected.')"
                    />

                    <a
                        href="{{ route('how-it-works') }}"
                        class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-brand-600 transition-colors hover:text-brand-700"
                    >
                        {{ __('See the full walkthrough') }}
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>

                <div>
                    <x-marketing.step number="1" :title="__('Search and compare')">
                        {{ __('Filter by body type, transmission, seats, and daily rate, or let the recommendation engine surface the closest matches to what you need.') }}
                    </x-marketing.step>

                    <x-marketing.step number="2" :title="__('Book and verify your identity')">
                        {{ __('Pick your dates and upload two valid government-issued IDs. Verification is one-time and usually clears in minutes — the owner sees a confirmed identity before accepting.') }}
                    </x-marketing.step>

                    <x-marketing.step number="3" :title="__('Pay securely')">
                        {{ __('Pay with GCash, Maya, card, or bank transfer. Every payment is validated and confirmed before a reservation is held, and released in full if the owner declines.') }}
                    </x-marketing.step>

                    <x-marketing.step number="4" :title="__('Drive, tracked and covered')" :last="true">
                        {{ __('Sign the digital rental contract at pickup. The trip is GPS-tracked and insured for its full duration, and closes out automatically on return.') }}
                    </x-marketing.step>
                </div>
            </div>
        </div>
    </section>

    {{-- Featured vehicles --}}
    <section class="border-t border-zinc-200 bg-zinc-50 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <x-marketing.section-heading
                    align="left"
                    :eyebrow="__('Available now')"
                    :title="__('Featured vehicles')"
                    :description="__('A cross-section of what owners near you have listed this week.')"
                />

                <a
                    href="{{ route('vehicles.index') }}"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50"
                >
                    {{ __('Browse all vehicles') }}
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $vehicle)
                    <x-marketing.vehicle-card :vehicle="$vehicle" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Trust & safety --}}
    <section class="bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-marketing.section-heading
                :eyebrow="__('Trust & safety')"
                :title="__('Nobody hands over keys to a stranger')"
                :description="__('Renting between private parties only works if both sides are accountable. These checks are not optional on CarHub.')"
            />

            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <x-marketing.feature-card :title="__('Two valid IDs required')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5a.75.75 0 0 1 .75.75v9a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1-.75-.75v-9a.75.75 0 0 1 .75-.75Zm10.5 3.75h4.5m-4.5 3h3" />
                            <circle cx="8.25" cy="11.25" r="1.75" />
                            <path stroke-linecap="round" d="M5.5 15.25c.4-1.1 1.45-1.75 2.75-1.75s2.35.65 2.75 1.75" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Every renter submits two independent government-issued IDs before their first booking. No verification, no keys.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Verified owners')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v6c0 4.4 3.1 8.2 7.5 9.4 4.4-1.2 7.5-5 7.5-9.4V6L12 3Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.5 12 1.9 1.9 3.6-3.8" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Owners prove vehicle registration and identity before listing. Their rating, response time, and trip count are public on every listing.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Digital rental contract')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3v4.5a.75.75 0 0 0 .75.75h4.5M14.25 3H6.75a.75.75 0 0 0-.75.75v16.5a.75.75 0 0 0 .75.75h10.5a.75.75 0 0 0 .75-.75V8.25L14.25 3Z" />
                            <path stroke-linecap="round" d="M9 13.5h6M9 16.5h4" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Both parties sign a contract that records condition, fuel level, mileage, and deposit at pickup and return. Disputes are settled against a record, not memory.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Insured every trip')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21c4.4-1.4 7.5-5.2 7.5-9.7V6.2L12 3 4.5 6.2v5.1c0 4.5 3.1 8.3 7.5 9.7Z" />
                            <path stroke-linecap="round" d="M12 9v6m-3-3h6" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Third-party liability coverage and roadside assistance are included on every booking, with comprehensive cover available at checkout.') }}
                </x-marketing.feature-card>
            </div>
        </div>
    </section>

    {{-- For owners --}}
    <section class="border-y border-zinc-200 bg-zinc-50 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                <div>
                    <x-marketing.section-heading
                        align="left"
                        :eyebrow="__('For vehicle owners')"
                        :title="__('Your idle car is working capital')"
                        :description="__('Most privately owned vehicles sit unused five days a week. List yours and let CarHub handle the matching, the vetting, and the paperwork.')"
                    />

                    <ul class="mt-8 space-y-4">
                        @foreach ([
                            __('AI-suggested daily rates based on live local demand — override them any time.'),
                            __('Review every renter\'s verification status, rating, and trip history before you accept.'),
                            __('See your vehicle on a map for the duration of an active rental.'),
                            __('Payouts to GCash, Maya, or your bank within 24 hours of trip completion.'),
                        ] as $benefit)
                            <li class="flex gap-3">
                                <svg class="mt-0.5 size-5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span class="text-base/7 text-zinc-600">{{ $benefit }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-9 flex flex-wrap gap-3">
                        <a
                            href="{{ route('register') }}"
                            class="rounded-lg bg-brand-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                        >
                            {{ __('List your vehicle') }}
                        </a>
                        <a
                            href="{{ route('how-it-works') }}#for-owners"
                            class="rounded-lg border border-zinc-300 bg-white px-5 py-3 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400"
                        >
                            {{ __('How hosting works') }}
                        </a>
                    </div>
                </div>

                {{-- Illustrative earnings panel --}}
                <div class="glass-card p-6 sm:p-8">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-zinc-500">{{ __('Suggested daily rate') }}</p>
                            <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900">&#8369;3,200</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                            <svg class="size-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 4.5 20 16H4l8-11.5Z" />
                            </svg>
                            {{ __('+18% vs. last month') }}
                        </span>
                    </div>

                    <p class="mt-4 text-sm/6 text-zinc-600">
                        {{ __('Forecast for a 2021 Toyota Innova in Cebu City over the next four weeks.') }}
                    </p>

                    {{-- Demand forecast sparkline --}}
                    <div class="mt-6" aria-hidden="true">
                        <div class="flex h-32 items-end gap-1.5">
                            @foreach ([38, 44, 41, 52, 49, 61, 74, 66, 58, 71, 83, 79, 88, 94] as $index => $height)
                                <div
                                    @class([
                                        'flex-1 rounded-t-sm',
                                        'bg-brand-200' => $index < 9,
                                        'bg-linear-to-t from-brand-500 to-spark-400' => $index >= 9,
                                    ])
                                    style="height: {{ $height }}%"
                                ></div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex justify-between text-xs text-zinc-400">
                            <span>{{ __('Observed') }}</span>
                            <span>{{ __('Forecast') }}</span>
                        </div>
                    </div>

                    <dl class="mt-6 grid grid-cols-3 gap-4 border-t border-zinc-200 pt-6">
                        <div>
                            <dt class="text-xs text-zinc-500">{{ __('Booked days') }}</dt>
                            <dd class="mt-1 text-lg font-semibold text-zinc-900">18</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500">{{ __('Gross') }}</dt>
                            <dd class="mt-1 text-lg font-semibold text-zinc-900">&#8369;57,600</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500">{{ __('Your payout') }}</dt>
                            <dd class="mt-1 text-lg font-semibold text-brand-600">&#8369;48,960</dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-xs text-zinc-400">{{ __('Illustrative figures for demonstration purposes.') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    <section class="bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-marketing.section-heading
                :eyebrow="__('From the community')"
                :title="__('Renters and owners, both covered')"
            />

            <div class="mt-14 grid gap-6 lg:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <figure class="flex flex-col rounded-2xl border border-zinc-200 bg-white p-6">
                        <div class="flex gap-0.5" aria-label="{{ trans_choice('{1} :count star|[2,*] :count stars', $testimonial['rating'], ['count' => $testimonial['rating']]) }}">
                            @for ($i = 0; $i < $testimonial['rating']; $i++)
                                <svg class="size-4 text-amber-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 2.5 15 9l7 .8-5.2 4.7 1.5 6.9L12 17.9 5.7 21.4l1.5-6.9L2 9.8 9 9l3-6.5Z" />
                                </svg>
                            @endfor
                        </div>

                        <blockquote class="mt-4 flex-1 text-base/7 text-zinc-700">
                            &ldquo;{{ $testimonial['quote'] }}&rdquo;
                        </blockquote>

                        <figcaption class="mt-6 flex items-center gap-3 border-t border-zinc-100 pt-5">
                            <span class="flex size-10 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700">
                                {{ \Illuminate\Support\Str::of($testimonial['name'])->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-zinc-900">{{ $testimonial['name'] }}</span>
                                <span class="block truncate text-sm text-zinc-500">{{ $testimonial['role'] }}</span>
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="relative isolate overflow-hidden bg-linear-to-br from-brand-700 via-brand-600 to-brand-500 py-20 lg:py-24">
        <x-marketing.glow variant="cta" class="opacity-60" />

        <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">
                {{ __('Your next trip is one search away') }}
            </h2>
            <p class="mx-auto mt-4 max-w-xl text-lg/8 text-pretty text-brand-100">
                {{ __('Join CarHub as a renter or list the vehicle already sitting in your garage.') }}
            </p>

            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a
                    href="{{ route('vehicles.index') }}"
                    class="rounded-lg bg-white px-6 py-3 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-50"
                >
                    {{ __('Find a vehicle') }}
                </a>
                <a
                    href="{{ route('register') }}"
                    class="rounded-lg border border-white/30 bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20"
                >
                    {{ __('List your vehicle') }}
                </a>
            </div>
        </div>
    </section>
</x-layouts::marketing>
