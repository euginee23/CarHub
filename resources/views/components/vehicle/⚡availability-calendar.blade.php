<?php

use App\Models\Vehicle;
use App\Services\Availability\AvailabilityChecker;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Vehicle $vehicle;

    /**
     * The month on display, as Y-m.
     */
    public string $month = '';

    /**
     * The pickup day tapped on the calendar, as Y-m-d.
     */
    public string $pickupDate = '';

    /**
     * The return day tapped on the calendar, as Y-m-d.
     */
    public string $returnDate = '';

    /**
     * Why the last tapped day could not be used, in plain words.
     */
    public string $notice = '';

    /**
     * Which way the calendar last paged ("next" or "previous"), so the month slides in from that side.
     */
    public string $slide = '';

    /**
     * Start on the current month.
     */
    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    /**
     * The first day of the month on display, clamped to the bookable window.
     */
    #[Computed]
    public function monthStart(): CarbonImmutable
    {
        try {
            $month = CarbonImmutable::createFromFormat('!Y-m', $this->month);
        } catch (\Throwable) {
            $month = null;
        }

        return ($month ?: $this->firstMonth())->max($this->firstMonth())->min($this->lastMonth());
    }

    /**
     * Where the renter is in choosing dates: the pickup day first, then the return day, then done.
     */
    #[Computed]
    public function step(): string
    {
        if ($this->pickupDate === '') {
            return 'pickup';
        }

        return $this->returnDate === '' ? 'return' : 'done';
    }

    /**
     * The chosen pickup day, once tapped.
     */
    #[Computed]
    public function pickupDay(): ?CarbonImmutable
    {
        return $this->parseDay($this->pickupDate);
    }

    /**
     * The chosen return day, once tapped.
     */
    #[Computed]
    public function returnDay(): ?CarbonImmutable
    {
        return $this->parseDay($this->returnDate);
    }

    /**
     * The calendar grid: weeks starting on Sunday, each day with its state and its
     * place in the chosen trip ("band" is the strip joining pickup to return).
     *
     * @return array<int, array<int, array{date: string, day: int, weekday: int, inMonth: bool, bookable: bool, unavailable: bool, isToday: bool, isPickup: bool, isReturn: bool, band: string|null, bandStep: int}>>
     */
    #[Computed]
    public function weeks(): array
    {
        $start = $this->monthStart->startOfWeek(CarbonImmutable::SUNDAY);
        $end = $this->monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);
        $today = CarbonImmutable::today();
        $lastBookableDay = $today->addDays(config('carhub.booking.max_advance_days'));
        $unavailable = app(AvailabilityChecker::class)->unavailableDates($this->vehicle, $start, $end);
        $hasTrip = $this->pickupDay !== null && $this->returnDay !== null;

        $days = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $isUnavailable = isset($unavailable[$date]);

            $days[] = [
                'date' => $date,
                'day' => $day->day,
                'weekday' => $day->dayOfWeek,
                'inMonth' => $day->month === $this->monthStart->month,
                'bookable' => ! $isUnavailable && $day->gte($today) && $day->lte($lastBookableDay),
                'unavailable' => $isUnavailable,
                'isToday' => $day->isSameDay($today),
                'isPickup' => $date === $this->pickupDate,
                'isReturn' => $date === $this->returnDate,
                'band' => match (true) {
                    ! $hasTrip => null,
                    $date === $this->pickupDate => 'right',
                    $date === $this->returnDate => 'left',
                    $date > $this->pickupDate && $date < $this->returnDate => 'full',
                    default => null,
                },
                'bandStep' => $hasTrip ? (int) min(14, max(0, $this->pickupDay->diffInDays($day))) : 0,
            ];
        }

        return array_chunk($days, 7);
    }

    /**
     * Whether earlier or later months can be shown.
     *
     * @return array{previous: bool, next: bool}
     */
    #[Computed]
    public function navigation(): array
    {
        return [
            'previous' => $this->monthStart->gt($this->firstMonth()),
            'next' => $this->monthStart->lt($this->lastMonth()),
        ];
    }

    /**
     * Move the calendar by the given number of months.
     */
    public function shiftMonth(int $months): void
    {
        $this->slide = $months > 0 ? 'next' : 'previous';
        $this->notice = '';
        $this->month = $this->monthStart->addMonths($months)->max($this->firstMonth())->min($this->lastMonth())->format('Y-m');

        unset($this->monthStart);
    }

    /**
     * Use a tapped day: the first tap picks up, the next later tap returns.
     * Tapping the pickup day again undoes it, an earlier day moves the pickup,
     * and once both are set a tap starts over. The booking panel is told each time.
     */
    public function select(string $date): void
    {
        $this->notice = '';
        $day = $this->parseDay($date);

        if ($day === null || ! $this->isBookable($day)) {
            $this->notice = __('That day can’t be booked. Pick one of the dark-numbered days.');

            return;
        }

        $choosingReturn = $this->pickupDate !== '' && $this->returnDate === '';

        if ($choosingReturn && $date === $this->pickupDate) {
            $this->pickupDate = '';
        } elseif ($choosingReturn && $date > $this->pickupDate) {
            if (! $this->canReturnOn($day)) {
                return;
            }

            $this->returnDate = $date;
        } else {
            $this->pickupDate = $date;
            $this->returnDate = '';
        }

        $this->dispatchDates();
    }

    /**
     * Forget both days and begin again with the pickup day.
     */
    public function clearDates(): void
    {
        $this->reset('pickupDate', 'returnDate', 'notice');

        $this->dispatchDates();
    }

    /**
     * Show dates typed into the booking panel, turning to the pickup month.
     */
    #[On('booking-dates-changed')]
    public function syncDates(string $pickupDate = '', string $returnDate = ''): void
    {
        $pickup = $this->parseDay($pickupDate);
        $return = $this->parseDay($returnDate);

        $this->pickupDate = $pickup?->toDateString() ?? '';
        $this->returnDate = $pickup && $return?->gt($pickup) ? $return->toDateString() : '';
        $this->notice = '';

        if ($pickup && $pickup->format('Y-m') !== $this->month) {
            $this->slide = $pickup->format('Y-m') > $this->month ? 'next' : 'previous';
            $this->month = $pickup->format('Y-m');
        }
    }

    /**
     * Whether the trip can run from the pickup day to this one: no unavailable
     * day in between and no longer than the longest rental. Explains why not.
     */
    protected function canReturnOn(CarbonImmutable $day): bool
    {
        $pickup = $this->parseDay($this->pickupDate);
        $blocked = array_keys(app(AvailabilityChecker::class)->unavailableDates($this->vehicle, $pickup, $day));

        if ($blocked !== []) {
            $this->notice = __('You can’t return on :return — the car isn’t available on :blocked. Pick an earlier return day, or tap a new pickup day after :blocked.', [
                'return' => $day->format('M j'),
                'blocked' => CarbonImmutable::parse(min($blocked))->format('M j'),
            ]);

            return false;
        }

        $maxDays = (int) config('carhub.booking.max_rental_days');

        if ($pickup->diffInDays($day) > $maxDays) {
            $this->notice = __('Trips can be up to :days days. Pick a return day on or before :date.', [
                'days' => $maxDays,
                'date' => $pickup->addDays($maxDays)->format('M j'),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Whether a day is in the bookable window and the vehicle is free on it.
     */
    protected function isBookable(CarbonImmutable $day): bool
    {
        $today = CarbonImmutable::today();

        return $day->gte($today)
            && $day->lte($today->addDays(config('carhub.booking.max_advance_days')))
            && app(AvailabilityChecker::class)->unavailableDates($this->vehicle, $day, $day) === [];
    }

    /**
     * Tell the booking panel which days are chosen.
     */
    protected function dispatchDates(): void
    {
        $this->dispatch('calendar-dates-selected', pickupDate: $this->pickupDate, returnDate: $this->returnDate);
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

    /**
     * The earliest month that can be shown.
     */
    protected function firstMonth(): CarbonImmutable
    {
        return CarbonImmutable::today()->startOfMonth();
    }

    /**
     * The latest month that can be shown.
     */
    protected function lastMonth(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays(config('carhub.booking.max_advance_days'))->startOfMonth();
    }
}; ?>

<div
    x-data="{
        hovered: null,
        previewShape(date) {
            const pickup = this.$wire.pickupDate;

            if (! pickup || this.$wire.returnDate || ! this.hovered || this.hovered <= pickup) {
                return null;
            }

            if (date === pickup) {
                return 'right';
            }

            if (date === this.hovered) {
                return 'left';
            }

            return date > pickup && date < this.hovered ? 'full' : null;
        },
        previewLabel() {
            const days = Math.round((Date.parse(this.hovered) - Date.parse(this.$wire.pickupDate)) / 86400000);

            return days === 1 ? @js(__('1 day')) : @js(__(':count days')).replace(':count', days);
        },
    }"
    data-test="availability-calendar"
>
    @php
        $step = $this->step;
        $tripDays = $step === 'done' ? (int) $this->pickupDay->diffInDays($this->returnDay) : 0;
    @endphp

    {{-- Step by step: the pickup day, then the return day --}}
    <ol class="grid grid-cols-2 gap-2 sm:gap-3" aria-label="{{ __('Your trip dates') }}">
        @foreach ([
            'pickup' => ['number' => 1, 'label' => __('Pickup day'), 'day' => $this->pickupDay, 'waiting' => __('Tap a day')],
            'return' => ['number' => 2, 'label' => __('Return day'), 'day' => $this->returnDay, 'waiting' => $step === 'pickup' ? __('Comes next') : __('Tap a day')],
        ] as $key => $pill)
            @php($state = $pill['day'] ? 'done' : ($step === $key ? 'active' : 'waiting'))

            <li
                wire:key="pill-{{ $key }}"
                @class([
                    'flex items-center gap-2.5 rounded-xl border px-2.5 py-2.5 transition duration-300 sm:gap-3 sm:px-3',
                    'border-brand-500 bg-white shadow-md shadow-brand-600/10 ring-4 ring-brand-100' => $state === 'active',
                    'border-brand-100 bg-brand-50' => $state === 'done',
                    'border-dashed border-zinc-200 bg-zinc-50' => $state === 'waiting',
                ])
            >
                <span @class([
                    'relative flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-bold sm:size-8',
                    'bg-brand-600 text-white' => $state === 'active',
                    'bg-emerald-500 text-white' => $state === 'done',
                    'bg-zinc-200 text-zinc-500' => $state === 'waiting',
                ])>
                    @if ($state === 'active')
                        <span class="absolute inset-0 rounded-full bg-brand-400 opacity-50 motion-safe:animate-ping" aria-hidden="true"></span>
                    @endif

                    @if ($state === 'done')
                        <flux:icon.check variant="micro" class="relative size-4 motion-safe:animate-date-pop" />
                    @else
                        <span class="relative">{{ $pill['number'] }}</span>
                    @endif
                </span>

                <span class="min-w-0">
                    <span class="block text-[0.65rem] font-semibold uppercase tracking-wide text-zinc-500 sm:text-[0.7rem]">{{ $pill['label'] }}</span>
                    <span
                        wire:key="pill-{{ $key }}-{{ $pill['day']?->toDateString() ?? $state }}"
                        @class([
                            'block text-sm font-semibold leading-snug motion-safe:animate-step-in',
                            'text-zinc-900' => $state === 'done',
                            'text-brand-700' => $state === 'active',
                            'text-zinc-400' => $state === 'waiting',
                        ])
                        data-test="{{ $key }}-day"
                    >{{ $pill['day']?->format('D, M j') ?? $pill['waiting'] }}</span>
                </span>
            </li>
        @endforeach
    </ol>

    {{-- What to do next, in plain words --}}
    <div class="mt-3" aria-live="polite">
        <div
            wire:key="guide-{{ $step }}-{{ $pickupDate }}-{{ $returnDate }}"
            @class([
                'flex flex-col gap-3 rounded-xl px-4 py-3.5 motion-safe:animate-step-in sm:flex-row sm:items-center',
                'bg-brand-50 text-brand-950' => $step !== 'done',
                'bg-emerald-50 text-emerald-950' => $step === 'done',
            ])
            data-test="calendar-guide"
        >
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <span @class([
                    'flex size-10 shrink-0 items-center justify-center rounded-full bg-white shadow-sm',
                    'text-brand-600' => $step !== 'done',
                    'text-emerald-600' => $step === 'done',
                ])>
                    @if ($step === 'done')
                        <flux:icon.sparkles class="size-5 motion-safe:animate-date-pop" />
                    @else
                        <flux:icon.cursor-arrow-rays class="size-5 motion-safe:animate-bounce" />
                    @endif
                </span>

                <div class="min-w-0">
                    @if ($step === 'pickup')
                        <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-brand-600">{{ __('Step 1 of 2') }}</p>
                        <p class="font-semibold">{{ __('Tap the day you’ll pick up the car') }}</p>
                        <p class="mt-0.5 text-sm text-brand-950/70">{{ __('Dark numbers are free days. Red ones are taken.') }}</p>
                    @elseif ($step === 'return')
                        <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-brand-600">{{ __('Step 2 of 2') }}</p>
                        <p class="font-semibold">{{ __('Nice! Now tap the day you’ll bring it back') }}</p>
                        <p class="mt-0.5 text-sm text-brand-950/70">{{ __('Picking up :date. Tapped the wrong day? Tap it again to undo.', ['date' => $this->pickupDay->format('l, M j')]) }}</p>
                    @else
                        <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-emerald-600">{{ __('All set') }}</p>
                        <p class="font-semibold">{{ __('A :count-day trip — :pickup to :return', ['count' => $tripDays, 'pickup' => $this->pickupDay->format('M j'), 'return' => $this->returnDay->format('M j')]) }}</p>
                        <p class="mt-0.5 text-sm text-emerald-950/70">{{ __('Now choose your pickup and return times in the booking box, then book.') }}</p>
                    @endif
                </div>
            </div>

            @if ($step !== 'pickup')
                <div class="flex shrink-0 items-center gap-2 sm:justify-end">
                    @if ($step === 'done')
                        <a
                            href="#book"
                            x-on:click.prevent="document.getElementById('book')?.scrollIntoView({ behavior: 'smooth' })"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 lg:hidden"
                        >
                            {{ __('See price & book') }}
                            <flux:icon.arrow-down variant="micro" class="size-4 motion-safe:animate-bounce" />
                        </a>
                    @endif

                    <button
                        type="button"
                        wire:click="clearDates"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm ring-1 ring-zinc-200 transition hover:bg-zinc-50 hover:text-zinc-900"
                    >
                        <flux:icon.arrow-uturn-left variant="micro" class="size-4" />
                        {{ __('Start over') }}
                    </button>
                </div>
            @endif
        </div>

        @if ($notice !== '')
            <div
                wire:key="notice-{{ md5($notice) }}"
                role="alert"
                class="mt-3 flex items-start gap-2.5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200 motion-safe:animate-shake"
                data-test="calendar-notice"
            >
                <flux:icon.exclamation-triangle variant="mini" class="mt-0.5 size-4 shrink-0 text-amber-500" />
                <p>{{ $notice }}</p>
            </div>
        @endif
    </div>

    {{-- Month --}}
    <div class="mt-5 flex items-center justify-between">
        <button
            type="button"
            wire:click="shiftMonth(-1)"
            @disabled(! $this->navigation['previous'])
            class="rounded-full p-2 text-zinc-600 transition hover:bg-zinc-100 active:scale-90 disabled:opacity-30"
            aria-label="{{ __('Previous month') }}"
        >
            <flux:icon.chevron-left variant="micro" class="size-5" />
        </button>
        <p wire:key="month-label-{{ $this->monthStart->format('Y-m') }}" class="text-base font-semibold text-zinc-900 motion-safe:animate-step-in">
            {{ $this->monthStart->format('F Y') }}
        </p>
        <button
            type="button"
            wire:click="shiftMonth(1)"
            @disabled(! $this->navigation['next'])
            class="rounded-full p-2 text-zinc-600 transition hover:bg-zinc-100 active:scale-90 disabled:opacity-30"
            aria-label="{{ __('Next month') }}"
        >
            <flux:icon.chevron-right variant="micro" class="size-5" />
        </button>
    </div>

    <div class="mt-3 grid grid-cols-7 text-center text-xs font-medium text-zinc-400" aria-hidden="true">
        @foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $weekday)
            <span class="pb-2">{{ __($weekday) }}</span>
        @endforeach
    </div>

    {{-- Days --}}
    <div class="overflow-hidden">
        <div
            wire:key="grid-{{ $this->monthStart->format('Y-m') }}"
            wire:loading.class="opacity-60"
            wire:target="shiftMonth"
            x-on:mouseleave="hovered = null"
            @class([
                'grid grid-cols-7 gap-y-1 transition-opacity',
                'motion-safe:animate-month-next' => $slide === 'next',
                'motion-safe:animate-month-previous' => $slide === 'previous',
            ])
        >
            @foreach ($this->weeks as $week)
                @foreach ($week as $day)
                    @php($selected = $day['isPickup'] || $day['isReturn'])

                    <div
                        wire:key="day-{{ $day['date'] }}"
                        class="relative flex h-12 items-center justify-center sm:h-14"
                        @if ($day['inMonth']) x-on:mouseenter="hovered = {{ $day['bookable'] ? "'".$day['date']."'" : 'null' }}" @endif
                    >
                        @if ($day['inMonth'])
                            {{-- The chosen trip, filling in from pickup to return --}}
                            @if ($day['band'])
                                <span
                                    aria-hidden="true"
                                    @class([
                                        'absolute inset-y-1 origin-left bg-brand-100 motion-safe:animate-band-in',
                                        'inset-x-0' => $day['band'] === 'full',
                                        'left-1/2 right-0' => $day['band'] === 'right',
                                        'left-0 right-1/2' => $day['band'] === 'left',
                                        'rounded-l-full' => $day['weekday'] === 0 && $day['band'] !== 'right',
                                        'rounded-r-full' => $day['weekday'] === 6 && $day['band'] !== 'left',
                                    ])
                                    style="animation-delay: {{ $day['bandStep'] * 35 }}ms"
                                ></span>
                            @endif

                            {{-- A preview of the trip while choosing the return day --}}
                            <span
                                aria-hidden="true"
                                x-cloak
                                x-show="previewShape('{{ $day['date'] }}')"
                                class="absolute inset-y-1 border-y-2 border-dashed border-brand-200 bg-brand-50"
                                :class="{
                                    'inset-x-0': previewShape('{{ $day['date'] }}') === 'full',
                                    'left-1/2 right-0': previewShape('{{ $day['date'] }}') === 'right',
                                    'left-0 right-1/2': previewShape('{{ $day['date'] }}') === 'left',
                                }"
                            ></span>

                            <span
                                x-cloak
                                x-show="previewShape('{{ $day['date'] }}') === 'left'"
                                x-transition.opacity
                                x-text="previewLabel()"
                                class="pointer-events-none absolute -top-2.5 left-1/2 z-20 -translate-x-1/2 whitespace-nowrap rounded-full bg-zinc-900 px-2 py-0.5 text-[0.65rem] font-semibold text-white shadow-md"
                            ></span>

                            <button
                                type="button"
                                wire:click="select('{{ $day['date'] }}')"
                                @disabled(! $day['bookable'])
                                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                aria-label="{{ \Carbon\CarbonImmutable::parse($day['date'])->format('F j') }}{{ $day['unavailable'] ? ', '.__('unavailable') : '' }}{{ $day['isPickup'] ? ', '.__('your pickup day') : '' }}{{ $day['isReturn'] ? ', '.__('your return day') : '' }}{{ $day['isToday'] ? ', '.__('today') : '' }}"
                                @if ($day['unavailable']) title="{{ __('Unavailable') }}" @endif
                                @class([
                                    'relative z-10 flex size-11 flex-col items-center justify-center rounded-full text-sm transition duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:size-12',
                                    'bg-brand-600 font-semibold text-white shadow-lg shadow-brand-600/30 motion-safe:animate-date-pop' => $selected,
                                    'font-semibold text-brand-800 hover:bg-white' => $day['band'] === 'full',
                                    'font-medium text-zinc-900 hover:scale-110 hover:bg-brand-50 hover:text-brand-700 active:scale-95' => $day['bookable'] && ! $selected && $day['band'] !== 'full',
                                    'data-loading:scale-90 data-loading:bg-brand-200' => $day['bookable'] && ! $selected,
                                    'cursor-not-allowed bg-red-50 text-red-300 line-through' => $day['unavailable'],
                                    'cursor-not-allowed text-zinc-300' => ! $day['bookable'] && ! $day['unavailable'],
                                ])
                                :class="{ 'scale-110 bg-white text-brand-700 ring-2 ring-brand-400': previewShape('{{ $day['date'] }}') === 'left' }"
                            >
                                <span class="leading-none">{{ $day['day'] }}</span>

                                @if ($day['isPickup'])
                                    <span class="mt-0.5 text-[0.55rem] font-bold uppercase leading-none tracking-wide sm:text-[0.6rem]">{{ __('Pickup') }}</span>
                                @elseif ($day['isReturn'])
                                    <span class="mt-0.5 text-[0.55rem] font-bold uppercase leading-none tracking-wide sm:text-[0.6rem]">{{ __('Return') }}</span>
                                @elseif ($day['isToday'])
                                    <span class="mt-1 size-1 rounded-full bg-brand-500" aria-hidden="true"></span>
                                @endif
                            </button>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-zinc-100 pt-4 text-xs text-zinc-500">
        <span class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-white ring-1 ring-zinc-300"></span>{{ __('Available') }}</span>
        <span class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-red-50 ring-1 ring-red-200"></span>{{ __('Unavailable') }}</span>
        <span class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-brand-600"></span>{{ __('Your pickup & return') }}</span>
        <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-full bg-brand-100"></span>{{ __('Your trip') }}</span>
        <span class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-brand-500"></span>{{ __('Today') }}</span>
    </div>
</div>
