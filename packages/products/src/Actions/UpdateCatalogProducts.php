<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Product;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $price = match ($action) {
                'set' => $minor,
                'increase_percent' => (int) round($current * (1 + $value / 100)),
                'decrease_percent' => (int) round($current * (1 - $value / 100)),
                'increase_amount' => $current + $minor,
                'decrease_amount' => $current - $minor,
            };

            $product->update(['price' => max(0, $price)]);
        });
    }

    /** @param iterable<Product> $records */
    public function setVisibility(iterable $records, ProductVisibility $visibility): void
    {
        $this->mutate($records, static function (Product $product) use ($visibility): void {
            $product->update(['visibility' => $visibility]);
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

        $ids = array_unique($ids);
        sort($ids);

        DB::transaction(function () use ($ids, $mutation): void {
            $query = Product::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            foreach ($ids as $id) {
                $product = (clone $query)->whereKey($id)->lockForUpdate()->firstOrFail();
                $mutation($product);
            }
        }, 3);
    }
}
