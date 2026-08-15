{{-- Uses url() rather than route(): a 500 can be caused by the very thing that
     would make route resolution throw, and an error page must not error. --}}
<x-layouts::error
    code="500"
    :title="__('Something went wrong on our end')"
    :message="__('This one is on us, not you. The problem has been logged and nothing you were doing was charged or confirmed. Try again in a moment.')"
>
    <x-slot:actions>
        <x-error-action :href="url('/')">{{ __('Back to home') }}</x-error-action>
        <x-error-action :href="url('/contact')" variant="secondary">{{ __('Contact support') }}</x-error-action>
    </x-slot:actions>
</x-layouts::error>
