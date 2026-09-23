<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'payment_method',
        'gross_amount',
        'mdr_fee',
        'net_amount',
        'payment_date',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'mdr_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }
}
