<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Completed = 'completed';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Menunggu',
            self::Completed => 'Selesai',
            self::Void => 'Dibatalkan',
        };
    }

    /**
     * Status ini menutup pesanan: tidak bisa dilanjuti tanpa tindakan admin.
     */
    public function isClosed(): bool
    {
        return $this === self::Completed || $this === self::Void;
    }
}
