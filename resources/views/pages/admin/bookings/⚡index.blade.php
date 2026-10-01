<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Bookings')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $search = '';

    /**
     * Every booking on the platform matching the filters, newest first.
     *
     * @return LengthAwarePaginator<int, Booking>
     */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        return Booking::query()
            ->with(['vehicle', 'renter', 'owner', 'successfulPayment'])
            ->when(BookingStatus::tryFrom($this->status), fn (Builder $query, BookingStatus $status) => $query->where('status', $status))
            ->when(filled($this->search), function (Builder $query) {
                $term = '%'.trim($this->search).'%';

                $query->where(fn (Builder $query) => $query
                    ->where('reference', 'like', $term)
                    ->orWhereHas('renter', fn (Builder $query) => $query->where('name', 'like', $term))
                    ->orWhereHas('owner', fn (Builder $query) => $query->where('name', 'like', $term))
                    ->orWhereHas('vehicle', fn (Builder $query) => $query->where('brand', 'like', $term)->orWhere('model', 'like', $term)));
            })
            ->latest()
            ->paginate(15);
    }

    /**
     * How many bookings are in each status, for the filter tabs.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return Booking::toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->map(fn ($count) => (int) $count)->all();
    }

    /**
     * Go back to the first page whenever the filters change.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'search'], true)) {
            $this->resetPage();
        }
    }
}; ?>

<div>
    <x-app.page-header :title="__('Bookings')" :description="__('Monitor every rental on CarHub, from request to return.')" />

    <x-app.content class="space-y-6">
        <div class="flex flex-wrap gap-2">
            <flux:button size="sm" :variant="$status === '' ? 'primary' : 'outline'" wire:click="$set('status', '')">
                {{ __('All') }} <span class="ms-1 opacity-70">{{ array_sum($this->counts) }}</span>
            </flux:button>
            @foreach (BookingStatus::cases() as $option)
                <flux:button wire:key="status-{{ $option->value }}" size="sm" :variant="$status === $option->value ? 'primary' : 'outline'" wire:click="$set('status', '{{ $option->value }}')">
                    {{ $option->label() }} <span class="ms-1 opacity-70">{{ $this->counts[$option->value] ?? 0 }}</span>
                </flux:button>
            @endforeach
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by reference, renter, owner, or vehicle')" clearable />

        @if ($this->bookings->isEmpty())
            <flux:card class="text-center">
                <flux:text>{{ __('No bookings match.') }}</flux:text>
            </flux:card>
        @else
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white px-4 sm:px-6">
                <flux:table :paginate="$this->bookings">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Booking') }}</flux:table.column>
                        <flux:table.column>{{ __('Renter / owner') }}</flux:table.column>
                        <flux:table.column>{{ __('Dates') }}</flux:table.column>
                        <flux:table.column>{{ __('Total') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->bookings as $booking)
                            <flux:table.row :key="$booking->id">
                                <flux:table.cell>
                                    <a href="{{ route('admin.bookings.show', $booking) }}" wire:navigate class="font-medium text-zinc-900 hover:text-brand-700">{{ $booking->vehicle->name }}</a>
                                    <div class="text-xs text-zinc-500">{{ $booking->reference }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="text-zinc-900">{{ $booking->renter->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $booking->owner->name }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-600">{{ $booking->pickup_at->format('M j') }} &ndash; {{ $booking->return_at->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900">&#8369;{{ number_format($booking->total) }}</div>
                                    <div class="text-xs text-zinc-500">{{ $booking->successfulPayment ? __('Paid') : __('Unpaid') }}</div>
                                </flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" :color="$booking->status->color()">{{ $booking->status->label() }}</flux:badge></flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </x-app.content>
</div>
