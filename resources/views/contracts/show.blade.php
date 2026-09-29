<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="robots" content="noindex" />
        <title>{{ __('Rental contract :number', ['number' => $contract->contract_number]) }} - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-zinc-100 text-zinc-900 print:bg-white">
        <main class="mx-auto max-w-3xl px-4 py-8 print:p-0">
            <div class="mb-6 flex items-center justify-between gap-4 print:hidden">
                <a href="{{ url()->previous() }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">&larr; {{ __('Back') }}</a>
                <button type="button" onclick="window.print()" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700">
                    {{ __('Print or save as PDF') }}
                </button>
            </div>

            <article class="rounded-2xl bg-white p-8 shadow-sm print:rounded-none print:p-0 print:shadow-none">
                <p class="mb-6 text-lg font-bold">{{ config('app.name') }}</p>
                @include('contracts.document', ['contract' => $contract])
            </article>
        </main>
    </body>
</html>
