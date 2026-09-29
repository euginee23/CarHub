{{-- The vehicle, schedule, and price breakdown of a booking. --}}
@props(['booking'])

<flux:card {{ $attributes->class('space-y-5') }}>
    <div class="flex items-start gap-4">
        <x-marketing.vehicle-image :vehicle="$booking->vehicle" :caption="false" class="aspect-[4/3] !w-24 shrink-0 rounded-lg" />
        <div class="min-w-0 flex-1">
            <flux:heading size="lg">{{ $booking->vehicle->year }} {{ $booking->vehicle->name }}</flux:heading>
            <flux:text class="mt-1">{{ $booking->pickup_location }}</flux:text>
            <div class="mt-2">
                <flux:badge size="sm" :color="$booking->status->color()">{{ $booking->status->label() }}</flux:badge>
            </div>
        </div>
    </div>

    <dl class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Pickup') }}</dt>
            <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $booking->pickup_at->format('D, M j, Y') }}</dd>
            <dd class="text-zinc-500">{{ $booking->pickup_at->format('g:i A') }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Return') }}</dt>
            <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $booking->return_at->format('D, M j, Y') }}</dd>
            <dd class="text-zinc-500">{{ $booking->return_at->format('g:i A') }}</dd>
        </div>
    </dl>

    <div class="space-y-2 border-t border-zinc-100 pt-4 text-sm dark:border-zinc-700">
        <div class="flex justify-between text-zinc-600 dark:text-zinc-300">
            <span>&#8369;{{ number_format($booking->daily_rate) }} &times; {{ trans_choice('{1} :count day|[2,*] :count days', $booking->days, ['count' => $booking->days]) }}</span>
            <span>&#8369;{{ number_format($booking->subtotal) }}</span>
        </div>
        <div class="flex justify-between text-zinc-600 dark:text-zinc-300">
            <span>{{ __('Service fee') }}</span>
            <span>&#8369;{{ number_format($booking->service_fee) }}</span>
        </div>
        <div class="flex justify-between border-t border-zinc-100 pt-2 font-semibold text-zinc-900 dark:border-zinc-700 dark:text-white">
            <span>{{ __('Total') }}</span>
            <span>&#8369;{{ number_format($booking->total) }}</span>
        </div>
    </div>

    <flux:text class="text-xs">{{ __('Reference :reference', ['reference' => $booking->reference]) }}</flux:text>
</flux:card>
