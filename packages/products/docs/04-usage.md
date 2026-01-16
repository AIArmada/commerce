---
title: Usage
---

# Usage

## Creating Products

### Simple Product

```php
use AIArmada\Products\Models\Product;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductStatus;

$product = Product::create([
    'name' => 'Basic T-Shirt',
    'sku' => 'TSHIRT-001',
    'type' => ProductType::Simple,
    'status' => ProductStatus::Active,
    'price' => 2999,          // Price in cents (RM 29.99)
    'compare_price' => 3999,  // Original price in cents
    'cost' => 1500,           // Cost in cents
    'short_description' => 'Comfortable cotton t-shirt',
    'description' => 'Full product description...',
    'is_featured' => true,
]);
```

### Configurable Product with Variants

```php
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Services\VariantGeneratorService;

// Create the product
$product = Product::create([
    'name' => 'Premium T-Shirt',
    'sku' => 'TSHIRT-PRO',
    'type' => ProductType::Configurable,
    'status' => ProductStatus::Active,
    'price' => 4999,
]);

// Create options
$sizeOption = Option::create([
    'name' => 'Size',
    'position' => 1,
]);

$colorOption = Option::create([
    'name' => 'Color',
    'position' => 2,
]);

// Create option values
$small = OptionValue::create(['option_id' => $sizeOption->id, 'value' => 'S']);
$medium = OptionValue::create(['option_id' => $sizeOption->id, 'value' => 'M']);
$large = OptionValue::create(['option_id' => $sizeOption->id, 'value' => 'L']);

$red = OptionValue::create(['option_id' => $colorOption->id, 'value' => 'Red']);
$blue = OptionValue::create(['option_id' => $colorOption->id, 'value' => 'Blue']);

// Attach options to product
$product->options()->attach([$sizeOption->id, $colorOption->id]);

// Generate all variant combinations
$generator = app(VariantGeneratorService::class);
$variants = $generator->generate($product);
// Creates: S-Red, S-Blue, M-Red, M-Blue, L-Red, L-Blue (6 variants)
```

## Working with Variants

### Update Variant Pricing

```php
$variant = $product->variants()->where('sku', 'TSHIRT-PRO-L-RED')->first();

$variant->update([
    'price' => 5499,     // Override base price
    'cost' => 2000,
    'weight' => 250,     // grams
]);
```

### Get Effective Price

```php
// Returns variant price if set, otherwise product price
$effectivePrice = $variant->getEffectivePrice();
```

## Categories

### Create Category Hierarchy

```php
use AIArmada\Products\Models\Category;

$clothing = Category::create([
    'name' => 'Clothing',
    'description' => 'All clothing items',
]);

$shirts = Category::create([
    'name' => 'Shirts',
    'parent_id' => $clothing->id,
]);

$tshirts = Category::create([
    'name' => 'T-Shirts',
    'parent_id' => $shirts->id,
]);
```

### Assign Products to Categories

```php
$product->categories()->sync([$tshirts->id, $shirts->id]);

// Or attach
$product->categories()->attach($tshirts->id);
```

### Query Products by Category

```php
// Products in specific category
$products = Product::whereHas('categories', function ($query) use ($categoryId) {
    $query->where('id', $categoryId);
})->get();

// Via category
$products = $category->products;
```

## Collections

### Manual Collection

```php
use AIArmada\Products\Models\Collection;

$collection = Collection::create([
    'name' => 'Summer Sale',
    'type' => 'manual',
    'is_active' => true,
]);

$collection->products()->attach([$product1->id, $product2->id]);
```

### Automatic Collection (Rule-based)

```php
$collection = Collection::create([
    'name' => 'Featured Products',
    'type' => 'automatic',
    'conditions' => [
        ['field' => 'is_featured', 'operator' => '=', 'value' => true],
    ],
    'is_active' => true,
]);

// Apply conditions to get products
$products = $collection->applyConditions()->get();
```

## Custom Attributes (EAV)

### Create Attribute Structure

```php
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Enums\AttributeType;

// Create attribute group
$specsGroup = AttributeGroup::create([
    'name' => 'Specifications',
    'position' => 1,
]);

// Create attributes
$material = Attribute::create([
    'code' => 'material',
    'name' => 'Material',
    'type' => AttributeType::Text,
    'is_required' => false,
    'is_filterable' => true,
]);

$weight = Attribute::create([
    'code' => 'fabric_weight',
    'name' => 'Fabric Weight (gsm)',
    'type' => AttributeType::Number,
    'validation_rules' => ['min' => 50, 'max' => 500],
]);

// Assign to group
$specsGroup->groupAttributes()->attach([$material->id, $weight->id]);

// Create attribute set
$apparelSet = AttributeSet::create([
    'name' => 'Apparel',
]);
$apparelSet->groups()->attach($specsGroup->id);
```

### Use Attributes on Products

```php
// Using HasAttributes trait
$product->setAttributeValue('material', 'Cotton');
$product->setAttributeValue('fabric_weight', 180);

// Get value
$material = $product->getAttributeValue('material'); // 'Cotton'

// Get all attributes
$attributes = $product->getAttributes();
```

## Media Management

### Add Images

```php
// Add to gallery collection
$product->addMedia($pathToImage)->toMediaCollection('gallery');

// Add hero image
$product->addMedia($heroImage)->toMediaCollection('hero');

// Get gallery images
$gallery = $product->getMedia('gallery');

// Get first hero with conversion
$heroUrl = $product->getFirstMediaUrl('hero', 'large');
```

### Available Collections

- `gallery` - Product image gallery
- `hero` - Featured/hero images
- `icon` - Icons and thumbnails
- `banner` - Banner images
- `videos` - Product videos
- `documents` - Downloadable files

## Money Helpers

```php
// Get formatted price
$product->getFormattedPrice();         // "RM 29.99"
$product->getFormattedComparePrice();  // "RM 39.99"
$product->getFormattedCost();          // "RM 15.00"

// Check if on sale
$product->isOnSale();  // true if compare_price > price

// Get discount percentage
$product->getDiscountPercentage();  // 25 (for 25% off)
```

## Status Helpers

```php
$product->isDraft();     // true if status === Draft
$product->isActive();    // true if status === Active
$product->isArchived();  // true if status === Archived
$product->isVisible();   // true if visibility !== Hidden
$product->isFeatured();  // true if is_featured === true
```

## Owner Scoping

All queries are automatically scoped to the current owner:

```php
// Automatically scoped
$products = Product::all();

// Include global products
$products = Product::forOwner()->get();

// Only global products
$products = Product::globalOnly()->get();

// Bypass scoping (use carefully!)
$products = Product::withoutOwnerScope()->get();
```
