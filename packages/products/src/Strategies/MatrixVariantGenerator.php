<?php

declare(strict_types=1);

namespace AIArmada\Products\Strategies;

use AIArmada\Products\Contracts\VariantGeneratorInterface;
use AIArmada\Products\Events\VariantsGenerated;
use AIArmada\Products\Exceptions\VariantGenerationLimitExceeded;
use AIArmada\Products\Jobs\GenerateVariantsJob;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MatrixVariantGenerator implements VariantGeneratorInterface
{
    /**
     * @return Collection<int, Variant>
     */
    public function generate(Product $product): Collection
    {
        $options = $product->options()->with('values.option')->get();

        if ($options->isEmpty()) {
            return collect();
        }

        $maximum = (int) config('products.features.variants.max_generated', 200);
        $combinationCount = $this->countCombinations($options);

        if ($combinationCount > $maximum) {
            throw new VariantGenerationLimitExceeded($combinationCount, $maximum);
        }

        $combinations = $this->buildCombinations($options);

        $threshold = (int) config('products.features.variants.queue_threshold', 50);

        if (count($combinations) > $threshold) {
            GenerateVariantsJob::dispatch(
                productId: $product->getKey(),
                ownerType: $product->owner_type,
                ownerId: $product->owner_id,
                ownerIsGlobal: $product->owner_type === null && $product->owner_id === null,
            );

            return collect();
        }

        return $this->generateCombinations($product, $combinations);
    }

    /**
     * The queued entry point deliberately bypasses the queue threshold.
     *
     * @return Collection<int, Variant>
     */
    public function generateSynchronously(Product $product): Collection
    {
        $options = $product->options()->with('values.option')->get();

        if ($options->isEmpty()) {
            return collect();
        }

        $maximum = (int) config('products.features.variants.max_generated', 200);
        $combinationCount = $this->countCombinations($options);

        if ($combinationCount > $maximum) {
            throw new VariantGenerationLimitExceeded($combinationCount, $maximum);
        }

        return $this->generateCombinations($product, $this->buildCombinations($options));
    }

    /**
     * @param  array<int, array<int, OptionValue>>  $combinations
     * @return Collection<int, Variant>
     */
    private function generateCombinations(Product $product, array $combinations): Collection
    {
        $variants = collect();

        foreach (array_chunk($combinations, 50) as $chunk) {
            DB::transaction(function () use ($product, $chunk, $variants): void {
                foreach ($chunk as $combination) {
                    $optionValues = collect($combination);
                    $sku = $this->skuForCombination($product, $optionValues);
                    $owner = $product->owner;

                    /** @var Variant|null $existing */
                    $existing = Variant::query()
                        ->forOwner($owner, false)
                        ->where('sku', $sku)
                        ->first();

                    if ($existing !== null) {
                        $variants->push($existing);

                        continue;
                    }

                    $variant = Variant::create([
                        'product_id' => $product->id,
                        'name' => $optionValues->map(fn (OptionValue $value): string => $value->name)->join(' / '),
                        'price' => $product->price,
                        'is_enabled' => true,
                        'sku' => $sku,
                        'owner_type' => $product->owner_type,
                        'owner_id' => $product->owner_id,
                    ]);

                    $variant->optionValues()->sync($optionValues->pluck('id')->all());
                    $variants->push($variant);
                }
            });
        }

        event(new VariantsGenerated($product, $variants));

        return $variants;
    }

    /**
     * @param  Collection<int, OptionValue>  $optionValues
     */
    private function skuForCombination(Product $product, Collection $optionValues): string
    {
        $pattern = config('products.features.variants.sku_pattern', '{parent_sku}-{option_codes}');
        $optionCodes = $optionValues
            ->sortBy(fn (OptionValue $value): int => (int) ($value->option?->position ?? 0))
            ->map(fn (OptionValue $value): string => mb_strtoupper(mb_substr($value->name, 0, 2)))
            ->implode('-');
        $parentSku = $product->sku ?: 'PROD-' . mb_strtoupper(mb_substr((string) $product->id, -8));

        return str_replace(['{parent_sku}', '{option_codes}'], [$parentSku, $optionCodes], $pattern);
    }

    /**
     * @param  Collection<int, Option>  $options
     * @return array<int, array<int, OptionValue>>
     */
    private function buildCombinations(Collection $options): array
    {
        $valueGroups = $options
            ->map(fn ($option) => $option->values)
            ->filter()
            ->values();

        if ($valueGroups->isEmpty()) {
            return [];
        }

        return array_reduce(
            $valueGroups->all(),
            function (?array $carry, $values): array {
                if ($carry === null) {
                    return array_map(fn ($v) => [$v], $values->all());
                }

                $result = [];

                foreach ($carry as $existing) {
                    foreach ($values as $value) {
                        $result[] = [...$existing, $value];
                    }
                }

                return $result;
            },
        ) ?? [];
    }

    /**
     * Count the matrix without materialising it so the configured cap also
     * protects the request that performs the validation.
     *
     * @param  Collection<int, Option>  $options
     */
    private function countCombinations(Collection $options): int
    {
        $count = 1;

        foreach ($options as $option) {
            $valueCount = $option->values->count();

            if ($valueCount === 0) {
                return 0;
            }

            $count *= $valueCount;
        }

        return $count;
    }
}
