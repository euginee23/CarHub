<x-layouts::error
    code="419"
    :title="__('Your session expired')"
    :message="__('You were away long enough that we closed the session for security. Nothing was submitted — reload the page and try again.')"
>
    <x-slot:actions>
        <x-error-action :href="url()->previous()">{{ __('Try again') }}</x-error-action>
        <x-error-action :href="route('home')" variant="secondary">{{ __('Back to home') }}</x-error-action>
    </x-slot:actions>
</x-layouts::error>
