<?php

use App\Models\Vehicle;
use App\Services\Availability\AvailabilityChecker;
use App\Services\Pricing\RentalQuote;
use Carbon\CarbonImmutable;
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
     * Pickup and return times offered, on the half hour through the day.
     *
     * @return array<int, string>
     */
    #[Computed(persist: true)]
    public function timeOptions(): array
    {
        return collect(range(6 * 2, 21 * 2))
            ->map(fn (int $halfHour) => sprintf('%02d:%02d', intdiv($halfHour, 2), ($halfHour % 2) * 30))
            ->all();
    }

    /**
     * Fill in the dates from a day clicked on the availability calendar: the
     * first click sets pickup, the next later day sets the return.
     */
    #[On('calendar-date-selected')]
    public function selectDate(string $date): void
    {
        if (filled($this->pickupDate) && blank($this->returnDate) && $date > $this->pickupDate) {
            $this->returnDate = $date;

            return;
        }

        $this->pickupDate = $date;
        $this->returnDate = '';
    }
}; ?>

<div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-lg shadow-zinc-900/5">
    <p class="flex items-baseline gap-1.5">
        <span class="text-3xl font-bold tracking-tight text-zinc-900">&#8369;{{ number_format($vehicle->price_per_day) }}</span>
        <span class="text-zinc-500">/ {{ __('day') }}</span>
    </p>

    <div class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-zinc-300 bg-zinc-300">
        <div class="bg-white p-3">
            <label for="pickup-date" class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Pickup') }}</label>
            <input
                id="pickup-date"
                type="date"
                wire:model.live="pickupDate"
                min="{{ now()->toDateString() }}"
                max="{{ now()->addDays(config('carhub.booking.max_advance_days'))->toDateString() }}"
                class="mt-1 w-full border-0 bg-transparent p-0 text-sm text-zinc-900 outline-hidden"
            />
            <select wire:model.live="pickupTime" aria-label="{{ __('Pickup time') }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm text-zinc-600 outline-hidden">
                @foreach ($this->timeOptions as $time)
                    <option value="{{ $time }}">{{ \Carbon\CarbonImmutable::createFromFormat('H:i', $time)->format('g:i A') }}</option>
                @endforeach
            </select>
        </div>
        <div class="bg-white p-3">
            <label for="return-date" class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Return') }}</label>
            <input
                id="return-date"
                type="date"
                wire:model.live="returnDate"
                min="{{ $pickupDate ?: now()->toDateString() }}"
                class="mt-1 w-full border-0 bg-transparent p-0 text-sm text-zinc-900 outline-hidden"
            />
            <select wire:model.live="returnTime" aria-label="{{ __('Return time') }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm text-zinc-600 outline-hidden">
                @foreach ($this->timeOptions as $time)
                    <option value="{{ $time }}">{{ \Carbon\CarbonImmutable::createFromFormat('H:i', $time)->format('g:i A') }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Scheduling validation --}}
    <div wire:loading.remove wire:target="pickupDate,pickupTime,returnDate,returnTime">
        @if ($this->problems !== [])
            <ul class="mt-3 space-y-1.5" role="alert">
                @foreach ($this->problems as $problem)
                    <li class="flex items-start gap-2 text-sm text-red-600">
                        <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path stroke-linecap="round" d="M12 8v4.5m0 3h.01" />
                        </svg>
                        {{ $problem }}
                    </li>
                @endforeach
            </ul>
        @elseif ($this->isAvailable)
            <p class="mt-3 flex items-center gap-2 text-sm font-medium text-emerald-700">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                {{ __('Available for your dates') }}
            </p>
        @endif
    </div>
    <p wire:loading wire:target="pickupDate,pickupTime,returnDate,returnTime" class="mt-3 text-sm text-zinc-500">{{ __('Checking availability…') }}</p>

    @if ($this->quote)
        <div class="mt-6 space-y-3 border-t border-zinc-100 pt-5 text-sm">
            <div class="flex items-center justify-between text-zinc-600">
                <span>&#8369;{{ number_format($this->quote->dailyRate) }} &times; {{ trans_choice('{1} :count day|[2,*] :count days', $this->quote->days, ['count' => $this->quote->days]) }}</span>
                <span class="font-medium text-zinc-900">&#8369;{{ number_format($this->quote->subtotal) }}</span>
            </div>
            <div class="flex items-center justify-between text-zinc-600">
                <span>{{ __('Service fee (:percent%)', ['percent' => RentalQuote::serviceFeePercent()]) }}</span>
                <span class="font-medium text-zinc-900">&#8369;{{ number_format($this->quote->serviceFee) }}</span>
            </div>
            <div class="flex items-center justify-between border-t border-zinc-100 pt-3 text-base font-semibold text-zinc-900">
                <span>{{ __('Total') }}</span>
                <span>&#8369;{{ number_format($this->quote->total) }}</span>
            </div>
        </div>
    @endif

    @guest
        <a
            href="{{ route('register') }}"
            class="mt-6 block rounded-xl bg-brand-600 px-5 py-3.5 text-center text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
        >
            {{ $vehicle->instant_book ? __('Book instantly') : __('Request to book') }}
        </a>
    @else
        <button
            type="button"
            disabled
            class="mt-6 block w-full rounded-xl bg-brand-600 px-5 py-3.5 text-center text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition disabled:cursor-not-allowed disabled:opacity-60"
        >
            {{ $vehicle->instant_book ? __('Book instantly') : __('Request to book') }}
        </button>
    @endguest

    <p class="mt-3 text-center text-xs text-zinc-500">
        {{ __('You will not be charged until the owner confirms.') }}
    </p>

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
