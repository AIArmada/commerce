<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class EnsureProductBySlug
{
    /** @param array<string, mixed> $attributes */
    public function execute(string $slug, array $attributes): Product
    {
        return DB::transaction(function () use ($slug, $attributes): Product {
            try {
                $product = Product::query()->createOrFirst(
                    ['slug' => $slug],
                    ['name' => $attributes['name']],
                );
            } catch (InvalidArgumentException $exception) {
                $product = Product::query()->where('slug', $slug)->first();

                if ($product === null) {
                    throw $exception;
                }
            }

            $created = $product->wasRecentlyCreated;
            $product = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $product->fill($attributes);

            if ($created && $product->published_at === null) {
                $product->published_at = CarbonImmutable::now();
            }

            if ($product->isDirty()) {
                $product->save();
            }

            return $product;
        });
    }
}
