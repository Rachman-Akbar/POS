<?php

namespace App\Enums;

enum PaymentType: string
{
    case PayNow = 'pay_now';
    case PayLater = 'pay_later';

    public function label(): string
    {
        return match ($this) {
            self::PayNow => 'Bayar Dulu',
            self::PayLater => 'Bayar Nanti',
        };
    }
}
