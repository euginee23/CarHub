<?php

namespace App\Enums;

enum FuelType: string
{
    case Gasoline = 'Gasoline';
    case Diesel = 'Diesel';
    case Hybrid = 'Hybrid';
    case Electric = 'Electric';

    /**
     * The human-readable name of the fuel type.
     */
    public function label(): string
    {
        return $this->value;
    }
}
