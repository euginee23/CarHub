<?php

namespace App\Enums;

enum FuelLevel: string
{
    case Empty = 'empty';
    case Quarter = 'quarter';
    case Half = 'half';
    case ThreeQuarters = 'three_quarters';
    case Full = 'full';

    /**
     * The human-readable name of the fuel level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Empty => __('Empty'),
            self::Quarter => __('1/4 tank'),
            self::Half => __('1/2 tank'),
            self::ThreeQuarters => __('3/4 tank'),
            self::Full => __('Full tank'),
        };
    }
}
