@php
    $researchers = [
        ['name' => 'Jayrald P. Ancheta', 'role' => __('Researcher')],
        ['name' => 'Christian J. Neri', 'role' => __('Researcher')],
        ['name' => 'Hannah Claire P. Pontillas', 'role' => __('Researcher')],
    ];
@endphp

<x-layouts::marketing
    :title="__('About')"
    :description="__('CarHub is a web-based vehicle rental marketplace connecting vehicle owners and renters, built as an undergraduate thesis in Computer Science.')"
>
    {{-- Header --}}
    <section class="relative isolate overflow-hidden border-b border-zinc-200 bg-white">
        <x-marketing.glow />

        <div class="relative mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8 lg:py-24">
            <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/70 px-3.5 py-1.5 text-sm font-medium text-brand-700 shadow-sm backdrop-blur">
                {{ __('About CarHub') }}
            </span>
            <h1 class="mt-6 text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl">
                {{ __('Cars sit idle. People need rides.') }}
                <span class="text-gradient">{{ __('That is the whole problem.') }}</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg/8 text-pretty text-zinc-600">
                {{ __('CarHub is a marketplace that puts privately owned vehicles to work — safely, transparently, and with enough structure that neither side has to take the other on trust alone.') }}
            </p>
        </div>
    </section>

    {{-- Problem / approach --}}
    <section class="bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-14 lg:grid-cols-2 lg:gap-20">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">{{ __('The problem') }}</h2>
                    <div class="mt-5 space-y-4 text-base/7 text-zinc-600">
                        <p>{{ __('Most privately owned vehicles in the Philippines are used a fraction of the week. Meanwhile, renting a car through traditional agencies is expensive, inventory is thin outside the major cities, and informal peer-to-peer rentals happen over group chats with no verification, no contract, and no recourse when something goes wrong.') }}</p>
                        <p>{{ __('Both sides carry real risk. Owners hand over a major asset to someone whose identity they cannot confirm. Renters send deposits to strangers and hope the vehicle exists and is roadworthy. Neither has a record to point to when a dispute happens.') }}</p>
                    </div>
                </div>

                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">{{ __('Our approach') }}</h2>
                    <div class="mt-5 space-y-4 text-base/7 text-zinc-600">
                        <p>{{ __('CarHub formalises the transaction without making it slow. Identity is verified once with two government-issued IDs. Payments are authorised and validated before a reservation is held. Every trip is governed by a digital contract that records the vehicle\'s condition at both ends, and GPS-tracked for its duration.') }}</p>
                        <p>{{ __('On top of that base sits the matching work a plain listings page cannot do: narrowing thousands of combinations down to the vehicles that actually suit a trip, helping owners price against real local demand, and locating vehicles by distance rather than by city label.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Technology --}}
    <section class="relative isolate overflow-hidden bg-zinc-950 py-20 lg:py-28">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <div class="absolute -left-24 top-0 size-[30rem] rounded-full bg-brand-600/20 blur-3xl"></div>
            <div class="absolute -right-24 bottom-0 size-[30rem] rounded-full bg-spark-500/15 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-spark-400">{{ __('How it holds together') }}</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">
                    {{ __('What is actually running underneath') }}
                </h2>
            </div>

            <div class="mt-14 grid gap-6 lg:grid-cols-3">
                <x-marketing.feature-card tone="dark" :title="__('Identity verification')" :badge="__('Two IDs')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5a.75.75 0 0 1 .75.75v9a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1-.75-.75v-9a.75.75 0 0 1 .75-.75Zm10.5 3.75h4.5m-4.5 3h3" />
                            <circle cx="8.25" cy="11.25" r="1.75" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Renters submit two independently issued government IDs before their first booking, matched against the name on the account. Owners prove registration and ownership before a listing goes live. Documents are stored encrypted and never shared between users.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card tone="dark" :title="__('Validated payments')" :badge="__('Authorise & capture')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5a.75.75 0 0 1 .75.75v9a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1-.75-.75v-9a.75.75 0 0 1 .75-.75Z" />
                            <path stroke-linecap="round" d="M3 10.5h18M6.75 14.25h3" />
                        </svg>
                    </x-slot:icon>
                    {{ __('The rental total is authorised when a booking is requested, checked against the booking record, and captured only once the owner accepts. A reservation is never held against an unverified payment, and a declined request releases the authorisation in full.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card tone="dark" :title="__('Location & tracking')" :badge="__('GPS')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20.25 3 22.5v-15L9 5.25m0 15 6-2.25m-6 2.25V5.25m6 12.75 6 2.25V6l-6-2.25m0 14.25V3.75" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Listings carry coordinates so search can be run by radius from a dropped pin. During an active rental the vehicle reports its position to the owner; the feed is closed the moment the trip ends.') }}
                </x-marketing.feature-card>
            </div>
        </div>
    </section>

    {{-- Principles --}}
    <section class="bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-marketing.section-heading
                :eyebrow="__('What we hold to')"
                :title="__('Three commitments')"
            />

            <div class="mt-14 grid gap-6 lg:grid-cols-3">
                @foreach ([
                    ['title' => __('Verification is not optional'), 'body' => __('Two valid IDs from every renter, proof of ownership from every host. There is no fast lane around it, because the fast lane is where fraud lives.')],
                    ['title' => __('Tracking has a boundary'), 'body' => __('Location data exists to protect a vehicle during a rental — nothing more. It is visible only to the owner, only while a trip is active, and it is disclosed up front.')],
                    ['title' => __('Pricing should be explainable'), 'body' => __('The model suggests; the owner decides. Renters see the full breakdown of rate, service fee, and deposit before committing to anything.')],
                ] as $index => $principle)
                    <div class="rounded-2xl border border-zinc-200 bg-white p-6">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-sm font-bold text-brand-700">
                            0{{ $index + 1 }}
                        </span>
                        <h3 class="mt-5 text-lg font-semibold text-zinc-900">{{ $principle['title'] }}</h3>
                        <p class="mt-2 text-sm/6 text-zinc-600">{{ $principle['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Research team --}}
    <section class="border-y border-zinc-200 bg-zinc-50 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-marketing.section-heading
                :eyebrow="__('The team')"
                :title="__('Built as undergraduate research')"
                :description="__('CarHub: A Web-Based Vehicle Rental Marketplace for Connecting Vehicle Owners and Renters — presented to the Department of Computer Science, College of Computing Studies, in partial fulfilment of the requirements for the degree of Bachelor of Science in Computer Science.')"
            />

            <div class="mx-auto mt-14 grid max-w-4xl gap-6 sm:grid-cols-3">
                @foreach ($researchers as $researcher)
                    <div class="rounded-2xl border border-zinc-200 bg-white p-6 text-center">
                        <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-linear-to-br from-brand-600 to-brand-500 text-lg font-semibold text-white">
                            {{ \Illuminate\Support\Str::of($researcher['name'])->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                        </span>
                        <p class="mt-4 font-semibold text-zinc-900">{{ $researcher['name'] }}</p>
                        <p class="mt-1 text-sm text-zinc-500">{{ $researcher['role'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mx-auto mt-6 max-w-md rounded-2xl border border-zinc-200 bg-white p-6 text-center">
                <p class="font-semibold text-zinc-900">Joemarie Delgado</p>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Thesis adviser') }}</p>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="relative isolate overflow-hidden bg-linear-to-br from-brand-700 via-brand-600 to-brand-500 py-20">
        <x-marketing.glow variant="cta" class="opacity-60" />

        <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">{{ __('Questions about the project?') }}</h2>
            <p class="mx-auto mt-4 max-w-xl text-lg/8 text-pretty text-brand-100">
                {{ __('We are happy to talk through the research, the models, or the platform itself.') }}
            </p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('contact') }}" class="rounded-lg bg-white px-6 py-3 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-50">
                    {{ __('Get in touch') }}
                </a>
                <a href="{{ route('vehicles.index') }}" class="rounded-lg border border-white/30 bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20">
                    {{ __('See the platform') }}
                </a>
            </div>
        </div>
    </section>
</x-layouts::marketing>
