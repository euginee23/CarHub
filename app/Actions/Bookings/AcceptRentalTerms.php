<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\TermsVersion;
use Illuminate\Validation\ValidationException;

class AcceptRentalTerms
{
    /**
     * Record that the renter agreed to a specific version of the rental terms.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, TermsVersion $terms, ?string $ipAddress = null): Booking
    {
        if (! $booking->isInCheckout()) {
            throw ValidationException::withMessages(['terms' => __('This booking is no longer in checkout.')]);
        }

        if ($booking->contract?->isSigned()) {
            throw ValidationException::withMessages(['terms' => __('The contract for this booking has already been signed.')]);
        }

        $booking->forceFill([
            'terms_version_id' => $terms->id,
            'terms_accepted_at' => now(),
            'terms_accepted_ip' => $ipAddress,
        ])->save();

        return $booking;
    }
}
