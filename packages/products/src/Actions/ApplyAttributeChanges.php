<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Facades\DB;

final class ApplyAttributeChanges
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Product $product, array $attributes): Product
    {
        return $this->applyToProduct($product, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Product $product, array $attributes): Product
    {
        return $this->applyToProduct($product, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function forVariant(Variant $variant, array $attributes): Variant
    {
        return $this->applyToVariant($variant, $attributes);
    }

    /**
     * Unknown codes fail loudly via firstOrFail inside setCustomAttribute;
     * values are serialized through the attribute type.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function applyToProduct(Product $product, array $attributes): Product
    {
        return DB::transaction(function () use ($product, $attributes): Product {
            foreach ($attributes as $attributeCode => $value) {
                $product->setCustomAttribute($attributeCode, $value);
            }

            return $product->fresh() ?? $product;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyToVariant(Variant $variant, array $attributes): Variant
    {
        return DB::transaction(function () use ($variant, $attributes): Variant {
            foreach ($attributes as $attributeCode => $value) {
                $variant->setCustomAttribute($attributeCode, $value);
            }

            return $variant->fresh() ?? $variant;
        });
    }
}
