<?php

namespace App\Models;

use Database\Factories\RentalContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * The agreement between rental company and renter, frozen from the booking at the
 * moment it was generated so later edits to the listing cannot change it.
 *
 * @phpstan-type ContractSnapshot array{
 *     generated_at: string,
 *     booking: array{reference: string, pickup_at: string, return_at: string, pickup_location: string},
 *     renter: array{name: string, email: string, phone: string|null, identity_verified: bool},
 *     company: array{name: string, address?: string|null, email: string, phone: string|null, approved_at: string|null},
 *     vehicle: array{name: string, year: int, type: string, transmission: string, fuel: string, seats: int},
 *     pricing: array{daily_rate: int, days: int, subtotal: int, service_fee: int, total: int},
 *     terms: array{version: string|null, accepted_at: string|null, body: string|null}
 * }
 *
 * @property int $id
 * @property int $booking_id
 * @property string|null $contract_number
 * @property ContractSnapshot $snapshot
 * @property Carbon|null $renter_signed_at
 * @property string|null $renter_signature
 * @property string|null $renter_signature_path
 * @property string|null $renter_signed_ip
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Booking $booking
 */
#[Fillable(['snapshot'])]
class RentalContract extends Model
{
    /** @use HasFactory<RentalContractFactory> */
    use HasFactory;

    /**
     * The private disk drawn signatures are kept on; they are only ever shown
     * inside the contract, to the people allowed to read it.
     */
    public const string SIGNATURE_DISK = 'local';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'renter_signed_at' => 'datetime',
        ];
    }

    /**
     * The booking the contract covers.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Whether the renter has signed the contract.
     */
    public function isSigned(): bool
    {
        return $this->renter_signed_at !== null;
    }

    /**
     * The renter's drawn signature as an inline image, so the contract and its
     * printable copy can show it without a public URL. Contracts signed before
     * drawn signatures have none.
     */
    public function signatureImageDataUri(): ?string
    {
        $disk = Storage::disk(self::SIGNATURE_DISK);

        if (blank($this->renter_signature_path) || ! $disk->exists($this->renter_signature_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) $disk->get($this->renter_signature_path));
    }
}
