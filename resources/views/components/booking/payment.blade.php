{{-- The payment behind a booking: the successful one, or the latest attempt. --}}
@props(['booking'])

@php
    $payment = $booking->successfulPayment ?? $booking->latestPayment;
@endphp

@if ($payment)
    <flux:card {{ $attributes->class('space-y-3') }}>
        <div class="flex items-center justify-between gap-4">
            <flux:heading>{{ __('Payment') }}</flux:heading>
            <flux:badge size="sm" :color="$payment->status->color()">{{ $payment->status->label() }}</flux:badge>
        </div>
        <dl class="space-y-1.5 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-zinc-500">{{ __('Amount') }}</dt>
                <dd class="font-medium text-zinc-900">&#8369;{{ number_format($payment->amountInPesos(), 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-zinc-500">{{ __('Method') }}</dt>
                <dd class="text-zinc-900">{{ $payment->method->label() }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-zinc-500">{{ __('Reference') }}</dt>
                <dd class="text-zinc-900">{{ $payment->reference }}</dd>
            </div>
            @if ($payment->paid_at)
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Paid') }}</dt>
                    <dd class="text-zinc-900">{{ $payment->paid_at->format('M j, Y g:i A') }}</dd>
                </div>
            @endif
        </dl>
        @if ($payment->failure_reason)
            <flux:text class="text-xs text-red-600">{{ $payment->failure_reason }}</flux:text>
        @endif
    </flux:card>
@endif
