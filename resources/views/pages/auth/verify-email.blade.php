<x-layouts::auth
    :title="__('Email verification')"
    :panel-heading="__('One click and you are in.')"
    :panel-description="__('Verifying your email keeps your account recoverable and lets owners know your contact details are real.')"
>
    <div class="flex flex-col gap-8">
        <x-auth-header
            :title="__('Verify your email address')"
            :description="__('We just sent you a link. Click it to finish setting up your account.')"
        />

        @if (session('status') == 'verification-link-sent')
            <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm/6 font-medium text-emerald-800">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </p>
        @endif

        <div class="flex flex-col gap-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Resend verification email') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button variant="ghost" type="submit" class="w-full cursor-pointer text-sm" data-test="logout-button">
                    {{ __('Log out') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>
