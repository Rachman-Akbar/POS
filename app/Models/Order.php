<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'table_number',
        'customer_id',
        'user_id',
        'payment_type',
        'status',
        'payment_status',
        'total_amount',
        'paid_amount',
        'subtotal',
        'discount',
        'tax_amount',
        'notes',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Admin yang membatalkan pesanan ini, bila ada.
     *
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->status === OrderStatus::Void->value;
    }

    /**
     * Sisa tagihan yang belum pernah dibayar.
     *
     * `paid_amount` sengaja tidak dipakai di sini: setelah retur, uang yang
     * ditahan kasir berkurang sementara total tagihan tetap, jadi hanya
     * penjumlahan `gross` penerimaan yang bisa menjawab "apa yang belum dibayar".
     */
    public function outstandingAmount(): float
    {
        $received = (float) $this->invoice?->receipts()->sum('gross_amount');

        return round(max((float) $this->total_amount - $received, 0), 2);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<SalesInvoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(SalesInvoice::class);
    }
}
