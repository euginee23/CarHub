<?php

use App\Enums\BookingStatus;
use App\Enums\VehicleStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rental company overview')] class extends Component {
    /**
     * The signed-in rental company.
     */
    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    /**
     * Headline numbers for the rental company's fleet and bookings.
     *
     * @return array{listed: int, vehicles: int, pending: int, upcoming: int, waiting: int, earned: int, earnedThisMonth: int}
     */
    #[Computed]
    public function stats(): array
    {
        $earning = [BookingStatus::Confirmed, BookingStatus::Ongoing, BookingStatus::Completed];

        return [
            'listed' => $this->user->vehicles()->where('status', VehicleStatus::Listed)->count(),
            'vehicles' => $this->user->vehicles()->count(),
            'pending' => $this->user->companyBookings()->where('status', BookingStatus::Requested)->count(),
            'upcoming' => $this->user->companyBookings()->whereIn('status', [BookingStatus::Approved, BookingStatus::AwaitingPayment, BookingStatus::Confirmed])->count(),
            'waiting' => $this->user->companyBookings()->whereIn('status', [BookingStatus::Approved, BookingStatus::AwaitingPayment])->count(),
            'earned' => (int) $this->user->companyBookings()->whereIn('status', $earning)->sum('subtotal'),
            'earnedThisMonth' => (int) $this->user->companyBookings()
                ->whereIn('status', $earning)
                ->whereBetween('pickup_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('subtotal'),
        ];
    }

    /**
     * Requests waiting for the rental company's answer, soonest pickup first.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function pendingRequests(): Collection
    {
        return $this->user->companyBookings()
            ->with(['vehicle', 'renter'])
            ->where('status', BookingStatus::Requested)
            ->orderBy('pickup_at')
            ->take(5)
            ->get();
    }

    /**
     * Paid bookings whose car is handed over within the next two days.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function handovers(): Collection
    {
        return $this->user->companyBookings()
            ->with(['vehicle', 'renter'])
            ->where('status', BookingStatus::Confirmed)
            ->where('pickup_at', '<=', now()->addDays(2))
            ->orderBy('pickup_at')
            ->take(5)
            ->get();
    }

    /**
     * Cars out on a trip that come back within the next two days.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function returnsDue(): Collection
    {
        return $this->user->companyBookings()
            ->with(['vehicle', 'renter'])
            ->where('status', BookingStatus::Ongoing)
            ->where('return_at', '<=', now()->addDays(2))
            ->orderBy('return_at')
            ->take(5)
            ->get();
    }

    /**
     * Upcoming handovers and vehicles currently out on a trip.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function schedule(): Collection
    {
        return $this->user->companyBookings()
            ->with(['vehicle', 'renter'])
            ->whereIn('status', [BookingStatus::Approved, BookingStatus::AwaitingPayment, BookingStatus::Confirmed, BookingStatus::Ongoing])
            ->orderBy('pickup_at')
            ->take(5)
            ->get();
    }

    /**
     * The rental company's vehicles with how many active bookings each has.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function fleet(): Collection
    {
        return $this->user->vehicles()
            ->with('coverPhoto')
            ->withCount(['bookings as active_bookings_count' => fn ($query) => $query->whereIn('status', BookingStatus::holding())])
            ->orderByRaw('case when status = ? then 0 else 1 end', [VehicleStatus::Listed->value])
            ->latest()
            ->get();
    }
}; ?>

@php
    $stats = $this->stats;
    $pending = $this->pendingRequests;
    $handovers = $this->handovers;
    $returns = $this->returnsDue;
    $hasTodo = $pending->isNotEmpty() || $handovers->isNotEmpty() || $returns->isNotEmpty();

    /** When something happens, said the way a person would: "today at 2:00 PM". */
    $when = fn (CarbonInterface $moment) => match (true) {
        // Renters may pay late and pick up late, and returns can run over.
        $moment->isPast() => __('late — it was due :date at :time', ['date' => $moment->format('D, M j'), 'time' => $moment->format('g:i A')]),
        $moment->isToday() => __('today at :time', ['time' => $moment->format('g:i A')]),
        $moment->isTomorrow() => __('tomorrow at :time', ['time' => $moment->format('g:i A')]),
        default => __('on :date at :time', ['date' => $moment->format('D, M j'), 'time' => $moment->format('g:i A')]),
    };

    $coach = match (true) {
        $stats['pending'] > 0 => [
            'eyebrow' => __('Your to-do list'),
            'title' => trans_choice('{1} You have 1 booking request to answer.|[2,*] You have :count booking requests to answer.', $stats['pending'], ['count' => $stats['pending']]),
            'text' => __('Tap “Review and answer”, look over the renter and the dates, then say yes or no. A request you don’t answer expires at its pickup time.'),
            'tone' => 'amber',
        ],
        $handovers->isNotEmpty() => [
            'eyebrow' => __('Your to-do list'),
            'title' => __('Get ready: :renter picks up the :vehicle :when.', ['renter' => $handovers->first()->renter->name, 'vehicle' => $handovers->first()->vehicle->name, 'when' => $when($handovers->first()->pickup_at)]),
            'text' => __('Tap “See handover steps” for what to check and sign when you hand over the keys.'),
            'tone' => 'brand',
        ],
        $returns->isNotEmpty() => [
            'eyebrow' => __('Your to-do list'),
            'title' => __('The :vehicle comes back :when.', ['vehicle' => $returns->first()->vehicle->name, 'when' => $when($returns->first()->return_at)]),
            'text' => __('When it’s back, check the car’s condition and fuel, then close the rental.'),
            'tone' => 'brand',
        ],
        $stats['vehicles'] === 0 => [
            'eyebrow' => __('First things first'),
            'title' => __('Let’s list your first vehicle!'),
            'text' => __('Add a few photos, a daily price, and where renters pick it up. Renters can book it as soon as it is listed.'),
            'tone' => 'brand',
        ],
        default => [
            'eyebrow' => __('Your to-do list'),
            'title' => __('You’re all caught up!'),
            'text' => __('Nothing needs you right now. We’ll let you know the moment a new request comes in.'),
            'tone' => 'emerald',
        ],
    };
@endphp

<div wire:poll.visible.60s>
    <x-dashboard.greeting :subtitle="__('Here’s what needs you today.')">
        <x-slot:actions>
            <flux:button :href="route('company.vehicles.create')" variant="primary" icon="plus" wire:navigate>{{ __('List a vehicle') }}</flux:button>
        </x-slot:actions>
    </x-dashboard.greeting>

    <x-app.content class="space-y-8">
        {{-- The guide: what to do now, in one sentence --}}
        <section class="flex items-start gap-3 rounded-2xl border border-zinc-200 bg-white p-5 sm:gap-4 sm:p-6" aria-live="polite">
            <span @class([
                'flex size-12 shrink-0 items-center justify-center rounded-full text-white shadow-lg',
                'bg-linear-to-br from-amber-400 to-amber-600 shadow-amber-500/30' => $coach['tone'] === 'amber',
                'bg-linear-to-br from-brand-500 to-brand-700 shadow-brand-600/30' => $coach['tone'] === 'brand',
                'bg-linear-to-br from-emerald-400 to-emerald-600 shadow-emerald-500/30' => $coach['tone'] === 'emerald',
            ]) aria-hidden="true">
                @if ($coach['tone'] === 'emerald')
                    <flux:icon.sparkles variant="solid" class="size-6 motion-safe:animate-date-pop" />
                @else
                    <flux:icon.hand-raised variant="solid" class="size-6 origin-bottom motion-safe:animate-wave" />
                @endif
            </span>

            <div
                wire:key="coach-{{ md5($coach['title']) }}"
                @class([
                    'min-w-0 flex-1 rounded-2xl rounded-tl-md px-4 py-4 motion-safe:animate-step-in sm:px-5',
                    'bg-amber-50' => $coach['tone'] === 'amber',
                    'bg-brand-50' => $coach['tone'] === 'brand',
                    'bg-emerald-50' => $coach['tone'] === 'emerald',
                ])
                data-test="company-coach"
            >
                <p @class([
                    'text-xs font-bold uppercase tracking-wide',
                    'text-amber-700' => $coach['tone'] === 'amber',
                    'text-brand-600' => $coach['tone'] === 'brand',
                    'text-emerald-700' => $coach['tone'] === 'emerald',
                ])>{{ $coach['eyebrow'] }}</p>
                <p class="mt-1 text-lg font-bold leading-snug text-zinc-900 sm:text-xl">{{ $coach['title'] }}</p>
                <p class="mt-1.5 text-base leading-relaxed text-zinc-700">{{ $coach['text'] }}</p>

                @if ($stats['vehicles'] === 0)
                    <flux:button class="mt-3" :href="route('company.vehicles.create')" variant="primary" icon="plus" wire:navigate>{{ __('List my first vehicle') }}</flux:button>
                @endif
            </div>
        </section>

        {{-- To-do list: one big card per thing to do, most urgent first --}}
        @if ($hasTodo || $stats['waiting'] > 0)
            <section class="space-y-3" data-test="company-todo">
                @foreach ($pending as $booking)
                    @php
                        $minutesLeft = (int) max(0, now()->diffInMinutes($booking->pickup_at, false));
                        $urgent = $minutesLeft < 24 * 60;
                        $initials = Str::of($booking->renter->name)->explode(' ')->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
                    @endphp

                    <a
                        wire:key="todo-request-{{ $booking->id }}"
                        href="{{ route('company.bookings.show', $booking) }}"
                        wire:navigate
                        class="group block rounded-2xl border-2 border-amber-300 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-400 hover:shadow-lg hover:shadow-amber-500/10 motion-safe:animate-step-in"
                        style="animation-delay: {{ $loop->index * 70 }}ms"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-amber-100 text-base font-bold text-amber-800">{{ $initials }}</span>

                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">
                                        <span class="size-1.5 rounded-full bg-amber-500 motion-safe:animate-pulse" aria-hidden="true"></span>
                                        {{ __('New request') }}
                                    </span>
                                    <span class="text-xs text-zinc-500">{{ __('Sent :ago', ['ago' => $booking->created_at->diffForHumans()]) }}</span>
                                </p>
                                <p class="mt-1.5 text-lg font-bold text-zinc-900">{{ __(':renter wants your :vehicle', ['renter' => $booking->renter->name, 'vehicle' => $booking->vehicle->name]) }}</p>
                                <p class="mt-1 text-sm text-zinc-600">
                                    {{ $booking->pickup_at->format('D, M j · g:i A') }}
                                    <span aria-hidden="true">&rarr;</span>
                                    {{ $booking->return_at->format('D, M j · g:i A') }}
                                    &middot; {{ trans_choice('{1} :count day|[2,*] :count days', $booking->days, ['count' => $booking->days]) }}
                                </p>
                                <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                    <span><span class="font-bold text-zinc-900">&#8369;{{ number_format($booking->subtotal) }}</span> <span class="text-zinc-500">{{ __('for the rental') }}</span></span>
                                    <span @class(['inline-flex items-center gap-1 font-semibold', 'text-red-600' => $urgent, 'text-amber-700' => ! $urgent])>
                                        <flux:icon.clock variant="micro" class="size-4" />
                                        {{ __('Answer within :time', ['time' => $booking->pickup_at->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE, parts: 2)]) }}
                                    </span>
                                </p>
                            </div>

                            <span class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-amber-500 px-5 py-3 text-base font-bold text-white shadow-sm transition group-hover:bg-amber-600">
                                {{ __('Review and answer') }}
                                <flux:icon.arrow-right variant="mini" class="size-5 transition group-hover:translate-x-1" />
                            </span>
                        </div>
                    </a>
                @endforeach

                @foreach ($handovers as $booking)
                    <a
                        wire:key="todo-handover-{{ $booking->id }}"
                        href="{{ route('company.bookings.show', $booking) }}"
                        wire:navigate
                        class="group block rounded-2xl border-2 border-brand-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-400 hover:shadow-lg hover:shadow-brand-600/10 motion-safe:animate-step-in"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700">
                                <flux:icon.key variant="mini" class="size-6" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p><span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">{{ __('Paid — hand over') }}</span></p>
                                <p class="mt-1.5 text-lg font-bold text-zinc-900">{{ __('Hand over the :vehicle to :renter', ['vehicle' => $booking->vehicle->name, 'renter' => $booking->renter->name]) }}</p>
                                <p class="mt-1 text-sm text-zinc-600">{{ Str::ucfirst($when($booking->pickup_at)) }} &middot; {{ $booking->pickup_location }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-base font-bold text-white shadow-sm transition group-hover:bg-brand-700">
                                {{ __('See handover steps') }}
                                <flux:icon.arrow-right variant="mini" class="size-5 transition group-hover:translate-x-1" />
                            </span>
                        </div>
                    </a>
                @endforeach

                @foreach ($returns as $booking)
                    <a
                        wire:key="todo-return-{{ $booking->id }}"
                        href="{{ route('company.bookings.show', $booking) }}"
                        wire:navigate
                        class="group block rounded-2xl border-2 border-violet-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-400 hover:shadow-lg hover:shadow-violet-600/10 motion-safe:animate-step-in"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-700">
                                <flux:icon.arrow-uturn-left variant="mini" class="size-6" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p><span class="inline-flex rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-bold text-violet-800">{{ __('Coming back') }}</span></p>
                                <p class="mt-1.5 text-lg font-bold text-zinc-900">{{ __('The :vehicle comes back from :renter', ['vehicle' => $booking->vehicle->name, 'renter' => $booking->renter->name]) }}</p>
                                <p class="mt-1 text-sm text-zinc-600">{{ Str::ucfirst($when($booking->return_at)) }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-base font-bold text-white shadow-sm transition group-hover:bg-violet-700">
                                {{ __('Get ready for the return') }}
                                <flux:icon.arrow-right variant="mini" class="size-5 transition group-hover:translate-x-1" />
                            </span>
                        </div>
                    </a>
                @endforeach

                @if ($stats['waiting'] > 0)
                    <a
                        href="{{ route('company.bookings.index', ['tab' => 'upcoming']) }}"
                        wire:navigate
                        class="flex items-center gap-3 rounded-2xl border border-dashed border-zinc-300 bg-white/70 px-5 py-4 text-sm transition hover:border-zinc-400"
                    >
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-500">
                            <flux:icon.clock variant="mini" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1 text-zinc-600">
                            <span class="font-semibold text-zinc-900">{{ trans_choice('{1} 1 approved booking is waiting for the renter.|[2,*] :count approved bookings are waiting for their renters.', $stats['waiting'], ['count' => $stats['waiting']]) }}</span>
                            {{ __('They still need to sign the contract and pay. Nothing for you to do yet.') }}
                        </span>
                        <span class="shrink-0 font-semibold text-brand-600">{{ __('See them') }} <span aria-hidden="true">&rarr;</span></span>
                    </a>
                @endif
            </section>
        @endif

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-dashboard.stat
                :label="__('Requests to answer')"
                :value="$stats['pending']"
                :href="route('company.bookings.index')"
                :tone="$stats['pending'] > 0 ? 'attention' : 'default'"
                :hint="$stats['pending'] > 0 ? null : __('All answered')"
                :cta="$stats['pending'] > 0 ? __('Answer now') : null"
            />
            <x-dashboard.stat
                :label="__('Upcoming rentals')"
                :value="$stats['upcoming']"
                :hint="__('Approved and paid trips')"
                :href="route('company.bookings.index', ['tab' => 'upcoming'])"
                :cta="__('See schedule')"
            />
            <x-dashboard.stat
                :label="__('Listed vehicles')"
                :value="$stats['listed']"
                :hint="__(':count in your fleet', ['count' => $stats['vehicles']])"
                :href="route('company.vehicles.index')"
                :cta="__('Manage')"
            />
            <x-dashboard.stat
                :label="__('Earnings this month')"
                :value="'₱'.number_format($stats['earnedThisMonth'])"
                :hint="__('₱:total all time, before fees', ['total' => number_format($stats['earned'])])"
            />
        </div>

            <x-dashboard.panel :title="__('Coming up')" :description="__('Approved, confirmed, and ongoing rentals')" :href="route('company.bookings.index', ['tab' => 'upcoming'])">
                @forelse ($this->schedule as $booking)
                    <a wire:key="schedule-{{ $booking->id }}" href="{{ route('company.bookings.show', $booking) }}" wire:navigate class="-mx-2 flex items-center gap-3 rounded-xl px-2 py-3 transition hover:bg-zinc-50">
                        <span class="flex w-12 shrink-0 flex-col items-center rounded-lg bg-zinc-50 py-1 ring-1 ring-zinc-200">
                            <span class="text-[0.65rem] font-bold uppercase text-brand-600">{{ $booking->pickup_at->format('M') }}</span>
                            <span class="text-lg font-bold leading-none text-zinc-900">{{ $booking->pickup_at->format('j') }}</span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-zinc-900">{{ $booking->vehicle->name }}</p>
                            <p class="text-sm text-zinc-500">{{ $booking->renter->name }} &middot; {{ $booking->pickup_at->format('D, M j · g:i A') }}</p>
                        </div>
                        <flux:badge size="sm" :color="$booking->status->color()">{{ $booking->status->label() }}</flux:badge>
                    </a>
                @empty
                    <p class="py-4 text-center text-sm text-zinc-500">{{ __('No upcoming rentals yet.') }}</p>
                @endforelse
            </x-dashboard.panel>

        <x-dashboard.panel :title="__('Your fleet')" :href="route('company.vehicles.index')" :link-label="__('Manage vehicles')">
            @if ($this->fleet->isEmpty())
                <div class="py-6 text-center">
                    <p class="font-medium text-zinc-900">{{ __('No vehicles yet') }}</p>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('List your first vehicle to start receiving booking requests.') }}</p>
                    <flux:button class="mt-4" :href="route('company.vehicles.create')" variant="primary" icon="plus" wire:navigate>{{ __('List my first vehicle') }}</flux:button>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($this->fleet as $vehicle)
                        <a wire:key="fleet-{{ $vehicle->id }}" href="{{ route('company.vehicles.edit', $vehicle) }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-brand-200">
                            <x-marketing.vehicle-image :vehicle="$vehicle" :caption="false" class="aspect-[4/3] !w-16 shrink-0 rounded-lg" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-zinc-900">{{ $vehicle->name }}</p>
                                <p class="text-xs text-zinc-500">&#8369;{{ number_format($vehicle->price_per_day) }}/{{ __('day') }} &middot; {{ trans_choice('{0} no active bookings|{1} :count active booking|[2,*] :count active bookings', $vehicle->active_bookings_count, ['count' => $vehicle->active_bookings_count]) }}</p>
                            </div>
                            <flux:badge size="sm" :color="$vehicle->status->color()">{{ $vehicle->status->label() }}</flux:badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-dashboard.panel>
    </x-app.content>
</div>
