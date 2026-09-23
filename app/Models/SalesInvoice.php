<?php

namespace App\Models;

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
        'status',
        'issued_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

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
