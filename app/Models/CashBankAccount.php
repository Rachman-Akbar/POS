<?php

namespace App\Models;

use Database\Factories\CashBankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashBankAccount extends Model
{
    /** @use HasFactory<CashBankAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'account_number',
        'bank_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
