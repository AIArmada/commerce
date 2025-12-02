<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => $this->faker->randomElement(['Small', 'Medium', 'Large', 'XL', 'Red', 'Blue', 'Black', 'White']),
            'sku' => mb_strtoupper($this->faker->unique()->bothify('VAR-???-####')),
            'price' => $this->faker->numberBetween(1000, 50000),
            'stock_quantity' => $this->faker->numberBetween(0, 50),
            'options' => [
                'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
                'color' => $this->faker->safeColorName(),
            ],
            'is_active' => true,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'price' => $product->price,
        ]);
    }
}
