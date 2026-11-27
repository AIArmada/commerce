<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProductStatus
{
    public function execute(Product $product, ProductStatus $newStatus): Product
    {
        return DB::transaction(function () use ($product, $newStatus): Product {
            $query = Product::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $locked = $query->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $oldStatus = $locked->status;

            if ($oldStatus === $newStatus) {
                return $locked;
            }

            $locked->status = $newStatus;

            // Transition rules for lifecycle timestamps
            $now = CarbonImmutable::now();

            match ($newStatus) {
                ProductStatus::Active => $this->transitionToActive($locked, $now),
                ProductStatus::Disabled => $this->transitionToDisabled($locked, $now),
                ProductStatus::Archived => $this->transitionToArchived($locked, $now),
                ProductStatus::Draft => $this->transitionToDraft($locked),
            };

            $locked->save();

            return $locked;
        }, 3);
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
        $product->published_at = null;
    }
}
