<?php

namespace App\Enums;

enum CustomerType: string
{
    case Individual = 'individual';
    case Business = 'business';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Perorangan',
            self::Business => 'Badan Usaha',
        };
    }

    /**
     * Semua tipe pelanggan yang boleh dipilih.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
