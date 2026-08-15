{{-- Rendered while the application is in maintenance mode, so it sticks to url()
     and avoids anything that assumes a fully booted application. --}}
<x-layouts::error
    code="503"
    :title="__('Down for maintenance')"
    :message="__('CarHub is briefly offline while we ship an update. Existing bookings are unaffected — we will be back shortly.')"
>
    <x-slot:actions>
        <x-error-action :href="url('/')">{{ __('Try again') }}</x-error-action>
    </x-slot:actions>
</x-layouts::error>
