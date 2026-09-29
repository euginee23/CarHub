{{-- The body of a rental contract, rendered from its frozen snapshot. --}}
@php
    $snapshot = $contract->snapshot;
    $peso = fn (int $amount): string => '₱'.number_format($amount);
    $when = fn (?string $value): string => $value ? \Carbon\CarbonImmutable::parse($value)->format('M j, Y g:i A') : '—';
@endphp

<x-booking.legal-text>
    <h2>{{ __('Vehicle rental contract :number', ['number' => $contract->contract_number]) }}</h2>
    <p>{{ __('Booking :reference · prepared :date', ['reference' => $snapshot['booking']['reference'], 'date' => $when($snapshot['generated_at'])]) }}</p>

    <h3>{{ __('Parties') }}</h3>
    <ul>
        <li>
            <strong>{{ __('Owner:') }}</strong> {{ $snapshot['owner']['name'] }} ({{ $snapshot['owner']['email'] }})
            &middot; {{ __('accepted the booking :date', ['date' => $when($snapshot['owner']['approved_at'])]) }}
        </li>
        <li>
            <strong>{{ __('Renter:') }}</strong> {{ $snapshot['renter']['name'] }} ({{ $snapshot['renter']['email'] }})
            &middot; {{ $snapshot['renter']['identity_verified'] ? __('two government IDs verified by CarHub') : __('identity not yet verified') }}
        </li>
    </ul>

    <h3>{{ __('Vehicle') }}</h3>
    <p>
        {{ $snapshot['vehicle']['year'] }} {{ $snapshot['vehicle']['name'] }} &middot; {{ $snapshot['vehicle']['type'] }},
        {{ $snapshot['vehicle']['transmission'] }}, {{ $snapshot['vehicle']['fuel'] }}, {{ trans_choice('{1} :count seat|[2,*] :count seats', $snapshot['vehicle']['seats'], ['count' => $snapshot['vehicle']['seats']]) }}
    </p>

    <h3>{{ __('Rental period') }}</h3>
    <ul>
        <li><strong>{{ __('Pickup:') }}</strong> {{ $when($snapshot['booking']['pickup_at']) }}, {{ $snapshot['booking']['pickup_location'] }}</li>
        <li><strong>{{ __('Return:') }}</strong> {{ $when($snapshot['booking']['return_at']) }}</li>
    </ul>

    <h3>{{ __('Price') }}</h3>
    <ul>
        <li>{{ $peso($snapshot['pricing']['daily_rate']) }} &times; {{ trans_choice('{1} :count day|[2,*] :count days', $snapshot['pricing']['days'], ['count' => $snapshot['pricing']['days']]) }} = {{ $peso($snapshot['pricing']['subtotal']) }}</li>
        <li>{{ __('CarHub service fee: :amount', ['amount' => $peso($snapshot['pricing']['service_fee'])]) }}</li>
        <li><strong>{{ __('Total: :amount', ['amount' => $peso($snapshot['pricing']['total'])]) }}</strong></li>
    </ul>

    <h3>{{ __('Terms and conditions (version :version)', ['version' => $snapshot['terms']['version'] ?? '—']) }}</h3>
    <p>{{ __('Accepted by the renter :date.', ['date' => $when($snapshot['terms']['accepted_at'])]) }}</p>
    {!! \Illuminate\Support\Str::markdown((string) $snapshot['terms']['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}

    <h3>{{ __('Signatures') }}</h3>
    <ul>
        <li><strong>{{ __('Owner:') }}</strong> {{ __('accepted electronically by approving the booking, :date', ['date' => $when($snapshot['owner']['approved_at'])]) }}</li>
        <li>
            <strong>{{ __('Renter:') }}</strong>
            @if ($contract->isSigned())
                {{ __('signed electronically as ":name", :date', ['name' => $contract->renter_signature, 'date' => $contract->renter_signed_at->format('M j, Y g:i A')]) }}
            @else
                {{ __('not yet signed') }}
            @endif
        </li>
    </ul>
</x-booking.legal-text>
