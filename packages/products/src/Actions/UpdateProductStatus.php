<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use Carbon\CarbonImmutable;

final class UpdateProductStatus
{
    public function execute(Product $product, ProductStatus $newStatus): Product
    {
        $oldStatus = $product->status;

        if ($oldStatus === $newStatus) {
            return $product;
        }

        $product->status = $newStatus;

        // Transition rules for lifecycle timestamps
        $now = CarbonImmutable::now();

        match ($newStatus) {
            ProductStatus::Active => $this->transitionToActive($product, $now),
            ProductStatus::Disabled => $this->transitionToDisabled($product, $now),
            ProductStatus::Archived => $this->transitionToArchived($product, $now),
            ProductStatus::Draft => $this->transitionToDraft($product),
        };

        $product->save();

        return $product->fresh();
    }

    public function __invoke(Product $product, ProductStatus $newStatus): Product
    {
        return $this->execute($product, $newStatus);
    }

    private function transitionToActive(Product $product, CarbonImmutable $now): void
    {
        if ($product->published_at === null) {
            $product->published_at = $now;
        }

        $product->deactivated_at = null;
        $product->archived_at = null;
    }

    private function transitionToDisabled(Product $product, CarbonImmutable $now): void
    {
        $product->deactivated_at = $now;
        $product->archived_at = null;
    }

    private function transitionToArchived(Product $product, CarbonImmutable $now): void
    {
        $product->archived_at = $now;
        $product->deactivated_at = null;
    }

    private function transitionToDraft(Product $product): void
    {
        $product->archived_at = null;
        $product->deactivated_at = null;
    }
}
