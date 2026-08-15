<x-layouts::error
    code="401"
    :title="__('You need to be signed in')"
    :message="__('This page is only available to signed-in members. Log in to continue, or create an account if you do not have one yet.')"
>
    <x-slot:actions>
        <x-error-action :href="route('login')">{{ __('Log in') }}</x-error-action>
        <x-error-action :href="route('register')" variant="secondary">{{ __('Create an account') }}</x-error-action>
    </x-slot:actions>
</x-layouts::error>
