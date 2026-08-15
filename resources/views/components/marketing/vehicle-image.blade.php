{{-- Renders the vehicle photo when one has been dropped into public/images/vehicles/,
     otherwise falls back to a branded illustration so the grid never shows a broken image. --}}
@props([
    'vehicle',
    'class' => 'aspect-[16/10]',
])

@php
    $image = $vehicle['image'] ?? null;
    $hasImage = filled($image) && file_exists(public_path(ltrim($image, '/')));
@endphp

@if ($hasImage)
    <img
        src="{{ asset($image) }}"
        alt="{{ $vehicle['year'] }} {{ $vehicle['name'] }}"
        loading="lazy"
        class="{{ $class }} w-full object-cover"
    />
@else
    <div class="{{ $class }} relative w-full overflow-hidden bg-linear-to-br from-brand-700 via-brand-600 to-brand-500">
        <div class="absolute -right-8 -top-10 size-40 rounded-full bg-spark-400/25 blur-2xl"></div>
        <div class="absolute -bottom-12 -left-6 size-40 rounded-full bg-white/10 blur-2xl"></div>

        {{-- Sized as a fraction of the panel so the mark reads at both card and hero scale. --}}
        <div class="absolute inset-0 flex flex-col items-center justify-center gap-[6%] px-4">
            <x-app-logo-icon class="w-[38%] min-w-16 max-w-48 text-white/85" />
            <p class="text-center text-xs font-semibold uppercase tracking-wider text-white/70">
                {{ $vehicle['year'] }} {{ $vehicle['name'] }}
            </p>
        </div>
    </div>
@endif
