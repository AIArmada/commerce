<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Models\Product;
use AIArmada\Products\Strategies\MatrixVariantGenerator;

final readonly class GenerateQueuedVariants
{
    public function __construct(private MatrixVariantGenerator $generator) {}

    public function execute(string $productId): void
    {
        $product = Product::query()->forOwner()->whereKey($productId)->first();

        if ($product !== null) {
            $this->generator->generateSynchronously($product);
        }
    }
}
