<?php

use App\Models\Vehicle;
use App\Services\Availability\AvailabilityChecker;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Vehicle $vehicle;

    /**
     * The month on display, as Y-m.
     */
    public string $month = '';

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
     * The calendar grid: weeks starting on Sunday, each day with its state.
     *
     * @return array<int, array<int, array{date: string, day: int, inMonth: bool, bookable: bool, unavailable: bool}>>
     */
    #[Computed]
    public function weeks(): array
    {
        $start = $this->monthStart->startOfWeek(CarbonImmutable::SUNDAY);
        $end = $this->monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);
        $today = CarbonImmutable::today();
        $lastBookableDay = $today->addDays(config('carhub.booking.max_advance_days'));
        $unavailable = app(AvailabilityChecker::class)->unavailableDates($this->vehicle, $start, $end);

        $days = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $isUnavailable = isset($unavailable[$date]);

            $days[] = [
                'date' => $date,
                'day' => $day->day,
                'inMonth' => $day->month === $this->monthStart->month,
                'bookable' => ! $isUnavailable && $day->gte($today) && $day->lte($lastBookableDay),
                'unavailable' => $isUnavailable,
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
        $this->month = $this->monthStart->addMonths($months)->max($this->firstMonth())->min($this->lastMonth())->format('Y-m');
    }

    /**
     * Pass a clicked day on to the booking panel.
     */
    public function select(string $date): void
    {
        $day = collect($this->weeks)->flatten(1)->firstWhere('date', $date);

        if ($day && $day['bookable']) {
            $this->dispatch('calendar-date-selected', date: $date);
        }
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

<div>
    <div class="flex items-center justify-between">
        <button
            type="button"
            wire:click="shiftMonth(-1)"
            @disabled(! $this->navigation['previous'])
            class="rounded-lg p-2 text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-30"
            aria-label="{{ __('Previous month') }}"
        >
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
        </button>
        <p class="text-sm font-semibold text-zinc-900">{{ $this->monthStart->format('F Y') }}</p>
        <button
            type="button"
            wire:click="shiftMonth(1)"
            @disabled(! $this->navigation['next'])
            class="rounded-lg p-2 text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-30"
            aria-label="{{ __('Next month') }}"
        >
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
        </button>
    </div>

    <table class="mt-3 w-full table-fixed text-center text-sm" wire:loading.class="opacity-60">
        <thead>
            <tr>
                @foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $weekday)
                    <th scope="col" class="pb-2 text-xs font-medium text-zinc-500">{{ __($weekday) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($this->weeks as $week)
                <tr wire:key="week-{{ $week[0]['date'] }}">
                    @foreach ($week as $day)
                        <td class="p-0.5">
                            @if ($day['inMonth'])
                                <button
                                    type="button"
                                    wire:click="select('{{ $day['date'] }}')"
                                    @disabled(! $day['bookable'])
                                    @class([
                                        'aspect-square w-full rounded-lg text-sm transition',
                                        'font-medium text-zinc-900 hover:bg-brand-50 hover:text-brand-700' => $day['bookable'],
                                        'bg-red-50 text-red-400 line-through' => $day['unavailable'],
                                        'text-zinc-300' => ! $day['bookable'] && ! $day['unavailable'],
                                    ])
                                    aria-label="{{ \Carbon\CarbonImmutable::parse($day['date'])->format('F j') }}{{ $day['unavailable'] ? ', '.__('unavailable') : '' }}"
                                >
                                    {{ $day['day'] }}
                                </button>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-3 flex flex-wrap gap-4 text-xs text-zinc-500">
        <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-white ring-1 ring-zinc-300"></span>{{ __('Available') }}</span>
        <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-red-50 ring-1 ring-red-200"></span>{{ __('Unavailable') }}</span>
    </div>
</div>
