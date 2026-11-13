<?php

declare(strict_types=1);

namespace AIArmada\Products\Database\Factories;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductComponent>
 */
class ProductComponentFactory extends Factory
{
    protected $model = ProductComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->state([
                'type' => ProductType::Composite,
                'status' => ProductStatus::Draft,
                'supports_variants' => false,
                'tracks_inventory' => false,
            ]),
            'component_product_id' => Product::factory()->state([
                'type' => ProductType::Simple,
                'status' => ProductStatus::Active,
                'requires_shipping' => true,
                'tracks_inventory' => true,
            ]),
            'variant_id' => null,
            'quantity' => 1,
            'position' => 0,
        ];
    }

    public function quantity(int $quantity): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity' => $quantity,
        ]);
    }

    public function forVariant(string $variantId): static
    {
        return $this->state(fn (array $attributes): array => [
            'variant_id' => $variantId,
        ]);
    }
}
