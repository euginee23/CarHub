<?php

namespace App\Actions\Fortify;

use App\Concerns\ContactValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ContactValidationRules, PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'phone' => $this->phoneRules(),
            'account_type' => ['required', Rule::in(['renter', 'owner'])],
            'password' => $this->passwordRules(),
        ], [
            'phone.regex' => __('Enter a Philippine mobile number, e.g. 09171234567.'),
        ])->validate();

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => $input['password'],
        ]);

        // Renters and owners are separate kinds of account, chosen here once.
        $user->forceFill([
            'role' => $input['account_type'] === 'owner' ? UserRole::Owner : UserRole::Renter,
        ])->save();

        return $user;
    }
}
