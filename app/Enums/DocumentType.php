<?php

namespace App\Enums;

enum DocumentType: string
{
    case DriversLicense = 'drivers_license';
    case Passport = 'passport';
    case NationalId = 'national_id';
    case Umid = 'umid';
    case PrcId = 'prc_id';
    case PostalId = 'postal_id';
    case VotersId = 'voters_id';
    case VehicleRegistration = 'vehicle_registration';

    /**
     * The human-readable name of the document.
     */
    public function label(): string
    {
        return match ($this) {
            self::DriversLicense => __('Driver\'s license'),
            self::Passport => __('Passport'),
            self::NationalId => __('National ID (PhilSys)'),
            self::Umid => __('UMID'),
            self::PrcId => __('PRC ID'),
            self::PostalId => __('Postal ID'),
            self::VotersId => __('Voter\'s ID'),
            self::VehicleRegistration => __('Vehicle OR/CR'),
        };
    }

    /**
     * The government-issued identity documents a person can submit.
     *
     * @return array<int, self>
     */
    public static function governmentIds(): array
    {
        return [
            self::DriversLicense,
            self::Passport,
            self::NationalId,
            self::Umid,
            self::PrcId,
            self::PostalId,
            self::VotersId,
        ];
    }
}
