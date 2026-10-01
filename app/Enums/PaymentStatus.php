<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case RefundDue = 'refund_due';

    /**
     * The human-readable name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for payment'),
            self::Paid => __('Paid'),
            self::Failed => __('Failed'),
            self::Expired => __('Expired'),
            self::RefundDue => __('Refund due'),
        };
    }

    /**
     * The Flux badge colour used to display the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Paid => 'green',
            self::Failed, self::Expired => 'red',
            self::RefundDue => 'orange',
        };
    }
}
