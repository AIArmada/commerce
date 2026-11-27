<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Pricing\Actions\CopyPriceablePrices;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
            $newProduct->name = $record->name . ' (Copy)';
            $newProduct->slug = self::uniqueOwnerValue(Product::class, 'slug', $record->slug . '-copy', $ownerType, $ownerId);
            $newProduct->sku = $record->sku !== null && $record->sku !== ''
                ? self::uniqueOwnerValue(Product::class, 'sku', $record->sku . '-COPY', $ownerType, $ownerId)
                : null;
            $newProduct->status = ProductStatus::Draft;
            $newProduct->published_at = null;
            $newProduct->deactivated_at = null;
            $newProduct->archived_at = null;
            $newProduct->save();

            $newProduct->categories()->sync($record->categories()->pluck($record->categories()->getRelated()->qualifyColumn('id'))->all());
            $newProduct->collections()->sync($record->collections()->pluck($record->collections()->getRelated()->qualifyColumn('id'))->all());
            $newProduct->tags()->sync($record->tags()->pluck($record->tags()->getRelated()->qualifyColumn('id'))->all());

            $valueMap = [];

            foreach ($record->options()->with('values')->get() as $option) {
                $newOption = $option->replicate();
                $newOption->product_id = $newProduct->getKey();
                $newOption->save();

                foreach ($option->values as $value) {
                    $newValue = $value->replicate();
                    $newValue->option_id = $newOption->getKey();
                    $newValue->save();
                    $valueMap[(string) $value->getKey()] = (string) $newValue->getKey();
                }
            }

            $variantMap = [];

            foreach ($record->variants()->with('optionValues')->get() as $variant) {
                $newVariant = $variant->replicate();
                $newVariant->product_id = $newProduct->getKey();
                $newVariant->sku = self::uniqueOwnerValue(Variant::class, 'sku', $variant->sku . '-COPY', $ownerType, $ownerId);
                $newVariant->save();
                $variantMap[(string) $variant->getKey()] = (string) $newVariant->getKey();

                $remapped = $variant->optionValues
                    ->map(static fn ($optionValue): ?string => $valueMap[(string) $optionValue->getKey()] ?? null)
                    ->filter()
                    ->all();

                $newVariant->optionValues()->sync($remapped);
            }

            if ($record->isCompositeProduct()) {
                foreach ($record->components()->get() as $component) {
                    $copy = $component->replicate();
                    $copy->product_id = $newProduct->getKey();
                    $copy->parent_variant_id = $component->parent_variant_id === null
                        ? null
                        : ($variantMap[(string) $component->parent_variant_id] ?? throw new RuntimeException('Unable to remap composite parent variant.'));
                    $copy->save();
                }
            }

            if (class_exists(CopyPriceablePrices::class)) {
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
        $candidate = $base;

        for ($suffix = 2; $suffix <= 100; $suffix++) {
            $query = $modelClass::query()->withoutGlobalScope(OwnerScope::class)->where($column, $candidate);

            if ($ownerType === null || $ownerId === null) {
                $query->whereNull('owner_type')->whereNull('owner_id');
            } else {
                $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
            }

            if (! $query->exists()) {
                return $candidate;
            }

            $candidate = $base . '-' . $suffix;
        }

        throw new RuntimeException("Unable to generate a unique {$column} for the duplicated product.");
    }

}
