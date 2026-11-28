<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class EnsureProductBySlug
{
    /** @param array<string, mixed> $attributes */
    public function execute(string $slug, array $attributes): Product
    {
        return DB::transaction(function () use ($slug, $attributes): Product {
            $query = Product::query();
            $identity = ['slug' => $slug];

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
                $identity['owner_type'] = $owner?->getMorphClass();
                $identity['owner_id'] = $owner?->getKey();
            }

            try {
                $product = (clone $query)->createOrFirst(
                    $identity,
                    ['name' => $attributes['name']],
                );
            } catch (InvalidArgumentException $exception) {
                $product = (clone $query)->where('slug', $slug)->first();

                if ($product === null) {
                    throw $exception;
                }
            }

            if (! $product->exists) {
                throw new RuntimeException('Catalog record creation was cancelled.');
            }

            $created = $product->wasRecentlyCreated;
            $product = $query->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $product->fill(Arr::except($attributes, ['id', 'slug', 'owner_type', 'owner_id']));

            if ($created && $product->published_at === null) {
                $product->published_at = CarbonImmutable::now();
            }

            if ($product->isDirty() && ! $product->save()) {
                throw new RuntimeException('Catalog record update was cancelled.');
            }

            return $product;
        }, 3);
    }
}
