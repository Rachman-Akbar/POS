<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'mdr_rate',
        'is_active',
    ];

    protected $casts = [
        'mdr_rate' => 'float',
        'is_active' => 'boolean',
    ];
}
