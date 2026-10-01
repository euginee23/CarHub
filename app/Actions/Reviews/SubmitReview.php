<?php

namespace App\Actions\Reviews;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitReview
{
    /**
     * Record the renter's rating of a completed rental and refresh the vehicle's
     * average rating.
     *
     * @throws ValidationException
     */
    public function handle(Booking $booking, User $renter, int $rating, ?string $comment = null): Review
    {
        if ($booking->renter_id !== $renter->id || $booking->status !== BookingStatus::Completed) {
            throw ValidationException::withMessages(['rating' => __('Only the renter can review a completed rental.')]);
        }

        if ($booking->review()->exists()) {
            throw ValidationException::withMessages(['rating' => __('You have already reviewed this rental.')]);
        }

        return DB::transaction(function () use ($booking, $renter, $rating, $comment): Review {
            $review = new Review(['rating' => $rating, 'comment' => $comment]);
            $review->forceFill([
                'booking_id' => $booking->id,
                'vehicle_id' => $booking->vehicle_id,
                'reviewer_id' => $renter->id,
                'owner_id' => $booking->owner_id,
            ])->save();

            $this->refreshRating($booking->vehicle);

            ActivityLogger::record('review.submitted', __(':name rated the :vehicle :rating/5.', ['name' => $renter->name, 'vehicle' => $booking->vehicle->name, 'rating' => $rating]), $review, actor: $renter);

            return $review;
        });
    }

    /**
     * Recalculate the vehicle's average rating from its reviews.
     */
    protected function refreshRating(Vehicle $vehicle): void
    {
        $stats = $vehicle->reviews()->reorder()->toBase()->selectRaw('count(*) as total, avg(rating) as average')->first();

        $vehicle->forceFill([
            'reviews_count' => (int) $stats->total,
            'rating' => round((float) $stats->average, 1),
        ])->save();
    }
}
