<?php

namespace App\Enums;

/**
 * The lifecycle of a rental:
 *
 * Requested → Approved → AwaitingPayment → Confirmed → Ongoing → Completed
 *
 * with Declined, Cancelled, and Expired as the ways a booking can end early.
 */
enum BookingStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case AwaitingPayment = 'awaiting_payment';
    case Confirmed = 'confirmed';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * The human-readable name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Awaiting owner approval'),
            self::Approved => __('Approved — complete checkout'),
            self::AwaitingPayment => __('Awaiting payment'),
            self::Confirmed => __('Confirmed'),
            self::Ongoing => __('Ongoing'),
            self::Completed => __('Completed'),
            self::Declined => __('Declined'),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('Expired'),
        };
    }

    /**
     * The Flux badge colour used to display the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Requested => 'amber',
            self::Approved, self::AwaitingPayment => 'blue',
            self::Confirmed => 'green',
            self::Ongoing => 'indigo',
            self::Completed => 'zinc',
            self::Declined, self::Cancelled, self::Expired => 'red',
        };
    }

    /**
     * The statuses a booking may move to from this one.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Approved, self::Declined, self::Cancelled, self::Expired],
            self::Approved => [self::AwaitingPayment, self::Cancelled, self::Expired],
            self::AwaitingPayment => [self::Confirmed, self::Cancelled, self::Expired],
            self::Confirmed => [self::Ongoing, self::Cancelled],
            self::Ongoing => [self::Completed],
            self::Completed, self::Declined, self::Cancelled, self::Expired => [],
        };
    }

    /**
     * Determine whether a booking may move from this status to the given one.
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Whether a booking in this status holds the vehicle, so that no other
     * booking may overlap it. Unapproved requests do not hold the vehicle.
     */
    public function holdsVehicle(): bool
    {
        return in_array($this, self::holding(), true);
    }

    /**
     * Whether the booking has reached an end state.
     */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * The statuses that hold the vehicle for the booked window.
     *
     * @return array<int, self>
     */
    public static function holding(): array
    {
        return [self::Approved, self::AwaitingPayment, self::Confirmed, self::Ongoing];
    }
}
