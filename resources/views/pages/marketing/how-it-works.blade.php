<x-layouts::marketing
    :title="__('How it works')"
    :description="__('How renting and hosting on CarHub works — from search and ID verification through validated payments, scheduling rules, and the digital rental contract.')"
>
    {{-- Header --}}
    <section class="relative isolate overflow-hidden border-b border-zinc-200 bg-white">
        <x-marketing.glow />

        <div class="relative mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8 lg:py-24">
            <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/70 px-3.5 py-1.5 text-sm font-medium text-brand-700 shadow-sm backdrop-blur">
                {{ __('How it works') }}
            </span>
            <h1 class="mt-6 text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl">
                {{ __('Two sides, one') }} <span class="text-gradient">{{ __('accountable process') }}</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg/8 text-pretty text-zinc-600">
                {{ __('Renting a stranger\'s car only works when both parties are verified, the money is validated, and the schedule cannot be double-booked. Here is exactly how CarHub handles all three.') }}
            </p>

            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a href="#for-renters" class="rounded-lg bg-brand-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700">
                    {{ __('I want to rent') }}
                </a>
                <a href="#for-owners" class="rounded-lg border border-zinc-300 bg-white px-5 py-3 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400">
                    {{ __('I want to host') }}
                </a>
            </div>
        </div>
    </section>

    {{-- For renters --}}
    <section id="for-renters" class="scroll-mt-24 bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-14 lg:grid-cols-[24rem_1fr] lg:gap-20">
                <div class="lg:sticky lg:top-24 lg:h-fit">
                    <x-marketing.section-heading
                        align="left"
                        :eyebrow="__('For renters')"
                        :title="__('From search to keys')"
                        :description="__('Five steps. Verification happens once — every booking after that is immediate.')"
                    />

                    <div class="mt-8 rounded-2xl border border-brand-200 bg-brand-50 p-5">
                        <p class="flex items-center gap-2 text-sm font-semibold text-brand-800">
                            <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.36.39-1.59 1.59M21 12h-2.25m-.39 6.36-1.59-1.59M12 18.75V21m-4.77-2.23-1.59 1.59M5.25 12H3m2.64-6.36L7.23 7.23" />
                                <circle cx="12" cy="12" r="3.25" />
                            </svg>
                            {{ __('Where the AI helps') }}
                        </p>
                        <p class="mt-2 text-sm/6 text-brand-900/80">
                            {{ __('Content-based filtering profiles every listing by body type, seats, transmission, features, and price band, then ranks them against your search history and past trips — so the first three results are usually the right ones.') }}
                        </p>
                    </div>
                </div>

                <div>
                    <x-marketing.step number="1" :title="__('Search or get matched')">
                        {{ __('Filter by city, dates, body type, transmission, seats, and daily rate. If you would rather not filter at all, the recommendation engine surfaces the closest matches to what you have booked before.') }}
                    </x-marketing.step>

                    <x-marketing.step number="2" :title="__('Choose your dates')">
                        {{ __('Bookings run for a minimum of one day, start at least two hours from now, and reach up to 90 days ahead. The calendar blocks any window that overlaps an existing reservation, so a vehicle can never be double-booked.') }}
                    </x-marketing.step>

                    <x-marketing.step number="3" :title="__('Verify your identity')">
                        {{ __('Upload two valid government-issued IDs plus your driver\'s licence. Both IDs are matched against the name on your account. This is a one-time check — later bookings skip straight to payment.') }}
                    </x-marketing.step>

                    <x-marketing.step number="4" :title="__('Pay and get confirmed')">
                        {{ __('Pay with GCash, Maya, card, or bank transfer. The amount is authorised, validated, and only captured once the owner accepts. If they decline or let the window lapse, the authorisation is released in full.') }}
                    </x-marketing.step>

                    <x-marketing.step number="5" :title="__('Sign, drive, return')" :last="true">
                        {{ __('At pickup you and the owner sign a digital contract recording condition, fuel, mileage, and deposit. The trip is GPS-tracked and insured until you return the vehicle, then it closes out and tracking stops.') }}
                    </x-marketing.step>
                </div>
            </div>
        </div>
    </section>

    {{-- For owners --}}
    <section id="for-owners" class="scroll-mt-24 border-y border-zinc-200 bg-zinc-50 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-14 lg:grid-cols-[24rem_1fr] lg:gap-20">
                <div class="lg:sticky lg:top-24 lg:h-fit">
                    <x-marketing.section-heading
                        align="left"
                        :eyebrow="__('For owners')"
                        :title="__('From listing to payout')"
                        :description="__('You keep control over who drives your vehicle, when, and for how much.')"
                    />

                    <div class="mt-8 rounded-2xl border border-brand-200 bg-brand-50 p-5">
                        <p class="flex items-center gap-2 text-sm font-semibold text-brand-800">
                            <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 20.25h18M6.75 16.5V9.75M11.25 16.5V6.75M15.75 16.5v-4.5M20.25 16.5V4.5" />
                            </svg>
                            {{ __('Where the AI helps') }}
                        </p>
                        <p class="mt-2 text-sm/6 text-brand-900/80">
                            {{ __('An LSTM demand model reads historical bookings, seasonality, and local events to project the coming weeks, then suggests a daily rate that reflects real demand. You can accept it, adjust it, or ignore it entirely.') }}
                        </p>
                    </div>
                </div>

                <div>
                    <x-marketing.step number="1" :title="__('List your vehicle')">
                        {{ __('Add photos, specs, and features, then prove ownership with your OR/CR and a valid ID. Listings go live once verification clears.') }}
                    </x-marketing.step>

                    <x-marketing.step number="2" :title="__('Set availability and pricing')">
                        {{ __('Block out the days you need the vehicle yourself. Start from the suggested daily rate or set your own — you can change it any time without affecting confirmed bookings.') }}
                    </x-marketing.step>

                    <x-marketing.step number="3" :title="__('Review and accept requests')">
                        {{ __('See each renter\'s verification status, rating, and trip history before you decide. Turn on Instant Book to accept qualified renters automatically, or leave every request for manual approval.') }}
                    </x-marketing.step>

                    <x-marketing.step number="4" :title="__('Hand over and track')">
                        {{ __('Sign the digital contract together at pickup. For the duration of the trip you can see your vehicle live on a map; tracking switches off automatically at return.') }}
                    </x-marketing.step>

                    <x-marketing.step number="5" :title="__('Get paid')" :last="true">
                        {{ __('Payouts land in your GCash, Maya, or bank account within 24 hours of the trip completing. CarHub takes a 15% service fee covering payment processing, insurance, and support.') }}
                    </x-marketing.step>
                </div>
            </div>
        </div>
    </section>

    {{-- Payments & security --}}
    <section class="bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-marketing.section-heading
                :eyebrow="__('Payments & security')"
                :title="__('Money moves only when it should')"
                :description="__('Every payment is authorised, validated, and matched to a booking before a reservation is held.')"
            />

            <div class="mt-14 grid gap-6 lg:grid-cols-3">
                <x-marketing.feature-card :title="__('Accepted methods')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5a.75.75 0 0 1 .75.75v9a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1-.75-.75v-9a.75.75 0 0 1 .75-.75Z" />
                            <path stroke-linecap="round" d="M3 10.5h18M6.75 14.25h3" />
                        </svg>
                    </x-slot:icon>
                    {{ __('GCash, Maya, Visa and Mastercard, and direct bank transfer. Card details never touch CarHub\'s servers — they are tokenised by the payment processor.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Payment validation')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21c4.4-1.4 7.5-5.2 7.5-9.7V6.2L12 3 4.5 6.2v5.1c0 4.5 3.1 8.3 7.5 9.7Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.25 12 1.9 1.9 3.6-3.8" />
                        </svg>
                    </x-slot:icon>
                    {{ __('A booking is never held on an unconfirmed payment. The amount is authorised at request, verified against the booking total, and captured only after the owner accepts.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Deposits & refunds')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9.75h7.5m-7.5 4.5h4.5M4.5 4.5h15a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-1.2.6l-2.3-1.7-2.3 1.7-2.45-1.7-2.45 1.7-2.3-1.7-2.3 1.7a.75.75 0 0 1-1.2-.6V5.25a.75.75 0 0 1 .75-.75Z" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Refundable deposits are shown before you book and released within three business days of a clean return. Free cancellation up to 24 hours before pickup.') }}
                </x-marketing.feature-card>
            </div>
        </div>
    </section>

    {{-- Scheduling rules --}}
    <section class="border-t border-zinc-200 bg-zinc-50 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-14 lg:grid-cols-2 lg:gap-20">
                <div>
                    <x-marketing.section-heading
                        align="left"
                        :eyebrow="__('Scheduling')"
                        :title="__('The booking rules, in plain terms')"
                        :description="__('These constraints are enforced by the calendar itself — an invalid booking cannot reach the payment step.')"
                    />
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-6 sm:p-8">
                    <ul class="space-y-5">
                        @foreach ([
                            __('A rental runs for a minimum of one full day.'),
                            __('Pickup must be at least two hours from the moment of booking.'),
                            __('Bookings can be made up to 90 days in advance.'),
                            __('The return date must fall after the pickup date.'),
                            __('A requested window cannot overlap an existing confirmed booking on the same vehicle.'),
                            __('Owner-blocked dates are removed from the calendar entirely.'),
                            __('Extensions are allowed only when no booking follows immediately after yours.'),
                        ] as $rule)
                            <li class="flex gap-3">
                                <svg class="mt-0.5 size-5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span class="text-base/7 text-zinc-600">{{ $rule }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="relative isolate overflow-hidden bg-linear-to-br from-brand-700 via-brand-600 to-brand-500 py-20">
        <x-marketing.glow variant="cta" class="opacity-60" />

        <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">{{ __('Ready when you are') }}</h2>
            <p class="mx-auto mt-4 max-w-xl text-lg/8 text-pretty text-brand-100">
                {{ __('Browse what is available near you, or read the fine print first — both are one click away.') }}
            </p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('vehicles.index') }}" class="rounded-lg bg-white px-6 py-3 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-50">
                    {{ __('Browse vehicles') }}
                </a>
                <a href="{{ route('faq') }}" class="rounded-lg border border-white/30 bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20">
                    {{ __('Read the FAQ') }}
                </a>
            </div>
        </div>
    </section>
</x-layouts::marketing>
