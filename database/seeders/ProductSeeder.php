<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed sample products.
     */
    public function run(): void
    {
        $products = [
            ['name' => 'Nasi Goreng Spesial', 'category' => 'Makanan', 'price' => 25000, 'cost_price' => 12000, 'stock' => 50, 'image' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=400&h=400&fit=crop'],
            ['name' => 'Mie Goreng Jawa', 'category' => 'Makanan', 'price' => 22000, 'cost_price' => 10000, 'stock' => 50, 'image' => 'https://images.unsplash.com/photo-1612929633738-8fe44f7ec841?w=400&h=400&fit=crop'],
            ['name' => 'Ayam Geprek', 'category' => 'Makanan', 'price' => 20000, 'cost_price' => 9000, 'stock' => 40, 'image' => 'https://images.unsplash.com/photo-1562967914-608f82629710?w=400&h=400&fit=crop'],
            ['name' => 'Sate Ayam (10 tusuk)', 'category' => 'Makanan', 'price' => 30000, 'cost_price' => 15000, 'stock' => 30, 'image' => 'https://images.unsplash.com/photo-1529193591184-b1d58069ecdd?w=400&h=400&fit=crop'],
            ['name' => 'Gado-Gado', 'category' => 'Makanan', 'price' => 18000, 'cost_price' => 8000, 'stock' => 30, 'image' => 'https://images.unsplash.com/photo-1541696432-82c129e44543?w=400&h=400&fit=crop'],
            ['name' => 'Es Teh Manis', 'category' => 'Minuman', 'price' => 6000, 'cost_price' => 1500, 'stock' => 100, 'image' => 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=400&h=400&fit=crop'],
            ['name' => 'Es Jeruk', 'category' => 'Minuman', 'price' => 8000, 'cost_price' => 2500, 'stock' => 100, 'image' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?w=400&h=400&fit=crop'],
            ['name' => 'Kopi Susu Gula Aren', 'category' => 'Minuman', 'price' => 15000, 'cost_price' => 6000, 'stock' => 60, 'image' => 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=400&h=400&fit=crop'],
            ['name' => 'Air Mineral', 'category' => 'Minuman', 'price' => 5000, 'cost_price' => 2500, 'stock' => 120, 'image' => 'https://images.unsplash.com/photo-1616118132534-381148898bb4?w=400&h=400&fit=crop'],
            ['name' => 'Pisang Goreng', 'category' => 'Snack', 'price' => 10000, 'cost_price' => 4000, 'stock' => 60, 'image' => 'https://images.unsplash.com/photo-1611485988300-b7530defb8e2?w=400&h=400&fit=crop'],
            ['name' => 'Kentang Goreng', 'category' => 'Snack', 'price' => 15000, 'cost_price' => 7000, 'stock' => 60, 'image' => 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=400&h=400&fit=crop'],
            ['name' => 'Baso Tahu', 'category' => 'Snack', 'price' => 12000, 'cost_price' => 5000, 'stock' => 50, 'image' => 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?w=400&h=400&fit=crop'],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['name' => $product['name']],
                $product + ['description' => $product['name'], 'is_active' => true]
            );
        }
    }
}
