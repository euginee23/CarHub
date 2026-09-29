{{-- The booking's audit trail, oldest step first. --}}
@props(['booking'])

<flux:card {{ $attributes }}>
    <flux:heading>{{ __('History') }}</flux:heading>

    <ol class="mt-4 space-y-4">
        @foreach ($booking->statusChanges as $change)
            <li wire:key="status-change-{{ $change->id }}" class="flex gap-3">
                <span class="mt-1.5 size-2 shrink-0 rounded-full bg-zinc-400"></span>
                <div class="min-w-0 text-sm">
                    <p class="font-medium text-zinc-900 dark:text-white">{{ $change->to_status->label() }}</p>
                    <p class="text-xs text-zinc-500">
                        {{ $change->created_at->format('M j, Y g:i A') }}
                        &middot; {{ $change->actor?->name ?? __('CarHub') }}
                    </p>
                    @if ($change->note)
                        <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $change->note }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</flux:card>
