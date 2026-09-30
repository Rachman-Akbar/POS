<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Bayar',
            self::Partial => 'Bayar Sebagian',
            self::Paid => 'Lunas',
            self::Refunded => 'Sudah Retur',
        };
    }
}
