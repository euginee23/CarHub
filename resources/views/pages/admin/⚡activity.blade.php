<?php

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Activity')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $area = '';

    #[Url(except: '')]
    public string $search = '';

    /**
     * The areas of the platform the log can be filtered by.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function areas(): array
    {
        return [
            'booking' => __('Bookings'),
            'payment' => __('Payments'),
            'auth' => __('Sign-ins'),
            'user' => __('Accounts'),
            'vehicle' => __('Listings'),
            'owner_application' => __('Owner applications'),
            'identity_document' => __('ID reviews'),
            'tracker' => __('GPS trackers'),
            'review' => __('Reviews'),
        ];
    }

    /**
     * Log entries matching the filters, newest first.
     *
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->with(['actor', 'subject'])
            ->when(array_key_exists($this->area, $this->areas), fn (Builder $query) => $query->where('action', 'like', $this->area.'.%'))
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('description', 'like', '%'.trim($this->search).'%')
                ->orWhereHas('actor', fn (Builder $query) => $query->where('name', 'like', '%'.trim($this->search).'%'))))
            ->latest('id')
            ->paginate(30);
    }

    /**
     * Where an entry's subject can be opened, if anywhere.
     */
    public function subjectUrl(ActivityLog $entry): ?string
    {
        return match (true) {
            $entry->subject instanceof Booking => route('admin.bookings.show', $entry->subject),
            $entry->subject instanceof Payment => route('admin.payments.index', ['search' => $entry->subject->reference]),
            $entry->subject instanceof Vehicle => route('admin.vehicles.index', ['search' => $entry->subject->model]),
            $entry->subject instanceof User => route('admin.users', ['search' => $entry->subject->email]),
            default => null,
        };
    }

    /**
     * Go back to the first page whenever the filters change.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['area', 'search'], true)) {
            $this->resetPage();
        }
    }
}; ?>

<div>
    <x-app.page-header :title="__('Activity')" :description="__('The platform\'s audit trail: bookings, payments, reviews, moderation, and sign-ins. Kept for a year.')" />

    <x-app.content class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-[1fr_14rem]">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search descriptions or people')" clearable />
            <flux:select wire:model.live="area">
                <flux:select.option value="">{{ __('Everything') }}</flux:select.option>
                @foreach ($this->areas as $key => $label)
                    <flux:select.option :value="$key">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->entries->isEmpty())
            <flux:card class="text-center">
                <flux:text>{{ __('Nothing recorded yet.') }}</flux:text>
            </flux:card>
        @else
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white">
                <ol class="divide-y divide-zinc-100">
                    @foreach ($this->entries as $entry)
                        @php($url = $this->subjectUrl($entry))
                        <li wire:key="activity-{{ $entry->id }}" class="flex flex-wrap items-start gap-x-4 gap-y-1 px-5 py-3 text-sm">
                            <span class="w-36 shrink-0 text-xs text-zinc-500 tabular-nums">{{ $entry->created_at->format('M j, g:i:s A') }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-zinc-900">
                                    @if ($url)
                                        <a href="{{ $url }}" wire:navigate class="hover:text-brand-700">{{ $entry->description }}</a>
                                    @else
                                        {{ $entry->description }}
                                    @endif
                                </p>
                                @foreach ((array) $entry->properties as $key => $value)
                                    <p class="text-xs text-zinc-500">{{ ucfirst($key) }}: {{ is_scalar($value) ? $value : json_encode($value) }}</p>
                                @endforeach
                            </div>
                            <div class="text-end text-xs text-zinc-500">
                                <p>{{ $entry->actor?->name ?? __('CarHub') }}</p>
                                <p class="font-mono">{{ $entry->action }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{ $this->entries->links() }}
        @endif
    </x-app.content>
</div>
