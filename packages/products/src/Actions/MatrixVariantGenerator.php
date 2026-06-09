<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Contracts\VariantGeneratorInterface;
use AIArmada\Products\Events\VariantsGenerated;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class MatrixVariantGenerator implements VariantGeneratorInterface
{
    /**
     * @return Collection<int, Variant>
     */
    public function generate(Product $product): Collection
    {
        $options = $product->options()->with('values')->get();

        if ($options->isEmpty()) {
            return collect();
        }

        $combinations = $this->buildCombinations($options);
        $variants = collect();

        foreach ($combinations as $combination) {
            $optionValues = collect($combination);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => $optionValues->map(fn ($ov) => $ov->name)->join(' / '),
                'price' => $product->price,
                'is_enabled' => true,
                'sku' => Str::orderedUuid()->toString(),
                'owner_type' => $product->owner_type,
                'owner_id' => $product->owner_id,
            ]);

            $variant->optionValues()->attach($optionValues->pluck('id'));

            $variant->sku = $variant->generateSku();
            $variant->save();

            $variants->push($variant);
        }

        event(new VariantsGenerated($product, $variants));

        return $variants;
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
}
