<x-layouts::auth
    :title="__('Confirm password')"
    :panel-heading="__('A quick security check.')"
    :panel-description="__('Sensitive settings ask for your password again, so a forgotten session cannot be used to change your account.')"
>
    <div class="flex flex-col gap-8">
        <x-auth-header
            :title="__('Confirm your password')"
            :description="__('This is a secure area of CarHub. Please confirm your password before continuing.')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                {{ __('Confirm') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
