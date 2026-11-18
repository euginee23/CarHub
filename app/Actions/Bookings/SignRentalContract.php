<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\RentalContract;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignRentalContract
{
    public function __construct(
        protected GenerateRentalContract $generateContract,
        protected TransitionBooking $transitions,
    ) {}

    /**
     * Sign the contract on the renter's behalf and move the booking on to payment.
     * Every earlier checkout step must be complete: rental company approval, accepted
     * terms, and two approved government IDs. The renter types their name (checked
     * against the account) and draws a signature, kept as a PNG on a private disk.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, User $renter, string $signature, UploadedFile $drawing, ?string $ipAddress = null): Booking
    {
        if ($booking->status !== BookingStatus::Approved) {
            throw ValidationException::withMessages(['signature' => __('Only approved bookings can be signed.')]);
        }

        // Signing after the pickup time is fine (the renter picks up late), but not once the rental period is over.
        if ($booking->return_at->isPast()) {
            throw ValidationException::withMessages(['signature' => __('The dates for this booking are over, so it can no longer be signed. Pick new dates to book again.')]);
        }

        if (! $booking->hasAcceptedTerms()) {
            throw ValidationException::withMessages(['signature' => __('Accept the rental terms first.')]);
        }

        if (! $renter->hasVerifiedIdentity()) {
            throw ValidationException::withMessages(['signature' => __('Two approved government IDs are required before you can sign.')]);
        }

        if (Str::lower(Str::squish($signature)) !== Str::lower(Str::squish($renter->name))) {
            throw ValidationException::withMessages(['signature' => __('Type your full name exactly as it appears on your account: :name', ['name' => $renter->name])]);
        }

        // Regenerate so the signed copy reflects the renter's verified status right now.
        $contract = $this->generateContract->handle($booking);

        $contract->forceFill([
            'renter_signed_at' => now(),
            'renter_signature' => Str::squish($signature),
            'renter_signature_path' => $drawing->store('contract-signatures', RentalContract::SIGNATURE_DISK),
            'renter_signed_ip' => $ipAddress,
        ])->save();

        // The vehicle is held while the renter pays, but not indefinitely, and never past the end of the rental.
        $paymentDueAt = now()->addHours((int) config('carhub.payments.window_hours'))->min($booking->return_at);

        return $this->transitions->handle(
            $booking,
            BookingStatus::AwaitingPayment,
            $renter,
            __('Rental contract :number signed.', ['number' => $contract->contract_number]),
            ['payment_due_at' => $paymentDueAt],
        );
    }
}
