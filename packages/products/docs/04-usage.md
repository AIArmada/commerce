---
title: Usage
---

# Usage

## Create a product in owner context

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;

$product = OwnerContext::withOwner($team, function () {
    return Product::query()->create([
        'name' => 'Basic T-Shirt',
        'slug' => 'basic-t-shirt',
        'sku' => 'TSHIRT-001',
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
        'price' => 2999,
    ]);
});
```

## Create an intentional global record

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Category;

$category = OwnerContext::withOwner(null, function () {
    return Category::query()->create([
        'name' => 'Global Category',
        'slug' => 'global-category',
    ]);
});
```

## Create variants and generate SKUs

```php
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Variant;

$size = Option::query()->create([
    'product_id' => $product->id,
    'name' => 'Size',
    'position' => 1,
]);

$small = OptionValue::query()->create([
    'option_id' => $size->id,
    'name' => 'Small',
    'value' => 'S',
    'position' => 1,
]);

$variant = Variant::query()->create([
    'product_id' => $product->id,
    'price' => 3299,
]);

$variant->optionValues()->sync([$small->id]);
$variant->sku = $variant->generateSku();
$variant->save();
```

## Work with categories and collections

```php
use AIArmada\Products\Models\Collection;

$product->categories()->sync([$category->id]);

$collection = Collection::query()->create([
    'name' => 'Featured Products',
    'slug' => 'featured-products',
    'type' => 'automatic',
    'conditions' => [
        ['field' => 'is_featured', 'value' => true],
    ],
]);

$matches = $collection->getMatchingProducts();
$collection->rebuildProductList();
```

## Use custom attributes

```php
$product->setCustomAttribute('material', 'Cotton');
$product->setCustomAttribute('care_instructions', 'Cold wash only');

$material = $product->getCustomAttribute('material');
$attributes = $product->getCustomAttributesArray();
```

## Media helpers

```php
$product->addMedia($imagePath)->toMediaCollection('gallery');
$product->addMedia($heroPath)->toMediaCollection('hero');

$gallery = $product->getMedia('gallery');
$heroUrl = $product->getFirstMediaUrl('hero', 'detail');
```

## Money and status helpers

```php
$product->getFormattedPrice();
$product->getFormattedComparePrice();
$product->getFormattedCost();

$product->isActive();
$product->isDraft();
$product->isVisible();
$product->isPurchasable();
$product->isOnSale();
$product->getDiscountPercentage();
```

## Query with owner semantics

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;

$ownerProducts = Product::query()->forOwner($team)->get();
$ownerAndGlobal = Product::query()->forOwner($team, includeGlobal: true)->get();
$globalOnly = Product::query()->globalOnly()->get();

$explicitGlobalRead = OwnerContext::withOwner(null, function () {
    return Product::query()->forOwner()->get();
});
```

Avoid using `withoutOwnerScope()` unless you are intentionally performing a documented system-level operation.
