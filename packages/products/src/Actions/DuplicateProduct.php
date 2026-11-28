<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Pricing\Actions\CopyPriceablePrices;
use AIArmada\Pricing\Models\Price;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final readonly class DuplicateProduct
{
    public function execute(Product $record): Product
    {
        return DB::transaction(static function () use ($record): Product {
            $query = Product::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $record = $query->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $ownerType = $record->getAttribute('owner_type');
            $ownerId = $record->getAttribute('owner_id');

            $newProduct = $record->replicate();
            $newProduct->name = mb_substr($record->name, 0, 248) . ' (Copy)';
            $newProduct->slug = self::uniqueOwnerValue(Product::class, 'slug', $record->slug . '-copy', $ownerType, $ownerId);
            $newProduct->sku = $record->sku !== null && $record->sku !== ''
                ? self::uniqueOwnerValue(Product::class, 'sku', $record->sku . '-COPY', $ownerType, $ownerId)
                : null;
            $newProduct->status = ProductStatus::Draft;
            $newProduct->published_at = null;
            $newProduct->deactivated_at = null;
            $newProduct->archived_at = null;
            if (! $newProduct->save()) {
                throw new RuntimeException('Catalog duplication was cancelled.');
            }

            $newProduct->categories()->sync($record->categories()->pluck($record->categories()->getRelated()->qualifyColumn('id'))->all());
            $newProduct->collections()->sync($record->collections()->pluck($record->collections()->getRelated()->qualifyColumn('id'))->all());
            $newProduct->tags()->sync($record->tags()->pluck($record->tags()->getRelated()->qualifyColumn('id'))->all());

            $valueMap = [];

            foreach ($record->options()->with('values')->get() as $option) {
                $newOption = $option->replicate();
                $newOption->product_id = $newProduct->getKey();
                if (! $newOption->save()) {
                    throw new RuntimeException('Catalog duplication was cancelled.');
                }

                foreach ($option->values as $value) {
                    $newValue = $value->replicate();
                    $newValue->option_id = $newOption->getKey();
                    if (! $newValue->save()) {
                        throw new RuntimeException('Catalog duplication was cancelled.');
                    }
                    $valueMap[(string) $value->getKey()] = (string) $newValue->getKey();
                }
            }

            $variantMap = [];
            $copyPricing = class_exists(Price::class) && Schema::hasTable((new Price)->getTable());

            foreach ($record->variants()->with('optionValues')->get() as $variant) {
                $remapped = $variant->optionValues
                    ->map(static fn ($optionValue): string => $valueMap[(string) $optionValue->getKey()] ?? throw new RuntimeException('Unable to remap variant option value.'))
                    ->values()
                    ->all();

                $newVariant = $variant->replicate();
                $newVariant->product_id = $newProduct->getKey();
                $newVariant->sku = self::uniqueOwnerValue(Variant::class, 'sku', $variant->sku . '-COPY', $ownerType, $ownerId);
                $newVariant->combination_key = $variant->combination_key === null
                    ? null
                    : Variant::combinationKeyFor((string) $newProduct->getKey(), $remapped);
                if (! $newVariant->save()) {
                    throw new RuntimeException('Catalog duplication was cancelled.');
                }
                $variantMap[(string) $variant->getKey()] = (string) $newVariant->getKey();
                $newVariant->optionValues()->sync($remapped);

                if ($copyPricing) {
                    app(CopyPriceablePrices::class)->execute($variant, $newVariant);
                }
            }

            if ($record->isCompositeProduct()) {
                foreach ($record->components()->get() as $component) {
                    $copy = $component->replicate();
                    $copy->product_id = $newProduct->getKey();
                    $copy->parent_variant_id = $component->parent_variant_id === null
                        ? null
                        : ($variantMap[(string) $component->parent_variant_id] ?? throw new RuntimeException('Unable to remap composite parent variant.'));
                    if (! $copy->save()) {
                        throw new RuntimeException('Catalog duplication was cancelled.');
                    }
                }
            }

            if ($copyPricing) {
                app(CopyPriceablePrices::class)->execute($record, $newProduct);
            }

            return $newProduct->refresh();
        }, 3);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function uniqueOwnerValue(string $modelClass, string $column, string $base, mixed $ownerType, mixed $ownerId): string
    {
        $copySuffix = $column === 'slug' ? '-copy' : '-COPY';
        $stem = mb_substr($base, 0, mb_strlen($base) - mb_strlen($copySuffix));

        for ($suffix = 1; $suffix <= 100; $suffix++) {
            $ending = $copySuffix . ($suffix === 1 ? '' : '-' . $suffix);
            $candidate = mb_substr($stem, 0, 255 - mb_strlen($ending)) . $ending;
            $query = $modelClass::query()->withoutGlobalScope(OwnerScope::class)->where($column, $candidate);

            if ($ownerType === null || $ownerId === null) {
                $query->whereNull('owner_type')->whereNull('owner_id');
            } else {
                $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
            }

            if (! $query->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException("Unable to generate a unique {$column} for the duplicated product.");
    }
}
