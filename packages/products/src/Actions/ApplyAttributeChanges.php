<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;

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
     * @param  array<string, mixed>  $attributes
     */
    private function applyToProduct(Product $product, array $attributes): Product
    {
        foreach ($attributes as $attributeCode => $value) {
            $attribute = $product->attributeValues()
                ->whereHas('attribute', fn ($q) => $q->where('code', $attributeCode))
                ->first();

            if ($attribute) {
                $attribute->update(['value' => $value]);
            }
        }

        return $product->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyToVariant(Variant $variant, array $attributes): Variant
    {
        foreach ($attributes as $attributeCode => $value) {
            $attribute = $variant->attributeValues()
                ->whereHas('attribute', fn ($q) => $q->where('code', $attributeCode))
                ->first();

            if ($attribute) {
                $attribute->update(['value' => $value]);
            }
        }

        return $variant->fresh();
    }
}
