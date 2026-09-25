<?php

namespace App\Models;

use Database\Factories\CashBankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashBankAccount extends Model
{
    /** @use HasFactory<CashBankAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'payment_method_id',
        'is_default',
        'account_number',
        'bank_name',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['balance'];

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return HasMany<SalesReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(SalesReceipt::class, 'payment_account_id');
    }

    /**
     * Incoming balance derived from settled receipts linked to this account.
     */
    public function balance(): float
    {
        $receipts = $this->relationLoaded('receipts') ? $this->receipts : $this->receipts()->get();

        return round((float) $receipts->sum('net_amount'), 2);
    }

    /**
     * Accessor exposed in every serialized payload.
     */
    public function getBalanceAttribute(): float
    {
        return $this->balance();
    }
}
