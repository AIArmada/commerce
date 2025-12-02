<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(rand(2, 5), true);
        $price = $this->faker->numberBetween(1000, 100000);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->paragraphs(rand(1, 3), true),
            'sku' => mb_strtoupper($this->faker->unique()->bothify('???-####')),
            'price' => $price,
            'compare_at_price' => $this->faker->optional(0.3)->numberBetween($price + 1000, $price + 50000),
            'currency' => 'MYR',
            'is_active' => true,
            'track_stock' => true,
            'stock_quantity' => $this->faker->numberBetween(0, 100),
            'low_stock_threshold' => 5,
            'category_id' => null,
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => $this->faker->numberBetween(1, 5),
        ]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    public function withDiscount(): static
    {
        return $this->state(function (array $attributes) {
            $originalPrice = $attributes['price'] + $this->faker->numberBetween(5000, 30000);

            return [
                'compare_at_price' => $originalPrice,
            ];
        });
    }
}
