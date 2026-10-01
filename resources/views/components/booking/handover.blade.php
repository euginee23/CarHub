{{-- The condition record captured at pickup and at return. --}}
@props(['booking'])

@if ($booking->picked_up_at)
    <flux:card {{ $attributes->class('space-y-4') }}>
        <flux:heading>{{ __('Handover record') }}</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-zinc-50 p-4 text-sm">
                <p class="font-semibold text-zinc-900">{{ __('Pickup') }}</p>
                <p class="mt-1 text-zinc-600">{{ $booking->picked_up_at->format('M j, Y g:i A') }}</p>
                <p class="mt-2 text-zinc-600">{{ number_format((int) $booking->pickup_odometer) }} km &middot; {{ $booking->pickup_fuel?->label() }}</p>
                @if ($booking->pickup_notes)
                    <p class="mt-2 text-zinc-600">{{ $booking->pickup_notes }}</p>
                @endif
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 text-sm">
                <p class="font-semibold text-zinc-900">{{ __('Return') }}</p>
                @if ($booking->returned_at)
                    <p class="mt-1 text-zinc-600">
                        {{ $booking->returned_at->format('M j, Y g:i A') }}
                        @if ($booking->wasReturnedLate())
                            <flux:badge size="sm" color="red" class="ms-1">{{ __('Late') }}</flux:badge>
                        @endif
                    </p>
                    <p class="mt-2 text-zinc-600">{{ number_format((int) $booking->return_odometer) }} km &middot; {{ $booking->return_fuel?->label() }}</p>
                    @if ($booking->distanceDriven() !== null)
                        <p class="mt-1 text-zinc-600">{{ __(':km km driven', ['km' => number_format($booking->distanceDriven())]) }}</p>
                    @endif
                    @if ($booking->return_notes)
                        <p class="mt-2 text-zinc-600">{{ $booking->return_notes }}</p>
                    @endif
                @else
                    <p class="mt-1 text-zinc-500">{{ __('Due :date', ['date' => $booking->return_at->format('M j, Y g:i A')]) }}</p>
                @endif
            </div>
        </div>
    </flux:card>
@endif
