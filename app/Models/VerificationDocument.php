<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Database\Factories\VerificationDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A privately stored identity or vehicle document submitted for review.
 *
 * @property int $id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property int $user_id
 * @property DocumentType $type
 * @property string $path
 * @property string|null $original_name
 * @property DocumentStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Model $documentable
 */
#[Fillable(['user_id', 'type', 'path', 'original_name'])]
class VerificationDocument extends Model
{
    /** @use HasFactory<VerificationDocumentFactory> */
    use HasFactory;

    /**
     * The storage disk documents are kept on. It is private, so files are only
     * reachable through the authorized document route.
     */
    public const string DISK = 'local';

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
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The user the document belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The record the document was submitted for.
     *
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
