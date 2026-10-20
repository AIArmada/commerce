<?php

declare(strict_types=1);

use AIArmada\Products\Actions\GenerateVariants;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use AIArmada\Products\Strategies\MatrixVariantGenerator;
use Illuminate\Support\Facades\DB;

/**
 * The variants sku column is declared as string() (varchar(255) on
 * PostgreSQL). The built-in pattern keeps the 8-character identity suffix
 * verbatim and reserves room for the longest collision suffix (separator
 * plus a 32-character hash, 33 characters) before trimming the readable
 * prefix, so a complete generated sku never exceeds 255 characters.
 */
const PRODUCT_SKU_COLUMN_LIMIT = 255;
const PRODUCT_SKU_READABLE_BUDGET = 213;
const PRODUCT_SKU_BOUNDED_LENGTH = 222;

function seedSkuLengthOptions(Product $product, string $optionName, array $values, int $position = 1): Option
{
    $option = Option::create(['product_id' => $product->id, 'name' => $optionName, 'position' => $position]);

    foreach ($values as $valuePosition => $name) {
        OptionValue::create(['option_id' => $option->id, 'name' => $name, 'position' => $valuePosition + 1]);
    }

    return $option;
}

/**
 * @param  list<string>  $valueIds
 */
function skuLengthIdentitySuffix(Product $product, array $valueIds): string
{
    $key = Variant::combinationKeyFor((string) $product->id, $valueIds);

    return '-' . mb_strtoupper(mb_substr(hash('sha256', $key), 0, 8));
}

function skuLengthCollisionHash(string $combinationKey): string
{
    return mb_strtoupper(hash('sha256', $combinationKey));
}

it('keeps a default sku that exactly fills the readable budget untrimmed', function (): void {
    $parentSku = str_repeat('A', 210);
    $product = Product::create(['name' => 'Exact Fit Shirt', 'sku' => $parentSku, 'price' => 1000]);
    $option = seedSkuLengthOptions($product, 'Color', ['Red']);
    $red = $option->values()->firstOrFail();

    $variants = app(GenerateVariants::class)->execute($product);
    $sku = (string) $variants->first()?->sku;

    expect($variants)->toHaveCount(1)
        ->and($sku)->toBe($parentSku . '-RE' . skuLengthIdentitySuffix($product, [(string) $red->id]))
        ->and(mb_strlen($sku))->toBe(PRODUCT_SKU_BOUNDED_LENGTH);
});

it('bounds the complete default sku when the readable prefix exceeds its budget', function (): void {
    // Pre-fix reproduction: a 244-character parent sku plus '-RE' plus the
    // 8-character identity suffix is 256 characters, one over the column.
    $parentSku = str_repeat('P', 244);
    $product = Product::create(['name' => 'Oversized Parent Shirt', 'sku' => $parentSku, 'price' => 1000]);
    $option = seedSkuLengthOptions($product, 'Color', ['Red']);
    $red = $option->values()->firstOrFail();

    $variants = app(GenerateVariants::class)->execute($product);
    $sku = (string) $variants->first()?->sku;

    expect($variants)->toHaveCount(1)
        ->and(mb_strlen($sku))->toBe(PRODUCT_SKU_BOUNDED_LENGTH)
        ->and($sku)->toBe(
            str_repeat('P', PRODUCT_SKU_READABLE_BUDGET) . skuLengthIdentitySuffix($product, [(string) $red->id]),
        )
        ->and(mb_strlen($sku))->toBeLessThanOrEqual(PRODUCT_SKU_COLUMN_LIMIT);
});

it('bounds skus generated from a maximum-length parent sku', function (): void {
    $parentSku = str_repeat('M', 255);
    $product = Product::create(['name' => 'Maximum Parent Shirt', 'sku' => $parentSku, 'price' => 1000]);
    seedSkuLengthOptions($product, 'Color', ['Red', 'Blue']);

    $variants = app(GenerateVariants::class)->execute($product);

    expect($variants)->toHaveCount(2)
        ->and($variants->pluck('sku')->unique()->count())->toBe(2)
        ->and($product->variants()->count())->toBe(2);

    foreach ($variants as $variant) {
        $sku = (string) $variant->sku;

        expect(mb_strlen($sku))->toBe(PRODUCT_SKU_BOUNDED_LENGTH)
            ->and(mb_strlen($sku))->toBeLessThanOrEqual(PRODUCT_SKU_COLUMN_LIMIT)
            ->and($sku)->toStartWith(str_repeat('M', PRODUCT_SKU_READABLE_BUDGET))
            ->and(preg_match('/-[0-9A-F]{8}$/', $sku))->toBe(1);
    }
});

it('bounds every sku across a multi-option matrix and regenerates them unchanged', function (): void {
    $parentSku = str_repeat('O', 250);
    $product = Product::create(['name' => 'Wide Matrix Shirt', 'sku' => $parentSku, 'price' => 1000]);

    seedSkuLengthOptions($product, 'Color', ['Red', 'Blue', 'Green'], 1);
    seedSkuLengthOptions($product, 'Size', ['Small', 'Large'], 2);

    $first = app(GenerateVariants::class)->execute($product);
    $second = app(GenerateVariants::class)->execute($product->fresh());

    expect($first)->toHaveCount(6)
        ->and($first->pluck('sku')->unique()->count())->toBe(6)
        ->and($second->pluck('sku')->sort()->values()->all())->toBe($first->pluck('sku')->sort()->values()->all())
        ->and($second->pluck('id')->sort()->values()->all())->toBe($first->pluck('id')->sort()->values()->all())
        ->and($product->variants()->count())->toBe(6);

    foreach ($first as $variant) {
        $sku = (string) $variant->sku;

        expect(mb_strlen($sku))->toBe(PRODUCT_SKU_BOUNDED_LENGTH)
            ->and(mb_strlen($sku))->toBeLessThanOrEqual(PRODUCT_SKU_COLUMN_LIMIT)
            ->and(preg_match('/-[0-9A-F]{8}$/', $sku))->toBe(1);
    }
});

it('bounds multibyte skus by characters, not bytes', function (): void {
    $parentSku = str_repeat("\u{00E9}", 244);
    $product = Product::create(['name' => 'Multibyte Parent Shirt', 'sku' => $parentSku, 'price' => 1000]);
    $option = seedSkuLengthOptions($product, 'Color', ['Red']);
    $red = $option->values()->firstOrFail();

    $variants = app(GenerateVariants::class)->execute($product);
    $sku = (string) $variants->first()?->sku;

    expect(mb_strlen($sku))->toBe(PRODUCT_SKU_BOUNDED_LENGTH)
        ->and(mb_strlen($sku))->toBeLessThanOrEqual(PRODUCT_SKU_COLUMN_LIMIT)
        // The '8bit' encoding returns the byte length: the bound counts
        // characters (as PostgreSQL does), not bytes.
        ->and(mb_strlen($sku, '8bit'))->toBeGreaterThan(PRODUCT_SKU_BOUNDED_LENGTH)
        ->and(mb_substr($sku, 0, PRODUCT_SKU_READABLE_BUDGET))
        ->toBe(str_repeat("\u{00E9}", PRODUCT_SKU_READABLE_BUDGET))
        ->and($sku)->toEndWith(skuLengthIdentitySuffix($product, [(string) $red->id]));
});

it('reserves room for the longest collision suffix at the boundary', function (): void {
    $parentSku = str_repeat('C', 244);
    $product = Product::create(['name' => 'Boundary Collision Shirt', 'sku' => $parentSku, 'price' => 1000]);
    $option = seedSkuLengthOptions($product, 'Color', ['Red']);
    $red = $option->values()->firstOrFail();

    $first = app(GenerateVariants::class)->execute($product);
    $occupant = $first->first();
    $base = (string) $occupant?->sku;

    expect($base)->toHaveLength(PRODUCT_SKU_BOUNDED_LENGTH);

    // Turn the generated row into an unrelated manual record and occupy
    // every shorter collision candidate so the longest hash tail is used.
    $occupant->update(['combination_key' => null]);
    $occupant->optionValues()->sync([]);

    $combinationKey = Variant::combinationKeyFor((string) $product->id, [(string) $red->id]);
    $hash = skuLengthCollisionHash($combinationKey);

    for ($length = 6; $length <= 28; $length += 2) {
        Variant::create([
            'product_id' => $product->id,
            'name' => 'Collision Occupant ' . $length,
            'sku' => $base . '-' . mb_substr($hash, 0, $length),
        ]);
    }

    $second = app(GenerateVariants::class)->execute($product->fresh());
    $sku = (string) $second->first()?->sku;

    // availableSku() checks each candidate before extending the hash one
    // step further, so the last reachable candidate carries the 30-character
    // tail; the reserved budget still keeps the complete sku under 255.
    expect($second)->toHaveCount(1)
        ->and($sku)->toBe($base . '-' . mb_substr($hash, 0, 30))
        ->and(mb_strlen($sku))->toBe(PRODUCT_SKU_BOUNDED_LENGTH + 31)
        ->and(mb_strlen($sku))->toBeLessThanOrEqual(PRODUCT_SKU_COLUMN_LIMIT)
        ->and($product->variants()->count())->toBe(14);
});

it('bounds default skus generated through the queued entry point', function (): void {
    $parentSku = str_repeat('Q', 244);
    $product = Product::create(['name' => 'Queued Matrix Shirt', 'sku' => $parentSku, 'price' => 1000]);

    seedSkuLengthOptions($product, 'Cut', ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9'], 1);
    seedSkuLengthOptions($product, 'Finish', ['B1', 'B2', 'B3', 'B4', 'B5', 'B6'], 2);

    $variants = app(MatrixVariantGenerator::class)->generateSynchronously($product);

    expect($variants)->toHaveCount(54)
        ->and($variants->pluck('sku')->unique()->count())->toBe(54)
        ->and($product->variants()->count())->toBe(54);

    foreach ($variants as $variant) {
        $sku = (string) $variant->sku;

        expect(mb_strlen($sku))->toBe(PRODUCT_SKU_BOUNDED_LENGTH)
            ->and(mb_strlen($sku))->toBeLessThanOrEqual(PRODUCT_SKU_COLUMN_LIMIT)
            ->and(preg_match('/-[0-9A-F]{8}$/', $sku))->toBe(1);
    }
});

it('accepts a custom sku pattern whose output exactly fills the column capacity', function (): void {
    config()->set('products.features.variants.sku_pattern', '{parent_sku}-{option_codes}-TAIL');

    $parentSku = str_repeat('Y', 247);
    $product = Product::create(['name' => 'Exact Custom Shirt', 'sku' => $parentSku, 'price' => 1000]);
    seedSkuLengthOptions($product, 'Code', ['B1']);

    $variants = app(GenerateVariants::class)->execute($product);
    $sku = (string) $variants->first()?->sku;

    expect($variants)->toHaveCount(1)
        ->and($sku)->toBe($parentSku . '-B1-TAIL')
        ->and(mb_strlen($sku))->toBe(PRODUCT_SKU_COLUMN_LIMIT);
});

it('rejects an oversized custom pattern before any batch write above fifty combinations', function (): void {
    config()->set('products.features.variants.queue_threshold', 200);
    config()->set('products.features.variants.sku_pattern', '{parent_sku}-{option_codes}-TAIL');

    $product = Product::create(['name' => 'Oversized Custom Shirt', 'sku' => str_repeat('X', 250), 'price' => 1000]);

    seedSkuLengthOptions($product, 'Cut', ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9'], 1);
    seedSkuLengthOptions($product, 'Finish', ['B1', 'B2', 'B3', 'B4', 'B5', 'B6'], 2);

    try {
        app(GenerateVariants::class)->execute($product);
        $thrown = null;
    } catch (InvalidArgumentException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->not->toBeNull()
        ->and($thrown?->getMessage())->toContain('sku pattern')
        ->and($thrown?->getMessage())->toContain((string) PRODUCT_SKU_COLUMN_LIMIT)
        ->and($product->variants()->count())->toBe(0)
        ->and(Variant::query()->withoutOwnerScope()->where('product_id', $product->id)->count())->toBe(0)
        ->and(DB::table(config('products.database.tables.variant_options', 'product_variant_options'))->count())->toBe(0);
});

it('rejects an oversized custom pattern through the queued entry point without partial writes', function (): void {
    config()->set('products.features.variants.sku_pattern', '{parent_sku}-{option_codes}-TAIL');

    $product = Product::create(['name' => 'Queued Oversized Custom Shirt', 'sku' => str_repeat('Z', 250), 'price' => 1000]);

    seedSkuLengthOptions($product, 'Cut', ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9'], 1);
    seedSkuLengthOptions($product, 'Finish', ['B1', 'B2', 'B3', 'B4', 'B5', 'B6'], 2);

    try {
        app(MatrixVariantGenerator::class)->generateSynchronously($product);
        $thrown = null;
    } catch (InvalidArgumentException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->not->toBeNull()
        ->and($product->variants()->count())->toBe(0)
        ->and(Variant::query()->withoutOwnerScope()->where('product_id', $product->id)->count())->toBe(0)
        ->and(DB::table(config('products.database.tables.variant_options', 'product_variant_options'))->count())->toBe(0);
});
