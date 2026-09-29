{{-- A settings form panel. Navigation between settings pages lives in the page
     header's Account tabs. --}}
<flux:card class="space-y-2">
    <flux:heading size="lg">{{ $heading ?? '' }}</flux:heading>
    <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

    <div class="w-full">
        {{ $slot }}
    </div>
</flux:card>
