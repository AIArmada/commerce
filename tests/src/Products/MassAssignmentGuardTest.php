<?php

declare(strict_types=1);

use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\AttributeValue;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;

it('guards id from mass assignment on product', function (): void {
    $product = new Product;
    $product->fill(['id' => 'forged-id']);
    expect($product->id)->toBeNull();
});

it('guards id from mass assignment on variant', function (): void {
    $variant = new Variant;
    $variant->fill(['id' => 'forged-id']);
    expect($variant->id)->toBeNull();
});

it('guards id from mass assignment on option', function (): void {
    $option = new Option;
    $option->fill(['id' => 'forged-id']);
    expect($option->id)->toBeNull();
});

it('guards id from mass assignment on option value', function (): void {
    $value = new OptionValue;
    $value->fill(['id' => 'forged-id']);
    expect($value->id)->toBeNull();
});

it('guards id from mass assignment on attribute', function (): void {
    $attr = new Attribute;
    $attr->fill(['id' => 'forged-id']);
    expect($attr->id)->toBeNull();
});

it('guards id from mass assignment on attribute group', function (): void {
    $group = new AttributeGroup;
    $group->fill(['id' => 'forged-id']);
    expect($group->id)->toBeNull();
});

it('guards id from mass assignment on attribute set', function (): void {
    $set = new AttributeSet;
    $set->fill(['id' => 'forged-id']);
    expect($set->id)->toBeNull();
});

it('guards id from mass assignment on attribute value', function (): void {
    $value = new AttributeValue;
    $value->fill(['id' => 'forged-id']);
    expect($value->id)->toBeNull();
});

it('guards id from mass assignment on category', function (): void {
    $cat = new Category;
    $cat->fill(['id' => 'forged-id']);
    expect($cat->id)->toBeNull();
});

it('guards id from mass assignment on collection', function (): void {
    $col = new Collection;
    $col->fill(['id' => 'forged-id']);
    expect($col->id)->toBeNull();
});
