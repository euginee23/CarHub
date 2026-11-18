<?php

use App\Actions\Bookings\AcceptRentalTerms;
use App\Actions\Bookings\GenerateRentalContract;
use App\Actions\Bookings\SignRentalContract;
use App\Actions\Payments\StartPayment;
use App\Enums\BookingStatus;
use App\Enums\IdentityStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\RentalContract;
use App\Models\TermsVersion;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Checkout')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public Booking $booking;

    public bool $agreeToTerms = false;

    public string $signature = '';

    /**
     * The signature the renter drew on the pad, uploaded as a PNG when they sign.
     */
    public ?TemporaryUploadedFile $signatureDrawing = null;

    public bool $agreeToContract = false;

    /**
     * Load the renter's booking, sending them back to the trip if checkout is over.
     */
    public function mount(Booking $booking): void
    {
        Gate::authorize('checkout', $booking);

        if (! $this->isInCheckout($booking)) {
            $this->redirectRoute('trips.show', $booking, navigate: true);

            return;
        }

        $this->booking = $booking->load(['vehicle.coverPhoto', 'vehicle.company', 'termsVersion', 'contract', 'latestPayment']);
    }

    /**
     * The rental terms the renter must accept.
     */
    #[Computed]
    public function currentTerms(): ?TermsVersion
    {
        return TermsVersion::current();
    }

    /**
     * Whether the renter has accepted the latest published terms.
     */
    #[Computed]
    public function termsAccepted(): bool
    {
        return $this->booking->hasAcceptedTerms()
            && ($this->currentTerms === null || $this->booking->terms_version_id === $this->currentTerms->id || $this->booking->contract?->isSigned());
    }

    /**
     * Whether the renter has two approved government IDs.
     */
    #[Computed]
    public function identityVerified(): bool
    {
        return Auth::user()->hasVerifiedIdentity();
    }

    /**
     * Whether both IDs are uploaded and an administrator has yet to approve them.
     */
    #[Computed]
    public function identityPending(): bool
    {
        return Auth::user()->identityStatus() === IdentityStatus::Pending;
    }

    /**
     * The rental contract, prepared as soon as every earlier step is complete.
     */
    #[Computed]
    public function contract(): ?RentalContract
    {
        if ($this->booking->contract || $this->booking->status !== BookingStatus::Approved || ! $this->termsAccepted || ! $this->identityVerified) {
            return $this->booking->contract;
        }

        return app(GenerateRentalContract::class)->handle($this->booking);
    }

    /**
     * The one thing the renter should do now: accept the terms, get IDs approved,
     * wait for the rental company, sign the contract, or pay.
     */
    #[Computed]
    public function focus(): string
    {
        return match (true) {
            $this->booking->status === BookingStatus::AwaitingPayment => 'payment',
            ! $this->termsAccepted => 'terms',
            ! $this->identityVerified => 'identity',
            $this->booking->status === BookingStatus::Requested => 'waiting',
            default => 'contract',
        };
    }

    /**
     * Each checkout step and how it shows: done, the current one, waiting on the
     * rental company, next up, or still locked.
     *
     * @return array<string, array{label: string, state: 'done'|'current'|'waiting'|'next'|'locked'}>
     */
    #[Computed]
    public function steps(): array
    {
        $signed = (bool) $this->booking->contract?->isSigned();

        return [
            'terms' => ['label' => __('Rental terms'), 'state' => match (true) {
                $this->termsAccepted => 'done',
                $this->focus === 'terms' => 'current',
                default => 'next',
            }],
            'identity' => ['label' => __('Two valid IDs'), 'state' => match (true) {
                $this->identityVerified => 'done',
                $this->focus === 'identity' => 'current',
                default => 'next',
            }],
            'contract' => ['label' => __('Rental contract'), 'state' => match (true) {
                $signed => 'done',
                $this->focus === 'contract' => 'current',
                $this->focus === 'waiting' => 'waiting',
                default => 'locked',
            }],
            'payment' => ['label' => __('Payment'), 'state' => $this->focus === 'payment' ? 'current' : 'locked'],
        ];
    }

    /**
     * The payment methods offered on the gateway's checkout page.
     *
     * @return array<int, PaymentMethod>
     */
    #[Computed]
    public function paymentMethods(): array
    {
        return PaymentMethod::enabled();
    }

    /**
     * Record the renter's agreement to the current rental terms.
     */
    public function acceptTerms(AcceptRentalTerms $acceptRentalTerms): void
    {
        Gate::authorize('checkout', $this->booking);

        $this->validate(['agreeToTerms' => ['accepted']], ['agreeToTerms.accepted' => __('Tick the box to confirm you agree to the rental terms.')]);

        abort_if($this->currentTerms === null, 503, 'No rental terms have been published.');

        $acceptRentalTerms->handle($this->booking, $this->currentTerms, request()->ip());

        // An unsigned contract prepared under older terms is refreshed to the accepted version.
        if ($this->booking->contract && ! $this->booking->contract->isSigned() && $this->booking->status === BookingStatus::Approved) {
            app(GenerateRentalContract::class)->handle($this->booking->load('termsVersion'));
        }

        $this->reset('agreeToTerms');
        $this->refreshSteps();
    }

    /**
     * Sign the contract and move on to payment.
     */
    public function signContract(SignRentalContract $signRentalContract): void
    {
        Gate::authorize('checkout', $this->booking);

        $this->validate([
            'signature' => ['required', 'string', 'max:255'],
            'signatureDrawing' => ['required', 'image', 'mimes:png', 'max:1024', 'dimensions:min_width=100,min_height=40,max_width=4000,max_height=2000'],
            'agreeToContract' => ['accepted'],
        ], [
            'signatureDrawing.required' => __('Draw your signature in the box first.'),
            'signatureDrawing.*' => __('We could not read your drawn signature. Clear the box and sign again.'),
            'agreeToContract.accepted' => __('Tick the box to confirm you agree to the contract.'),
        ]);

        $signRentalContract->handle($this->booking, Auth::user(), $this->signature, $this->signatureDrawing, request()->ip());

        $this->booking->load('contract');
        $this->reset(['signature', 'signatureDrawing', 'agreeToContract']);
        $this->refreshSteps();

        Flux::toast(variant: 'success', text: __('Contract signed.'));
    }

    /**
     * Open the gateway's hosted checkout for the booking total; the renter picks how to pay there.
     */
    public function pay(StartPayment $startPayment): void
    {
        Gate::authorize('checkout', $this->booking);

        $payment = $startPayment->handle($this->booking);

        $this->redirect($payment->checkout_url);
    }

    /**
     * Look again while the renter waits on someone else (the rental company's
     * answer or an administrator's ID review), leaving checkout once it is over.
     */
    public function checkProgress(): void
    {
        $this->booking->refresh();

        if (! $this->isInCheckout($this->booking)) {
            $this->redirectRoute('trips.show', $this->booking, navigate: true);

            return;
        }

        $this->refreshSteps();
    }

    /**
     * Re-evaluate the steps after the renter's IDs change.
     */
    #[On('identity-documents-updated')]
    public function refreshSteps(): void
    {
        unset($this->termsAccepted, $this->identityVerified, $this->identityPending, $this->contract, $this->focus, $this->steps);
    }

    /**
     * Whether the booking is still somewhere between request and payment.
     */
    protected function isInCheckout(Booking $booking): bool
    {
        return in_array($booking->status, [BookingStatus::Requested, BookingStatus::Approved, BookingStatus::AwaitingPayment], true);
    }
}; ?>

@php
    $focus = $this->focus;
    $steps = $this->steps;
    $user = auth()->user();
    $firstName = Str::before(Str::squish($user->name), ' ');
    $companyName = $booking->vehicle->company?->displayName() ?: __('the rental company');
    $deadline = $booking->payment_due_at;
    $shouldPoll = $focus === 'waiting' || ($focus === 'identity' && $this->identityPending);

    $coach = match ($focus) {
        'terms' => [
            'step' => __('Step 1 of 4'),
            'title' => __('Hi :name! Let’s get you on the road.', ['name' => $firstName]),
            'text' => __('First, read the rental rules — we made a short version for you. Then tick the box and tap “I agree”.'),
        ],
        'identity' => $this->identityPending
            ? [
                'step' => __('Step 2 of 4'),
                'title' => __('Thank you! We’re checking your IDs.'),
                'text' => __('An administrator is looking at your two IDs. You don’t need to do anything — this page updates by itself once they’re approved.'),
            ]
            : [
                'step' => __('Step 2 of 4'),
                'title' => __('Great job! Now, two photos of your IDs.'),
                'text' => __('Upload two different government IDs, like your driver’s license and your PhilSys ID. An administrator checks them for you.'),
            ],
        'waiting' => [
            'step' => __('Waiting for the rental company'),
            'title' => __('You’re all done for now — time for a coffee!'),
            'text' => __(':company is looking at your request. We’ll let you know when they answer, and this page updates by itself.', ['company' => Str::ucfirst($companyName)]),
        ],
        'contract' => [
            'step' => __('Step 3 of 4'),
            'title' => $booking->vehicle->instant_book
                ? __('Good news — this car is ready for you!')
                : __('Good news — :company said yes!', ['company' => $companyName]),
            'text' => __('Now read your rental contract, type your name and draw your signature. It only takes a minute.'),
        ],
        default => [
            'step' => __('Step 4 of 4 — the last one!'),
            'title' => __('Almost there! Pay to lock in your car.'),
            'text' => $deadline
                ? __('Pay ₱:amount and the car is yours for the trip. We’re holding it for you until :deadline.', ['amount' => number_format($booking->total), 'deadline' => $deadline->format('M j, g:i A')])
                : __('Pay ₱:amount and the car is yours for the trip.', ['amount' => number_format($booking->total)]),
        ],
    };

    $focusStop = ['terms' => 0, 'identity' => 1, 'waiting' => 1.5, 'contract' => 2, 'payment' => 3][$focus];
    $carPosition = $focusStop * 25;
    $stops = [
        ['label' => __('Rules'), 'done' => $steps['terms']['state'] === 'done'],
        ['label' => __('IDs'), 'done' => $steps['identity']['state'] === 'done'],
        ['label' => __('Contract'), 'done' => $steps['contract']['state'] === 'done'],
        ['label' => __('Pay'), 'done' => false],
        ['label' => __('Booked!'), 'done' => false],
    ];
@endphp

<div @if ($shouldPoll) wire:poll.15s="checkProgress" @endif>
    <x-app.page-header
        :title="__('Checkout')"
        :description="__('Four easy steps to confirm your :vehicle. We’ll walk you through each one.', ['vehicle' => $booking->vehicle->name])"
        :back="route('trips.show', $booking)"
        :back-label="__('Trip :reference', ['reference' => $booking->reference])"
    />

    <x-app.content class="space-y-6">
        @if (session('status'))
            <flux:callout variant="secondary" icon="information-circle" :heading="session('status')" data-test="checkout-status" />
        @endif

        {{-- Remembers the step on this device; when it moves on, scroll back to the journey and celebrate. --}}
        <span
            wire:key="focus-{{ $focus }}"
            class="hidden"
            x-init="
                let previous = null;
                try {
                    previous = sessionStorage.getItem('carhub-checkout-{{ $booking->id }}');
                    sessionStorage.setItem('carhub-checkout-{{ $booking->id }}', '{{ $focus }}');
                } catch (error) {}

                if (previous && previous !== '{{ $focus }}') {
                    setTimeout(() => {
                        document.getElementById('checkout-journey')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        window.dispatchEvent(new CustomEvent('checkout-celebrate'));
                    }, 80);
                }
            "
        ></span>

        {{-- The journey: a car drives along the road as each step is done, and a guide says what to do next --}}
        <section
            id="checkout-journey"
            x-data="{ celebrating: false, celebrateTimer: null }"
            x-on:checkout-celebrate.window="celebrating = true; clearTimeout(celebrateTimer); celebrateTimer = setTimeout(() => celebrating = false, 1700)"
            class="scroll-mt-4 overflow-hidden rounded-2xl border border-zinc-200 bg-white px-5 pb-6 pt-4 sm:px-7"
        >
            <div class="relative mx-4 h-24 sm:mx-10" aria-hidden="true">
                <div class="absolute inset-x-0 top-11 h-3 rounded-full bg-zinc-100"></div>
                <div class="absolute left-0 top-11 h-3 rounded-full bg-linear-to-r from-brand-500 to-spark-400 transition-[width] duration-1000 ease-out" style="width: {{ $carPosition }}%"></div>
                <div class="absolute inset-x-1 top-[3.05rem] border-t-2 border-dashed border-white/80"></div>

                @foreach ($stops as $index => $stop)
                    @php
                        $stopState = $stop['done'] ? 'done' : ((float) $index === (float) $focusStop ? 'here' : 'ahead');
                    @endphp

                    <div wire:key="stop-{{ $index }}-{{ $stopState }}" class="absolute top-11 -translate-x-1/2" style="left: {{ $index * 25 }}%">
                        <span @class([
                            'relative -mt-1 flex size-5 items-center justify-center rounded-full ring-4 ring-white',
                            'bg-emerald-500 text-white motion-safe:animate-date-pop' => $stopState === 'done',
                            'bg-brand-600' => $stopState === 'here',
                            'bg-zinc-200 text-zinc-500' => $stopState === 'ahead',
                        ])>
                            @if ($stopState === 'done')
                                <flux:icon.check variant="micro" class="size-3.5" />
                            @elseif ($loop->last)
                                <flux:icon.flag variant="micro" class="size-3" />
                            @endif
                        </span>
                        <span @class([
                            'absolute left-1/2 top-6 -translate-x-1/2 whitespace-nowrap text-xs font-semibold',
                            'text-emerald-700' => $stopState === 'done',
                            'text-brand-700' => $stopState === 'here',
                            'text-zinc-400' => $stopState === 'ahead',
                        ])>{{ $stop['label'] }}</span>
                    </div>
                @endforeach

                {{-- The car --}}
                <div class="absolute top-0 z-10 -translate-x-1/2 transition-[left] duration-1000 ease-out" style="left: {{ $carPosition }}%" data-test="journey-car">
                    <div class="relative motion-safe:animate-car-arrive">
                        <div @class(['motion-safe:animate-car-bob' => $focus !== 'waiting'])>
                            <x-app-logo-icon class="size-11 text-brand-600 drop-shadow-md" />
                        </div>

                        @if ($focus === 'waiting')
                            <span class="absolute -right-3 -top-1 flex size-6 items-center justify-center rounded-full bg-amber-400 text-white shadow motion-safe:animate-bounce">
                                <flux:icon.clock variant="micro" class="size-4" />
                            </span>
                        @endif

                        <span x-cloak x-show="celebrating" class="pointer-events-none absolute left-1/2 top-5">
                            @foreach (range(0, 15) as $piece)
                                @php
                                    $angle = deg2rad($piece * 22.5 - 90);
                                    $distance = 44 + ($piece % 3) * 16;
                                    $colors = ['bg-brand-500', 'bg-spark-400', 'bg-amber-400', 'bg-emerald-400', 'bg-pink-400'];
                                @endphp
                                <span
                                    @class(['absolute size-2 rounded-[2px] motion-safe:animate-confetti', $colors[$piece % 5]])
                                    style="--confetti-x: {{ round(cos($angle) * $distance) }}px; --confetti-y: {{ round(sin($angle) * $distance) }}px; --confetti-turn: {{ ($piece % 2 ? 1 : -1) * (200 + $piece * 15) }}deg; animation-delay: {{ ($piece % 4) * 25 }}ms;"
                                ></span>
                            @endforeach
                        </span>
                        <span x-cloak x-show="celebrating" x-transition.opacity class="absolute -top-7 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-emerald-500 px-2.5 py-0.5 text-xs font-bold text-white shadow">
                            {{ __('Nice work!') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- The guide --}}
            <div class="mt-4 flex items-start gap-3 sm:gap-4" aria-live="polite">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-500 to-brand-700 text-white shadow-lg shadow-brand-600/30" aria-hidden="true">
                    <flux:icon.hand-raised variant="solid" class="size-6 origin-bottom motion-safe:animate-wave" />
                </span>

                <div
                    wire:key="coach-{{ $focus }}-{{ $this->identityPending ? 'pending' : 'open' }}"
                    class="min-w-0 flex-1 rounded-2xl rounded-tl-md bg-brand-50 px-4 py-4 motion-safe:animate-step-in sm:px-5"
                    data-test="checkout-coach"
                >
                    <p class="text-xs font-bold uppercase tracking-wide text-brand-600">{{ $coach['step'] }}</p>
                    <p class="mt-1 text-lg font-bold leading-snug text-brand-950 sm:text-xl">{{ $coach['title'] }}</p>
                    <p class="mt-1.5 text-base leading-relaxed text-brand-950/75">{{ $coach['text'] }}</p>

                    @if ($focus !== 'waiting' && ! ($focus === 'identity' && $this->identityPending))
                        <button
                            type="button"
                            x-on:click="document.getElementById('current-step')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                        >
                            {{ __('Show me where') }}
                            <flux:icon.arrow-down variant="micro" class="size-4 motion-safe:animate-bounce" />
                        </button>
                    @endif
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-4">
                {{-- 1. Rental terms --}}
                <x-booking.checkout-step
                    wire:key="step-terms-{{ $steps['terms']['state'] }}"
                    number="1"
                    :title="$steps['terms']['label']"
                    :state="$steps['terms']['state']"
                    :note="$booking->terms_accepted_at ? __('Accepted :date · version :version', ['date' => $booking->terms_accepted_at->format('M j'), 'version' => $booking->termsVersion?->version ?? '—']) : null"
                >
                    @if ($steps['terms']['state'] === 'done' && $booking->termsVersion)
                        <x-booking.legal-text class="max-h-72 overflow-y-auto rounded-lg border border-emerald-100 bg-white p-4">
                            {{ $booking->termsVersion->html() }}
                        </x-booking.legal-text>
                    @elseif ($steps['terms']['state'] === 'current')
                        @if ($this->currentTerms)
                            <p class="text-base text-zinc-700">{{ __('Here are the most important rules, in plain words:') }}</p>

                            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                                @foreach ([
                                    ['user', __('You’re 21 or older and have a driver’s license that is valid for the whole trip.')],
                                    ['identification', __('Only you — the verified renter — may drive the car.')],
                                    ['credit-card', __('You pay online through CarHub only. The booking is confirmed once your payment goes through.')],
                                    ['calendar-days', __('You can cancel for free up to 24 hours before pickup.')],
                                    ['clock', __('Bring the car back on time, with the same amount of fuel.')],
                                    ['map-pin', __('The car’s GPS shares its location during your trip only.')],
                                    ['wrench-screwdriver', __('You are responsible for damage you cause, beyond normal wear and tear.')],
                                    ['shield-check', __('Traffic tickets, tolls, and parking fees during your trip are yours to pay.')],
                                ] as $index => [$icon, $rule])
                                    <li class="flex items-start gap-3 rounded-xl bg-zinc-50 p-3.5 motion-safe:animate-step-in" style="animation-delay: {{ $index * 60 }}ms">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-sm">
                                            <flux:icon :name="$icon" variant="mini" class="size-5" />
                                        </span>
                                        <span class="text-sm leading-relaxed text-zinc-800">{{ $rule }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-3 text-xs text-zinc-500">{{ __('This is a quick summary. The full rental terms below are what count.') }}</p>

                            <div x-data="{ open: false }" class="mt-4 rounded-xl border border-zinc-200">
                                <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left" :aria-expanded="open">
                                    <span class="flex items-center gap-2 text-sm font-semibold text-zinc-800">
                                        <flux:icon.document-text variant="mini" class="size-5 text-zinc-400" />
                                        <span x-text="open ? @js(__('Hide the full rental terms')) : @js(__('Read the full rental terms'))">{{ __('Read the full rental terms') }}</span>
                                    </span>
                                    <span class="flex items-center gap-2 text-xs font-medium text-zinc-500">
                                        {{ __('Version :version', ['version' => $this->currentTerms->version]) }}
                                        <flux:icon.chevron-down variant="micro" class="size-4 transition" ::class="{ 'rotate-180': open }" />
                                    </span>
                                </button>
                                <div x-cloak x-show="open" x-collapse>
                                    <x-booking.legal-text class="max-h-80 overflow-y-auto border-t border-zinc-200 p-4">
                                        {{ $this->currentTerms->html() }}
                                    </x-booking.legal-text>
                                </div>
                            </div>

                            <form wire:submit="acceptTerms" class="mt-6 space-y-3">
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-xl border-2 p-4 transition duration-300"
                                    :class="$wire.agreeToTerms ? 'border-emerald-400 bg-emerald-50' : 'border-zinc-300 bg-white hover:border-brand-400 hover:bg-brand-50/40'"
                                >
                                    <input type="checkbox" wire:model="agreeToTerms" class="mt-0.5 size-6 shrink-0 cursor-pointer accent-emerald-600" data-test="agree-terms" />
                                    <span class="text-base leading-relaxed text-zinc-800">{{ __('I have read and agree to the rental terms, including the cancellation, payment, and GPS tracking conditions.') }}</span>
                                </label>
                                <flux:error name="agreeToTerms" />

                                <button
                                    type="submit"
                                    x-bind:disabled="! $wire.agreeToTerms"
                                    data-test="accept-terms"
                                    class="group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-brand-600 px-6 py-4 text-lg font-bold text-white shadow-lg shadow-brand-600/25 transition hover:bg-brand-700 active:scale-[0.98] disabled:cursor-not-allowed disabled:bg-zinc-200 disabled:text-zinc-500 disabled:shadow-none sm:w-auto"
                                >
                                    <span class="in-data-loading:hidden">{{ __('I agree — next step') }}</span>
                                    <flux:icon.arrow-right variant="mini" class="size-5 transition group-hover:translate-x-1 group-disabled:hidden in-data-loading:hidden" />
                                    <span class="hidden in-data-loading:inline">{{ __('Saving…') }}</span>
                                </button>
                                <p x-show="! $wire.agreeToTerms" class="flex items-center gap-1.5 text-sm text-zinc-500">
                                    <flux:icon.arrow-up variant="micro" class="size-4 motion-safe:animate-bounce" />
                                    {{ __('Tick the box first, then the button turns on.') }}
                                </p>
                            </form>
                        @else
                            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Rental terms are being updated. Please check back shortly.')" />
                        @endif
                    @endif
                </x-booking.checkout-step>

                {{-- 2. Two valid IDs --}}
                <x-booking.checkout-step
                    wire:key="step-identity-{{ $steps['identity']['state'] }}"
                    number="2"
                    :title="$steps['identity']['label']"
                    :state="$steps['identity']['state']"
                    :note="match ($steps['identity']['state']) {
                        'done' => __('Both IDs approved — you’re verified.'),
                        'next' => __('Comes right after the rental terms.'),
                        default => null,
                    }"
                >
                    @if ($steps['identity']['state'] === 'current')
                        <livewire:identity.id-documents wire:key="checkout-id-documents" />

                        @unless ($this->identityPending)
                            <div class="mt-5 rounded-xl bg-zinc-50 p-4">
                                <p class="flex items-center gap-2 text-sm font-semibold text-zinc-800">
                                    <flux:icon.camera variant="mini" class="size-5 text-brand-600" />
                                    {{ __('Tips for a good photo') }}
                                </p>
                                <ul class="mt-2 grid gap-2 text-sm text-zinc-600 sm:grid-cols-3">
                                    @foreach ([__('Use good light, with no glare.'), __('Show all four corners of the ID.'), __('Make sure your name and photo are sharp.')] as $tip)
                                        <li class="flex items-start gap-1.5">
                                            <flux:icon.check-circle variant="micro" class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                            {{ $tip }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endunless
                    @endif
                </x-booking.checkout-step>

                {{-- 3. Rental contract --}}
                <x-booking.checkout-step
                    wire:key="step-contract-{{ $steps['contract']['state'] }}"
                    number="3"
                    :title="$steps['contract']['label']"
                    :state="$steps['contract']['state']"
                    :note="match ($steps['contract']['state']) {
                        'done' => __('Signed :date as “:signature”', ['date' => $booking->contract?->renter_signed_at?->format('M j') ?? '', 'signature' => $booking->contract?->renter_signature]),
                        'locked' => $booking->status === BookingStatus::Requested
                            ? __('The contract is prepared once the rental company approves your request.')
                            : __('Accept the rental terms and get two IDs approved to prepare your contract.'),
                        default => null,
                    }"
                >
                    @if ($steps['contract']['state'] === 'waiting')
                        <p class="text-base font-medium text-amber-950">{{ __('The contract is prepared once the rental company approves your request.') }}</p>
                        <p class="mt-1.5 text-sm text-amber-900/80">{{ __('You can close this page — we’ll let you know when :company answers.', ['company' => $companyName]) }}</p>
                        <p class="mt-3 flex items-center gap-2 text-xs font-medium text-amber-800">
                            <span class="relative flex size-2">
                                <span class="absolute inline-flex size-full rounded-full bg-amber-500 opacity-75 motion-safe:animate-ping"></span>
                                <span class="relative inline-flex size-2 rounded-full bg-amber-500"></span>
                            </span>
                            {{ __('Checking for an answer every 15 seconds…') }}
                        </p>
                    @elseif ($steps['contract']['state'] === 'done' && $booking->contract)
                        <div class="max-h-72 overflow-y-auto rounded-lg border border-emerald-100 bg-white p-4">
                            @include('contracts.document', ['contract' => $booking->contract])
                        </div>
                        <flux:button size="sm" class="mt-3" :href="route('bookings.contract', $booking)" target="_blank" icon="printer">{{ __('Open printable copy') }}</flux:button>
                    @elseif ($steps['contract']['state'] === 'current' && $this->contract)
                        <p class="text-base text-zinc-700">{{ __('This is your agreement with :company. Here it is in short:', ['company' => $companyName]) }}</p>

                        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ([
                                ['truck', __('Your car'), $booking->vehicle->year.' '.$booking->vehicle->name],
                                ['building-storefront', __('Rental company'), Str::ucfirst($companyName)],
                                ['arrow-right-circle', __('You pick it up'), $booking->pickup_at->format('D, M j · g:i A')],
                                ['arrow-left-circle', __('You bring it back'), $booking->return_at->format('D, M j · g:i A')],
                                ['map-pin', __('Where'), $booking->pickup_location],
                                ['banknotes', __('Total to pay'), '₱'.number_format($booking->total)],
                            ] as $index => [$icon, $label, $value])
                                <div class="flex items-start gap-3 rounded-xl bg-zinc-50 p-3.5 motion-safe:animate-step-in" style="animation-delay: {{ $index * 60 }}ms">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-sm">
                                        <flux:icon :name="$icon" variant="mini" class="size-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $label }}</dt>
                                        <dd class="mt-0.5 font-semibold text-zinc-900">{{ $value }}</dd>
                                    </div>
                                </div>
                            @endforeach
                        </dl>

                        <div class="mt-4 rounded-xl border border-zinc-200">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-4 py-3">
                                <p class="flex items-center gap-2 text-sm font-semibold text-zinc-800">
                                    <flux:icon.document-text variant="mini" class="size-5 text-zinc-400" />
                                    {{ __('The full contract') }}
                                </p>
                                <a href="{{ route('bookings.contract', $booking) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:underline">
                                    <flux:icon.printer variant="micro" class="size-4" />
                                    {{ __('Open printable copy') }}
                                </a>
                            </div>
                            <div class="max-h-80 overflow-y-auto p-4">
                                @include('contracts.document', ['contract' => $this->contract])
                            </div>
                        </div>

                        @if ($booking->return_at->isPast())
                            <div class="mt-6 rounded-2xl border-2 border-amber-300 bg-amber-50 p-5" role="alert" data-test="rental-over">
                                <p class="flex items-center gap-2 text-lg font-bold text-amber-900">
                                    <flux:icon.calendar variant="mini" class="size-6 shrink-0" />
                                    {{ __('These dates are over') }}
                                </p>
                                <p class="mt-2 text-base text-amber-900">
                                    {{ __('This rental was meant to end :time, so it can’t be signed any more. Pick new dates to book the car again — it only takes a minute.', ['time' => $booking->return_at->format('M j, g:i A')]) }}
                                </p>
                                <flux:button variant="primary" class="mt-4" :href="route('vehicles.show', $booking->vehicle)" icon="calendar-days">{{ __('Pick new dates') }}</flux:button>
                            </div>
                        @elseif (! $this->contract->isSigned())
                            @if ($booking->pickup_at->isPast())
                                <div class="mt-6 flex items-start gap-3 rounded-xl bg-sky-50 p-4 text-sky-900" data-test="late-pickup">
                                    <flux:icon.clock variant="mini" class="mt-0.5 size-5 shrink-0 text-sky-600" />
                                    <p class="text-sm leading-relaxed">
                                        <strong class="font-semibold">{{ __('Running late? No problem.') }}</strong>
                                        {{ __('Sign and pay, then pick up the car as soon as you can. Your rental still ends :time.', ['time' => $booking->return_at->format('M j, g:i A')]) }}
                                    </p>
                                </div>
                            @endif

                            <form
                                x-on:submit.prevent="signNow()"
                                x-on:signature-changed="hasSignature = $event.detail.hasSignature"
                                class="mt-6 space-y-6"
                                x-data="{
                                    expected: @js(Str::lower(Str::squish($user->name))),
                                    hasSignature: false,
                                    saving: false,
                                    problem: null,
                                    giveUp: null,
                                    init() {
                                        {{-- Registered once: the remover $interceptMessage returns throws in Livewire 4.4. --}}
                                        this.$wire.$interceptMessage(({ onError, onFailure }) => {
                                            if (! this.saving) {
                                                return;
                                            }

                                            onError(({ response, preventDefault }) => {
                                                this.problem = response.status === 419 ? 'session' : 'server';

                                                if (response.status === 419) {
                                                    preventDefault();
                                                }

                                                this.giveUp?.();
                                            });
                                            onFailure(() => {
                                                this.problem = 'network';
                                                this.giveUp?.();
                                            });
                                        });
                                    },
                                    get matches() {
                                        return (this.$wire.signature || '').trim().replace(/\s+/g, ' ').toLowerCase() === this.expected;
                                    },
                                    get ready() {
                                        return this.matches && this.hasSignature && this.$wire.agreeToContract;
                                    },
                                    async signNow() {
                                        if (! this.ready || this.saving) {
                                            return;
                                        }

                                        this.saving = true;
                                        this.problem = null;

                                        try {
                                            {{-- An x-ref on the pad would belong to the pad's own x-data, so find it in the DOM. --}}
                                            const drawing = await window.Alpine.$data(this.$root.querySelector('[data-signature-pad]')).toFile();

                                            {{-- Livewire 4.4's $upload takes callbacks rather than returning a promise, and never calls
                                                 back when its first request fails, so the interceptor in init() gives up for it. --}}
                                            await new Promise((resolve, reject) => {
                                                this.giveUp = () => reject(new Error('The request failed.'));
                                                this.$wire.$upload('signatureDrawing', drawing, resolve, reject, () => {}, () => reject(new Error('The upload was cancelled.')));
                                            });
                                            await this.$wire.signContract();
                                        } catch (error) {
                                            this.$wire.$cancelUpload('signatureDrawing');

                                            if (! this.problem) {
                                                this.problem = 'other';
                                                console.error(error);
                                            }
                                        } finally {
                                            this.giveUp = null;
                                            this.saving = false;
                                        }
                                    },
                                }"
                                data-test="sign-form"
                            >
                                {{-- 1. The name, checked against the account --}}
                                <div>
                                    <label for="signature" class="flex items-center gap-2.5 text-lg font-bold text-zinc-900">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-bold transition duration-300" :class="matches ? 'bg-emerald-500 text-white' : 'bg-brand-100 text-brand-700'">
                                            <span x-show="! matches">1</span>
                                            <span x-cloak x-show="matches"><flux:icon.check variant="micro" class="size-4" /></span>
                                        </span>
                                        {{ __('Type your full name') }}
                                    </label>
                                    <p class="mt-1.5 text-sm text-zinc-600">
                                        {{ __('Type it exactly like this:') }}
                                        <strong class="rounded bg-zinc-100 px-1.5 py-0.5 font-semibold text-zinc-900">{{ $user->name }}</strong>
                                    </p>
                                    <input
                                        id="signature"
                                        type="text"
                                        wire:model="signature"
                                        autocomplete="name"
                                        autocapitalize="words"
                                        placeholder="{{ $user->name }}"
                                        class="mt-3 h-14 w-full rounded-xl border-2 bg-white px-4 text-lg text-zinc-900 transition focus:outline-hidden focus:ring-4 focus:ring-brand-100"
                                        :class="matches ? 'border-emerald-400' : 'border-zinc-300 focus:border-brand-500'"
                                        data-test="signature-input"
                                    />
                                    <p x-cloak x-show="matches" x-transition.opacity class="mt-2 flex items-center gap-1.5 text-sm font-semibold text-emerald-700">
                                        <flux:icon.check-circle variant="micro" class="size-4" />
                                        {{ __('That matches your name.') }}
                                    </p>
                                    <flux:error name="signature" />
                                </div>

                                {{-- 2. The drawn signature, saved as an image --}}
                                <div>
                                    <p class="flex items-center gap-2.5 text-lg font-bold text-zinc-900">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-bold transition duration-300" :class="hasSignature ? 'bg-emerald-500 text-white' : 'bg-brand-100 text-brand-700'">
                                            <span x-show="! hasSignature">2</span>
                                            <span x-cloak x-show="hasSignature"><flux:icon.check variant="micro" class="size-4" /></span>
                                        </span>
                                        {{ __('Draw your signature') }}
                                    </p>
                                    <p class="mt-1.5 text-sm text-zinc-600">{{ __('Use your finger, a stylus, or your mouse — just like signing on paper.') }}</p>

                                    <div wire:ignore class="mt-3">
                                        <div x-data="signaturePad" data-signature-pad class="relative select-none">
                                            <canvas
                                                x-ref="canvas"
                                                x-on:pointerdown="start($event)"
                                                x-on:pointermove="move($event)"
                                                x-on:pointerup="end()"
                                                x-on:pointercancel="end()"
                                                class="block h-48 w-full cursor-crosshair touch-none rounded-xl border-2 border-dashed border-brand-300 bg-amber-50/60 sm:h-52"
                                                role="img"
                                                aria-label="{{ __('Signature pad — draw your signature here') }}"
                                                data-test="signature-pad"
                                            ></canvas>

                                            <span class="pointer-events-none absolute bottom-10 left-5 text-2xl font-light leading-none text-zinc-400" aria-hidden="true">&times;</span>
                                            <span class="pointer-events-none absolute inset-x-5 bottom-10 border-b-2 border-zinc-300" aria-hidden="true"></span>
                                            <span class="pointer-events-none absolute bottom-3 left-5 right-5 flex justify-between text-xs text-zinc-500" aria-hidden="true">
                                                <span>{{ __('Renter’s signature') }}</span>
                                                <span>{{ now()->format('M j, Y') }}</span>
                                            </span>

                                            <div x-show="inkPoints === 0" x-transition.opacity class="pointer-events-none absolute inset-x-0 top-7 flex flex-col items-center gap-1.5 text-brand-700" aria-hidden="true">
                                                <flux:icon.pencil variant="mini" class="size-7 motion-safe:animate-bounce" />
                                                <span class="text-base font-semibold">{{ __('Sign here') }}</span>
                                            </div>

                                            <div x-cloak x-show="inkPoints > 0" x-transition.opacity class="absolute right-2 top-2 flex gap-1.5">
                                                <button type="button" x-on:click="undo()" class="inline-flex items-center gap-1 rounded-lg bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm ring-1 ring-zinc-200 transition hover:bg-zinc-50">
                                                    <flux:icon.arrow-uturn-left variant="micro" class="size-4" />
                                                    {{ __('Undo') }}
                                                </button>
                                                <button type="button" x-on:click="clear()" class="inline-flex items-center gap-1 rounded-lg bg-white px-3 py-2 text-sm font-medium text-red-600 shadow-sm ring-1 ring-zinc-200 transition hover:bg-red-50">
                                                    <flux:icon.trash variant="micro" class="size-4" />
                                                    {{ __('Clear') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <flux:error name="signatureDrawing" />
                                </div>

                                {{-- 3. Agreement --}}
                                <div>
                                    <p class="flex items-center gap-2.5 text-lg font-bold text-zinc-900">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-bold transition duration-300" :class="$wire.agreeToContract ? 'bg-emerald-500 text-white' : 'bg-brand-100 text-brand-700'">
                                            <span x-show="! $wire.agreeToContract">3</span>
                                            <span x-cloak x-show="$wire.agreeToContract"><flux:icon.check variant="micro" class="size-4" /></span>
                                        </span>
                                        {{ __('Tick to agree') }}
                                    </p>
                                    <label
                                        class="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border-2 p-4 transition duration-300"
                                        :class="$wire.agreeToContract ? 'border-emerald-400 bg-emerald-50' : 'border-zinc-300 bg-white hover:border-brand-400 hover:bg-brand-50/40'"
                                    >
                                        <input type="checkbox" wire:model="agreeToContract" class="mt-0.5 size-6 shrink-0 cursor-pointer accent-emerald-600" data-test="agree-contract" />
                                        <span class="text-base leading-relaxed text-zinc-800">{{ __('I agree to this rental contract and understand that my typed name and drawn signature are my electronic signature.') }}</span>
                                    </label>
                                    <flux:error name="agreeToContract" />
                                </div>

                                <div>
                                    <button
                                        type="submit"
                                        x-bind:disabled="! ready || saving"
                                        data-test="sign-contract"
                                        class="group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-brand-600 px-6 py-4 text-lg font-bold text-white shadow-lg shadow-brand-600/25 transition hover:bg-brand-700 active:scale-[0.98] disabled:cursor-not-allowed disabled:bg-zinc-200 disabled:text-zinc-500 disabled:shadow-none sm:w-auto"
                                    >
                                        <span x-show="! saving" class="inline-flex items-center gap-2">
                                            <flux:icon.pencil-square variant="mini" class="size-5" />
                                            {{ __('Sign contract') }}
                                        </span>
                                        <span x-cloak x-show="saving" class="inline-flex items-center gap-2">
                                            <svg class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
                                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                            </svg>
                                            {{ __('Saving your signature…') }}
                                        </span>
                                    </button>
                                    <p x-show="! ready && ! saving" class="mt-3 flex items-center gap-1.5 text-sm text-zinc-500">
                                        <flux:icon.arrow-up variant="micro" class="size-4 shrink-0 motion-safe:animate-bounce" />
                                        <span x-text="! matches ? @js(__('Type your name in step 1 first.')) : (! hasSignature ? @js(__('Now draw your signature in step 2.')) : @js(__('Now tick the box in step 3, then the button turns on.')))"></span>
                                    </p>
                                    <div x-cloak x-show="problem === 'session'" x-transition class="mt-4 rounded-xl border-2 border-amber-300 bg-amber-50 p-4" role="alert" data-test="session-expired">
                                        <p class="flex items-center gap-2 font-bold text-amber-900">
                                            <flux:icon.clock variant="mini" class="size-5 shrink-0" />
                                            {{ __('You were logged out because this page was open for a long time.') }}
                                        </p>
                                        <p class="mt-1 text-sm text-amber-900">{{ __('Nothing was signed yet. Log in again, then sign — it only takes a moment.') }}</p>
                                        <button type="button" x-on:click="window.location.reload()" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-amber-600">
                                            <flux:icon.arrow-right-end-on-rectangle variant="micro" class="size-4" />
                                            {{ __('Log in again') }}
                                        </button>
                                    </div>
                                    <p x-cloak x-show="problem === 'network'" class="mt-3 text-sm font-medium text-red-600" role="alert">
                                        {{ __('We couldn’t reach CarHub. Check your internet connection, then tap “Sign contract” again.') }}
                                    </p>
                                    <p x-cloak x-show="problem === 'server' || problem === 'other'" class="mt-3 text-sm font-medium text-red-600" role="alert">
                                        {{ __('Something went wrong while saving your signature. Tap “Sign contract” again. If it keeps happening, tap the chat button in the corner for help.') }}
                                    </p>
                                </div>
                            </form>
                        @endif
                    @endif
                </x-booking.checkout-step>

                {{-- 4. Payment --}}
                <x-booking.checkout-step
                    wire:key="step-payment-{{ $steps['payment']['state'] }}"
                    number="4"
                    :title="$steps['payment']['label']"
                    :state="$steps['payment']['state']"
                    :note="$steps['payment']['state'] === 'locked' ? __('Payment opens after you sign the contract.') : null"
                >
                    @if ($steps['payment']['state'] === 'current')
                        <div class="grid items-center gap-4 rounded-2xl bg-linear-to-br from-brand-600 to-brand-800 p-5 text-white shadow-lg shadow-brand-700/20 sm:grid-cols-[1fr_auto]">
                            <div>
                                <p class="text-sm font-medium text-brand-100">{{ __('Total to pay') }}</p>
                                <p class="mt-0.5 text-4xl font-bold tracking-tight">&#8369;{{ number_format($booking->total) }}</p>
                                <p class="mt-1 text-sm text-brand-100">
                                    {{ __('Rental ₱:rental + service fee ₱:fee', ['rental' => number_format($booking->subtotal), 'fee' => number_format($booking->service_fee)]) }}
                                </p>
                            </div>

                            @if ($deadline)
                                @php
                                    $secondsLeft = max(0, now()->diffInSeconds($deadline, false));
                                @endphp
                                <div
                                    x-data="{
                                        deadline: {{ $deadline->getTimestampMs() }},
                                        left: {{ (int) $secondsLeft * 1000 }},
                                        timer: null,
                                        init() {
                                            this.tick();
                                            this.timer = setInterval(() => this.tick(), 1000);
                                        },
                                        destroy() {
                                            clearInterval(this.timer);
                                        },
                                        tick() {
                                            this.left = Math.max(0, this.deadline - Date.now());
                                        },
                                        part(size, modulo) {
                                            return String(Math.floor(this.left / size) % modulo).padStart(2, '0');
                                        },
                                    }"
                                    class="rounded-xl bg-white/10 px-4 py-3 text-center ring-1 ring-white/20"
                                    data-test="payment-countdown"
                                >
                                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-100">{{ __('Car held for you') }}</p>
                                    <div class="mt-1 flex items-start justify-center gap-1 tabular-nums" x-show="left > 0">
                                        @foreach ([[3600000, 1000, __('hours')], [60000, 60, __('min')], [1000, 60, __('sec')]] as $index => [$size, $modulo, $unit])
                                            @if ($index > 0)
                                                <span class="text-2xl font-bold text-brand-200">:</span>
                                            @endif
                                            <span class="flex flex-col items-center">
                                                <span class="text-2xl font-bold" x-text="part({{ $size }}, {{ $modulo }})">{{ str_pad((string) (intdiv((int) $secondsLeft * 1000, $size) % $modulo), 2, '0', STR_PAD_LEFT) }}</span>
                                                <span class="text-[0.65rem] uppercase tracking-wide text-brand-200">{{ $unit }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                    <p x-cloak x-show="left === 0" class="mt-1 text-sm font-semibold">{{ __('Time is up — refresh the page.') }}</p>
                                </div>
                            @endif
                        </div>

                        @if ($booking->latestPayment && in_array($booking->latestPayment->status, [PaymentStatus::Failed, PaymentStatus::Expired], true))
                            <flux:callout class="mt-5" variant="warning" icon="exclamation-triangle" :heading="__('Your last payment attempt did not go through. No worries — just try again.')">
                                @if ($booking->latestPayment->failure_reason)
                                    <flux:callout.text>{{ $booking->latestPayment->failure_reason }}</flux:callout.text>
                                @endif
                            </flux:callout>
                        @endif

                        <h3 class="mt-6 text-lg font-bold text-zinc-900">{{ __('How paying works') }}</h3>
                        <ol class="mt-3 space-y-4">
                            <li class="flex gap-3 motion-safe:animate-step-in">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">1</span>
                                <div>
                                    <p class="font-semibold text-zinc-900">{{ __('Tap the big blue “Pay” button below.') }}</p>
                                    <p class="text-sm text-zinc-600">{{ __('We’ll open PayMongo’s secure payment page for you.') }}</p>
                                </div>
                            </li>
                            <li class="flex gap-3 motion-safe:animate-step-in" style="animation-delay: 80ms">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">2</span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-zinc-900">{{ __('Choose how you want to pay:') }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2" data-test="payment-methods">
                                        @foreach ($this->paymentMethods as $method)
                                            <span wire:key="method-{{ $method->value }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-sm font-medium text-zinc-800 ring-1 ring-zinc-200">
                                                <flux:icon
                                                    :name="match ($method) { PaymentMethod::QrPh => 'qr-code', PaymentMethod::Card => 'credit-card', default => 'device-phone-mobile' }"
                                                    variant="micro"
                                                    class="size-4 text-brand-600"
                                                />
                                                {{ $method->label() }}
                                            </span>
                                        @endforeach
                                    </div>
                                    @if (in_array(PaymentMethod::QrPh, $this->paymentMethods, true))
                                        <p class="mt-2 text-sm text-zinc-600">{{ __('QR Ph: scan the code with any bank or e-wallet app (GCash, Maya, BPI, BDO and more).') }}</p>
                                    @endif
                                </div>
                            </li>
                            <li class="flex gap-3 motion-safe:animate-step-in" style="animation-delay: 160ms">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">3</span>
                                <div>
                                    <p class="font-semibold text-zinc-900">{{ __('You come right back here, and you’re booked!') }}</p>
                                    <p class="text-sm text-zinc-600">{{ __('Your booking is confirmed as soon as the payment goes through.') }}</p>
                                </div>
                            </li>
                        </ol>

                        <form wire:submit="pay" class="mt-6">
                            <flux:error name="payment" />

                            <button
                                type="submit"
                                data-test="pay-now"
                                class="group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-brand-600 px-6 py-5 text-xl font-bold text-white shadow-xl shadow-brand-600/30 transition hover:bg-brand-700 active:scale-[0.98]"
                            >
                                <span aria-hidden="true" class="pointer-events-none absolute inset-y-0 left-0 w-1/3 bg-linear-to-r from-transparent via-white/30 to-transparent motion-safe:animate-shine"></span>
                                <flux:icon.lock-closed variant="mini" class="relative size-5 in-data-loading:hidden" />
                                <span class="relative in-data-loading:hidden">{{ __('Pay ₱:amount', ['amount' => number_format($booking->total)]) }}</span>
                                <flux:icon.arrow-right variant="mini" class="relative size-5 transition group-hover:translate-x-1 in-data-loading:hidden" />
                                <span class="relative hidden items-center gap-2 in-data-loading:inline-flex">
                                    <svg class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
                                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                    </svg>
                                    {{ __('Taking you to the payment page…') }}
                                </span>
                            </button>
                            <p class="mt-3 flex items-center justify-center gap-1.5 text-center text-sm text-zinc-500">
                                <flux:icon.shield-check variant="micro" class="size-4 shrink-0 text-emerald-600" />
                                {{ __('Secure payment by PayMongo. Nothing is charged until you finish there.') }}
                            </p>
                        </form>
                    @endif
                </x-booking.checkout-step>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                <x-booking.summary :booking="$booking" />

                <div class="rounded-2xl border border-zinc-200 bg-white p-5">
                    <p class="flex items-center gap-2 font-semibold text-zinc-900">
                        <flux:icon.chat-bubble-left-right variant="mini" class="size-5 text-brand-600" />
                        {{ __('Need a hand?') }}
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ __('Tap the chat button in the corner of the screen, and our assistant will help you with any step.') }}</p>
                </div>
            </aside>
        </div>
    </x-app.content>
</div>
