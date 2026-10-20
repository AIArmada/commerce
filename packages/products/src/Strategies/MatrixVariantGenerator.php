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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MatrixVariantGenerator implements VariantGeneratorInterface
{
    /**
     * The built-in pattern. Generation appends a deterministic suffix
     * derived from the canonical combination key so option codes that are
     * ambiguous across option boundaries cannot collapse two combinations
     * onto one sku, and bounds the complete sku to the variants sku column
     * capacity. Any other configured pattern is used verbatim; its
     * collisions and oversized output fail generation explicitly.
     */
    private const DEFAULT_SKU_PATTERN = '{parent_sku}-{option_codes}';

    /**
     * The variants sku column is declared as string() (varchar(255) on
     * PostgreSQL). Every generated sku must fit the schema capacity.
     */
    private const MAX_SKU_LENGTH = 255;

    /**
     * Room kept for the collision disambiguator appended by availableSku():
     * one separator plus the candidate loop's nominal 32-character hash
     * bound. Reserving the nominal bound keeps every collision sku inside
     * the column even when the longest candidate is reached.
     */
    private const COLLISION_SUFFIX_RESERVE = 33;

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
        $prepared = $this->prepareCombinations($product, $combinations);

        $variants = collect();

        // One transaction for the whole matrix: a collision in any
        // combination (including batches above 50 combinations) rolls back
        // every insert instead of committing earlier combinations.
        DB::transaction(function () use ($product, $prepared, $variants): void {
            foreach ($prepared as $combination) {
                $variants->push($this->createOrReuseVariant($product, $combination));
            }
        });

        event(new VariantsGenerated($product, $variants));

        return $variants;
    }

    /**
     * Canonicalise every combination before any write. Two distinct
     * combinations may never share a generated sku: the built-in pattern
     * carries the canonical combination identity, and a custom pattern
     * whose candidates collapse, exceed the sku column, or collide with an
     * existing record fails atomically before any write.
     *
     * @param  array<int, array<int, OptionValue>>  $combinations
     * @return list<array{values: array<int, OptionValue>, ids: list<string>, key: string, sku: string, default: bool}>
     */
    private function prepareCombinations(Product $product, array $combinations): array
    {
        $codes = $this->optionValueCodes($combinations);
        $usesDefaultPattern = $this->usesDefaultSkuPattern();
        $prepared = [];
        $skus = [];

        foreach ($combinations as $combination) {
            $optionValues = array_values($combination);
            $ids = $this->sortedValueIds($optionValues);
            $key = Variant::combinationKeyFor((string) $product->getKey(), $ids);
            $sku = $this->skuForCombination($product, $optionValues, $codes, $key, $usesDefaultPattern);

            if (! $usesDefaultPattern && mb_strlen($sku) > self::MAX_SKU_LENGTH) {
                throw new InvalidArgumentException(sprintf(
                    'Configured variant sku pattern [%s] generates sku [%s] with %d characters, exceeding the %d-character sku column. Adjust products.features.variants.sku_pattern so every combination produces a shorter sku.',
                    $this->skuPattern(),
                    $sku,
                    mb_strlen($sku),
                    self::MAX_SKU_LENGTH,
                ));
            }

            if (isset($skus[$sku]) && $skus[$sku] !== $key) {
                throw new InvalidArgumentException(sprintf(
                    'Configured variant sku pattern generates duplicate sku [%s] for distinct option combinations. Adjust products.features.variants.sku_pattern so every combination produces its own sku.',
                    $sku,
                ));
            }

            $skus[$sku] = $key;

            $prepared[] = [
                'values' => $optionValues,
                'ids' => $ids,
                'key' => $key,
                'sku' => $sku,
                'default' => $usesDefaultPattern,
            ];
        }

        if (! $usesDefaultPattern) {
            $this->assertCustomSkusAvailable($product, $prepared);
        }

        return $prepared;
    }

    /**
     * A custom pattern is used verbatim: the only record that may already
     * hold a candidate sku is one carrying the exact canonical membership
     * (a replay). Any other occupant, on this product or a sibling
     * product, fails generation before any write.
     *
     * @param  list<array{values: array<int, OptionValue>, ids: list<string>, key: string, sku: string, default: bool}>  $prepared
     */
    private function assertCustomSkusAvailable(Product $product, array $prepared): void
    {
        $bySku = [];

        foreach ($prepared as $combination) {
            $bySku[$combination['sku']] = $combination;
        }

        $occupied = Variant::query()
            ->forOwner($product->owner, false)
            ->whereIn('sku', array_keys($bySku))
            ->get();

        foreach ($occupied as $variant) {
            $combination = $bySku[(string) $variant->sku] ?? null;

            if (
                $combination !== null
                && (string) $variant->product_id === (string) $product->getKey()
                && $this->hasMembership($variant, $combination['ids'])
            ) {
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'Configured variant sku pattern [%s] generates sku [%s] that is already used by another variant record. Use a pattern that produces a unique sku per combination, or remove the conflicting record first.',
                $this->skuPattern(),
                (string) $variant->sku,
            ));
        }
    }

    /**
     * @param  array{values: array<int, OptionValue>, ids: list<string>, key: string, sku: string, default: bool}  $combination
     */
    private function createOrReuseVariant(Product $product, array $combination): Variant
    {
        $owner = $product->owner;

        $existing = Variant::query()
            ->forOwner($owner, false)
            ->where('product_id', $product->getKey())
            ->where('combination_key', $combination['key'])
            ->first();

        if ($existing instanceof Variant) {
            if ($this->hasMembership($existing, $combination['ids'])) {
                return $existing;
            }

            // Membership changed outside the generator (direct pivot edits):
            // repair the stale key, then give the freed combination its own
            // variant again instead of collapsing the two together.
            $this->repairCombinationKey($existing, $owner);
        }

        $adopted = $this->findByMembership($product, $owner, $combination['ids']);

        if ($adopted instanceof Variant) {
            $adopted->update(['combination_key' => $combination['key']]);

            return $adopted;
        }

        return $this->insertVariant($product, $combination);
    }

    /**
     * @param  array{values: array<int, OptionValue>, ids: list<string>, key: string, sku: string, default: bool}  $combination
     */
    private function insertVariant(Product $product, array $combination): Variant
    {
        $owner = $product->owner;

        try {
            // Nested (savepoint) transaction so a losing concurrent generator
            // can recover on databases where a failed statement aborts the
            // surrounding transaction, such as PostgreSQL.
            return DB::transaction(function () use ($product, $owner, $combination): Variant {
                $variant = Variant::create([
                    'product_id' => $product->id,
                    'name' => collect($combination['values'])
                        ->map(fn (OptionValue $value): string => (string) $value->name)
                        ->join(' / '),
                    'price' => $product->price,
                    'is_enabled' => true,
                    'sku' => $combination['default']
                        ? $this->availableSku($product, $owner, $combination['key'], $combination['sku'])
                        : $combination['sku'],
                    'combination_key' => $combination['key'],
                    'owner_type' => $product->owner_type,
                    'owner_id' => $product->owner_id,
                ]);

                $variant->optionValues()->sync($combination['ids']);

                return $variant;
            });
        } catch (UniqueConstraintViolationException | InvalidArgumentException $exception) {
            $winner = Variant::query()
                ->forOwner($owner, false)
                ->where('product_id', $product->getKey())
                ->where('combination_key', $combination['key'])
                ->first();

            if ($winner instanceof Variant && $this->hasMembership($winner, $combination['ids'])) {
                return $winner;
            }

            throw $exception;
        }
    }

    /**
     * Opinionated disambiguation for the built-in pattern only: when this
     * product already uses the candidate sku for a different row (manual
     * edits, legacy data), append a deterministic hash suffix. Custom
     * patterns never reach this path — their collisions fail generation.
     * A collision with a sibling product keeps the candidate so the
     * owner-level sku uniqueness guard fails loudly instead of silently
     * inventing skus.
     */
    private function availableSku(Product $product, ?Model $owner, string $combinationKey, string $sku): string
    {
        $candidate = $sku;

        for ($length = 6; $length <= 32; $length += 2) {
            $taken = Variant::query()
                ->forOwner($owner, false)
                ->where('product_id', $product->getKey())
                ->where('sku', $candidate)
                ->exists();

            if (! $taken) {
                return $candidate;
            }

            $candidate = $sku . '-' . mb_strtoupper(mb_substr(hash('sha256', $combinationKey), 0, $length));
        }

        throw new InvalidArgumentException(sprintf(
            'Unable to allocate a unique variant sku [%s]; resolve the conflicting sku records first.',
            $sku,
        ));
    }

    /**
     * Verify the persisted pivot membership equals the combination exactly.
     *
     * @param  list<string>  $ids  sorted value IDs
     */
    private function hasMembership(Variant $variant, array $ids): bool
    {
        $membership = $variant->optionValues()
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        sort($membership);

        return $membership === $ids;
    }

    /**
     * The stored key no longer describes the actual membership. Repair it to
     * the real combination, demoting the row to a legacy (keyless) variant
     * when the true key is already taken by a sibling.
     */
    private function repairCombinationKey(Variant $variant, ?Model $owner): void
    {
        $actualIds = $variant->optionValues()
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        sort($actualIds);

        $actualKey = Variant::combinationKeyFor((string) $variant->product_id, $actualIds);

        $alreadyKeyed = Variant::query()
            ->forOwner($owner, false)
            ->where('product_id', $variant->product_id)
            ->where('combination_key', $actualKey)
            ->whereKeyNot($variant->getKey())
            ->exists();

        $variant->update(['combination_key' => $alreadyKeyed ? null : $actualKey]);
    }

    /**
     * Adopt manually created variants that carry no stored key but whose
     * pivot membership already matches the combination exactly.
     *
     * @param  list<string>  $ids
     */
    private function findByMembership(Product $product, ?Model $owner, array $ids): ?Variant
    {
        /** @var Variant|null $variant */
        $variant = Variant::query()
            ->forOwner($owner, false)
            ->where('product_id', $product->getKey())
            ->whereNull('combination_key')
            ->get()
            ->first(fn (Variant $candidate): bool => $this->hasMembership($candidate, $ids));

        return $variant;
    }

    /**
     * @param  array<int, OptionValue>  $optionValues
     * @return list<string>
     */
    private function sortedValueIds(array $optionValues): array
    {
        $ids = array_map(static fn (OptionValue $value): string => (string) $value->getKey(), $optionValues);

        sort($ids);

        return $ids;
    }

    /**
     * Codes only need to be unique within their own option: the combination
     * tuple is unique whenever every option slot is, so different options may
     * reuse the same codes without collapsing combinations.
     *
     * @param  array<int, array<int, OptionValue>>  $combinations
     * @return array<string, string>
     */
    private function optionValueCodes(array $combinations): array
    {
        /** @var array<string, array<string, array{position: int, label: string}>> $byOption */
        $byOption = [];

        foreach ($combinations as $combination) {
            foreach ($combination as $value) {
                $byOption[(string) $value->option_id][(string) $value->getKey()] = [
                    'position' => (int) $value->position,
                    'label' => (string) $value->name,
                ];
            }
        }

        $codes = [];

        foreach ($byOption as $values) {
            uasort($values, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

            $codes += $this->uniqueCodes(array_map(static fn (array $value): string => $value['label'], $values));
        }

        return $codes;
    }

    /**
     * Assign each label a distinct code, starting from the historical
     * two-character prefix and extending only the colliding groups.
     *
     * @param  array<string, string>  $labels  value id => label, in precedence order
     * @return array<string, string>
     */
    private function uniqueCodes(array $labels): array
    {
        $remaining = [];

        foreach ($labels as $id => $label) {
            $upper = mb_strtoupper(mb_trim($label));

            $remaining[$id] = $upper !== '' ? $upper : 'X';
        }

        $codes = [];
        $length = 2;

        while ($remaining !== []) {
            $groups = [];

            foreach ($remaining as $id => $candidate) {
                $groups[mb_substr($candidate, 0, $length)][$id] = $candidate;
            }

            $extended = [];
            $identical = [];

            foreach ($groups as $prefix => $members) {
                if (count($members) === 1) {
                    $codes[(string) array_key_first($members)] = $prefix;

                    continue;
                }

                foreach ($members as $id => $candidate) {
                    if (mb_strlen($candidate) > $length) {
                        $extended[$id] = $candidate;

                        continue;
                    }

                    $identical[$prefix][] = $id;
                }
            }

            foreach ($identical as $prefix => $ids) {
                foreach ($ids as $index => $id) {
                    $codes[$id] = $index === 0 ? $prefix : $prefix . '-' . ($index + 1);
                }
            }

            $remaining = $extended;
            $length++;
        }

        return $codes;
    }

    /**
     * @param  array<int, OptionValue>  $optionValues
     * @param  array<string, string>  $codes  value id => code
     */
    private function skuForCombination(
        Product $product,
        array $optionValues,
        array $codes,
        string $combinationKey,
        bool $usesDefaultPattern,
    ): string {
        $optionCodes = collect($optionValues)
            ->sortBy(fn (OptionValue $value): int => (int) ($value->option?->position ?? 0))
            ->map(fn (OptionValue $value): string => $codes[(string) $value->getKey()]
                ?? mb_strtoupper(mb_substr((string) $value->name, 0, 2)))
            ->implode('-');

        $parentSku = $product->sku ?: 'PROD-' . mb_strtoupper(mb_substr((string) $product->id, -8));

        $sku = str_replace(['{parent_sku}', '{option_codes}'], [$parentSku, $optionCodes], $this->skuPattern());

        if ($usesDefaultPattern) {
            return $this->boundedDefaultSku($sku, $combinationKey);
        }

        return $sku;
    }

    /**
     * Bound the complete default-generated sku to the sku column capacity.
     * The identity suffix is preserved verbatim and the readable prefix is
     * shortened so the longest collision suffix still fits afterwards, which
     * keeps collision handling and regeneration within the same bound.
     */
    private function boundedDefaultSku(string $sku, string $combinationKey): string
    {
        $suffix = '-' . $this->combinationSuffix($combinationKey);
        $budget = self::MAX_SKU_LENGTH - self::COLLISION_SUFFIX_RESERVE - mb_strlen($suffix);

        if (mb_strlen($sku) > $budget) {
            $sku = mb_rtrim(mb_substr($sku, 0, $budget), '-');
        }

        return $sku . $suffix;
    }

    private function skuPattern(): string
    {
        $pattern = config('products.features.variants.sku_pattern', self::DEFAULT_SKU_PATTERN);

        return is_string($pattern) && $pattern !== '' ? $pattern : self::DEFAULT_SKU_PATTERN;
    }

    private function usesDefaultSkuPattern(): bool
    {
        return $this->skuPattern() === self::DEFAULT_SKU_PATTERN;
    }

    /**
     * Bounded, deterministic disambiguator derived from the canonical
     * combination key: identical membership always yields the same suffix,
     * so repeated and concurrent generation stay stable.
     */
    private function combinationSuffix(string $combinationKey): string
    {
        return mb_strtoupper(mb_substr(hash('sha256', $combinationKey), 0, 8));
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
