<?php

use App\Models\GpsDevice;
use App\Support\TrackerRequestLog;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public GpsDevice $device;

    /**
     * Only administrators see raw tracker traffic.
     */
    public function mount(): void
    {
        Gate::authorize('access-admin');
    }

    /**
     * The tracker's recent requests, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function entries(): array
    {
        return TrackerRequestLog::for($this->device);
    }

    /**
     * Empty the log.
     */
    public function clear(): void
    {
        Gate::authorize('access-admin');

        TrackerRequestLog::clear($this->device);
        unset($this->entries);
    }
}; ?>

<div wire:poll.3s class="space-y-3">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm text-zinc-500">{{ __('Refreshes every 3 seconds. Kept for 2 hours.') }}</p>
        @if ($this->entries !== [])
            <flux:button size="xs" variant="ghost" wire:click="clear">{{ __('Clear') }}</flux:button>
        @endif
    </div>

    @if ($this->entries === [])
        <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500">
            {{ __('No requests from this tracker yet. Send a ping and it will appear here.') }}
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 text-xs uppercase tracking-wide text-zinc-500">
                        <th scope="col" class="py-2 pe-3 text-start font-medium">{{ __('Time') }}</th>
                        <th scope="col" class="py-2 pe-3 text-start font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="py-2 pe-3 text-start font-medium">{{ __('Stored') }}</th>
                        <th scope="col" class="py-2 pe-3 text-start font-medium">{{ __('Position') }}</th>
                        <th scope="col" class="py-2 text-start font-medium">{{ __('From') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($this->entries as $entry)
                        <tr wire:key="log-{{ $loop->index }}-{{ $entry['at'] }}">
                            <td class="py-2 pe-3 whitespace-nowrap text-zinc-600">{{ \Carbon\CarbonImmutable::parse($entry['at'])->format('g:i:s A') }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge size="sm" :color="$entry['status'] < 300 ? 'green' : 'red'">{{ $entry['status'] }}</flux:badge>
                                @if ($entry['error'])
                                    <span class="ms-1 text-xs text-red-600">{{ $entry['error'] }}</span>
                                @endif
                            </td>
                            <td class="py-2 pe-3 text-zinc-600">{{ $entry['stored'] }} / {{ $entry['received'] }}</td>
                            <td class="py-2 pe-3 whitespace-nowrap text-zinc-600">
                                @if ($entry['lat'] !== null)
                                    {{ number_format($entry['lat'], 5) }}, {{ number_format($entry['lng'], 5) }}
                                @elseif ($entry['status'] < 300)
                                    <span class="text-xs text-zinc-400">{{ __('not stored — no ongoing rental') }}</span>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="py-2 text-xs text-zinc-500">{{ $entry['ip'] }} &middot; {{ $entry['agent'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
