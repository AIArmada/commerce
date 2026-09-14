<?php

declare(strict_types=1);

use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Facades\DB;

function variantPivotRowCount(): int
{
    return DB::table(config('products.database.tables.variant_options', 'product_variant_options'))->count();
}

it('removes options, values, variants, and pivots when a product is deleted', function (): void {
    $product = Product::create(['name' => 'Cascade Product', 'price' => 1000]);
    $option = Option::create(['product_id' => $product->id, 'name' => 'Color']);
    $red = OptionValue::create(['option_id' => $option->id, 'name' => 'Red']);
    $blue = OptionValue::create(['option_id' => $option->id, 'name' => 'Blue']);
    $variant = Variant::create(['product_id' => $product->id, 'name' => 'Red Variant', 'sku' => 'CASCADE-RED']);
    $variant->optionValues()->sync([$red->id, $blue->id]);

    expect(variantPivotRowCount())->toBe(2);

    $product->delete();

    expect(Variant::query()->withoutOwnerScope()->where('product_id', $product->id)->count())->toBe(0)
        ->and(Option::query()->withoutOwnerScope()->where('product_id', $product->id)->count())->toBe(0)
        ->and(OptionValue::query()->withoutOwnerScope()->where('option_id', $option->id)->count())->toBe(0)
        ->and(variantPivotRowCount())->toBe(0);
});

it('removes values and detaches pivots when an option is deleted', function (): void {
    $product = Product::create(['name' => 'Option Cascade Product', 'price' => 1000]);
    $option = Option::create(['product_id' => $product->id, 'name' => 'Size']);
    $value = OptionValue::create(['option_id' => $option->id, 'name' => 'Large']);
    $variant = Variant::create(['product_id' => $product->id, 'name' => 'Large Variant', 'sku' => 'CASCADE-LG']);
    $variant->optionValues()->sync([$value->id]);

    $option->delete();

    expect(OptionValue::query()->withoutOwnerScope()->where('option_id', $option->id)->count())->toBe(0)
        ->and(variantPivotRowCount())->toBe(0)
        ->and(Variant::query()->withoutOwnerScope()->whereKey($variant->id)->exists())->toBeTrue();
});
