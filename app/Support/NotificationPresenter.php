<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Enums\BookingStatus;
use App\Enums\DocumentStatus;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingRequested;
use App\Notifications\BookingStatusUpdated;
use App\Notifications\CompanyApplicationReviewed;
use App\Notifications\IdentityDocumentReviewed;
use App\Notifications\VehicleTakenDown;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Turns a stored notification into what the bell shows: an icon and colour, a short
 * status chip, a plain "what to do" button, and where tapping it takes the user now.
 * The destination follows the booking as it stands today, so an approval opens
 * checkout while there is still something to do there, and an answered request
 * stops asking for an answer.
 */
class NotificationPresenter
{
    /**
     * Present several notifications, loading the bookings they mention in one query.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return array<string, array{icon: string, tone: string, chip: string|null, action: string, url: string}>
     */
    public static function presentAll(Collection $notifications, User $user): array
    {
        $references = $notifications->map(fn (DatabaseNotification $notification) => $notification->data['booking'] ?? null)->filter()->unique()->values();

        $bookings = $references->isEmpty()
            ? collect()
            : Booking::whereIn('reference', $references)
                ->where(fn ($query) => $query->where('renter_id', $user->id)->orWhere('company_id', $user->id))
                ->get()
                ->keyBy('reference');

        return $notifications->mapWithKeys(fn (DatabaseNotification $notification) => [
            $notification->id => self::present($notification, $user, $bookings->get($notification->data['booking'] ?? '')),
        ])->all();
    }

    /**
     * Present one notification.
     *
     * @return array{icon: string, tone: string, chip: string|null, action: string, url: string}
     */
    public static function present(DatabaseNotification $notification, User $user, ?Booking $booking = null): array
    {
        $data = $notification->data;
        $fallback = ['icon' => 'bell', 'tone' => 'brand', 'chip' => null, 'action' => __('Open'), 'url' => $data['url'] ?? route('dashboard')];

        return match ($notification->type) {
            BookingRequested::class => self::bookingRequest($booking, $fallback),
            BookingStatusUpdated::class => self::bookingUpdate(BookingStatus::tryFrom($data['status'] ?? ''), $user, $booking, $fallback),
            IdentityDocumentReviewed::class => self::identityReview(($data['status'] ?? '') === DocumentStatus::Approved->value, $user, $fallback),
            CompanyApplicationReviewed::class => self::companyReview(($data['status'] ?? '') === ApplicationStatus::Approved->value, $user, $fallback),
            VehicleTakenDown::class => [...$fallback, 'icon' => 'exclamation-triangle', 'tone' => 'red', 'chip' => __('Action needed'), 'action' => __('Fix the listing')],
            default => $fallback,
        };
    }

    /**
     * A new request for the rental company: answer it, or look back at it once answered.
     *
     * @param  array{icon: string, tone: string, chip: string|null, action: string, url: string}  $fallback
     * @return array{icon: string, tone: string, chip: string|null, action: string, url: string}
     */
    protected static function bookingRequest(?Booking $booking, array $fallback): array
    {
        if ($booking === null) {
            return [...$fallback, 'icon' => 'inbox-arrow-down', 'action' => __('See booking')];
        }

        $url = route('company.bookings.show', $booking);

        return $booking->status === BookingStatus::Requested
            ? ['icon' => 'inbox-arrow-down', 'tone' => 'amber', 'chip' => __('Needs your answer'), 'action' => __('Review and answer'), 'url' => $url]
            : ['icon' => 'inbox-arrow-down', 'tone' => 'zinc', 'chip' => $booking->status->label(), 'action' => __('See booking'), 'url' => $url];
    }

    /**
     * A booking moved on: the rental company goes to its booking page, the renter to
     * checkout while there is something to finish there, otherwise to the trip.
     *
     * @param  array{icon: string, tone: string, chip: string|null, action: string, url: string}  $fallback
     * @return array{icon: string, tone: string, chip: string|null, action: string, url: string}
     */
    protected static function bookingUpdate(?BookingStatus $status, User $user, ?Booking $booking, array $fallback): array
    {
        if ($booking !== null && $booking->company_id === $user->id) {
            $url = route('company.bookings.show', $booking);

            return match ($status) {
                BookingStatus::Confirmed => ['icon' => 'banknotes', 'tone' => 'emerald', 'chip' => __('Paid'), 'action' => __('See handover details'), 'url' => $url],
                BookingStatus::Ongoing => ['icon' => 'key', 'tone' => 'brand', 'chip' => null, 'action' => __('See the rental'), 'url' => $url],
                BookingStatus::Completed => ['icon' => 'check-circle', 'tone' => 'emerald', 'chip' => null, 'action' => __('See booking'), 'url' => $url],
                BookingStatus::Cancelled, BookingStatus::Expired => ['icon' => 'x-circle', 'tone' => 'zinc', 'chip' => null, 'action' => __('See booking'), 'url' => $url],
                default => [...$fallback, 'action' => __('See booking'), 'url' => $url],
            };
        }

        $tripUrl = $booking ? route('trips.show', $booking) : $fallback['url'];
        $stillCheckingOut = $booking && in_array($booking->status, [BookingStatus::Approved, BookingStatus::AwaitingPayment], true);

        return match ($status) {
            BookingStatus::Approved => $stillCheckingOut
                ? ['icon' => 'check-badge', 'tone' => 'emerald', 'chip' => __('Action needed'), 'action' => __('Continue to checkout'), 'url' => route('trips.checkout', $booking)]
                : ['icon' => 'check-badge', 'tone' => 'emerald', 'chip' => null, 'action' => __('See your trip'), 'url' => $tripUrl],
            BookingStatus::Declined => ['icon' => 'x-circle', 'tone' => 'red', 'chip' => null, 'action' => __('See why'), 'url' => $tripUrl],
            BookingStatus::Confirmed => ['icon' => 'check-circle', 'tone' => 'emerald', 'chip' => __('Paid'), 'action' => __('See pickup details'), 'url' => $tripUrl],
            BookingStatus::Ongoing => ['icon' => 'key', 'tone' => 'brand', 'chip' => null, 'action' => __('See your trip'), 'url' => $tripUrl],
            BookingStatus::Completed => ['icon' => 'star', 'tone' => 'amber', 'chip' => null, 'action' => __('Leave a rating'), 'url' => $tripUrl],
            BookingStatus::Cancelled, BookingStatus::Expired => ['icon' => 'x-circle', 'tone' => 'zinc', 'chip' => null, 'action' => __('See details'), 'url' => $tripUrl],
            default => [...$fallback, 'action' => __('See your trip'), 'url' => $tripUrl],
        };
    }

    /**
     * An ID was checked: once both are approved the renter can go find a car;
     * a rejected one needs replacing.
     *
     * @param  array{icon: string, tone: string, chip: string|null, action: string, url: string}  $fallback
     * @return array{icon: string, tone: string, chip: string|null, action: string, url: string}
     */
    protected static function identityReview(bool $approved, User $user, array $fallback): array
    {
        if (! $approved) {
            return ['icon' => 'identification', 'tone' => 'red', 'chip' => __('Action needed'), 'action' => __('Upload a new ID'), 'url' => route('identity.edit')];
        }

        return $user->hasVerifiedIdentity()
            ? ['icon' => 'identification', 'tone' => 'emerald', 'chip' => __('You’re verified'), 'action' => __('Find a car to rent'), 'url' => route('vehicles.index')]
            : [...$fallback, 'icon' => 'identification', 'tone' => 'emerald', 'action' => __('See your IDs'), 'url' => route('identity.edit')];
    }

    /**
     * The rental company application was decided: list a vehicle, or fix and resubmit.
     *
     * @param  array{icon: string, tone: string, chip: string|null, action: string, url: string}  $fallback
     * @return array{icon: string, tone: string, chip: string|null, action: string, url: string}
     */
    protected static function companyReview(bool $approved, User $user, array $fallback): array
    {
        if (! $approved) {
            return ['icon' => 'building-storefront', 'tone' => 'red', 'chip' => __('Action needed'), 'action' => __('Fix and resubmit'), 'url' => route('company.apply')];
        }

        return $user->vehicles()->exists()
            ? [...$fallback, 'icon' => 'building-storefront', 'tone' => 'emerald', 'action' => __('See your vehicles'), 'url' => route('company.vehicles.index')]
            : ['icon' => 'building-storefront', 'tone' => 'emerald', 'chip' => __('Next step'), 'action' => __('List your first vehicle'), 'url' => route('company.vehicles.create')];
    }
}
