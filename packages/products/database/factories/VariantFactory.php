<?php

declare(strict_types=1);

namespace AIArmada\Products\Database\Factories;

use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Variant>
 */
class VariantFactory extends Factory
{
    protected $model = Variant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => mb_strtoupper(Str::random(10)),
            'barcode' => $this->faker->optional()->ean13(),
            'price' => null, // Uses product price by default
            'compare_price' => null,
            'cost' => null,
            'weight' => $this->faker->optional()->randomFloat(2, 0.1, 50),
            'length' => null,
            'width' => null,
            'height' => null,
            'is_enabled' => true,
            'is_default' => false,
        ];
    }

    /**
     * Default variant.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Disabled variant.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    /**
     * Variant with price override.
     */
    public function withPrice(int $priceInCents): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $priceInCents,
        ]);
    }
}
