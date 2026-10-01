<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One entry in the platform's audit trail: who did what, to which record.
 * Written through App\Support\ActivityLogger and kept for a year.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action e.g. "booking.confirmed", "user.suspended"
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $description
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User|null $actor
 * @property-read Model|null $subject
 */
#[Fillable(['action', 'description', 'properties', 'ip_address'])]
class ActivityLog extends Model
{
    use MassPrunable;

    /**
     * How long entries are kept, in days.
     */
    public const int RETENTION_DAYS = 365;

    /**
     * The name of the "updated at" column; entries are never edited.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The user who did it, if it was not automatic.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * The record it was done to.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The area of the platform the action belongs to, e.g. "booking".
     */
    public function area(): string
    {
        return strstr($this->action, '.', before_needle: true) ?: $this->action;
    }

    /**
     * Entries past the retention period.
     *
     * @return Builder<ActivityLog>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
