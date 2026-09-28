<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'primary_category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 99999),
            'sku' => 'SKU-'.fake()->unique()->numerify('########'),
            'type' => fake()->randomElement(['product', 'service']),
            'description' => fake()->paragraphs(2, true),
            'brand' => fake()->company(),
            'thumbnail' => null,
            'price' => fake()->randomFloat(2, 15000, 5000000),
            'track_stock' => true,
            'stock' => fake()->numberBetween(5, 250),
            'status' => 'published',
            'is_featured' => fake()->boolean(25),
            'is_active' => true,
        ];
    }
}
