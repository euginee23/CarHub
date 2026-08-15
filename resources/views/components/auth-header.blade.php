@props([
    'title',
    'description' => null,
])

<div class="flex w-full flex-col">
    <h1 class="text-3xl font-bold tracking-tight text-balance text-zinc-900">{{ $title }}</h1>

    @if ($description)
        <p class="mt-2 text-base/7 text-pretty text-zinc-600">{{ $description }}</p>
    @endif
</div>
