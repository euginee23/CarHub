<x-layouts::auth
    :title="__('Register')"
    :panel-heading="__('Start renting, or start earning.')"
    :panel-description="__('One account covers both sides of CarHub — book a vehicle for your next trip, or list the one already sitting in your garage.')"
>
    <div class="flex flex-col gap-8">
        <x-auth-header :title="__('Create your account')" :description="__('It takes a minute. You can verify your ID later, when you make your first booking.')" />

        <!-- Session Status -->
        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                {{ __('Create account') }}
            </flux:button>
        </form>

        <p class="text-sm text-zinc-600">
            {{ __('Already have an account?') }}
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </p>
    </div>
</x-layouts::auth>
