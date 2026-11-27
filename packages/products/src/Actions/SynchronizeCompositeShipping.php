<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;

final readonly class SynchronizeCompositeShipping
{
    public function forComponent(ProductComponent $component): void
    {
        $parent = Product::query()->whereKey($component->product_id)->first();

        if ($parent instanceof Product) {
            $parent->save();
        }
    }

    public function forParents(Product $product): void
    {
        ProductComponent::query()
            ->where('component_product_id', $product->getKey())
            ->with('product')
            ->chunkById(100, static function ($references): void {
                foreach ($references as $reference) {
                    $reference->product?->save();
                }
            });
    }
}
