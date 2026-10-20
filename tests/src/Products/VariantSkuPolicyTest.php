<?php

declare(strict_types=1);

use AIArmada\Products\Actions\GenerateVariants;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Facades\DB;

function seedSkuPolicyOptions(Product $product, string $optionName, array $values, int $position = 1): Option
{
    $option = Option::create(['product_id' => $product->id, 'name' => $optionName, 'position' => $position]);

    foreach ($values as $valuePosition => $name) {
        OptionValue::create(['option_id' => $option->id, 'name' => $name, 'position' => $valuePosition + 1]);
    }

    return $option;
}

/**
 * @return list<string>
 */
function skuPolicyMembership(Variant $variant): array
{
    $ids = $variant->optionValues()->pluck('id')->map(static fn ($id): string => (string) $id)->all();

    sort($ids);

    return $ids;
}

it('generates four distinct default skus across ambiguous option-code boundaries', function (): void {
    $product = Product::create(['name' => 'Boundary Shirt', 'sku' => 'BOUNDARY', 'price' => 1000]);

    $cut = seedSkuPolicyOptions($product, 'Cut', ['AB', 'AB-CD'], 1);
    $finish = seedSkuPolicyOptions($product, 'Finish', ['-C', 'C'], 2);

    $variants = app(GenerateVariants::class)->execute($product);

    expect($variants)->toHaveCount(4)
        ->and($variants->pluck('id')->unique()->count())->toBe(4)
        ->and($variants->pluck('sku')->unique()->count())->toBe(4)
        ->and($product->variants()->count())->toBe(4);

    $memberships = $variants
        ->map(fn (Variant $variant): string => implode(',', skuPolicyMembership($variant)))
        ->sort()
        ->values()
        ->all();

    $expected = [];

    foreach ($cut->values()->get() as $cutValue) {
        foreach ($finish->values()->get() as $finishValue) {
            $ids = [(string) $cutValue->getKey(), (string) $finishValue->getKey()];
            sort($ids);

            $expected[] = implode(',', $ids);
        }
    }

    sort($expected);

    expect($memberships)->toBe($expected);

    foreach ($variants as $variant) {
        expect($variant->sku)->toStartWith('BOUNDARY-');
    }

    $again = app(GenerateVariants::class)->execute($product->fresh());

    expect($again->pluck('id')->sort()->values()->all())->toBe($variants->pluck('id')->sort()->values()->all())
        ->and($again->pluck('sku')->sort()->values()->all())->toBe($variants->pluck('sku')->sort()->values()->all())
        ->and($product->variants()->count())->toBe(4);
});

it('keeps short and delimiter-suffixed default codes unambiguous', function (): void {
    $product = Product::create(['name' => 'Short Code Shirt', 'sku' => 'SHORT', 'price' => 1000]);

    seedSkuPolicyOptions($product, 'Code', ['A', 'A-'], 1);
    seedSkuPolicyOptions($product, 'Tail', ['-B', 'B'], 2);

    $variants = app(GenerateVariants::class)->execute($product);

    expect($variants)->toHaveCount(4)
        ->and($variants->pluck('sku')->unique()->count())->toBe(4)
        ->and($variants->pluck('id')->unique()->count())->toBe(4)
        ->and($product->variants()->count())->toBe(4);
});

it('recovers a concurrently generated default variant by combination identity', function (): void {
    $product = Product::create(['name' => 'Concurrent Default Shirt', 'sku' => 'CONC-DEFAULT', 'price' => 1000]);
    $option = seedSkuPolicyOptions($product, 'Color', ['Red']);
    $red = $option->values()->firstOrFail();

    $key = Variant::combinationKeyFor((string) $product->id, [(string) $red->id]);

    $winner = Variant::create([
        'product_id' => $product->id,
        'name' => 'Concurrent Default Winner',
        'sku' => 'CONC-DEFAULT-EXTERNAL',
        'combination_key' => $key,
    ]);
    $winner->optionValues()->sync([$red->id]);

    $variants = app(GenerateVariants::class)->execute($product->fresh());

    expect($variants)->toHaveCount(1)
        ->and($variants->first()?->getKey())->toBe($winner->getKey())
        ->and($product->variants()->count())->toBe(1);
});

it('keeps opinionated default disambiguation when a manual record occupies the generated sku', function (): void {
    $product = Product::create(['name' => 'Default Occupied Shirt', 'sku' => 'DEFAULT-OCC', 'price' => 1000]);
    seedSkuPolicyOptions($product, 'Color', ['Red']);

    $first = app(GenerateVariants::class)->execute($product);

    expect($first)->toHaveCount(1);

    $occupiedSku = (string) $first->first()->sku;

    // Turn the generated row into an unrelated manual record: no canonical
    // membership and no combination key. The generated candidate is now
    // taken by a different record on the same product.
    $occupant = $first->first();
    $occupant->update(['combination_key' => null]);
    $occupant->optionValues()->sync([]);

    $second = app(GenerateVariants::class)->execute($product->fresh());

    expect($second)->toHaveCount(1)
        ->and($second->first()?->getKey())->not->toBe($occupant->getKey())
        ->and($second->first()?->sku)->toStartWith($occupiedSku . '-')
        ->and($product->variants()->count())->toBe(2)
        ->and($product->variants()->pluck('sku')->unique()->count())->toBe(2);
});

it('fails explicitly when a custom sku pattern collides with a record on the same product', function (): void {
    config()->set('products.features.variants.sku_pattern', '{parent_sku}-{option_codes}-v');

    $product = Product::create(['name' => 'Custom Occupied Shirt', 'sku' => 'CUSTOM-OCCUPIED', 'price' => 1000]);
    seedSkuPolicyOptions($product, 'Color', ['B1', 'B2']);

    Variant::create([
        'product_id' => $product->id,
        'name' => 'Manual Occupant',
        'sku' => 'CUSTOM-OCCUPIED-B1-v',
    ]);

    try {
        app(GenerateVariants::class)->execute($product);
        $thrown = null;
    } catch (InvalidArgumentException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->not->toBeNull()
        ->and($thrown?->getMessage())->toContain('CUSTOM-OCCUPIED-B1-v')
        ->and($product->variants()->count())->toBe(1)
        ->and(Variant::query()->where('product_id', $product->id)->pluck('sku')->all())->toBe(['CUSTOM-OCCUPIED-B1-v'])
        ->and(DB::table(config('products.database.tables.variant_options', 'product_variant_options'))->count())->toBe(0);
});

it('fails atomically when a later batch combination collides with a sibling product sku', function (): void {
    config()->set('products.features.variants.queue_threshold', 200);
    config()->set('products.features.variants.sku_pattern', '{parent_sku}-{option_codes}-v');

    $product = Product::create(['name' => 'Chunked Shirt', 'sku' => 'CHUNK', 'price' => 1000]);

    seedSkuPolicyOptions($product, 'Cut', ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9'], 1);
    seedSkuPolicyOptions($product, 'Finish', ['B1', 'B2', 'B3', 'B4', 'B5', 'B6'], 2);

    // The final combination of the batch is (A9, B6) => CHUNK-A9-B6-v. An
    // unrelated record on a sibling product already holds that sku: the
    // whole 54-combination batch must fail before anything is written.
    $sibling = Product::create(['name' => 'Sibling Holder', 'sku' => 'HOLDER', 'price' => 1000]);

    Variant::create([
        'product_id' => $sibling->id,
        'name' => 'Sibling Occupant',
        'sku' => 'CHUNK-A9-B6-v',
    ]);

    try {
        app(GenerateVariants::class)->execute($product);
        $thrown = null;
    } catch (InvalidArgumentException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->not->toBeNull()
        ->and($product->variants()->count())->toBe(0)
        ->and(Variant::query()->where('product_id', $product->id)->count())->toBe(0)
        ->and(DB::table(config('products.database.tables.variant_options', 'product_variant_options'))->count())->toBe(0)
        ->and($sibling->variants()->count())->toBe(1);
});

it('reuses an existing record only for the same canonical membership', function (): void {
    config()->set('products.features.variants.sku_pattern', '{parent_sku}-FIXED');

    $product = Product::create(['name' => 'Replay Shirt', 'sku' => 'REPLAY', 'price' => 1000]);
    $option = seedSkuPolicyOptions($product, 'Color', ['Red']);
    $red = $option->values()->firstOrFail();

    $manual = Variant::create([
        'product_id' => $product->id,
        'name' => 'Manual Replay',
        'sku' => 'REPLAY-FIXED',
    ]);
    $manual->optionValues()->sync([$red->id]);

    $variants = app(GenerateVariants::class)->execute($product->fresh());

    expect($variants)->toHaveCount(1)
        ->and($variants->first()?->getKey())->toBe($manual->getKey())
        ->and($manual->fresh()?->combination_key)->toBe(
            Variant::combinationKeyFor((string) $product->id, [(string) $red->id]),
        )
        ->and($product->variants()->count())->toBe(1);
});

it('recovers a concurrently generated custom-pattern variant by combination identity', function (): void {
    config()->set('products.features.variants.sku_pattern', '{parent_sku}-FIXED');

    $product = Product::create(['name' => 'Concurrent Custom Shirt', 'sku' => 'CONC-CUSTOM', 'price' => 1000]);
    $option = seedSkuPolicyOptions($product, 'Color', ['Red']);
    $red = $option->values()->firstOrFail();

    $key = Variant::combinationKeyFor((string) $product->id, [(string) $red->id]);

    $winner = Variant::create([
        'product_id' => $product->id,
        'name' => 'Concurrent Custom Winner',
        'sku' => 'CONC-CUSTOM-FIXED',
        'combination_key' => $key,
    ]);
    $winner->optionValues()->sync([$red->id]);

    $variants = app(GenerateVariants::class)->execute($product->fresh());

    expect($variants)->toHaveCount(1)
        ->and($variants->first()?->getKey())->toBe($winner->getKey())
        ->and($product->variants()->count())->toBe(1);
});
