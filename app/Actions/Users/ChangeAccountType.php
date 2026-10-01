<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Validation\ValidationException;

class ChangeAccountType
{
    /**
     * Switch an account between renter and owner, for people who chose the wrong
     * type at sign-up. Only allowed while the account has nothing that belongs
     * to its current type, so no bookings or listings are left stranded.
     *
     * @throws ValidationException
     */
    public function handle(User $user, UserRole $role, User $administrator): User
    {
        if ($user->isAdmin() || $role === UserRole::Admin) {
            throw ValidationException::withMessages(['role' => __('Administrator accounts cannot be changed here.')]);
        }

        if ($user->is($administrator)) {
            throw ValidationException::withMessages(['role' => __('You cannot change your own account type.')]);
        }

        if ($user->role === $role) {
            return $user;
        }

        if ($user->isRenter() && $user->bookings()->exists()) {
            throw ValidationException::withMessages(['role' => __(':name has bookings as a renter, so the account cannot become an owner account.', ['name' => $user->name])]);
        }

        if ($user->isOwner() && $user->vehicles()->exists()) {
            throw ValidationException::withMessages(['role' => __(':name has listed vehicles, so the account cannot become a renter account.', ['name' => $user->name])]);
        }

        // A renter account never carries owner verification; a new owner starts unverified.
        $previous = $user->role;

        $user->forceFill(['role' => $role, 'owner_verified_at' => null])->save();

        ActivityLogger::record('user.account_type_changed', __(':name changed from :from to :to account.', [
            'name' => $user->name,
            'from' => mb_strtolower($previous->label()),
            'to' => mb_strtolower($role->label()),
        ]), $user, actor: $administrator);

        return $user;
    }
}
