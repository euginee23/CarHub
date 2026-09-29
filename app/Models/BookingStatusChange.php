<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step in a booking's audit trail.
 *
 * @property int $id
 * @property int $booking_id
 * @property BookingStatus|null $from_status
 * @property BookingStatus $to_status
 * @property int|null $changed_by
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read Booking $booking
 * @property-read User|null $actor
 */
#[Fillable(['from_status', 'to_status', 'changed_by', 'note'])]
class BookingStatusChange extends Model
{
    /**
     * The name of the "updated at" column; status changes are never edited.
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
            'from_status' => BookingStatus::class,
            'to_status' => BookingStatus::class,
        ];
    }

    /**
     * The booking that changed.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * The user who made the change, if it was not automatic.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
