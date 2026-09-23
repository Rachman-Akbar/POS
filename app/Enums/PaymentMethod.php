<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Qris = 'qris';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Bank => 'Transfer Bank',
            self::Qris => 'QRIS',
        };
    }

    public function mdrRate(): float
    {
        return match ($this) {
            self::Cash => 0.0,
            self::Bank => 0.003,
            self::Qris => 0.007,
        };
    }
}
