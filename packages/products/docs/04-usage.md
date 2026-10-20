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

$product = app(CreateProduct::class)->execute([
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

$product = app(UpdateProduct::class)->execute($product, [
    'name' => 'Updated T-Shirt',
    'price' => 2499,
]);
```

### UpdateProductStatus

```php
use AIArmada\Products\Actions\UpdateProductStatus;
use AIArmada\Products\Enums\ProductStatus;

app(UpdateProductStatus::class)->execute($product, ProductStatus::Active);
```

### GenerateVariants

```php
use AIArmada\Products\Actions\GenerateVariants;

$variants = app(GenerateVariants::class)->execute($product);
// Returns Collection<int, Variant>
```

Generation caps at `features.variants.max_generated` (default 200) and throws `VariantGenerationLimitExceeded` above it. Above `features.variants.queue_threshold` (default 50) the request dispatches `GenerateVariantsJob` and returns an empty collection; the job re-runs via `generateSynchronously()`. All inserts for one generation run inside a single `DB::transaction()`, so a collision in any combination — including matrices above 50 combinations — rolls the whole batch back instead of leaving partial variants.

Re-running generation is idempotent and identity-safe. Variants are matched by their combination key (product ID plus the sorted option-value IDs, built by `Variant::combinationKeyFor()`) instead of SKU text, so distinct option combinations are never merged into one variant. A matching key whose pivot membership also matches returns the existing variant; pivots edited outside the generator get their stale key repaired; a manually created variant without a key is adopted when its membership matches exactly. With the default `sku_pattern`, each generated SKU carries a bounded deterministic suffix derived from the combination key, so option codes that are ambiguous across option boundaries (`AB`/`AB-CD` against `-C`/`C`) still produce four distinct SKUs; a manual record occupying the exact generated SKU gets the opinionated hash disambiguation. The complete default-generated SKU is bounded to the 255-character `sku` column: the readable prefix is trimmed while the identity suffix is preserved and room is kept for the longest collision suffix, so even a maximum-length or multibyte parent SKU stays insertable. A custom `sku_pattern` is used verbatim: two distinct combinations that map to the same SKU, a candidate already held by a different record on the same or a sibling product, or a candidate that exceeds the 255-character sku column fails the whole batch before any write. Concurrent duplicate inserts resolve to the surviving row. Filament surfaces the cap as a danger notification (`VariantsRelationManager`).

### ApplyAttributeChanges

```php
use AIArmada\Products\Actions\ApplyAttributeChanges;

app(ApplyAttributeChanges::class)->execute($product, [
    'material' => 'Cotton',
    'care_instructions' => 'Cold wash only',
]);

// Also supports variant-level application:
app(ApplyAttributeChanges::class)->forVariant($variant, [
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

// `slug` is NOT NULL with no default and is not auto-generated — always pass it.
$download = Product::query()->create([
    'name' => 'Digital Download',
    'slug' => 'digital-download',
    'type' => ProductType::Digital,
    'status' => ProductStatus::Active,
    'price' => 4900,
]);

$ticket = Product::query()->create([
    'name' => 'Workshop Ticket',
    'slug' => 'workshop-ticket',
    'type' => ProductType::Digital,
    'status' => ProductStatus::Active,
    'price' => 9700,
    'requires_shipping' => false,
    'supports_variants' => true,
    'tracks_inventory' => true,
]);

$shirt = Product::query()->create([
    'name' => 'Configurable T-Shirt',
    'slug' => 'configurable-t-shirt',
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
    'name' => 'Small', // the option value IS the name; there is no `value` column
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

Options, option values, and variants share the product's owner tuple: an owned product accepts children from its own owner context and rejects a different explicit owner, while a global product requires explicit global context (`OwnerContext::withOwner(null, ...)`) and rejects tenant-owned children. `GenerateVariants` adopts these manual rows by exact option-value membership instead of inserting duplicates.

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
