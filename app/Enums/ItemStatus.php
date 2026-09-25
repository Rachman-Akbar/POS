<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Pending = 'pending';
    case Cooking = 'cooking';
    case Sent = 'sent';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Dipesan',
            self::Cooking => 'Diproses',
            self::Sent => 'Dikirim',
            self::Done => 'Selesai',
        };
    }

    /**
     * Next status in the kitchen production flow.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Pending => self::Cooking,
            self::Cooking => self::Sent,
            self::Sent => self::Done,
            self::Done => null,
        };
    }
}
