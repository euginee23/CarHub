<?php

use App\Actions\Bookings\CreateBookingRequest;
use App\Enums\BookingStatus;
use App\Models\Vehicle;
use App\Services\Availability\AvailabilityChecker;
use App\Services\Pricing\RentalQuote;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Vehicle $vehicle;

    public string $pickupDate = '';

    public string $pickupTime = '09:00';

    public string $returnDate = '';

    public string $returnTime = '09:00';

    public string $notes = '';

    /**
     * Why a time was moved to one that works, in plain words.
     */
    public string $timeNote = '';

    /**
     * The requested rental window, once both dates and times parse.
     *
     * @return array{pickup: CarbonImmutable, return: CarbonImmutable}|null
     */
    #[Computed]
    public function schedule(): ?array
    {
        if (blank($this->pickupDate) || blank($this->returnDate)) {
            return null;
        }

        try {
            $pickup = CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->pickupDate.' '.$this->pickupTime);
            $return = CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->returnDate.' '.$this->returnTime);
        } catch (\Throwable) {
            return null;
        }

        return $pickup && $return ? ['pickup' => $pickup, 'return' => $return] : null;
    }

    /**
     * Every reason the chosen schedule cannot be booked: broken scheduling rules
     * first, then a clash with dates the vehicle is already unavailable.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function problems(): array
    {
        if (! $this->schedule) {
            return [];
        }

        $checker = app(AvailabilityChecker::class);
        $problems = $checker->scheduleErrors($this->schedule['pickup'], $this->schedule['return']);

        if ($problems === [] && ! $checker->isAvailable($this->vehicle, $this->schedule['pickup'], $this->schedule['return'])) {
            $problems[] = __('This vehicle is not available for all of those dates. Try different days.');
        }

        return $problems;
    }

    /**
     * Whether the chosen schedule is valid and the vehicle is free throughout.
     */
    #[Computed]
    public function isAvailable(): bool
    {
        return $this->schedule !== null && $this->problems === [];
    }

    /**
     * The price breakdown for a bookable schedule.
     */
    #[Computed]
    public function quote(): ?RentalQuote
    {
        return $this->isAvailable
            ? RentalQuote::for($this->vehicle, $this->schedule['pickup'], $this->schedule['return'])
            : null;
    }

    /**
     * The chosen pickup day, once picked.
     */
    #[Computed]
    public function pickupDay(): ?CarbonImmutable
    {
        return $this->parseDay($this->pickupDate);
    }

    /**
     * The chosen return day, once picked.
     */
    #[Computed]
    public function returnDay(): ?CarbonImmutable
    {
        return $this->parseDay($this->returnDate);
    }

    /**
     * Pickup and return times offered, on the half hour through the handover hours.
     *
     * @return array<int, string>
     */
    #[Computed(persist: true)]
    public function timeOptions(): array
    {
        $times = [];
        $until = CarbonImmutable::createFromFormat('!H:i', config('carhub.booking.handover_until'));

        for ($time = CarbonImmutable::createFromFormat('!H:i', config('carhub.booking.handover_from')); $time->lte($until); $time = $time->addMinutes(30)) {
            $times[] = $time->format('H:i');
        }

        return $times;
    }

    /**
     * The earliest pickup time on the chosen day, given the notice the rental company needs.
     */
    #[Computed]
    public function earliestPickupTime(): ?string
    {
        return $this->firstPickupSlot();
    }

    /**
     * The earliest return time on the chosen day, given the shortest rental.
     */
    #[Computed]
    public function earliestReturnTime(): ?string
    {
        return $this->firstReturnSlot();
    }

    /**
     * Send the booking request. Guests are asked to sign in first and brought
     * back to this vehicle afterwards.
     */
    public function requestBooking(CreateBookingRequest $createBookingRequest): void
    {
        $user = Auth::user();

        if ($user === null) {
            session()->put('url.intended', route('vehicles.show', $this->vehicle));

            $this->redirectRoute('login');

            return;
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $this->redirectRoute('verification.notice');

            return;
        }

        if ($user->isRenter() && ! $user->hasVerifiedIdentity()) {
            $this->redirectRoute('identity.edit');

            return;
        }

        $this->validate(['notes' => ['nullable', 'string', 'max:500']]);

        if (! $this->schedule) {
            $this->addError('schedule', __('Choose your pickup and return dates first.'));

            return;
        }

        $booking = $createBookingRequest->handle($user, $this->vehicle, $this->schedule['pickup'], $this->schedule['return'], filled($this->notes) ? $this->notes : null);

        session()->flash('status', $booking->status === BookingStatus::Approved
            ? __('Booked! The rental company uses instant book, so you can go straight to checkout.')
            : __('Request sent. The rental company has been notified.'));

        $this->redirectRoute('trips.show', $booking);
    }

    /**
     * Take the days tapped on the availability calendar, which walks the renter
     * through choosing pickup then return.
     */
    #[On('calendar-dates-selected')]
    public function useCalendarDates(string $pickupDate = '', string $returnDate = ''): void
    {
        if ($pickupDate !== $this->pickupDate) {
            $this->timeNote = '';
        }

        $this->pickupDate = $pickupDate;
        $this->returnDate = $returnDate;

        $this->fitTimes();
    }

    /**
     * Keep the return time in step with the pickup time until the renter sets it
     * apart, so a trip stays whole days.
     */
    public function updatingPickupTime(string $value): void
    {
        if ($this->returnTime === $this->pickupTime) {
            $this->returnTime = $value;
        }
    }

    /**
     * Move the times to ones that work whenever a date or time changes.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['pickupDate', 'pickupTime', 'returnDate', 'returnTime'], true)) {
            $this->timeNote = '';

            $this->fitTimes();
        }
    }

    /**
     * Rather than show an error, move a pickup that is too soon to the earliest
     * time that works, and a return that makes the trip too short to the
     * earliest return, saying what was changed. The note stays until the
     * renter changes a time or the pickup day.
     */
    protected function fitTimes(): void
    {
        $earliestPickup = $this->firstPickupSlot();

        if ($earliestPickup !== null && $this->pickupTime < $earliestPickup) {
            if ($this->returnTime === $this->pickupTime) {
                $this->returnTime = $earliestPickup;
            }

            $this->pickupTime = $earliestPickup;
            $this->timeNote = __('Pickups need :hours hours’ notice, so we set the earliest time that works: :time.', [
                'hours' => config('carhub.booking.min_lead_hours'),
                'time' => $this->timeLabel($earliestPickup),
            ]);
        }

        $earliestReturn = $this->firstReturnSlot();

        if ($earliestReturn !== null && $this->returnTime < $earliestReturn) {
            $this->returnTime = $earliestReturn;
            $this->timeNote = __('Trips last at least :hours hours, so we set the return to :time.', [
                'hours' => config('carhub.booking.min_rental_hours'),
                'time' => $this->timeLabel($earliestReturn),
            ]);
        }
    }

    /**
     * The first offered time on the pickup day that gives enough notice.
     */
    protected function firstPickupSlot(): ?string
    {
        $day = $this->parseDay($this->pickupDate);

        return $day ? $this->firstSlotFrom($day, CarbonImmutable::now()->addHours(config('carhub.booking.min_lead_hours'))) : null;
    }

    /**
     * The first offered time on the return day that makes the trip long enough.
     */
    protected function firstReturnSlot(): ?string
    {
        $pickupDay = $this->parseDay($this->pickupDate);
        $returnDay = $this->parseDay($this->returnDate);

        if (! $pickupDay || ! $returnDay || ! in_array($this->pickupTime, $this->timeOptions, true)) {
            return null;
        }

        $pickup = $pickupDay->setTimeFromTimeString($this->pickupTime);

        return $this->firstSlotFrom($returnDay, $pickup->addHours(config('carhub.booking.min_rental_hours')));
    }

    /**
     * The first offered time on a day that is not before the given moment.
     */
    protected function firstSlotFrom(CarbonImmutable $day, CarbonInterface $earliest): ?string
    {
        foreach ($this->timeOptions as $time) {
            if ($day->setTimeFromTimeString($time)->gte($earliest)) {
                return $time;
            }
        }

        return null;
    }

    /**
     * An offered time as the renter reads it, e.g. 9:30 AM.
     */
    protected function timeLabel(string $time): string
    {
        return CarbonImmutable::createFromFormat('!H:i', $time)->format('g:i A');
    }

    /**
     * A Y-m-d string as a date, or null when it is not one.
     */
    protected function parseDay(string $date): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }

        return $day && $day->toDateString() === $date ? $day : null;
    }
}; ?>

<div
    x-data="{
        flash: null,
        flashTimer: null,
        showCalendar() {
            document.getElementById('availability')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            window.dispatchEvent(new CustomEvent('highlight-calendar'));
        },
    }"
    x-on:calendar-dates-selected.window="
        flash = $event.detail.returnDate ? 'return' : ($event.detail.pickupDate ? 'pickup' : null);
        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => flash = null, 1400);
    "
    class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-lg shadow-zinc-900/5 sm:p-6"
    data-test="booking-panel"
>
    @php
        $pickupDay = $this->pickupDay;
        $returnDay = $this->returnDay;
        $hasDates = $pickupDay !== null && $returnDay !== null;
        $priceState = ! $hasDates ? 'waiting' : ($this->problems !== [] ? 'problem' : 'active');
    @endphp

    <div class="flex items-start justify-between gap-3">
        <p class="flex items-baseline gap-1.5">
            <span class="text-3xl font-bold tracking-tight text-zinc-900">&#8369;{{ number_format($vehicle->price_per_day) }}</span>
            <span class="text-zinc-500">/ {{ __('day') }}</span>
        </p>

        @if ($vehicle->instant_book)
            <span class="mt-1.5 inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">
                <flux:icon.bolt variant="micro" class="size-3.5" />
                {{ __('Instant book') }}
            </span>
        @endif
    </div>
    <p class="mt-1 text-sm text-zinc-500">{{ __('Book in 3 easy steps.') }}</p>

    {{-- Step 1: the dates, chosen on the calendar --}}
    <section class="mt-6">
        <x-booking.step-heading number="1" :title="__('Your dates')" :state="$hasDates ? 'done' : 'active'">
            @if ($hasDates)
                <button type="button" x-on:click="showCalendar()" class="text-sm font-semibold text-brand-600 underline-offset-2 hover:underline">
                    {{ __('Change') }}
                </button>
            @endif
        </x-booking.step-heading>

        <div class="mt-3 grid grid-cols-2 gap-2">
            @foreach (['pickup' => [__('Pick up'), $pickupDay], 'return' => [__('Bring back'), $returnDay]] as $key => [$label, $day])
                <button
                    type="button"
                    x-on:click="showCalendar()"
                    @class([
                        'rounded-xl border p-3 text-left transition duration-300 hover:border-brand-300 hover:bg-brand-50/60',
                        'border-zinc-200 bg-white' => $day,
                        'border-dashed border-zinc-300 bg-zinc-50' => ! $day,
                    ])
                    :class="{ 'border-brand-500! bg-brand-50! ring-4 ring-brand-100': flash === '{{ $key }}' }"
                    data-test="{{ $key }}-card"
                >
                    <span class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $label }}</span>

                    @if ($day)
                        <span wire:key="{{ $key }}-card-{{ $day->toDateString() }}" class="mt-1 block motion-safe:animate-step-in">
                            <span class="block text-base font-bold text-zinc-900">{{ $day->format('D, M j') }}</span>
                            <span class="block text-xs text-zinc-500">{{ $day->format('Y') }}</span>
                        </span>
                    @else
                        <span class="mt-1 block text-sm font-medium text-zinc-400">{{ __('Not picked yet') }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        @unless ($hasDates)
            <button
                type="button"
                x-on:click="showCalendar()"
                class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-700 ring-1 ring-brand-100 transition hover:bg-brand-100"
                data-test="choose-dates"
            >
                <flux:icon.calendar-days variant="mini" class="size-5 motion-safe:animate-bounce" />
                {{ $pickupDay ? __('Now tap your return day on the calendar') : __('Tap your dates on the calendar') }}
            </button>
        @endunless
    </section>

    {{-- Step 2: the times, already set to ones that work --}}
    <section class="mt-6">
        <x-booking.step-heading number="2" :title="__('Pickup & return times')" :state="$hasDates ? 'done' : 'waiting'" />

        @if ($hasDates)
            <div wire:key="times-open" class="mt-3 motion-safe:animate-step-in">
                <div class="grid grid-cols-2 gap-2">
                    @foreach ([
                        'pickup' => ['label' => __('Pick up at'), 'aria' => __('Pickup time'), 'model' => 'pickupTime', 'earliest' => $this->earliestPickupTime],
                        'return' => ['label' => __('Bring back by'), 'aria' => __('Return time'), 'model' => 'returnTime', 'earliest' => $this->earliestReturnTime],
                    ] as $key => $field)
                        <label class="block">
                            <span class="block text-sm font-medium text-zinc-700">{{ $field['label'] }}</span>
                            <span class="relative mt-1.5 block">
                                <flux:icon.clock variant="mini" class="pointer-events-none absolute left-2.5 top-1/2 size-5 -translate-y-1/2 text-brand-600" />
                                <select
                                    wire:model.live="{{ $field['model'] }}"
                                    aria-label="{{ $field['aria'] }}"
                                    class="h-12 w-full cursor-pointer appearance-none rounded-xl border border-zinc-300 bg-white pl-9 pr-7 text-base font-semibold text-zinc-900 transition hover:border-brand-300 focus:border-brand-500 focus:outline-hidden focus:ring-4 focus:ring-brand-100"
                                    data-test="{{ $key }}-time"
                                >
                                    @foreach ($this->timeOptions as $time)
                                        <option value="{{ $time }}" @disabled($field['earliest'] !== null && $time < $field['earliest'])>
                                            {{ \Carbon\CarbonImmutable::createFromFormat('H:i', $time)->format('g:i A') }}
                                        </option>
                                    @endforeach
                                </select>
                                <flux:icon.chevron-down variant="micro" class="pointer-events-none absolute right-2 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                            </span>
                        </label>
                    @endforeach
                </div>

                @if ($timeNote !== '')
                    <p wire:key="time-note-{{ md5($timeNote) }}" class="mt-3 flex items-start gap-2 rounded-lg bg-brand-50 px-3 py-2.5 text-sm text-brand-900 motion-safe:animate-step-in" role="status" data-test="time-note">
                        <flux:icon.information-circle variant="mini" class="mt-0.5 size-4 shrink-0 text-brand-600" />
                        {{ $timeNote }}
                    </p>
                @endif

                @if ($this->quote && $returnTime > $pickupTime)
                    <p class="mt-2 flex items-start gap-2 text-sm text-zinc-600">
                        <flux:icon.light-bulb variant="mini" class="mt-0.5 size-4 shrink-0 text-amber-500" />
                        {{ __('Tip: bringing it back after :time counts as one more day.', ['time' => \Carbon\CarbonImmutable::createFromFormat('H:i', $pickupTime)->format('g:i A')]) }}
                    </p>
                @endif
            </div>
        @else
            <p class="mt-1.5 pl-10 text-sm text-zinc-400">{{ __('Comes after your dates.') }}</p>
        @endif
    </section>

    {{-- Step 3: the price, then the button --}}
    <section class="mt-6">
        <x-booking.step-heading number="3" :title="__('Check the price & book')" :state="$priceState" />

        <p wire:loading.flex wire:target="pickupTime,returnTime" class="mt-3 items-center gap-2 text-sm text-zinc-500">
            <svg class="size-4 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-20" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
            {{ __('Checking availability…') }}
        </p>

        <div wire:loading.remove wire:target="pickupTime,returnTime">
            @if (! $hasDates)
                <p class="mt-1.5 pl-10 text-sm text-zinc-400">{{ __('Your price shows here once you pick dates.') }}</p>
            @elseif ($this->problems !== [])
                <div wire:key="problems-{{ md5(implode('|', $this->problems)) }}" class="mt-3 rounded-xl bg-red-50 p-4 ring-1 ring-red-100 motion-safe:animate-shake" role="alert">
                    <p class="font-semibold text-red-800">{{ __('These dates won’t work yet') }}</p>
                    <ul class="mt-2 space-y-1.5">
                        @foreach ($this->problems as $problem)
                            <li class="flex items-start gap-2 text-sm text-red-700">
                                <flux:icon.exclamation-circle variant="mini" class="mt-0.5 size-4 shrink-0" />
                                {{ $problem }}
                            </li>
                        @endforeach
                    </ul>
                    <button type="button" x-on:click="showCalendar()" class="mt-3 text-sm font-semibold text-red-800 underline underline-offset-2">
                        {{ __('Pick different dates') }}
                    </button>
                </div>
            @elseif ($this->quote)
                <div wire:key="quote-{{ $this->quote->days }}-{{ $this->quote->total }}" class="mt-3">
                    <p class="flex items-center gap-2.5 rounded-xl bg-emerald-50 px-3.5 py-3 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-100 motion-safe:animate-step-in">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white motion-safe:animate-date-pop">
                            <flux:icon.check variant="micro" class="size-4" />
                        </span>
                        {{ __('Available for your dates') }}
                    </p>

                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex items-center justify-between text-zinc-600 motion-safe:animate-step-in" style="animation-delay: 80ms">
                            <dt>&#8369;{{ number_format($this->quote->dailyRate) }} &times; {{ trans_choice('{1} :count day|[2,*] :count days', $this->quote->days, ['count' => $this->quote->days]) }}</dt>
                            <dd class="font-medium text-zinc-900">&#8369;{{ number_format($this->quote->subtotal) }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-zinc-600 motion-safe:animate-step-in" style="animation-delay: 160ms">
                            <dt>{{ __('Service fee (:percent%)', ['percent' => RentalQuote::serviceFeePercent()]) }}</dt>
                            <dd class="font-medium text-zinc-900">&#8369;{{ number_format($this->quote->serviceFee) }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-zinc-100 pt-3 text-zinc-900 motion-safe:animate-step-in" style="animation-delay: 240ms">
                            <dt class="text-base font-semibold">{{ __('Total') }}</dt>
                            <dd
                                class="text-xl font-bold tabular-nums"
                                x-data="{
                                    shown: {{ $this->quote->total }},
                                    init() {
                                        const target = this.shown;

                                        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                                            return;
                                        }

                                        const start = performance.now();
                                        const tick = (now) => {
                                            const progress = Math.min(1, (now - start) / 700);
                                            this.shown = Math.round(target * (1 - Math.pow(1 - progress, 3)));

                                            if (progress < 1) {
                                                requestAnimationFrame(tick);
                                            }
                                        };

                                        requestAnimationFrame(tick);
                                    },
                                }"
                                x-text="'₱' + shown.toLocaleString('en-PH')"
                                data-test="booking-total"
                            >&#8369;{{ number_format($this->quote->total) }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>

        @if (auth()->check() && ! auth()->user()->isRenter())
            <p class="mt-5 rounded-xl bg-zinc-100 px-4 py-3 text-center text-sm text-zinc-600">
                @if (auth()->user()->isAdmin())
                    {{ __('You are signed in as an administrator. Admin accounts manage the marketplace and cannot book vehicles.') }}
                @else
                    {{ __('You are signed in with a rental company account. Rental company accounts list vehicles; renting needs a separate renter account.') }}
                @endif
            </p>
        @elseif (auth()->check() && ! auth()->user()->hasVerifiedIdentity())
            <div class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-center text-sm text-amber-800" data-test="verify-ids-to-book">
                <p>{{ __('Booking opens once an administrator approves your two valid IDs.') }}</p>
                <a href="{{ route('identity.edit') }}" class="mt-2 inline-block font-semibold underline">{{ __('Verify your IDs to book') }}</a>
            </div>
        @else
            @auth
                @if ($this->isAvailable)
                    <div x-data="{ writing: @js(filled($notes)) }" class="mt-4">
                        <button
                            type="button"
                            x-show="! writing"
                            x-on:click="writing = true"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 underline-offset-2 hover:underline"
                        >
                            <flux:icon.chat-bubble-left-ellipsis variant="micro" class="size-4" />
                            {{ __('Add a note for the rental company (optional)') }}
                        </button>
                        <div x-cloak x-show="writing" x-transition.opacity>
                            <flux:textarea wire:model="notes" :label="__('Message to the rental company (optional)')" rows="2" :placeholder="__('Where you are headed, who is driving…')" />
                        </div>
                    </div>
                @endif
            @endauth

            @error('schedule')
                <p class="mt-3 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror

            @if (auth()->check() && ! $this->isAvailable)
                <p class="mt-5 text-center text-sm font-medium text-zinc-500">
                    {{ $hasDates ? __('Change your dates or times above to continue.') : __('Pick your dates first — then this button wakes up.') }}
                </p>
            @endif

            <button
                type="button"
                wire:click="requestBooking"
                @disabled(auth()->check() && ! $this->isAvailable)
                data-test="request-booking"
                @class([
                    'group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-brand-600 px-5 py-4 text-base font-semibold text-white shadow-lg shadow-brand-600/25 transition hover:bg-brand-700 active:scale-[0.98] disabled:cursor-not-allowed disabled:bg-zinc-200 disabled:text-zinc-500 disabled:shadow-none',
                    'mt-2' => auth()->check() && ! $this->isAvailable,
                    'mt-5' => ! auth()->check() || $this->isAvailable,
                ])
            >
                @if ($this->isAvailable)
                    <span aria-hidden="true" class="pointer-events-none absolute inset-y-0 left-0 w-1/3 bg-linear-to-r from-transparent via-white/30 to-transparent motion-safe:animate-shine"></span>
                @endif

                <span class="relative in-data-loading:hidden">
                    @guest
                        {{ __('Sign in to book') }}
                    @else
                        {{ $vehicle->instant_book ? __('Book instantly') : __('Request to book') }}
                    @endguest
                </span>
                <flux:icon.arrow-right variant="mini" class="relative size-5 transition group-hover:translate-x-1 group-disabled:hidden in-data-loading:hidden" />
                <span class="relative hidden items-center gap-2 in-data-loading:inline-flex">
                    <svg class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    {{ __('Sending your request…') }}
                </span>
            </button>
        @endif

        <p class="mt-3 text-center text-xs text-zinc-500">
            {{ __('You will not be charged until the rental company confirms.') }}
        </p>
    </section>

    <ul class="mt-6 space-y-2.5 border-t border-zinc-100 pt-5 text-sm text-zinc-600">
        @foreach ([
            __('Free cancellation up to 24 hours before pickup'),
            __('Third-party liability coverage included'),
            __('Roadside assistance on every trip'),
        ] as $assurance)
            <li class="flex items-start gap-2.5">
                <svg class="mt-0.5 size-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                {{ $assurance }}
            </li>
        @endforeach
    </ul>
</div>
