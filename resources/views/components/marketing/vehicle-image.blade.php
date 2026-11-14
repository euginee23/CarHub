{{-- Renders the vehicle's cover photo when the rental company has uploaded one, otherwise
     falls back to a branded illustration so the grid never shows a broken image. The same
     illustration replaces a photo whose file fails to load. --}}
@props([
    'vehicle',
    'class' => 'aspect-[16/10]',
    'caption' => true,
])

@php
    $photo = $vehicle->relationLoaded('photos') ? $vehicle->photos->first() : $vehicle->coverPhoto;
@endphp

@if ($photo)
    <div x-data="{ failed: false }" class="contents">
        <img
            src="{{ $photo->url() }}"
            alt="{{ $vehicle->year }} {{ $vehicle->name }}"
            loading="lazy"
            x-show="! failed"
            x-init="if ($el.complete && ! $el.naturalWidth) failed = true"
            x-on:error="failed = true"
            class="{{ $class }} w-full object-cover"
        />
        <template x-if="failed">
            <x-marketing.vehicle-placeholder :vehicle="$vehicle" :class="$class" :caption="$caption" />
        </template>
    </div>
@else
    <x-marketing.vehicle-placeholder :vehicle="$vehicle" :class="$class" :caption="$caption" />
@endif
