<?php

declare(strict_types=1);

namespace AIArmada\Products\Services;

use AIArmada\Products\Events\VariantsGenerated;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Service for generating product variants from option combinations.
 */
final class VariantGeneratorService
{
    /**
     * Generate all possible variants for a product based on its options.
     *
     * @return Collection<int, Variant>
     */
    public function generate(Product $product): Collection
    {
        $options = $product->options()->with('values')->ordered()->get();

        $options->each(function (Option $option): void {
            $option->values->each(fn (OptionValue $value) => $value->setRelation('option', $option));
        });

        if ($options->isEmpty()) {
            return collect();
        }

        $maxCombinations = (int) config('products.features.variants.max_combinations', 1000);

        $combinationCount = $options
            ->map(fn (Option $option) => $option->values->count())
            ->reduce(fn (int $carry, int $count) => $carry * $count, 1);

        if ($combinationCount === 0) {
            return collect();
        }

        if ($combinationCount > $maxCombinations) {
            throw new RuntimeException(
                "Too many variant combinations ({$combinationCount}). " .
                "Maximum allowed is {$maxCombinations}."
            );
        }

        // Get all combinations
        $combinations = $this->generateCombinations($options);

        /** @var Collection<int, Variant> $variants */
        $variants = DB::transaction(function () use ($product, $combinations): Collection {
            // Delete existing variants
            $product->variants()->chunk(100, function (Collection $variants): void {
                $variants->each(fn (Variant $variant) => $variant->delete());
            });

            $variants = new EloquentCollection;
            $isFirst = true;

            foreach ($combinations as $combination) {
                $variant = $this->createVariant($product, $combination, $isFirst);
                $variants->push($variant);
                $isFirst = false;
            }

            return $variants;
        });

        VariantsGenerated::dispatch($product, $variants);

        return $variants;
    }

    /**
     * Add a new variant for a specific combination.
     *
     * @param  array<string>  $optionValueIds
     */
    public function addVariant(Product $product, array $optionValueIds): Variant
    {
        $optionValueIds = collect($optionValueIds)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($optionValueIds === []) {
            throw new RuntimeException('Option values are required.');
        }

        // Check if variant already exists
        $existingVariant = $this->findVariantByCombination($product, $optionValueIds);

        if ($existingVariant) {
            throw new RuntimeException('A variant with this combination already exists.');
        }

        $optionValues = OptionValue::query()
            ->whereIn('id', $optionValueIds)
            ->whereHas('option', fn ($query) => $query->where('product_id', $product->id))
            ->with('option')
            ->get();

        if ($optionValues->count() !== count($optionValueIds)) {
            throw new RuntimeException('One or more option values do not belong to this product.');
        }

        return $this->createVariant($product, $optionValues, ! $product->variants()->exists());
    }

    /**
     * Find a variant by its option value combination.
     *
     * @param  array<string>  $optionValueIds
     */
    public function findVariantByCombination(Product $product, array $optionValueIds): ?Variant
    {
        $count = count($optionValueIds);

        if ($count === 0) {
            return null;
        }

        $pivotTable = config('products.database.tables.variant_options', 'product_variant_options');

        return $product->variants()
            ->has('optionValues', '=', $count)
            ->whereHas('optionValues', function ($query) use ($optionValueIds, $pivotTable): void {
                $query->whereIn($pivotTable . '.option_value_id', $optionValueIds);
            }, '=', $count)
            ->first();
    }

    /**
     * Generate Cartesian product of all option values.
     *
     * @param  Collection<int, Option>  $options
     * @return Collection<int, Collection>
     */
    protected function generateCombinations(Collection $options): Collection
    {
        $result = collect([collect()]);

        foreach ($options as $option) {
            $newResult = collect();

            foreach ($result as $combination) {
                foreach ($option->values as $value) {
                    $newResult->push($combination->merge([$value]));
                }
            }

            $result = $newResult;
        }

        return $result;
    }

    /**
     * Create a single variant from a combination of option values.
     *
     * @param  Collection<int, OptionValue>  $optionValues
     */
    protected function createVariant(Product $product, Collection $optionValues, bool $isDefault): Variant
    {
        $variant = $product->variants()->create([
            // Temporary SKU to satisfy non-null and unique constraints before option values are attached.
            'sku' => 'TMP-' . Str::uuid()->toString(),
            'is_default' => $isDefault,
            'is_enabled' => true,
        ]);

        $variant->setRelation('product', $product);

        // Attach option values
        $variant->optionValues()->attach($optionValues->pluck('id'));

        $variant->forceFill([
            'sku' => $variant->generateSku(),
        ])->save();

        return $variant->refresh();
    }
}
