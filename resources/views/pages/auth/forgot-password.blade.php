<x-layouts::auth
    :title="__('Forgot password')"
    :panel-heading="__('Locked out? It happens.')"
    :panel-description="__('We will email you a secure link to set a new password. The link expires in 60 minutes.')"
>
    <div class="flex flex-col gap-8">
        <x-auth-header :title="__('Forgot your password?')" :description="__('Enter the email on your account and we will send you a reset link.')" />

        <!-- Session Status -->
        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('Email password reset link') }}
            </flux:button>
        </form>

        <p class="text-sm text-zinc-600">
            {{ __('Remembered it?') }}
            <flux:link :href="route('login')" wire:navigate>{{ __('Back to log in') }}</flux:link>
        </p>
    </div>
</x-layouts::auth>
