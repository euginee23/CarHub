<?php

namespace App\Enums;

enum VehicleType: string
{
    case Sedan = 'Sedan';
    case Hatchback = 'Hatchback';
    case Suv = 'SUV';
    case Mpv = 'MPV';
    case Pickup = 'Pickup';
    case Van = 'Van';

    /**
     * The human-readable name of the body type.
     */
    public function label(): string
    {
        return $this->value;
    }
}
