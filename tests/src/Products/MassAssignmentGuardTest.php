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

it('guards id from mass assignment', function (string $modelClass): void {
    $model = new $modelClass;
    $model->fill(['id' => 'forged-id']);
    expect($model->id)->toBeNull();
})->with([
    'product' => [Product::class],
    'variant' => [Variant::class],
    'option' => [Option::class],
    'option value' => [OptionValue::class],
    'attribute' => [Attribute::class],
    'attribute group' => [AttributeGroup::class],
    'attribute set' => [AttributeSet::class],
    'attribute value' => [AttributeValue::class],
    'category' => [Category::class],
    'collection' => [Collection::class],
]);
