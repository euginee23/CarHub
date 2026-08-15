<x-layouts::error
    code="429"
    :title="__('Too many attempts')"
    :message="__('You have made a lot of requests in a short time, so we have paused things briefly. Wait a moment and try again.')"
>
    <x-slot:actions>
        <x-error-action :href="route('home')">{{ __('Back to home') }}</x-error-action>
        <x-error-action :href="route('contact')" variant="secondary">{{ __('Contact support') }}</x-error-action>
    </x-slot:actions>
</x-layouts::error>
