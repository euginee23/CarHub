{{-- Where a booking is in its lifecycle: request, approval, payment, trip, return. --}}
@props(['booking'])

@php
    use App\Enums\BookingStatus;

    $order = [BookingStatus::Requested, BookingStatus::Approved, BookingStatus::AwaitingPayment, BookingStatus::Confirmed, BookingStatus::Ongoing, BookingStatus::Completed];
    $position = array_search($booking->status, $order, true);
    $stopped = $position === false;

    $reached = $stopped
        ? $booking->statusChanges->pluck('to_status')->map(fn ($status) => array_search($status, $order, true))->filter(fn ($index) => $index !== false)->max() ?? 0
        : $position;

    $steps = [
        ['label' => __('Requested'), 'at' => 0],
        ['label' => __('Approved'), 'at' => 1],
        ['label' => __('Paid'), 'at' => 3],
        ['label' => __('On trip'), 'at' => 4],
        ['label' => __('Returned'), 'at' => 5],
    ];
@endphp

<div {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white p-5') }}>
    <ol class="grid grid-cols-5 gap-2">
        @foreach ($steps as $step)
            @php($done = $reached >= $step['at'])
            <li class="min-w-0">
                <span @class([
                    'block h-1.5 rounded-full',
                    'bg-brand-600' => $done && ! $stopped,
                    'bg-zinc-400' => $done && $stopped,
                    'bg-zinc-200' => ! $done,
                ])></span>
                <span @class([
                    'mt-2 block truncate text-xs font-medium',
                    'text-zinc-900' => $done,
                    'text-zinc-400' => ! $done,
                ])>{{ $step['label'] }}</span>
            </li>
        @endforeach
    </ol>

    @if ($stopped)
        <p class="mt-3 text-sm font-medium text-red-600">{{ $booking->status->label() }}</p>
    @endif
</div>
