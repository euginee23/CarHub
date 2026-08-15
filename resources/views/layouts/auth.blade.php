@props([
    'title' => null,
    'description' => null,
    'panelHeading' => null,
    'panelDescription' => null,
])

<x-layouts::auth.split
    :title="$title"
    :description="$description"
    :panel-heading="$panelHeading"
    :panel-description="$panelDescription"
>
    {{ $slot }}
</x-layouts::auth.split>
