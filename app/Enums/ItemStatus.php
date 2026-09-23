<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Pending = 'pending';
    case Cooking = 'cooking';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Cooking => 'Dimasak',
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
            self::Cooking => self::Done,
            self::Done => null,
        };
    }
}
