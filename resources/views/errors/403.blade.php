<x-layouts::error
    code="403"
    :title="__('That page is not yours to open')"
    :message="filled($exception ?? null) && filled($exception->getMessage())
        ? $exception->getMessage()
        : __('You are signed in, but this page belongs to someone else — another renter\'s trip, or a vehicle listing you do not own.')"
>
    <x-slot:actions>
        <x-error-action :href="route('home')">{{ __('Back to home') }}</x-error-action>
        <x-error-action :href="route('contact')" variant="secondary">{{ __('Contact support') }}</x-error-action>
    </x-slot:actions>
</x-layouts::error>
