<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'total_amount',
        'cogs_total',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'cogs_total' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function isVoided(): bool
    {
        return $this->status === InvoiceStatus::Void->value;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<SalesReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(SalesReceipt::class, 'invoice_id');
    }
}
