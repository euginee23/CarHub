<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Gcash = 'gcash';
    case Maya = 'maya';
    case Card = 'card';
    case GrabPay = 'grab_pay';

    /**
     * The human-readable name of the payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Gcash => __('GCash'),
            self::Maya => __('Maya'),
            self::Card => __('Credit or debit card'),
            self::GrabPay => __('GrabPay'),
        };
    }

    /**
     * The matching PayMongo checkout `payment_method_types` value.
     */
    public function payMongoType(): string
    {
        return match ($this) {
            self::Gcash => 'gcash',
            self::Maya => 'paymaya',
            self::Card => 'card',
            self::GrabPay => 'grab_pay',
        };
    }
}
