<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;

final readonly class GenerateQueuedVariants
{
    public function __construct(private GenerateVariantMatrix $generator) {}

    public function execute(string $productId): void
    {
        $query = Product::query();

        if (config('products.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner, 'Variant generation requires an owner context.');
            $query->forOwner($owner, false);
        }

        $product = $query->whereKey($productId)->first();

        if ($product !== null) {
            $this->generator->generateSynchronously($product);
        }
    }
}
