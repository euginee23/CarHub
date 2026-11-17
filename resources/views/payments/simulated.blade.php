{{-- The simulated gateway's hosted checkout. Local development and demos only. --}}
<x-layouts::marketing :title="__('Test checkout')" :noindex="true">
    <div class="bg-zinc-50 py-12 lg:py-20">
        <div class="mx-auto max-w-md px-4">
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">{{ __('Test payment — no money moves') }}</p>
                <p class="mt-1">{{ __('CarHub is using the simulated checkout (PAYMENT_DRIVER=simulated). Pick a method and choose how this payment ends.') }}</p>
            </div>

            <div class="mt-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-lg shadow-zinc-900/5">
                <p class="text-sm text-zinc-500">{{ __('Paying CarHub') }}</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900">&#8369;{{ number_format($payment->amountInPesos(), 2) }}</p>

                <dl class="mt-6 space-y-2 border-t border-zinc-100 pt-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Booking') }}</dt>
                        <dd class="font-medium text-zinc-900">{{ $payment->booking->reference }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Vehicle') }}</dt>
                        <dd class="font-medium text-zinc-900">{{ $payment->booking->vehicle->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Payment') }}</dt>
                        <dd class="font-medium text-zinc-900">{{ $payment->reference }}</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('payments.simulated.complete', $payment) }}" class="mt-6 grid gap-3">
                    @csrf

                    <fieldset class="mb-2 grid gap-2">
                        <legend class="mb-2 text-sm font-medium text-zinc-900">{{ __('Payment method') }}</legend>
                        @foreach ($methods as $method)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3 text-sm text-zinc-800 has-checked:border-brand-600 has-checked:bg-brand-50">
                                <input type="radio" name="method" value="{{ $method->value }}" @checked($loop->first) class="accent-brand-600" data-test="simulate-method-{{ $method->value }}">
                                {{ $method->label() }}
                            </label>
                        @endforeach
                    </fieldset>
                    <button type="submit" name="outcome" value="paid" class="rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700" data-test="simulate-paid">
                        {{ __('Pay ₱:amount', ['amount' => number_format($payment->amountInPesos(), 2)]) }}
                    </button>
                    <button type="submit" name="outcome" value="failed" class="rounded-xl border border-zinc-300 bg-white px-5 py-3 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50" data-test="simulate-failed">
                        {{ __('Simulate a failed payment') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts::marketing>
