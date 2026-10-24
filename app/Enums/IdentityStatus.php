<?php

namespace App\Enums;

use App\Models\User;

/**
 * Where a renter stands on the two-valid-ID requirement, as administrators see it.
 */
enum IdentityStatus: string
{
    case NotUploaded = 'not_uploaded';
    case Pending = 'pending';
    case Approved = 'approved';

    /**
     * The status for a renter with the given number of approved IDs and IDs on
     * file (pending or approved), each of a different type.
     */
    public static function fromCounts(int $approved, int $onFile): self
    {
        return match (true) {
            $approved >= User::REQUIRED_IDENTITY_DOCUMENTS => self::Approved,
            $onFile >= User::REQUIRED_IDENTITY_DOCUMENTS => self::Pending,
            default => self::NotUploaded,
        };
    }

    /**
     * The human-readable name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotUploaded => __('No IDs uploaded'),
            self::Pending => __('Pending'),
            self::Approved => __('Approved'),
        };
    }

    /**
     * The Flux badge colour used to display the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::NotUploaded => 'zinc',
            self::Pending => 'amber',
            self::Approved => 'green',
        };
    }
}
