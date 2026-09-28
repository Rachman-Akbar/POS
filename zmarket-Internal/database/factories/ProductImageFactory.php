<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'url' => 'products/'.fake()->uuid().'.webp',
            'alt_text' => fake()->words(3, true),
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }
}
