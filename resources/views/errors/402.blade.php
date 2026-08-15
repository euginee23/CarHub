<x-layouts::error
    code="402"
    :title="__('Payment required')"
    :message="__('This action needs a completed payment before it can go ahead. Check that your payment method went through, then try again.')"
>
    <x-slot:actions>
        <x-error-action :href="route('home')">{{ __('Back to home') }}</x-error-action>
        <x-error-action :href="route('faq').'#payments'" variant="secondary">{{ __('Payment help') }}</x-error-action>
    </x-slot:actions>
</x-layouts::error>
