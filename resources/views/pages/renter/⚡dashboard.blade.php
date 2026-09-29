<?php

use App\Enums\ApplicationStatus;
use App\Enums\BookingStatus;
use App\Enums\DocumentStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Overview')] class extends Component {
    /**
     * The signed-in renter.
     */
    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    /**
     * Things the renter has to do next, most urgent first.
     *
     * @return array<int, array{title: string, body: string, href: string, action: string}>
     */
    #[Computed]
    public function actions(): array
    {
        $actions = $this->user->bookings()
            ->with('vehicle')
            ->whereIn('status', [BookingStatus::AwaitingPayment, BookingStatus::Approved])
            ->orderBy('pickup_at')
            ->get()
            ->map(fn (Booking $booking) => $booking->status === BookingStatus::AwaitingPayment
                ? [
                    'title' => __('Pay for your :vehicle', ['vehicle' => $booking->vehicle->name]),
                    'body' => __('Your contract is signed. Pay ₱:total to confirm the booking.', ['total' => number_format($booking->total)]),
                    'href' => route('trips.checkout', $booking),
                    'action' => __('Pay now'),
                ]
                : [
                    'title' => __('Finish checkout for your :vehicle', ['vehicle' => $booking->vehicle->name]),
                    'body' => __('The owner approved your request for :date. Accept the terms and sign the contract.', ['date' => $booking->pickup_at->format('M j')]),
                    'href' => route('trips.checkout', $booking),
                    'action' => __('Continue'),
                ])
            ->all();

        if (! $this->user->hasVerifiedIdentity()) {
            $approved = $this->user->identityDocuments()->where('status', DocumentStatus::Approved)->count();

            $actions[] = [
                'title' => __('Verify your identity'),
                'body' => __('Two approved government IDs are needed before you can sign a rental contract (:approved of :required done).', [
                    'approved' => $approved,
                    'required' => User::REQUIRED_IDENTITY_DOCUMENTS,
                ]),
                'href' => route('identity.edit'),
                'action' => __('Upload IDs'),
            ];
        }

        return $actions;
    }

    /**
     * Headline numbers for the renter.
     *
     * @return array{upcoming: int, ongoing: int, completed: int, spent: int}
     */
    #[Computed]
    public function stats(): array
    {
        $counts = $this->user->bookings()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate, sum(total) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (BookingStatus ...$statuses) => collect($statuses)->sum(fn (BookingStatus $status) => (int) ($counts[$status->value]->aggregate ?? 0));
        $spent = fn (BookingStatus ...$statuses) => collect($statuses)->sum(fn (BookingStatus $status) => (int) ($counts[$status->value]->total ?? 0));

        return [
            'upcoming' => $count(BookingStatus::Requested, BookingStatus::Approved, BookingStatus::AwaitingPayment, BookingStatus::Confirmed),
            'ongoing' => $count(BookingStatus::Ongoing),
            'completed' => $count(BookingStatus::Completed),
            'spent' => $spent(BookingStatus::Confirmed, BookingStatus::Ongoing, BookingStatus::Completed),
        ];
    }

    /**
     * The renter's next trips.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function upcomingTrips(): Collection
    {
        return $this->user->bookings()
            ->with('vehicle.coverPhoto')
            ->whereIn('status', [BookingStatus::Requested, BookingStatus::Approved, BookingStatus::AwaitingPayment, BookingStatus::Confirmed, BookingStatus::Ongoing])
            ->orderBy('pickup_at')
            ->take(5)
            ->get();
    }

    /**
     * Highly rated listings to browse, excluding the renter's own vehicles.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function topRated(): Collection
    {
        return Vehicle::listed()
            ->with('coverPhoto')
            ->where('owner_id', '!=', $this->user->id)
            ->orderByDesc('rating')
            ->orderByDesc('trips_count')
            ->take(3)
            ->get();
    }
}; ?>

<div>
    <x-dashboard.greeting :subtitle="__('Here is what is happening with your rentals.')">
        <x-slot:actions>
            <flux:button :href="route('vehicles.index')" variant="primary" icon="magnifying-glass">{{ __('Find a vehicle') }}</flux:button>
        </x-slot:actions>
    </x-dashboard.greeting>

    <x-app.content class="space-y-8">
        @if ($this->actions !== [])
            <div class="space-y-3">
                @foreach ($this->actions as $action)
                    <div wire:key="action-{{ $loop->index }}" class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-brand-900">{{ $action['title'] }}</p>
                            <p class="mt-0.5 text-sm text-brand-800">{{ $action['body'] }}</p>
                        </div>
                        <flux:button size="sm" variant="primary" :href="$action['href']" wire:navigate>{{ $action['action'] }}</flux:button>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-dashboard.stat :label="__('Upcoming trips')" :value="$this->stats['upcoming']" :href="route('trips.index')" />
            <x-dashboard.stat :label="__('On a trip now')" :value="$this->stats['ongoing']" />
            <x-dashboard.stat :label="__('Completed trips')" :value="$this->stats['completed']" :href="route('trips.index', ['tab' => 'past'])" />
            <x-dashboard.stat :label="__('Total spent')" :value="'₱'.number_format($this->stats['spent'])" :hint="__('Confirmed and completed rentals')" />
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <x-dashboard.panel :title="__('Your upcoming trips')" :href="route('trips.index')">
                @forelse ($this->upcomingTrips as $booking)
                    <a wire:key="trip-{{ $booking->id }}" href="{{ route('trips.show', $booking) }}" wire:navigate class="-mx-2 flex items-center gap-4 rounded-xl px-2 py-3 transition hover:bg-zinc-50">
                        <x-marketing.vehicle-image :vehicle="$booking->vehicle" :caption="false" class="aspect-[4/3] !w-16 shrink-0 rounded-lg" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-zinc-900">{{ $booking->vehicle->year }} {{ $booking->vehicle->name }}</p>
                            <p class="text-sm text-zinc-500">{{ $booking->pickup_at->format('D, M j · g:i A') }}</p>
                        </div>
                        <flux:badge size="sm" :color="$booking->status->color()">{{ $booking->status->label() }}</flux:badge>
                    </a>
                @empty
                    <div class="py-6 text-center">
                        <p class="font-medium text-zinc-900">{{ __('No trips planned yet') }}</p>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('Pick your dates on any listing to send a booking request.') }}</p>
                    </div>
                @endforelse
            </x-dashboard.panel>

            <div class="space-y-6">
                <x-dashboard.panel :title="__('Identity')">
                    @if ($this->user->hasVerifiedIdentity())
                        <p class="flex items-center gap-2 text-sm font-medium text-emerald-700">
                            <flux:icon.check-badge class="size-5" />
                            {{ __('Two IDs verified — you can book.') }}
                        </p>
                    @else
                        <p class="text-sm text-zinc-600">{{ __('Upload two different government IDs once and reuse them for every booking.') }}</p>
                        <flux:button size="sm" class="mt-3" :href="route('identity.edit')" wire:navigate>{{ __('Manage IDs') }}</flux:button>
                    @endif
                </x-dashboard.panel>

                @unless ($this->user->isVerifiedOwner())
                    <x-dashboard.panel :title="__('Earn from your car')">
                        @php($application = $this->user->latestOwnerApplication)
                        @if ($application?->status === ApplicationStatus::Pending)
                            <p class="text-sm text-zinc-600">{{ __('Your owner application is being reviewed. We will email you once it is approved.') }}</p>
                        @else
                            <p class="text-sm text-zinc-600">{{ __('Get verified as an owner and list your vehicle for other renters.') }}</p>
                            <flux:button size="sm" class="mt-3" :href="route('owner.apply')" wire:navigate>{{ __('Become an owner') }}</flux:button>
                        @endif
                    </x-dashboard.panel>
                @endunless
            </div>
        </div>

        @if ($this->topRated->isNotEmpty())
            <section>
                <div class="flex items-end justify-between gap-4">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('Top-rated vehicles') }}</h2>
                    <a href="{{ route('vehicles.index', ['sort' => 'rating']) }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">{{ __('Browse all') }} &rarr;</a>
                </div>
                <div class="mt-4 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($this->topRated as $vehicle)
                        <x-marketing.vehicle-card wire:key="top-{{ $vehicle->id }}" :vehicle="$vehicle" />
                    @endforeach
                </div>
            </section>
        @endif
    </x-app.content>
</div>
