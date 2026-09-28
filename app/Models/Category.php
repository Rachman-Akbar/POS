<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Produk dikelompokkan lewat `products.category` yang berisi nama kategori ini.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category', 'name');
    }

    /**
     * Daftarkan kategori yang dipakai produk, lalu jaga cache urutan katalog.
     */
    public static function register(?string $name): void
    {
        if (blank($name)) {
            return;
        }

        static::query()->firstOrCreate(
            ['name' => $name],
            ['sort_order' => (int) static::query()->max('sort_order') + 1, 'is_active' => true],
        );

        $order = Setting::categoryOrder();

        if (! in_array($name, $order, true)) {
            Setting::saveCategoryOrder([...$order, $name]);
        }
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
