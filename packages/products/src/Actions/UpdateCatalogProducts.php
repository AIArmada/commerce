<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Product;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class UpdateCatalogProducts
{
    /** @param iterable<Product> $records */
    public function adjustPrices(iterable $records, string $action, mixed $value): void
    {
        $percentage = in_array($action, ['increase_percent', 'decrease_percent'], true);

        if (! in_array($action, ['set', 'increase_percent', 'decrease_percent', 'increase_amount', 'decrease_amount'], true)
            || ! is_numeric($value) || ! is_finite((float) $value)
            || (float) $value < 0 || (float) $value > ($percentage ? 100 : 1000000)) {
            throw ValidationException::withMessages(['value' => 'Invalid price adjustment.']);
        }

        $value = (float) $value;

        $this->mutate($records, static function (Product $product) use ($action, $value): void {
            $current = (int) $product->getAttribute('price');
            $minor = (int) round($value * 100);
            if ($action === 'increase_amount' && $current > PHP_INT_MAX - $minor) {
                throw ValidationException::withMessages(['value' => 'Price exceeds the supported integer range.']);
            }

            $price = match ($action) {
                'set' => $minor,
                'increase_percent' => $value === 0.0 ? $current : round($current * (1 + $value / 100)),
                'decrease_percent' => $value === 0.0 ? $current : round($current * (1 - $value / 100)),
                'increase_amount' => $current + $minor,
                'decrease_amount' => $current - $minor,
            };

            if (is_float($price) && $price >= (float) PHP_INT_MAX) {
                throw ValidationException::withMessages(['value' => 'Price exceeds the supported integer range.']);
            }

            if (! $product->update(['price' => max(0, (int) $price)])) {
                throw new RuntimeException('Catalog record update was cancelled.');
            }
        });
    }

    /** @param iterable<Product> $records */
    public function setVisibility(iterable $records, ProductVisibility $visibility): void
    {
        $this->mutate($records, static function (Product $product) use ($visibility): void {
            if (! $product->update(['visibility' => $visibility])) {
                throw new RuntimeException('Catalog record update was cancelled.');
            }
        });
    }

    /** @param iterable<Product> $records @param array<int, mixed> $ids */
    public function assignCategories(iterable $records, array $ids, bool $add): void
    {
        $this->mutate($records, static function (Product $product) use ($ids, $add): void {
            app(SyncCatalogRelationships::class)->syncProductCategories($product, $ids, $add);
        });
    }

    /** @param iterable<Product> $records @param Closure(Product): void $mutation */
    private function mutate(iterable $records, Closure $mutation): void
    {
        $ids = [];

        foreach ($records as $record) {
            $ids[] = $record->getKey();
        }

        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);

        DB::transaction(function () use ($ids, $mutation): void {
            $query = Product::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            foreach (array_chunk($ids, 200) as $chunk) {
                $products = (clone $query)->whereKey($chunk)->orderBy('id')->lockForUpdate()->get();

                if ($products->count() !== count($chunk)) {
                    throw (new ModelNotFoundException)->setModel(Product::class, $chunk);
                }

                foreach ($products as $product) {
                    $mutation($product);
                }
            }
        }, 3);
    }
}
