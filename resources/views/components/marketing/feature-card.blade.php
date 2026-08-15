@props([
    'title',
    'icon' => null,
    'badge' => null,
    'tone' => 'light',
])

<div @class([
    'flex flex-col rounded-2xl p-6',
    'glass-card' => $tone === 'glass',
    'border border-zinc-200 bg-white' => $tone === 'light',
    'border border-white/10 bg-white/5' => $tone === 'dark',
])>
    <div class="flex items-center justify-between gap-3">
        @if ($icon)
            <span @class([
                'flex size-11 items-center justify-center rounded-xl',
                'bg-linear-to-br from-brand-600 to-brand-500 text-white shadow-sm shadow-brand-600/25' => $tone !== 'dark',
                'bg-white/10 text-spark-300 ring-1 ring-white/15' => $tone === 'dark',
            ])>
                {{ $icon }}
            </span>
        @endif

        @if ($badge)
            <span @class([
                'rounded-full px-2.5 py-1 text-xs font-semibold',
                'bg-brand-50 text-brand-700' => $tone !== 'dark',
                'bg-white/10 text-spark-300' => $tone === 'dark',
            ])>
                {{ $badge }}
            </span>
        @endif
    </div>

    <h3 @class([
        'mt-5 text-lg font-semibold',
        'text-zinc-900' => $tone !== 'dark',
        'text-white' => $tone === 'dark',
    ])>
        {{ $title }}
    </h3>

    <div @class([
        'mt-2 text-sm/6',
        'text-zinc-600' => $tone !== 'dark',
        'text-zinc-300' => $tone === 'dark',
    ])>
        {{ $slot }}
    </div>
</div>
