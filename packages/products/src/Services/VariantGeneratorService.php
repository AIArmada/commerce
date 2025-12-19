<?php

declare(strict_types=1);

namespace AIArmada\Products\Services;

use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service for generating product variants from option combinations.
 */
class VariantGeneratorService
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

        // Get all combinations
        $combinations = $this->generateCombinations($options);

        // Check safety limit
        $maxCombinations = config('products.features.variants.max_combinations', 1000);
        if ($combinations->count() > $maxCombinations) {
            throw new RuntimeException(
                "Too many variant combinations ({$combinations->count()}). " .
                "Maximum allowed is {$maxCombinations}."
            );
        }

        return DB::transaction(function () use ($product, $combinations): Collection {
            // Delete existing variants
            $product->variants()->delete();

            $variants = collect();
            $isFirst = true;

            foreach ($combinations as $combination) {
                $variant = $this->createVariant($product, $combination, $isFirst);
                $variants->push($variant);
                $isFirst = false;
            }

            return $variants;
        });
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
            'sku' => $this->generateSku($product, $optionValues),
            'is_default' => $isDefault,
            'is_enabled' => true,
        ]);

        // Attach option values
        $variant->optionValues()->attach($optionValues->pluck('id'));

        return $variant;
    }

    /**
     * Generate SKU for a variant.
     *
     * @param  Collection<int, OptionValue>  $optionValues
     */
    protected function generateSku(Product $product, Collection $optionValues): string
    {
        $pattern = config('products.features.variants.sku_pattern', '{parent_sku}-{option_codes}');

        $optionCodes = $optionValues
            ->sortBy(fn ($val) => $val->option->position)
            ->map(function ($val): string {
                $cleaned = preg_replace('/[^a-zA-Z0-9]/', '', $val->name) ?? '';

                return mb_strtoupper(mb_substr($cleaned, 0, 3));
            })
            ->implode('-');

        $parentSku = $product->sku ?? 'PROD-' . mb_strtoupper(mb_substr((string) $product->id, 0, 8));

        return str_replace(
            ['{parent_sku}', '{option_codes}'],
            [$parentSku, $optionCodes],
            $pattern
        );
    }
}
