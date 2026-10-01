<?php

use App\Actions\Bookings\AcceptRentalTerms;
use App\Actions\Bookings\GenerateRentalContract;
use App\Actions\Bookings\SignRentalContract;
use App\Actions\Payments\StartPayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\RentalContract;
use App\Models\TermsVersion;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Checkout')] class extends Component {
    #[Locked]
    public Booking $booking;

    public bool $agreeToTerms = false;

    public string $signature = '';

    public bool $agreeToContract = false;

    public string $paymentMethod = 'gcash';

    /**
     * Load the renter's booking, sending them back to the trip if checkout is over.
     */
    public function mount(Booking $booking): void
    {
        Gate::authorize('checkout', $booking);

        if (! in_array($booking->status, [BookingStatus::Requested, BookingStatus::Approved, BookingStatus::AwaitingPayment], true)) {
            $this->redirectRoute('trips.show', $booking, navigate: true);

            return;
        }

        $this->booking = $booking->load(['vehicle.coverPhoto', 'termsVersion', 'contract', 'latestPayment']);
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
     * Each checkout step and whether it is done, available, or still locked.
     *
     * @return array<string, array{label: string, state: 'done'|'current'|'locked'}>
     */
    #[Computed]
    public function steps(): array
    {
        $signed = (bool) $this->booking->contract?->isSigned();

        $states = [
            'terms' => $this->termsAccepted ? 'done' : 'current',
            'identity' => $this->identityVerified ? 'done' : 'current',
            'contract' => match (true) {
                $signed => 'done',
                $this->booking->status === BookingStatus::Approved && $this->termsAccepted && $this->identityVerified => 'current',
                default => 'locked',
            },
            'payment' => $this->booking->status === BookingStatus::AwaitingPayment ? 'current' : 'locked',
        ];

        return [
            'terms' => ['label' => __('Rental terms'), 'state' => $states['terms']],
            'identity' => ['label' => __('Two valid IDs'), 'state' => $states['identity']],
            'contract' => ['label' => __('Rental contract'), 'state' => $states['contract']],
            'payment' => ['label' => __('Payment'), 'state' => $states['payment']],
        ];
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
            'agreeToContract' => ['accepted'],
        ], ['agreeToContract.accepted' => __('Tick the box to confirm you agree to the contract.')]);

        $signRentalContract->handle($this->booking, Auth::user(), $this->signature, request()->ip());

        $this->booking->load('contract');
        $this->reset(['signature', 'agreeToContract']);
        $this->refreshSteps();

        Flux::toast(variant: 'success', text: __('Contract signed.'));
    }

    /**
     * The payment methods renters can choose from.
     *
     * @return array<int, PaymentMethod>
     */
    #[Computed]
    public function paymentMethods(): array
    {
        return array_map(fn (string $method) => PaymentMethod::from($method), config('carhub.payments.methods'));
    }

    /**
     * Open the gateway's hosted checkout for the booking total.
     */
    public function pay(StartPayment $startPayment): void
    {
        Gate::authorize('checkout', $this->booking);

        $this->validate(['paymentMethod' => ['required', Rule::in(config('carhub.payments.methods'))]]);

        $payment = $startPayment->handle($this->booking, PaymentMethod::from($this->paymentMethod));

        $this->redirect($payment->checkout_url);
    }

    /**
     * Re-evaluate the steps after the renter's IDs change.
     */
    #[On('identity-documents-updated')]
    public function refreshSteps(): void
    {
        unset($this->termsAccepted, $this->identityVerified, $this->contract, $this->steps);
    }
}; ?>

<div>
    <x-app.page-header
        :title="__('Checkout')"
        :description="__('Four steps to confirm your :vehicle.', ['vehicle' => $booking->vehicle->name])"
        :back="route('trips.show', $booking)"
        :back-label="__('Trip :reference', ['reference' => $booking->reference])"
    />

    <x-app.content class="space-y-6">
        <ol class="grid gap-2 sm:grid-cols-4">
            @foreach ($this->steps as $key => $step)
                <li wire:key="step-{{ $key }}" @class([
                    'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm',
                    'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $step['state'] === 'done',
                    'border-brand-300 bg-brand-50 font-semibold text-brand-800 dark:border-brand-700 dark:bg-zinc-800 dark:text-white' => $step['state'] === 'current',
                    'border-zinc-200 text-zinc-400 dark:border-zinc-700' => $step['state'] === 'locked',
                ])>
                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full border border-current text-xs">
                        @if ($step['state'] === 'done') &#10003; @else {{ $loop->iteration }} @endif
                    </span>
                    {{ $step['label'] }}
                </li>
            @endforeach
        </ol>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-6">
                {{-- 1. Terms --}}
                <flux:card class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <flux:heading size="lg">{{ __('1. Rental terms') }}</flux:heading>
                        @if ($this->termsAccepted)
                            <flux:badge color="green" size="sm">{{ __('Accepted :date', ['date' => $booking->terms_accepted_at->format('M j')]) }}</flux:badge>
                        @endif
                    </div>

                    @if ($this->currentTerms)
                        <x-booking.legal-text class="max-h-72 overflow-y-auto rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            {{ $this->currentTerms->html() }}
                        </x-booking.legal-text>
                        <flux:text class="text-xs">{{ __('Version :version', ['version' => $this->currentTerms->version]) }}</flux:text>

                        @unless ($this->termsAccepted)
                            <form wire:submit="acceptTerms" class="space-y-4">
                                <flux:checkbox wire:model="agreeToTerms" :label="__('I have read and agree to the rental terms, including the cancellation, payment, and GPS tracking conditions.')" />
                                <flux:error name="terms" />
                                <flux:button type="submit" variant="primary" data-test="accept-terms">{{ __('Accept terms') }}</flux:button>
                            </form>
                        @endunless
                    @else
                        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Rental terms are being updated. Please check back shortly.')" />
                    @endif
                </flux:card>

                {{-- 2. IDs --}}
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('2. Two valid IDs') }}</flux:heading>
                    <livewire:identity.id-documents />
                </flux:card>

                {{-- 3. Contract --}}
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('3. Rental contract') }}</flux:heading>

                    @if ($this->contract)
                        <div class="max-h-96 overflow-y-auto rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            @include('contracts.document', ['contract' => $this->contract])
                        </div>
                        <flux:button size="sm" :href="route('bookings.contract', $booking)" target="_blank" icon="printer">{{ __('Open printable copy') }}</flux:button>

                        @unless ($this->contract->isSigned())
                            <form wire:submit="signContract" class="space-y-4">
                                <flux:input wire:model="signature" :label="__('Type your full name to sign')" :placeholder="auth()->user()->name" />
                                <flux:checkbox wire:model="agreeToContract" :label="__('I agree to this rental contract and understand that typing my name is my electronic signature.')" />
                                <flux:button type="submit" variant="primary" data-test="sign-contract">{{ __('Sign contract') }}</flux:button>
                            </form>
                        @endunless
                    @elseif ($booking->status === BookingStatus::Requested)
                        <flux:text>{{ __('The contract is prepared once the owner approves your request.') }}</flux:text>
                    @else
                        <flux:text>{{ __('Accept the rental terms and get two IDs approved to prepare your contract.') }}</flux:text>
                    @endif
                </flux:card>

                {{-- 4. Payment --}}
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('4. Payment') }}</flux:heading>
                    @if ($booking->status === BookingStatus::AwaitingPayment)
                        <flux:text>
                            {{ __('Your contract is signed and the vehicle is held for you until :deadline. Pay the total to confirm the booking.', ['deadline' => $booking->payment_due_at?->format('M j, g:i A') ?? '—']) }}
                        </flux:text>

                        @if ($booking->latestPayment && in_array($booking->latestPayment->status, [\App\Enums\PaymentStatus::Failed, \App\Enums\PaymentStatus::Expired], true))
                            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Your last payment attempt did not go through.')">
                                @if ($booking->latestPayment->failure_reason)
                                    <flux:callout.text>{{ $booking->latestPayment->failure_reason }}</flux:callout.text>
                                @endif
                            </flux:callout>
                        @endif

                        <form wire:submit="pay" class="space-y-4">
                            <flux:radio.group wire:model="paymentMethod" :label="__('Payment method')">
                                @foreach ($this->paymentMethods as $method)
                                    <flux:radio :value="$method->value" :label="$method->label()" />
                                @endforeach
                            </flux:radio.group>

                            <div class="space-y-1 rounded-xl bg-zinc-50 p-4 text-sm">
                                <div class="flex justify-between text-zinc-600">
                                    <span>{{ __('Rental') }}</span>
                                    <span>&#8369;{{ number_format($booking->subtotal) }}</span>
                                </div>
                                <div class="flex justify-between text-zinc-600">
                                    <span>{{ __('Service fee') }}</span>
                                    <span>&#8369;{{ number_format($booking->service_fee) }}</span>
                                </div>
                                <div class="flex justify-between border-t border-zinc-200 pt-2 font-semibold text-zinc-900">
                                    <span>{{ __('Total due now') }}</span>
                                    <span>&#8369;{{ number_format($booking->total) }}</span>
                                </div>
                            </div>

                            <flux:error name="payment" />

                            <flux:button type="submit" variant="primary" data-test="pay-now">
                                {{ __('Pay ₱:amount', ['amount' => number_format($booking->total)]) }}
                            </flux:button>
                            <flux:text class="text-xs">{{ __('You will be taken to our secure payment partner to finish paying.') }}</flux:text>
                        </form>
                    @else
                        <flux:text>{{ __('Payment opens after you sign the contract.') }}</flux:text>
                    @endif
                </flux:card>
            </div>

            <div>
                <x-booking.summary :booking="$booking" class="lg:sticky lg:top-6" />
            </div>
        </div>
    </x-app.content>
</div>
