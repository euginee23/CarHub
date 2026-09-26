<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Draft = 'draft';
    case Listed = 'listed';
    case Unlisted = 'unlisted';

    /**
     * The human-readable name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Listed => __('Listed'),
            self::Unlisted => __('Unlisted'),
        };
    }

    /**
     * The Flux badge colour used to display the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Listed => 'green',
            self::Unlisted => 'amber',
        };
    }
}
