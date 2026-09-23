<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Waiter = 'waiter';
    case Kitchen = 'kitchen';
    case Cashier = 'cashier';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Waiter => 'Waiter',
            self::Kitchen => 'Koki',
            self::Cashier => 'Kasir',
        };
    }
}
