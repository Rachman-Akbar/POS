<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Kategori katalog beserta urutan tampilnya di layar kasir.
     *
     * @var array<string, int>
     */
    private const CATEGORIES = [
        'Makanan' => 1,
        'Minuman' => 2,
        'Snack' => 3,
        'Dessert' => 4,
    ];

    /**
     * Seed the master categories and keep the cashier catalog order in sync.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $sortOrder) {
            Category::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $sortOrder, 'is_active' => true],
            );
        }

        Setting::saveCategoryOrder(array_keys(self::CATEGORIES));
    }
}
