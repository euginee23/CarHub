<?php

namespace App\Enums;

/**
 * What an account is for. Renters and rental companies are separate kinds of account,
 * chosen at registration; administrators run the platform.
 */
enum UserRole: string
{
    case Renter = 'renter';
    case Company = 'company';
    case Admin = 'admin';

    /**
     * The human-readable name of the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Renter => __('Renter'),
            self::Company => __('Rental company'),
            self::Admin => __('Admin'),
        };
    }
}
