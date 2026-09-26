<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\OwnerApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property ApplicationStatus $status
 * @property string|null $notes
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $reviewer
 * @property-read Collection<int, VerificationDocument> $documents
 */
#[Fillable(['notes'])]
class OwnerApplication extends Model
{
    /** @use HasFactory<OwnerApplicationFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The user applying to become a vehicle owner.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The administrator who reviewed the application.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The documents submitted with the application.
     *
     * @return MorphMany<VerificationDocument, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(VerificationDocument::class, 'documentable');
    }

    /**
     * Only applications that are still awaiting review.
     *
     * @param  Builder<OwnerApplication>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ApplicationStatus::Pending);
    }

    /**
     * Determine whether the application is still awaiting review.
     */
    public function isPending(): bool
    {
        return $this->status === ApplicationStatus::Pending;
    }
}
