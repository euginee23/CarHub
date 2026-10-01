@php
    // `?as=owner` (from the site's "List your vehicle" links) preselects an owner account.
    $accountType = old('account_type', request('as') === 'owner' ? 'owner' : 'renter');
@endphp

<x-layouts::auth
    :title="__('Register')"
    :panel-heading="__('Start renting, or start earning.')"
    :panel-description="__('Renters book vehicles from verified local owners. Owners list the car already sitting in their garage and earn from it.')"
>
    <div class="flex flex-col gap-8">
        <x-auth-header
            :title="$accountType === 'owner' ? __('Create your owner account') : __('Create your account')"
            :description="$accountType === 'owner'
                ? __('Sign up, then upload your documents so an administrator can verify you before your first listing.')
                : __('It takes a minute. You can verify your ID later, when you make your first booking.')"
        />

        <!-- Session Status -->
        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Account Type -->
            <fieldset>
                <legend class="text-sm font-medium text-zinc-800">{{ __('Account type') }}</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'renter' => ['label' => __('Renter'), 'description' => __('Find and book vehicles from verified owners.')],
                        'owner' => ['label' => __('Vehicle owner'), 'description' => __('Get verified, then list your vehicles for rent.')],
                    ] as $value => $option)
                        <label class="flex cursor-pointer gap-3 rounded-xl border border-zinc-300 bg-white p-4 has-checked:border-brand-600 has-checked:ring-1 has-checked:ring-brand-600">
                            <input
                                type="radio"
                                name="account_type"
                                value="{{ $value }}"
                                @checked($accountType === $value)
                                class="mt-0.5 accent-brand-600"
                            />
                            <span>
                                <span class="block text-sm font-semibold text-zinc-900">{{ $option['label'] }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-500">{{ $option['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-zinc-500">
                    {{ __('Renter and owner accounts are separate. To both rent and list, sign up for each with a different email.') }}
                </p>
            </fieldset>
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

            <!-- Mobile Number -->
            <flux:input
                name="phone"
                :label="__('Mobile number')"
                :value="old('phone')"
                type="tel"
                required
                autocomplete="tel"
                placeholder="09171234567"
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
