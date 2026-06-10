---
title: Usage
---

# Usage

## Canonical API: Actions

The packages provides action classes for common operations. Use these instead of direct model queries to ensure events are dispatched and business rules are enforced.

### CreateProduct

```php
use AIArmada\Products\Actions\CreateProduct;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;

$product = CreateProduct::run([
    'name' => 'Basic T-Shirt',
    'slug' => 'basic-t-shirt',
    'sku' => 'TSHIRT-001',
    'type' => ProductType::Simple,
    'status' => ProductStatus::Active,
    'price' => 2999,
]);
```

### UpdateProduct

```php
use AIArmada\Products\Actions\UpdateProduct;

$product = UpdateProduct::run($product, [
    'name' => 'Updated T-Shirt',
    'price' => 2499,
]);
```

### UpdateProductStatus

```php
use AIArmada\Products\Actions\UpdateProductStatus;
use AIArmada\Products\Enums\ProductStatus;

UpdateProductStatus::run($product, ProductStatus::Active);
```

### GenerateVariants

```php
use AIArmada\Products\Actions\GenerateVariants;

$variants = GenerateVariants::run($product);
// Returns Collection<int, Variant>
```

### ApplyAttributeChanges

```php
use AIArmada\Products\Actions\ApplyAttributeChanges;

ApplyAttributeChanges::run($product, [
    'material' => 'Cotton',
    'care_instructions' => 'Cold wash only',
]);

// Also supports variant-level application:
ApplyAttributeChanges::make()->forVariant($variant, [
    'color' => 'Red',
]);
```

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

## Fulfillment semantics vs capabilities

`ProductType` now describes the product's fulfillment/category semantics, while explicit booleans control runtime behavior.

- `type` answers **what it is**.
- `requires_shipping` answers **whether shipping is involved**.
- `supports_variants` answers **whether it can manage purchasable sub-items**.
- `tracks_inventory` answers **whether stock should be validated and consumed**.

This means:

- `Configurable` remains the type for physical configurable goods.
- `Digital` stays non-shipping by default.
- `Digital` can opt into variants and inventory when you need ticket-style or seat-style stock buckets.

```php
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;

$download = Product::query()->create([
    'name' => 'Digital Download',
    'type' => ProductType::Digital,
    'status' => ProductStatus::Active,
    'price' => 4900,
]);

$ticket = Product::query()->create([
    'name' => 'Workshop Ticket',
    'type' => ProductType::Digital,
    'status' => ProductStatus::Active,
    'price' => 9700,
    'requires_shipping' => false,
    'supports_variants' => true,
    'tracks_inventory' => true,
]);

$shirt = Product::query()->create([
    'name' => 'Configurable T-Shirt',
    'type' => ProductType::Configurable,
    'status' => ProductStatus::Active,
    'price' => 2999,
]);
```

In practice:

- the download remains unlimited by default,
- the ticket can have per-date or per-session variants with separate stock,
- the shirt stays the physical configurable product model.

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

Use this pattern for digital ticketing as well. A `Digital` parent product can keep `requires_shipping = false` while variants represent seats, sessions, or event dates and still participate in inventory-aware flows.

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
