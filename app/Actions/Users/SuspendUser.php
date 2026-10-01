<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Validation\ValidationException;

class SuspendUser
{
    /**
     * Suspend an account: the user is signed out on their next request and cannot
     * sign back in, and an owner's listings disappear from the marketplace.
     *
     * @throws ValidationException
     */
    public function handle(User $user, User $administrator, string $reason): User
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages(['suspension' => __('Administrator accounts cannot be suspended here.')]);
        }

        if ($user->isSuspended()) {
            return $user;
        }

        $user->forceFill(['suspended_at' => now(), 'suspension_reason' => $reason])->save();

        ActivityLogger::record('user.suspended', __(':name was suspended.', ['name' => $user->name]), $user, ['reason' => $reason], $administrator);

        return $user;
    }

    /**
     * Lift a suspension.
     */
    public function reinstate(User $user, User $administrator): User
    {
        if (! $user->isSuspended()) {
            return $user;
        }

        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();

        ActivityLogger::record('user.reinstated', __(':name was reinstated.', ['name' => $user->name]), $user, actor: $administrator);

        return $user;
    }
}
