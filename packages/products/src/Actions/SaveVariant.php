<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Variant;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class SaveVariant
{
    /** @param array<string, mixed> $attributes */
    public function create(Product $product, array $attributes): Variant
    {
        return DB::transaction(function () use ($product, $attributes): Variant {
            $parent = $this->lockProduct($product);

            return $parent->variants()->create(Arr::except($attributes, ['id', 'product_id', 'owner_type', 'owner_id']));
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, Variant $record, array $attributes): Variant
    {
        return DB::transaction(function () use ($product, $record, $attributes): Variant {
            $parent = $this->lockProduct($product);
            $locked = $parent->variants()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $locked->update(Arr::except($attributes, ['id', 'product_id', 'owner_type', 'owner_id']));

            return $locked;
        });
    }

    public function delete(Product $product, Variant $record): bool
    {
        return DB::transaction(function () use ($product, $record): bool {
            $parent = $this->lockProduct($product);
            $locked = $parent->variants()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            return (bool) $locked->delete();
        });
    }

    private function lockProduct(Product $product): Product
    {
        $query = Product::query();

        if (config('products.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            $query->forOwner($owner, false);
        }

        return $query->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
    }
}
