<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Actions\GenerateVariants;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

function seedVariantCombination(Product $product, string $optionName = 'Color', array $values = ['Black', 'Blue'], int $position = 1): Option
{
    $option = Option::create(['product_id' => $product->id, 'name' => $optionName, 'position' => $position]);

    foreach ($values as $valuePosition => $name) {
        OptionValue::create([
            'option_id' => $option->id,
            'name' => $name,
            'position' => $valuePosition + 1,
        ]);
    }

    return $option;
}

/**
 * @return list<string>
 */
function combinationMembershipOf(Variant $variant): array
{
    $ids = $variant->optionValues()->pluck('id')->map(static fn ($id): string => (string) $id)->all();

    sort($ids);

    return $ids;
}

it('creates one distinct variant per option combination when default codes collide', function (): void {
    $product = Product::create(['name' => 'Black Blue Shirt', 'sku' => 'BB-SHIRT', 'price' => 1000]);
    $option = seedVariantCombination($product);

    $black = $option->values()->where('name', 'Black')->firstOrFail();
    $blue = $option->values()->where('name', 'Blue')->firstOrFail();

    $variants = app(GenerateVariants::class)->execute($product);

    $memberships = $variants
        ->map(fn (Variant $variant): string => implode(',', combinationMembershipOf($variant)))
        ->sort()
        ->values()
        ->all();

    $expected = [(string) $black->id, (string) $blue->id];
    sort($expected);

    expect($variants)->toHaveCount(2)
        ->and($variants->pluck('id')->unique()->count())->toBe(2)
        ->and($variants->pluck('sku')->unique()->count())->toBe(2)
        ->and($product->variants()->count())->toBe(2)
        ->and($memberships)->toBe($expected);

    foreach ($variants as $variant) {
        expect($variant->sku)->toStartWith('BB-SHIRT-BL');
    }
});

it('keeps default skus distinct for labels colliding on their first two characters', function (): void {
    $product = Product::create(['name' => 'Punctuation Shirt', 'sku' => 'PUNCT', 'price' => 1000]);
    seedVariantCombination($product, 'Finish', ['Red!', 'Red?', 'Red']);

    $variants = app(GenerateVariants::class)->execute($product);

    expect($variants)->toHaveCount(3)
        ->and($variants->pluck('id')->unique()->count())->toBe(3)
        ->and($variants->pluck('sku')->unique()->count())->toBe(3)
        ->and($product->variants()->count())->toBe(3);
});

it('distinguishes combinations when different options share the same value labels', function (): void {
    $product = Product::create(['name' => 'Twin Labels', 'sku' => 'TWIN', 'price' => 1000]);
    $color = seedVariantCombination($product, 'Color', ['Red', 'Blue'], 1);
    $size = seedVariantCombination($product, 'Size', ['Red', 'Blue'], 2);

    $variants = app(GenerateVariants::class)->execute($product);

    expect($variants)->toHaveCount(4)
        ->and($variants->pluck('id')->unique()->count())->toBe(4)
        ->and($variants->pluck('sku')->unique()->count())->toBe(4)
        ->and($product->variants()->count())->toBe(4);

    foreach ($variants as $variant) {
        $membership = combinationMembershipOf($variant);

        expect($membership)->toHaveCount(2)
            ->and($color->values()->whereIn('id', $membership)->exists())->toBeTrue()
            ->and($size->values()->whereIn('id', $membership)->exists())->toBeTrue();
    }
});

it('treats regeneration as idempotent for identical combinations', function (): void {
    $product = Product::create(['name' => 'Idempotent Shirt', 'sku' => 'IDEMPOTENT', 'price' => 1000]);
    seedVariantCombination($product);

    $first = app(GenerateVariants::class)->execute($product);
    $second = app(GenerateVariants::class)->execute($product->fresh());

    expect($second)->toHaveCount(2)
        ->and($second->pluck('id')->sort()->values()->all())->toBe($first->pluck('id')->sort()->values()->all())
        ->and($product->variants()->count())->toBe(2)
        ->and($second->pluck('sku')->unique()->count())->toBe(2);
});

it('keeps combination identity stable across option value renames', function (): void {
    $product = Product::create(['name' => 'Renamed Shirt', 'sku' => 'RENAMED', 'price' => 1000]);
    $option = seedVariantCombination($product);

    $first = app(GenerateVariants::class)->execute($product);
    $skus = $first->mapWithKeys(fn (Variant $variant): array => [(string) $variant->id => $variant->sku])->all();

    $black = $option->values()->where('name', 'Black')->firstOrFail();
    $black->update(['name' => 'Jet']);

    $second = app(GenerateVariants::class)->execute($product->fresh());

    expect($second->pluck('id')->sort()->values()->all())->toBe($first->pluck('id')->sort()->values()->all())
        ->and($product->variants()->count())->toBe(2)
        ->and($second->mapWithKeys(fn (Variant $variant): array => [(string) $variant->id => $variant->sku])->all())->toBe($skus);

    $renamed = $second->first(fn (Variant $variant): bool => in_array((string) $black->id, combinationMembershipOf($variant), true));

    expect($renamed)->not->toBeNull()
        ->and($renamed?->getOptionSummary())->toBe('Jet');
});

it('identifies combinations independently of option order', function (): void {
    $product = Product::create(['name' => 'Reordered Shirt', 'sku' => 'REORDERED', 'price' => 1000]);
    $color = seedVariantCombination($product, 'Color', ['Black', 'Blue'], 1);
    $size = seedVariantCombination($product, 'Size', ['Small', 'Large'], 2);

    $first = app(GenerateVariants::class)->execute($product);

    $size->update(['position' => 1]);
    $color->update(['position' => 5]);

    $second = app(GenerateVariants::class)->execute($product->fresh());

    expect($second)->toHaveCount(4)
        ->and($second->pluck('id')->sort()->values()->all())->toBe($first->pluck('id')->sort()->values()->all())
        ->and($product->variants()->count())->toBe(4);
});

it('repairs stale combination identity after a membership change instead of collapsing combinations', function (): void {
    $product = Product::create(['name' => 'Drifted Shirt', 'sku' => 'DRIFTED', 'price' => 1000]);
    $option = seedVariantCombination($product, 'Color', ['Black', 'Blue', 'Red']);

    $blue = $option->values()->where('name', 'Blue')->firstOrFail();
    $red = $option->values()->where('name', 'Red')->firstOrFail();

    $first = app(GenerateVariants::class)->execute($product);

    $blueVariant = $first->first(fn (Variant $variant): bool => combinationMembershipOf($variant) === [(string) $blue->id]);

    expect($blueVariant)->not->toBeNull();

    // Unsupported direct pivot edit: the Blue variant now represents a new
    // two-value combination, so its stored identity is stale.
    $blueVariant->optionValues()->sync([$blue->id, $red->id]);

    $second = app(GenerateVariants::class)->execute($product->fresh());

    expect($second)->toHaveCount(3)
        ->and($second->pluck('id')->unique()->count())->toBe(3)
        ->and($product->variants()->count())->toBe(4);

    $repaired = Variant::query()->whereKey($blueVariant->id)->firstOrFail();

    $expectedKey = Variant::combinationKeyFor((string) $product->id, [(string) $blue->id, (string) $red->id]);

    expect($repaired->combination_key)->toBe($expectedKey);

    $repairedMembership = [(string) $blue->id, (string) $red->id];
    sort($repairedMembership);

    expect(combinationMembershipOf($repaired))->toBe($repairedMembership);
});

it('fails clearly and atomically when a custom sku pattern collapses distinct combinations', function (): void {
    config()->set('products.features.variants.sku_pattern', '{parent_sku}');

    $product = Product::create(['name' => 'Custom Collision Shirt', 'sku' => 'CUSTOM-COLLIDE', 'price' => 1000]);
    seedVariantCombination($product);

    try {
        app(GenerateVariants::class)->execute($product);
        $thrown = null;
    } catch (InvalidArgumentException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->not->toBeNull()
        ->and($thrown?->getMessage())->toContain('sku')
        ->and($product->variants()->count())->toBe(0)
        ->and(Variant::query()->withoutOwnerScope()->where('product_id', $product->id)->count())->toBe(0)
        ->and(DB::table(config('products.database.tables.variant_options', 'product_variant_options'))->count())->toBe(0);
});

it('reuses a manually created variant whose membership already matches', function (): void {
    $product = Product::create(['name' => 'Manual Shirt', 'sku' => 'MANUAL', 'price' => 1000]);
    $option = seedVariantCombination($product);

    $black = $option->values()->where('name', 'Black')->firstOrFail();

    $manual = Variant::create(['product_id' => $product->id, 'name' => 'Manual Black', 'sku' => 'MANUAL-BLACK']);
    $manual->optionValues()->sync([$black->id]);

    $variants = app(GenerateVariants::class)->execute($product->fresh());

    expect($variants)->toHaveCount(2)
        ->and($variants->pluck('id')->all())->toContain((string) $manual->id)
        ->and($product->variants()->count())->toBe(2);

    $reused = $variants->first(fn (Variant $variant): bool => $variant->getKey() === $manual->getKey());

    expect($reused)->not->toBeNull()
        ->and(combinationMembershipOf($reused))->toBe([(string) $black->id]);
});

it('enforces the combination identity boundary and recovers a concurrent insert', function (): void {
    $product = Product::create(['name' => 'Concurrent Shirt', 'sku' => 'CONCURRENT', 'price' => 1000]);
    $option = seedVariantCombination($product, 'Color', ['Black']);

    $black = $option->values()->firstOrFail();
    $key = Variant::combinationKeyFor((string) $product->id, [(string) $black->id]);

    $winner = Variant::create([
        'product_id' => $product->id,
        'name' => 'Concurrent Winner',
        'sku' => 'CONCURRENT-1',
        'combination_key' => $key,
    ]);
    $winner->optionValues()->sync([$black->id]);

    // The unique combination key is the application identity boundary that
    // serializes concurrent generators.
    expect(fn () => Variant::create([
        'product_id' => $product->id,
        'name' => 'Concurrent Loser',
        'sku' => 'CONCURRENT-2',
        'combination_key' => $key,
    ]))->toThrow(UniqueConstraintViolationException::class);

    $variants = app(GenerateVariants::class)->execute($product->fresh());

    expect($variants)->toHaveCount(1)
        ->and($variants->first()?->getKey())->toBe($winner->getKey())
        ->and($product->variants()->count())->toBe(1);
});

it('keeps combination identity isolated per owner and product', function (): void {
    $ownerA = User::query()->create(['name' => 'Combination Owner A', 'email' => 'combination-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Combination Owner B', 'email' => 'combination-b@example.com', 'password' => 'secret']);

    $productA = OwnerContext::withOwner($ownerA, fn (): Product => Product::create(['name' => 'Owner A Shirt', 'sku' => 'OWNER-SHIRT', 'price' => 1000]));
    $productB = OwnerContext::withOwner($ownerB, fn (): Product => Product::create(['name' => 'Owner B Shirt', 'sku' => 'OWNER-SHIRT', 'price' => 1000]));

    OwnerContext::withOwner($ownerA, fn (): Option => seedVariantCombination($productA));
    OwnerContext::withOwner($ownerB, fn (): Option => seedVariantCombination($productB));

    $variantsA = OwnerContext::withOwner($ownerA, fn () => app(GenerateVariants::class)->execute($productA));
    $variantsB = OwnerContext::withOwner($ownerB, fn () => app(GenerateVariants::class)->execute($productB));

    expect($variantsA)->toHaveCount(2)
        ->and($variantsB)->toHaveCount(2)
        ->and($variantsA->pluck('id')->intersect($variantsB->pluck('id'))->all())->toBeEmpty()
        ->and($variantsA->pluck('sku')->unique()->count())->toBe(2)
        ->and($variantsB->pluck('sku')->unique()->count())->toBe(2)
        ->and(OwnerContext::withOwner($ownerA, fn (): int => Variant::query()->where('product_id', $productB->id)->count()))->toBe(0);
});
