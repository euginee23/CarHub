<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\RentalContract;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-import-type ContractSnapshot from RentalContract
 */
class GenerateRentalContract
{
    /**
     * Create the rental contract for an approved booking, or refresh an unsigned
     * one so it reflects the latest accepted terms. Signed contracts never change.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking): RentalContract
    {
        $contract = $booking->contract;

        if ($contract?->isSigned()) {
            return $contract;
        }

        if ($booking->status !== BookingStatus::Approved) {
            throw ValidationException::withMessages(['contract' => __('A contract is prepared once the owner approves the booking.')]);
        }

        if (! $booking->hasAcceptedTerms()) {
            throw ValidationException::withMessages(['contract' => __('Accept the rental terms before the contract is prepared.')]);
        }

        $contract ??= new RentalContract;
        $contract->booking()->associate($booking);
        $contract->snapshot = $this->snapshot($booking);
        $contract->save();

        if ($contract->contract_number === null) {
            $contract->forceFill([
                'contract_number' => sprintf('RC-%s-%05d', $contract->created_at?->format('Y') ?? now()->format('Y'), $contract->id),
            ])->save();
        }

        return $booking->setRelation('contract', $contract)->contract;
    }

    /**
     * Freeze every detail of the agreement as it stands right now.
     *
     * @return ContractSnapshot
     */
    public function snapshot(Booking $booking): array
    {
        $booking->loadMissing(['renter', 'owner', 'vehicle', 'termsVersion']);

        return [
            'generated_at' => now()->toIso8601String(),
            'booking' => [
                'reference' => $booking->reference,
                'pickup_at' => $booking->pickup_at->toIso8601String(),
                'return_at' => $booking->return_at->toIso8601String(),
                'pickup_location' => $booking->pickup_location,
            ],
            'renter' => [
                'name' => $booking->renter->name,
                'email' => $booking->renter->email,
                'phone' => $booking->renter->phone,
                'identity_verified' => $booking->renter->hasVerifiedIdentity(),
            ],
            'owner' => [
                'name' => $booking->owner->name,
                'email' => $booking->owner->email,
                'phone' => $booking->owner->phone,
                'approved_at' => $booking->approved_at?->toIso8601String(),
            ],
            'vehicle' => [
                'name' => $booking->vehicle->name,
                'year' => $booking->vehicle->year,
                'type' => $booking->vehicle->type->label(),
                'transmission' => $booking->vehicle->transmission->label(),
                'fuel' => $booking->vehicle->fuel->label(),
                'seats' => $booking->vehicle->seats,
            ],
            'pricing' => [
                'daily_rate' => $booking->daily_rate,
                'days' => $booking->days,
                'subtotal' => $booking->subtotal,
                'service_fee' => $booking->service_fee,
                'total' => $booking->total,
            ],
            'terms' => [
                'version' => $booking->termsVersion?->version,
                'accepted_at' => $booking->terms_accepted_at?->toIso8601String(),
                'body' => $booking->termsVersion?->body,
            ],
        ];
    }
}
