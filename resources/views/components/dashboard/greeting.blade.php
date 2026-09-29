{{-- The page header every dashboard opens with: a time-of-day greeting. --}}
@props(['subtitle' => null])

@php
    $hour = now()->hour;
    $greeting = match (true) {
        $hour < 12 => __('Good morning'),
        $hour < 18 => __('Good afternoon'),
        default => __('Good evening'),
    };
    $firstName = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
@endphp

<x-app.page-header :title="$greeting.', '.$firstName" :description="$subtitle">
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
</x-app.page-header>
