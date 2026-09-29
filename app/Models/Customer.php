<?php

namespace App\Models;

use App\Enums\CustomerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_type',
        'name',
        'email',
        'phone',
        'address',
        'nik',
        'npwp',
        'province',
        'city',
        'company_name',
        'postal_code',
        'country',
        'notes',
        'is_active',
    ];

    /**
     * Accessor ikut dikirim ke API agar tabel admin tidak menghitung ulang.
     *
     * @var array<int, string>
     */
    protected $appends = ['display_name', 'region'];

    protected $casts = [
        'customer_type' => CustomerType::class,
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Nama yang tampil di daftar: badan usaha memakai nama perusahaan.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->customer_type === CustomerType::Business
            ? ($this->company_name ?: $this->name)
            : $this->name;
    }

    /**
     * Ringkasan kota/provinsi untuk tampilan tabel admin.
     */
    public function getRegionAttribute(): string
    {
        return collect([$this->city, $this->province])
            ->filter()
            ->implode(', ');
    }
}
