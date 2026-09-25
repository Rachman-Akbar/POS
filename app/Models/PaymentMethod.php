<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'name',
        'mdr_rate',
        'is_active',
    ];

    protected $casts = [
        'mdr_rate' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * Cash & Bank accounts linked to this payment method.
     *
     * @return HasMany<CashBankAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(CashBankAccount::class);
    }
}
