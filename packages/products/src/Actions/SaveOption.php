<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class SaveOption
{
    /** @param array<string, mixed> $attributes */
    public function create(Product $product, array $attributes): Option
    {
        return DB::transaction(function () use ($product, $attributes): Option {
            $parent = $this->lockProduct($product);

            $created = $parent->options()->create(Arr::except($attributes, ['id', 'product_id', 'owner_type', 'owner_id']));

            if (! $created->exists) {
                throw new RuntimeException('Catalog record creation was cancelled.');
            }

            return $created;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, Option $record, array $attributes): Option
    {
        return DB::transaction(function () use ($product, $record, $attributes): Option {
            $parent = $this->lockProduct($product);
            $locked = $parent->options()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->update(Arr::except($attributes, ['id', 'product_id', 'owner_type', 'owner_id']))) {
                throw new RuntimeException('Catalog record update was cancelled.');
            }

            return $locked;
        });
    }

    public function delete(Product $product, Option $record): bool
    {
        return DB::transaction(function () use ($product, $record): bool {
            $parent = $this->lockProduct($product);
            $locked = $parent->options()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

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
