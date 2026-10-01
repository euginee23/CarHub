<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Trip tracking')] class extends Component {
    #[Locked]
    public Booking $booking;

    /**
     * Load the rental being followed.
     */
    public function mount(Booking $booking): void
    {
        Gate::authorize('track', $booking);

        $this->booking = $booking->load(['vehicle', 'renter']);
    }

    /**
     * Where the back link goes for this viewer.
     */
    #[Computed]
    public function backRoute(): string
    {
        return match (true) {
            Auth::user()->isAdmin() => route('admin.bookings.show', $this->booking),
            Auth::id() === $this->booking->owner_id => route('owner.bookings.show', $this->booking),
            default => route('trips.show', $this->booking),
        };
    }
}; ?>

<div>
    <x-app.page-header
        :title="__('Tracking :vehicle', ['vehicle' => $booking->vehicle->name])"
        :description="$booking->status === BookingStatus::Ongoing
            ? __('Live position during :renter\'s rental. Tracking stops automatically when the rental closes.', ['renter' => $booking->renter->name])
            : __('Route recorded during the rental. Kept for 30 days for dispute resolution.')"
        :back="$this->backRoute"
        :back-label="__('Booking :reference', ['reference' => $booking->reference])"
    />

    <x-app.content>
        <livewire:tracking.live-map :booking="$booking" />
    </x-app.content>
</div>
