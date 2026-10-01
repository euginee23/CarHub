<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property int $renter_id
 * @property int $owner_id
 * @property int $vehicle_id
 * @property Carbon $pickup_at
 * @property Carbon $return_at
 * @property string $pickup_location
 * @property int $daily_rate
 * @property int $days
 * @property int $subtotal
 * @property int $service_fee
 * @property int $total
 * @property BookingStatus $status
 * @property string|null $renter_notes
 * @property Carbon|null $approved_at
 * @property string|null $decline_reason
 * @property int|null $terms_version_id
 * @property Carbon|null $terms_accepted_at
 * @property string|null $terms_accepted_ip
 * @property Carbon|null $payment_due_at
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $renter
 * @property-read User $owner
 * @property-read Vehicle $vehicle
 * @property-read TermsVersion|null $termsVersion
 * @property-read RentalContract|null $contract
 * @property-read Collection<int, BookingStatusChange> $statusChanges
 * @property-read Collection<int, Payment> $payments
 * @property-read Payment|null $latestPayment
 * @property-read Payment|null $successfulPayment
 */
#[Fillable([
    'pickup_at', 'return_at', 'pickup_location', 'daily_rate', 'days', 'subtotal', 'service_fee', 'total', 'renter_notes',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $booking->reference ??= static::newReference();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pickup_at' => 'datetime',
            'return_at' => 'datetime',
            'daily_rate' => 'integer',
            'days' => 'integer',
            'subtotal' => 'integer',
            'service_fee' => 'integer',
            'total' => 'integer',
            'status' => BookingStatus::class,
            'approved_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'payment_due_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Use the public reference in URLs instead of the sequential ID.
     */
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * The renter who requested the booking.
     *
     * @return BelongsTo<User, $this>
     */
    public function renter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renter_id');
    }

    /**
     * The owner of the booked vehicle.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The booked vehicle.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The version of the rental terms the renter accepted.
     *
     * @return BelongsTo<TermsVersion, $this>
     */
    public function termsVersion(): BelongsTo
    {
        return $this->belongsTo(TermsVersion::class);
    }

    /**
     * The rental contract generated for the booking.
     *
     * @return HasOne<RentalContract, $this>
     */
    public function contract(): HasOne
    {
        return $this->hasOne(RentalContract::class);
    }

    /**
     * Every payment attempt for the booking.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The most recent payment attempt.
     *
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * The payment that confirmed the booking, if any.
     *
     * @return HasOne<Payment, $this>
     */
    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->ofMany(['paid_at' => 'max'], fn ($query) => $query->where('status', PaymentStatus::Paid));
    }

    /**
     * The total owed for the booking, in centavos as payment gateways expect.
     */
    public function totalInCentavos(): int
    {
        return $this->total * 100;
    }

    /**
     * Every status the booking has passed through, oldest first.
     *
     * @return HasMany<BookingStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(BookingStatusChange::class)->oldest('id');
    }

    /**
     * Only bookings that hold their vehicle for the booked window.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeHoldingVehicle(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::holding());
    }

    /**
     * Only bookings whose window overlaps the given one. Windows that merely
     * touch — one ends as the next begins — do not overlap.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $query->where('pickup_at', '<', $to)->where('return_at', '>', $from);
    }

    /**
     * Whether the renter has accepted the rental terms for this booking.
     */
    public function hasAcceptedTerms(): bool
    {
        return $this->terms_accepted_at !== null;
    }

    /**
     * Whether the renter can still work through checkout for this booking.
     */
    public function isInCheckout(): bool
    {
        return in_array($this->status, [BookingStatus::Requested, BookingStatus::Approved], true);
    }

    /**
     * Whether the renter may still cancel without a payment having been taken.
     */
    public function isCancellableByRenter(): bool
    {
        return in_array($this->status, [BookingStatus::Requested, BookingStatus::Approved, BookingStatus::AwaitingPayment], true);
    }

    /**
     * Generate an unused, human-friendly booking reference such as BK-7Q2M9XKD.
     */
    public static function newReference(): string
    {
        do {
            $reference = 'BK-'.Str::upper(Str::random(8));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
