<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the platform audit trail the admin Activity page shows. Call it from
 * actions after the change has happened, never from views.
 */
class ActivityLogger
{
    /**
     * Record that something happened.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $actor = null): ActivityLog
    {
        $log = new ActivityLog([
            'action' => $action,
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);

        $actor ??= Auth::user();

        if ($actor instanceof User) {
            $log->actor()->associate($actor);
        }

        if ($subject !== null) {
            $log->subject()->associate($subject);
        }

        $log->save();

        return $log;
    }
}
