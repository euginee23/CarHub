<?php

use App\Concerns\ContactValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ContactValidationRules, ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $birthdate = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = (string) Auth::user()->phone;
        $this->address = (string) Auth::user()->address;
        $this->birthdate = (string) Auth::user()->birthdate?->toDateString();
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'phone' => $this->phoneRules(required: false),
            'address' => $this->addressRules(),
            'birthdate' => $this->birthdateRules(),
        ], [
            'phone.regex' => __('Enter a Philippine mobile number, e.g. 09171234567.'),
            'birthdate.before_or_equal' => __('You must be at least 18 years old.'),
        ]);

        $user->fill(array_map(fn (string $value): ?string => $value === '' ? null : $value, $validated));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<div>
    <x-app.page-header :title="__('Account settings')" :description="__('Manage your profile, contact details, and sign-in security.')" />

    <x-app.content width="3xl">
        <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your personal and contact details')">
            <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

                <div>
                    <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                    @if ($this->hasUnverifiedEmail)
                        <div>
                            <flux:text class="mt-4">
                                {{ __('Your email address is unverified.') }}

                                <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                    {{ __('Click here to re-send the verification email.') }}
                                </flux:link>
                            </flux:text>

                            @if (session('status') === 'verification-link-sent')
                                <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                    {{ __('A new verification link has been sent to your email address.') }}
                                </flux:text>
                            @endif
                        </div>
                    @endif
                </div>

                <flux:input wire:model="phone" :label="__('Mobile number')" type="tel" autocomplete="tel" placeholder="09171234567" />

                <flux:input wire:model="address" :label="__('Address')" type="text" autocomplete="street-address" />

                <flux:input wire:model="birthdate" :label="__('Birthdate')" type="date" autocomplete="bday" />

                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-end">
                        <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                            {{ __('Save') }}
                        </flux:button>
                    </div>

                </div>
            </form>

            @if ($this->showDeleteUser)
                <livewire:pages::settings.delete-user-form />
            @endif
        </x-pages::settings.layout>
    </x-app.content>
</div>
