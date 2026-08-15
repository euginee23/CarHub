@php
    $sections = [
        ['id' => 'agreement', 'title' => __('1. The agreement')],
        ['id' => 'eligibility', 'title' => __('2. Eligibility and identity verification')],
        ['id' => 'booking', 'title' => __('3. Bookings, scheduling, and cancellation')],
        ['id' => 'payments', 'title' => __('4. Payments, fees, and deposits')],
        ['id' => 'contract', 'title' => __('5. The rental contract')],
        ['id' => 'renter', 'title' => __('6. Renter obligations')],
        ['id' => 'owner', 'title' => __('7. Owner obligations')],
        ['id' => 'privacy', 'title' => __('8. GPS tracking and privacy')],
        ['id' => 'liability', 'title' => __('9. Insurance, damage, and liability')],
        ['id' => 'suspension', 'title' => __('10. Suspension and termination')],
        ['id' => 'changes', 'title' => __('11. Changes to these terms')],
    ];
@endphp

<x-layouts::marketing
    :title="__('Terms & conditions')"
    :description="__('The terms governing use of the CarHub vehicle rental marketplace — eligibility, ID verification, bookings, payments, the rental contract, GPS tracking, and liability.')"
>
    {{-- Header --}}
    <section class="border-b border-zinc-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-20">
            <h1 class="text-4xl font-bold tracking-tight text-zinc-900 sm:text-5xl">{{ __('Terms & conditions') }}</h1>
            <p class="mt-4 max-w-2xl text-lg/8 text-zinc-600">
                {{ __('These terms govern your use of CarHub as a renter, as a vehicle owner, or both. Read them before you book or list.') }}
            </p>
            <p class="mt-6 inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3.5 py-1.5 text-sm font-medium text-amber-800">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path stroke-linecap="round" d="M12 8v4.5m0 3h.01" />
                </svg>
                {{ __('Sample document prepared for an academic prototype — not legal advice.') }}
            </p>
        </div>
    </section>

    <section class="bg-white py-14 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-[16rem_1fr] lg:gap-16">
                {{-- Table of contents --}}
                <nav aria-label="{{ __('Contents') }}" class="hidden lg:sticky lg:top-24 lg:block lg:h-fit">
                    <p class="text-sm font-semibold text-zinc-900">{{ __('Contents') }}</p>
                    <ul class="mt-4 space-y-1">
                        @foreach ($sections as $section)
                            <li>
                                <a href="#{{ $section['id'] }}" class="block rounded-lg px-3 py-1.5 text-sm text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900">
                                    {{ $section['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <x-marketing.prose class="max-w-3xl">
                    <h2 id="agreement">{{ __('1. The agreement') }}</h2>
                    <p>{{ __('CarHub operates a marketplace that connects people who own vehicles with people who want to rent them. CarHub is not a car rental company and does not own, lease, or operate any of the vehicles listed on the platform. Each rental is a direct agreement between the vehicle owner and the renter; CarHub provides the platform, the verification and payment infrastructure, and the contract used to record the transaction.') }}</p>
                    <p>{{ __('By creating an account, listing a vehicle, or booking a rental, you accept these terms.') }}</p>

                    <h2 id="eligibility">{{ __('2. Eligibility and identity verification') }}</h2>
                    <p>{{ __('To rent a vehicle on CarHub you must:') }}</p>
                    <ul>
                        <li>{{ __('Be at least 21 years of age.') }}</li>
                        <li>{{ __('Hold a driver\'s licence that remains valid for the entire rental period.') }}</li>
                        <li>{{ __('Submit two valid government-issued identification documents.') }}</li>
                    </ul>
                    <p>{{ __('The two-ID requirement is not waivable. The documents must be independently issued — for example a driver\'s licence together with a passport, UMID, PhilSys national ID, or postal ID — and the name on both must match the name on your CarHub account. Verification is performed once per account. CarHub may decline or revoke verification where documents are illegible, expired, inconsistent, or suspected to be altered.') }}</p>
                    <p>{{ __('Owners must additionally provide proof of ownership or authority to rent out the vehicle, together with valid registration, before a listing goes live.') }}</p>

                    <h2 id="booking">{{ __('3. Bookings, scheduling, and cancellation') }}</h2>
                    <p>{{ __('The booking calendar enforces the following rules. A request that violates any of them cannot proceed to payment.') }}</p>
                    <ul>
                        <li>{{ __('A rental must run for a minimum of one full day.') }}</li>
                        <li>{{ __('Pickup must be scheduled at least two hours after the time of booking.') }}</li>
                        <li>{{ __('Bookings may be made up to 90 days in advance.') }}</li>
                        <li>{{ __('The return date and time must fall after the pickup date and time.') }}</li>
                        <li>{{ __('A requested window may not overlap any confirmed booking on the same vehicle, nor any date the owner has blocked.') }}</li>
                    </ul>
                    <p><strong>{{ __('Cancellation.') }}</strong> {{ __('Renters may cancel free of charge up to 24 hours before the scheduled pickup. Cancellations made within 24 hours forfeit the first day of the rental. Where an owner cancels a confirmed booking, the renter is refunded in full and CarHub may apply penalties to the owner\'s account for repeat cancellations.') }}</p>
                    <p><strong>{{ __('Extensions.') }}</strong> {{ __('An active rental may be extended only where no confirmed booking follows immediately after it. Additional days are charged at the original daily rate.') }}</p>

                    <h2 id="payments">{{ __('4. Payments, fees, and deposits') }}</h2>
                    <p>{{ __('CarHub accepts GCash, Maya, major credit and debit cards, and direct bank transfer. Card details are tokenised by the payment processor and are not stored by CarHub.') }}</p>
                    <p>{{ __('Every payment is validated before a reservation is held. The rental total is authorised when the booking is requested and captured only once the owner accepts. Where the owner declines or fails to respond within the response window, the authorisation is released in full. A reservation is never confirmed against an unverified or partially settled payment.') }}</p>
                    <p>{{ __('CarHub charges a service fee of 15% of the rental subtotal, which covers payment processing, insurance administration, and platform support. The fee is displayed in the price breakdown before you confirm.') }}</p>
                    <p>{{ __('Owners may require a refundable security deposit, which is disclosed on the listing before booking. Deposits are released within three business days of the vehicle being returned in its original condition, less any documented deductions agreed under the rental contract.') }}</p>
                    <p>{{ __('Owner payouts are released to the nominated GCash, Maya, or bank account within 24 hours of trip completion.') }}</p>

                    <h2 id="contract">{{ __('5. The rental contract') }}</h2>
                    <p>{{ __('Each confirmed booking generates a digital rental contract that both parties sign at handover. The contract records:') }}</p>
                    <ul>
                        <li>{{ __('The identities of the owner and renter, and their verification status.') }}</li>
                        <li>{{ __('The vehicle, its registration details, and the agreed rental period.') }}</li>
                        <li>{{ __('The agreed daily rate, service fee, total, and any security deposit held.') }}</li>
                        <li>{{ __('Odometer reading, fuel level, and a photographic record of the vehicle\'s condition at pickup and again at return.') }}</li>
                        <li>{{ __('Any mileage limits, geographic restrictions, or additional conditions imposed by the owner.') }}</li>
                    </ul>
                    <p>{{ __('The signed contract is the authoritative record for any dispute over condition, fuel, mileage, or deposit deductions. Both parties retain a copy in their CarHub account.') }}</p>

                    <h2 id="renter">{{ __('6. Renter obligations') }}</h2>
                    <ul>
                        <li>{{ __('Only the verified renter named on the booking may drive the vehicle, unless an additional driver has been verified and added to the contract.') }}</li>
                        <li>{{ __('Operate the vehicle lawfully and in accordance with any restrictions recorded in the contract.') }}</li>
                        <li>{{ __('Do not sublet, re-rent, or use the vehicle for commercial passenger transport without the owner\'s written consent.') }}</li>
                        <li>{{ __('Do not operate the vehicle under the influence of alcohol or prohibited substances.') }}</li>
                        <li>{{ __('Return the vehicle at the agreed time, place, and fuel level. Late returns are charged pro rata and may incur penalties where they disrupt a following booking.') }}</li>
                        <li>{{ __('Report any accident, breakdown, or traffic violation to the owner and to CarHub without delay.') }}</li>
                        <li>{{ __('Traffic violations, tolls, and parking penalties incurred during the rental period remain the renter\'s responsibility.') }}</li>
                    </ul>

                    <h2 id="owner">{{ __('7. Owner obligations') }}</h2>
                    <ul>
                        <li>{{ __('List only vehicles you own or are lawfully authorised to rent out, with valid registration and insurance.') }}</li>
                        <li>{{ __('Ensure the vehicle is roadworthy, clean, and mechanically sound at the start of every rental.') }}</li>
                        <li>{{ __('Represent the vehicle accurately — photographs, specifications, features, and condition must reflect reality.') }}</li>
                        <li>{{ __('Keep your availability calendar current and honour confirmed bookings.') }}</li>
                        <li>{{ __('Disclose any mileage limit, geographic restriction, or special handling requirement before the booking is confirmed.') }}</li>
                        <li>{{ __('Complete the condition record at both handover points.') }}</li>
                    </ul>

                    <h2 id="privacy">{{ __('8. GPS tracking and privacy') }}</h2>
                    <p>{{ __('Vehicles listed on CarHub carry a GPS unit. Location data is collected for two purposes only: to support distance-based search, and to allow an owner to locate their vehicle during an active rental.') }}</p>
                    <p>{{ __('Renters are informed of tracking before booking and again at pickup, and consent is recorded in the rental contract. During a trip, position data is visible to the vehicle owner and to CarHub support staff handling an active incident. It is not visible to any other user and is not sold or shared with advertisers.') }}</p>
                    <p>{{ __('Tracking is disabled automatically when a rental is closed out. Trip location history is retained for 30 days for dispute resolution and is then deleted. Outside an active rental, CarHub does not track a renter\'s location.') }}</p>
                    <p>{{ __('Identity documents submitted for verification are stored encrypted, used solely to confirm identity, and are never shared with vehicle owners — an owner sees only whether a renter is verified.') }}</p>

                    <h2 id="liability">{{ __('9. Insurance, damage, and liability') }}</h2>
                    <p>{{ __('Every trip booked through CarHub includes third-party liability coverage and roadside assistance. Comprehensive coverage is available as an optional add-on at checkout and is recommended for longer trips.') }}</p>
                    <p>{{ __('Damage claims are assessed against the condition record captured in the rental contract at pickup and return. Where damage is established, the deposit may be applied toward repair costs, with the balance handled through the applicable insurance policy.') }}</p>
                    <p>{{ __('CarHub acts as an intermediary. It is not a party to the rental contract itself and is not liable for the condition, roadworthiness, or legal status of any listed vehicle, nor for the conduct of any user. Nothing in these terms excludes liability that cannot lawfully be excluded.') }}</p>

                    <h2 id="suspension">{{ __('10. Suspension and termination') }}</h2>
                    <p>{{ __('CarHub may suspend or terminate an account that submits falsified identification, misrepresents a vehicle, attempts to move a transaction off-platform to avoid verification, repeatedly cancels confirmed bookings, or otherwise breaches these terms. Where a suspension occurs mid-rental, the active booking is completed under the existing contract before the account is closed.') }}</p>

                    <h2 id="changes">{{ __('11. Changes to these terms') }}</h2>
                    <p>{{ __('These terms may be updated as the platform develops. Material changes will be notified by email and announced in the application at least 14 days before taking effect. Continued use after that date constitutes acceptance. Bookings already confirmed remain governed by the terms in force when they were made.') }}</p>

                    <p>{{ __('Questions about these terms can be sent through our') }} <a href="{{ route('contact') }}">{{ __('contact page') }}</a>.</p>
                </x-marketing.prose>
            </div>
        </div>
    </section>
</x-layouts::marketing>
