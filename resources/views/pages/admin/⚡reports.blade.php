<?php

use App\Http\Controllers\Admin\ReportExportController;
use App\Services\Reports\RentalReports;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Reports')] class extends Component {
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /**
     * Default to the last 30 days.
     */
    public function mount(): void
    {
        if (! $this->validRange()) {
            $this->applyPreset('30');
        }
    }

    /**
     * The quick ranges offered above the reports.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function presets(): array
    {
        return [
            '7' => __('Last 7 days'),
            '30' => __('Last 30 days'),
            '90' => __('Last 90 days'),
            'month' => __('This month'),
            '365' => __('Last 12 months'),
        ];
    }

    /**
     * Jump to one of the quick ranges.
     */
    public function applyPreset(string $preset): void
    {
        $today = CarbonImmutable::today();

        [$from, $to] = match ($preset) {
            'month' => [$today->startOfMonth(), $today],
            '7', '90', '365' => [$today->subDays((int) $preset - 1), $today],
            default => [$today->subDays(29), $today],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
        unset($this->reports);
    }

    /**
     * Keep the range sensible when a date is typed in.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to'], true)) {
            if (! $this->validRange()) {
                $this->addError('range', __('Choose a start date on or before the end date.'));

                return;
            }

            unset($this->reports);
        }
    }

    /**
     * All the figures for the chosen range.
     */
    #[Computed]
    public function reports(): RentalReports
    {
        return new RentalReports(CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to));
    }

    /**
     * The CSV downloads for the chosen range.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function exports(): array
    {
        $labels = [
            'daily' => __('Daily summary'),
            'bookings' => __('Bookings'),
            'payments' => __('Payments collected'),
            'demand' => __('Demand by body type'),
        ];

        return collect(array_keys(ReportExportController::REPORTS))
            ->mapWithKeys(fn (string $report) => [$labels[$report] => route('admin.reports.export', ['report' => $report, 'from' => $this->from, 'to' => $this->to])])
            ->all();
    }

    /**
     * Whether both dates parse and are in order.
     */
    protected function validRange(): bool
    {
        try {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', $this->from);
            $to = CarbonImmutable::createFromFormat('!Y-m-d', $this->to);
        } catch (\Throwable) {
            return false;
        }

        return $from && $to && $from->lte($to);
    }
}; ?>

<div>
    <x-app.page-header :title="__('Reports')" :description="__('Bookings, payments, demand, and fleet use for any period — with CSV downloads for deeper analysis.')" />

    <x-app.content class="space-y-8">
        {{-- Filters scope everything below them. --}}
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
                @foreach ($this->presets as $key => $label)
                    <flux:button wire:key="preset-{{ $key }}" size="sm" variant="outline" wire:click="applyPreset('{{ $key }}')">{{ $label }}</flux:button>
                @endforeach
            </div>
            <div class="flex items-end gap-2">
                <flux:input type="date" wire:model.live="from" :label="__('From')" size="sm" />
                <flux:input type="date" wire:model.live="to" :label="__('To')" size="sm" />
            </div>
            <flux:dropdown class="ms-auto">
                <flux:button size="sm" icon="arrow-down-tray" icon:trailing="chevron-down">{{ __('Download CSV') }}</flux:button>
                <flux:menu>
                    @foreach ($this->exports as $label => $url)
                        <flux:menu.item :href="$url">{{ $label }}</flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        </div>
        <flux:error name="range" />

        @php
            $reports = $this->reports;
            $summary = $reports->summary();
        @endphp

        <div class="space-y-8" wire:loading.class="opacity-60" wire:target="from,to,applyPreset">
            <p class="text-sm text-zinc-500">
                {{ __(':from to :to · :days days', ['from' => $reports->from->format('M j, Y'), 'to' => $reports->to->format('M j, Y'), 'days' => $reports->days()]) }}
            </p>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-dashboard.stat :label="__('Collected')" :value="'₱'.\App\Support\ChartScale::compact($summary['collected'])" :hint="__('Payments that went through')" />
                <x-dashboard.stat :label="__('Platform revenue')" :value="'₱'.\App\Support\ChartScale::compact($summary['platformRevenue'])" :hint="__('CarHub service fees')" />
                <x-dashboard.stat :label="__('Owner earnings')" :value="'₱'.\App\Support\ChartScale::compact($summary['ownerEarnings'])" :hint="__('Rental subtotals paid')" />
                <x-dashboard.stat :label="__('Booking requests')" :value="number_format($summary['bookings'])" :hint="__('Average paid booking ₱:avg', ['avg' => number_format($summary['averageBooking'])])" />
                <x-dashboard.stat :label="__('Lost requests')" :value="$summary['cancellationRate'].'%'" :hint="__('Declined, cancelled, or expired')" />
                <x-dashboard.stat :label="__('Fleet utilization')" :value="$summary['utilization'].'%'" :hint="__('Vehicle-days rented of those available')" />
                <x-dashboard.stat :label="__('New accounts')" :value="number_format($summary['newUsers'])" />
                <x-dashboard.stat :label="__('Paid bookings')" :value="number_format(collect($reports->paymentsByMethod())->sum('count'))" />
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-dashboard.panel :title="__('Booking requests per day')">
                    <x-chart.columns :data="$reports->dailyBookings()" :value-label="__('Requests')" />
                </x-dashboard.panel>
                <x-dashboard.panel :title="__('Money collected per day')">
                    <x-chart.columns :data="$reports->dailyCollected()" prefix="₱" :value-label="__('Collected')" />
                </x-dashboard.panel>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-dashboard.panel :title="__('How requests ended')" :description="__('Requests made in the period, by where they are now')">
                    <x-chart.bars :rows="collect($reports->bookingsByStatus())->map(fn ($row) => ['label' => $row['status']->label(), 'value' => $row['count'], 'display' => number_format($row['count'])])->all()" />
                </x-dashboard.panel>
                <x-dashboard.panel :title="__('Collected by payment method')">
                    <x-chart.bars :rows="collect($reports->paymentsByMethod())->map(fn ($row) => ['label' => $row['method']->label(), 'value' => $row['amount'], 'display' => '₱'.number_format($row['amount']).' · '.trans_choice('{0} no payments|{1} :count payment|[2,*] :count payments', $row['count'], ['count' => $row['count']])])->all()" />
                </x-dashboard.panel>
            </div>

            <x-dashboard.panel :title="__('Demand and use by body type')" :description="__('Requests made and how much of each type\'s fleet was out on rentals')">
                <div class="-mx-5 -my-5 overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-sm">
                        <thead>
                            <tr class="border-b border-zinc-100 text-xs uppercase tracking-wide text-zinc-500">
                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('Body type') }}</th>
                                <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('Vehicles') }}</th>
                                <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('Requests') }}</th>
                                <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('Days rented') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('Utilization') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @forelse ($reports->demandByType() as $row)
                                <tr wire:key="type-{{ $row['type']->value }}">
                                    <td class="px-5 py-3 font-medium text-zinc-900">{{ $row['type']->label() }}</td>
                                    <td class="px-5 py-3 text-end text-zinc-600 tabular-nums">{{ $row['vehicles'] }}</td>
                                    <td class="px-5 py-3 text-end text-zinc-600 tabular-nums">{{ $row['requests'] }}</td>
                                    <td class="px-5 py-3 text-end text-zinc-600 tabular-nums">{{ number_format($row['rentedDays'], 1) }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2 w-28 overflow-hidden rounded-full bg-brand-100">
                                                <span class="block h-full rounded-full bg-brand-600" style="width: {{ $row['utilization'] }}%"></span>
                                            </span>
                                            <span class="text-zinc-900 tabular-nums">{{ $row['utilization'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-6 text-center text-zinc-500">{{ __('No vehicles or requests yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-dashboard.panel>

            <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <x-dashboard.panel :title="__('Top earning vehicles')">
                    @forelse ($reports->topVehicles() as $row)
                        <div wire:key="top-{{ $row['vehicle']->id }}" class="flex items-center justify-between gap-4 py-2 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-zinc-900">{{ $loop->iteration }}. {{ $row['vehicle']->year }} {{ $row['vehicle']->name }}</p>
                                <p class="text-xs text-zinc-500">{{ trans_choice('{1} :count paid booking|[2,*] :count paid bookings', $row['bookings'], ['count' => $row['bookings']]) }}</p>
                            </div>
                            <span class="font-semibold text-zinc-900 tabular-nums">&#8369;{{ number_format($row['earnings']) }}</span>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-zinc-500">{{ __('No paid bookings in this period.') }}</p>
                    @endforelse
                </x-dashboard.panel>

                <x-dashboard.panel :title="__('New accounts')">
                    <ul class="space-y-2 text-sm">
                        @foreach ($reports->newUsersByRole() as $role => $count)
                            <li class="flex justify-between"><span class="text-zinc-600">{{ $role }}</span><span class="font-semibold text-zinc-900 tabular-nums">{{ number_format($count) }}</span></li>
                        @endforeach
                    </ul>
                </x-dashboard.panel>
            </div>
        </div>
    </x-app.content>
</div>
