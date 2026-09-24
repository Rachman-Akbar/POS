<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Nasi Goreng', 'Mie Goreng', 'Ayam Geprek', 'Es Teh Manis', 'Kopi Hitam', 'Sate Ayam']),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(0, 5000, 75000),
            'cost_price' => fake()->randomFloat(0, 2000, 40000),
            'stock' => fake()->numberBetween(0, 100),
            'category' => fake()->randomElement(['Makanan', 'Minuman', 'Snack']),
            'image' => null,
            'is_favorite' => false,
            'is_active' => true,
        ];
    }
}
