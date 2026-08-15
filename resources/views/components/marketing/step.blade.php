@props([
    'number',
    'title',
    'last' => false,
])

<div class="relative flex gap-5">
    <div class="flex flex-col items-center">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-600 to-brand-500 text-sm font-bold text-white shadow-sm shadow-brand-600/25">
            {{ $number }}
        </span>
        @unless ($last)
            <span aria-hidden="true" class="mt-2 w-px flex-1 bg-linear-to-b from-brand-200 to-transparent"></span>
        @endunless
    </div>

    <div @class(['min-w-0', 'pb-10' => ! $last])>
        <h3 class="text-lg font-semibold text-zinc-900">{{ $title }}</h3>
        <div class="mt-2 text-sm/6 text-zinc-600">{{ $slot }}</div>
    </div>
</div>
