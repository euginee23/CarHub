<?php

use App\Actions\Bookings\ApproveBooking;
use App\Actions\Bookings\CompleteRental;
use App\Actions\Bookings\DeclineBooking;
use App\Actions\Bookings\StartRental;
use App\Enums\BookingStatus;
use App\Enums\FuelLevel;
use Illuminate\Validation\Rule;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Booking')] class extends Component {
    #[Locked]
    public Booking $booking;

    public string $declineReason = '';

    public string $pickupOdometer = '';

    public string $pickupFuel = 'full';

    public string $pickupNotes = '';

    public string $returnOdometer = '';

    public string $returnFuel = 'full';

    public string $returnNotes = '';

    /**
     * Load a booking on one of the owner's vehicles.
     */
    public function mount(Booking $booking): void
    {
        Gate::authorize('manage', $booking);

        $this->booking = $booking;
        $this->loadRelations();
    }

    /**
     * Other requests that would be declined automatically if this one is approved.
     */
    #[Computed]
    public function competingRequests(): int
    {
        if ($this->booking->status !== BookingStatus::Requested) {
            return 0;
        }

        return Booking::whereBelongsTo($this->booking->vehicle)
            ->whereKeyNot($this->booking->id)
            ->where('status', BookingStatus::Requested)
            ->overlapping($this->booking->pickup_at, $this->booking->return_at)
            ->count();
    }

    /**
     * Accept the request.
     */
    public function approve(ApproveBooking $approveBooking): void
    {
        Gate::authorize('manage', $this->booking);

        $approveBooking->handle($this->booking, Auth::user());

        $this->loadRelations();
        unset($this->competingRequests);

        Flux::toast(variant: 'success', text: __('Request approved. The renter can now complete checkout.'));
    }

    /**
     * Turn down the request.
     */
    public function decline(DeclineBooking $declineBooking): void
    {
        Gate::authorize('manage', $this->booking);

        $this->validate(['declineReason' => ['required', 'string', 'min:5', 'max:500']]);

        $declineBooking->handle($this->booking, Auth::user(), $this->declineReason);

        $this->reset('declineReason');
        $this->loadRelations();

        Flux::modal('decline-booking')->close();
        Flux::toast(variant: 'success', text: __('Request declined.'));
    }

    /**
     * Hand the vehicle over to the renter and start the rental.
     */
    public function releaseVehicle(StartRental $startRental): void
    {
        Gate::authorize('manage', $this->booking);

        $this->validate([
            'pickupOdometer' => ['required', 'integer', 'min:0', 'max:9999999'],
            'pickupFuel' => ['required', Rule::enum(FuelLevel::class)],
            'pickupNotes' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['pickupOdometer' => __('odometer reading'), 'pickupFuel' => __('fuel level')]);

        $startRental->handle($this->booking, Auth::user(), (int) $this->pickupOdometer, FuelLevel::from($this->pickupFuel), filled($this->pickupNotes) ? $this->pickupNotes : null);

        $this->loadRelations();

        Flux::toast(variant: 'success', text: __('Vehicle released. The rental has started.'));
    }

    /**
     * Take the vehicle back and close the rental.
     */
    public function recordReturn(CompleteRental $completeRental): void
    {
        Gate::authorize('manage', $this->booking);

        $this->validate([
            'returnOdometer' => ['required', 'integer', 'min:0', 'max:9999999'],
            'returnFuel' => ['required', Rule::enum(FuelLevel::class)],
            'returnNotes' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['returnOdometer' => __('odometer reading'), 'returnFuel' => __('fuel level')]);

        $completeRental->handle($this->booking, Auth::user(), (int) $this->returnOdometer, FuelLevel::from($this->returnFuel), filled($this->returnNotes) ? $this->returnNotes : null);

        $this->loadRelations();

        Flux::toast(variant: 'success', text: __('Return recorded. The rental is complete.'));
    }

    /**
     * Refresh the booking and everything shown alongside it.
     */
    protected function loadRelations(): void
    {
        $this->booking->refresh()->load(['vehicle.coverPhoto', 'vehicle.gpsDevice', 'renter', 'statusChanges.actor', 'contract', 'successfulPayment', 'latestPayment', 'review']);
    }
}; ?>

<div>
    <x-app.page-header
        :title="$booking->vehicle->year.' '.$booking->vehicle->name"
        :description="__('Booking :reference', ['reference' => $booking->reference])"
        :back="route('owner.bookings.index')"
        :back-label="__('Booking requests')"
    />

    <x-app.content width="5xl" class="space-y-6">
        <flux:error name="booking" />

        <x-booking.progress :booking="$booking" />

        @if ($booking->status === BookingStatus::Confirmed)
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Hand over the vehicle') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Pickup is :date. Check the renter\'s ID matches :name, then record the odometer and fuel level as you hand over the keys.', ['date' => $booking->pickup_at->format('D, M j, g:i A'), 'name' => $booking->renter->name]) }}
                    </flux:text>
                </div>
                <form wire:submit="releaseVehicle" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="pickupOdometer" type="number" min="0" :label="__('Odometer (km)')" />
                        <flux:select wire:model="pickupFuel" :label="__('Fuel level')">
                            @foreach (FuelLevel::cases() as $level)
                                <flux:select.option :value="$level->value">{{ $level->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:textarea wire:model="pickupNotes" :label="__('Condition notes (optional)')" rows="2" :placeholder="__('Existing scratches, items in the car…')" />
                    <flux:error name="handover" />
                    <flux:button type="submit" variant="primary" data-test="release-vehicle">{{ __('Release vehicle') }}</flux:button>
                </form>
            </flux:card>
        @elseif ($booking->status === BookingStatus::Ongoing)
            <flux:card class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Vehicle is out on the trip') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Due back :date. Record the return when you get the keys back.', ['date' => $booking->return_at->format('D, M j, g:i A')]) }}</flux:text>
                    </div>
                    <flux:button :href="route('bookings.tracking', $booking)" icon="map-pin" wire:navigate>{{ __('Live tracking') }}</flux:button>
                </div>
                <form wire:submit="recordReturn" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="returnOdometer" type="number" :min="$booking->pickup_odometer ?? 0" :label="__('Odometer (km)')" />
                        <flux:select wire:model="returnFuel" :label="__('Fuel level')">
                            @foreach (FuelLevel::cases() as $level)
                                <flux:select.option :value="$level->value">{{ $level->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:textarea wire:model="returnNotes" :label="__('Condition notes (optional)')" rows="2" :placeholder="__('New damage, cleanliness, anything to follow up…')" />
                    <flux:error name="handover" />
                    <flux:button type="submit" variant="primary" data-test="record-return">{{ __('Record return') }}</flux:button>
                </form>
            </flux:card>
        @elseif ($booking->status === BookingStatus::Completed)
            <flux:card class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <flux:heading>{{ __('Rental complete') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Trip history is kept for 30 days in case anything needs checking.') }}</flux:text>
                </div>
                <flux:button :href="route('bookings.tracking', $booking)" icon="map" wire:navigate>{{ __('Trip route') }}</flux:button>
            </flux:card>
        @endif

        @if ($booking->status === BookingStatus::Requested)
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __(':name wants to rent your :vehicle', ['name' => $booking->renter->name, 'vehicle' => $booking->vehicle->name]) }}</flux:heading>
                @if ($booking->renter_notes)
                    <flux:text>&ldquo;{{ $booking->renter_notes }}&rdquo;</flux:text>
                @endif
                @if ($this->competingRequests > 0)
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        <flux:callout.text>{{ trans_choice('{1} Approving will decline :count other request for overlapping dates.|[2,*] Approving will decline :count other requests for overlapping dates.', $this->competingRequests, ['count' => $this->competingRequests]) }}</flux:callout.text>
                    </flux:callout>
                @endif
                <div class="flex gap-2">
                    <flux:button variant="primary" wire:click="approve" data-test="approve-booking">{{ __('Approve') }}</flux:button>
                    <flux:modal.trigger name="decline-booking">
                        <flux:button variant="danger">{{ __('Decline') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </flux:card>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-6">
                @if ($booking->status === BookingStatus::Ongoing)
                    <flux:card class="space-y-4">
                        <flux:heading size="lg">{{ __('Where the vehicle is now') }}</flux:heading>
                        <livewire:tracking.live-map :booking="$booking" :compact="true" />
                    </flux:card>
                @endif

                <x-booking.summary :booking="$booking" />

                <x-booking.handover :booking="$booking" />

                @if ($booking->review)
                    <x-booking.review :review="$booking->review" />
                @endif

                @if ($booking->contract)
                    <flux:card class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <flux:heading>{{ __('Rental contract :number', ['number' => $booking->contract->contract_number]) }}</flux:heading>
                            <flux:text class="mt-1">{{ $booking->contract->isSigned() ? __('Signed by the renter') : __('Waiting for the renter to sign') }}</flux:text>
                        </div>
                        <flux:button :href="route('bookings.contract', $booking)" target="_blank" icon="document-text">{{ __('View contract') }}</flux:button>
                    </flux:card>
                @endif
            </div>

            <div class="space-y-6">
                <flux:card class="space-y-2">
                    <flux:heading>{{ __('Renter') }}</flux:heading>
                    <flux:text>{{ $booking->renter->name }}</flux:text>
                    @if ($booking->renter->hasVerifiedIdentity())
                        <flux:badge size="sm" color="green" icon="check-badge">{{ __('Two IDs verified') }}</flux:badge>
                    @else
                        <flux:badge size="sm" color="zinc">{{ __('IDs not yet verified') }}</flux:badge>
                    @endif
                    @if (in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Ongoing], true))
                        <flux:text>{{ $booking->renter->phone }}</flux:text>
                    @endif
                </flux:card>

                <x-booking.payment :booking="$booking" />

                <x-booking.timeline :booking="$booking" />
            </div>
        </div>

        <flux:modal name="decline-booking" class="md:w-md">
            <form wire:submit="decline" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Decline this request?') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('The renter will see your reason.') }}</flux:text>
                </div>
                <flux:textarea wire:model="declineReason" :label="__('Reason')" rows="3" :placeholder="__('e.g. The vehicle is in for repairs that week.')" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Decline') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </x-app.content>
</div>
