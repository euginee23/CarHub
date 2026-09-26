<?php

namespace App\Enums;

enum Transmission: string
{
    case Automatic = 'Automatic';
    case Manual = 'Manual';

    /**
     * The human-readable name of the transmission.
     */
    public function label(): string
    {
        return $this->value;
    }
}
