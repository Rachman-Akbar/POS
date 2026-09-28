<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'customer_type',
        'name',
        'phone',
        'address',
        'nik',
        'npwp',
        'province',
        'city',
        'company_name',
        'postal_code',
        'country',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
