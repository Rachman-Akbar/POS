<?php

namespace App\Enums;

enum ItemStatus: string
{
    /** Item milik pesanan yang masih draft: belum pernah masuk dapur. */
    case Draft = 'draft';
    case Pending = 'pending';
    case Cooking = 'cooking';
    case Sent = 'sent';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Diproses',
            self::Cooking => 'Dimasak',
            self::Sent => 'Dikirim',
            self::Done => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Next status in the kitchen production flow.
     *
     * Draft -> Pending happens when the draft is finalized, not in the kitchen,
     * but it is mapped here so the five-step flow
     * (Draft -> Diproses -> Dimasak -> Dikirim -> Selesai) stays complete.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Draft => self::Pending,
            self::Pending => self::Cooking,
            self::Cooking => self::Sent,
            self::Sent => self::Done,
            self::Done, self::Cancelled => null,
        };
    }
}
