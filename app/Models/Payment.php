<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One attempt to pay for a booking through the payment gateway.
 *
 * @property int $id
 * @property string $reference
 * @property int $booking_id
 * @property PaymentMethod $method
 * @property int $amount In centavos.
 * @property string $currency
 * @property PaymentStatus $status
 * @property string $provider
 * @property string|null $provider_checkout_id
 * @property string|null $provider_payment_intent_id
 * @property string|null $provider_payment_id
 * @property string|null $checkout_url
 * @property Carbon|null $paid_at
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Booking $booking
 */
#[Fillable(['method', 'amount', 'currency', 'provider'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'currency' => 'PHP',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->reference ??= 'PAY-'.Str::upper(Str::random(10));
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
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'payload' => 'array',
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
     * The booking being paid for.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * The amount in pesos, for display.
     */
    public function amountInPesos(): float
    {
        return $this->amount / 100;
    }

    /**
     * Whether the payment is still waiting on the renter.
     */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}
