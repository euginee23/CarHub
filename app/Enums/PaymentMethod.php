<?php

namespace App\Enums;

use Illuminate\Support\Facades\Log;

enum PaymentMethod: string
{
    case QrPh = 'qrph';
    case Gcash = 'gcash';
    case Maya = 'maya';
    case Card = 'card';
    case GrabPay = 'grab_pay';

    /**
     * The methods offered on the gateway's checkout page, from PAYMONGO_METHODS.
     *
     * @return array<int, self>
     */
    public static function enabled(): array
    {
        $methods = [];

        foreach ((array) config('services.paymongo.methods') as $type) {
            $method = self::fromPayMongo((string) $type);

            if ($method === null) {
                Log::warning('Ignoring an unsupported PayMongo payment method in PAYMONGO_METHODS.', ['method' => $type]);

                continue;
            }

            $methods[$method->value] = $method;
        }

        return array_values($methods);
    }

    /**
     * The method matching a PayMongo `payment_method_types` or payment source type, if CarHub knows it.
     */
    public static function fromPayMongo(?string $type): ?self
    {
        return match ($type) {
            'paymaya' => self::Maya,
            null => null,
            default => self::tryFrom($type),
        };
    }

    /**
     * The human-readable name of the payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::QrPh => __('QR Ph'),
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
            self::QrPh => 'qrph',
            self::Gcash => 'gcash',
            self::Maya => 'paymaya',
            self::Card => 'card',
            self::GrabPay => 'grab_pay',
        };
    }
}
