# Product Architecture

> **Document:** 02 of 08  
> **Package:** `aiarmada/products`  
> **Status:** Vision

---

## Product Type Taxonomy

The Products package supports multiple product types, each with distinct behaviors and capabilities.

```
┌─────────────────────────────────────────────────────────────────┐
│                     PRODUCT TYPE HIERARCHY                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌───────────────────────────────────────────────────────────┐ │
│   │                    BASE PRODUCT                            │ │
│   │  - name, slug, description, status, visibility             │ │
│   │  - implements: BuyableInterface, HasMedia                  │ │
│   └───────────────────────────────────────────────────────────┘ │
│                               │                                  │
│       ┌───────────────────────┼───────────────────────┐         │
│       │           │           │           │           │         │
│       ▼           ▼           ▼           ▼           ▼         │
│   ┌───────┐   ┌───────┐   ┌───────┐   ┌───────┐   ┌───────┐    │
│   │Simple │   │Config │   │Bundle │   │Digital│   │Subscr │    │
│   │       │   │urable │   │       │   │       │   │iption │    │
│   └───────┘   └───────┘   └───────┘   └───────┘   └───────┘    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Product Types

### 1. Simple Product
A single purchasable item with one SKU.

```php
$product = Product::create([
    'name' => 'Basic T-Shirt',
    'type' => ProductType::Simple,
    'sku' => 'TSH-001',
    'price' => 2500, // cents
]);
```

### 2. Configurable Product
A product with multiple variants based on options (size, color).

```php
$product = Product::create([
    'name' => 'Premium T-Shirt',
    'type' => ProductType::Configurable,
]);

// Options are attached
$product->options()->attach([$sizeOption, $colorOption]);

// Variants are auto-generated or manually created
// TSH-001-S-RED, TSH-001-M-RED, TSH-001-L-RED...
```

### 3. Bundle Product
A collection of products sold together at a bundle price.

```php
$bundle = Product::create([
    'name' => 'Complete Outfit',
    'type' => ProductType::Bundle,
    'bundle_price_mode' => BundlePriceMode::Fixed, // or Calculated
]);

$bundle->bundleItems()->createMany([
    ['product_id' => $shirt->id, 'quantity' => 1],
    ['product_id' => $pants->id, 'quantity' => 1],
    ['product_id' => $belt->id, 'quantity' => 1],
]);
```

### 4. Digital Product
Downloadable products with file assets.

```php
$ebook = Product::create([
    'name' => 'Laravel Mastery eBook',
    'type' => ProductType::Digital,
    'download_limit' => 5,
    'download_expiry_days' => 30,
]);

$ebook->addMedia($pdfPath)->toMediaCollection('downloads');
```

### 5. Subscription Product
Products that integrate with Cashier for recurring billing.

```php
$subscription = Product::create([
    'name' => 'Pro Plan',
    'type' => ProductType::Subscription,
    'billing_interval' => 'month',
    'stripe_price_id' => 'price_xxx',
    'chip_price_id' => 'chip_price_xxx',
]);
```

---

## Core Models

### Product Model

```php
namespace AIArmada\Products\Models;

class Product extends Model implements BuyableInterface, HasMedia
{
    // Relationships
    public function variants(): HasMany;
    public function options(): BelongsToMany;
    public function categories(): BelongsToMany;
    public function collections(): BelongsToMany;
    public function attributes(): HasMany;
    public function bundleItems(): HasMany;
    
    // BuyableInterface
    public function getBuyableIdentifier(): string;
    public function getBuyableName(): string;
    public function getBuyablePrice(): Money;
    public function canBePurchased(?int $quantity = null): bool;
    public function getBuyableStock(): ?int;
    
    // Type Helpers
    public function isSimple(): bool;
    public function isConfigurable(): bool;
    public function isBundle(): bool;
    public function isDigital(): bool;
    public function isSubscription(): bool;
    
    // Scopes
    public function scopeActive(Builder $query): Builder;
    public function scopeVisible(Builder $query): Builder;
    public function scopeOfType(Builder $query, ProductType $type): Builder;
}
```

### Variant Model

```php
namespace AIArmada\Products\Models;

class Variant extends Model implements BuyableInterface, InventoryableInterface
{
    // Relationships
    public function product(): BelongsTo;
    public function optionValues(): BelongsToMany;
    public function inventoryLevels(): MorphMany; // From Inventory
    
    // Unique per product
    protected $uniqueWith = ['product_id', 'sku'];
    
    // Auto-generated from option combination
    public function generateSku(): string;
    
    // Price can differ from parent
    public function getEffectivePrice(): Money;
}
```

---

## Product State Machine

```
┌────────────────────────────────────────────────────────────────┐
│                    PRODUCT LIFECYCLE STATES                     │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│   ┌─────────┐     ┌──────────┐     ┌───────────┐               │
│   │  DRAFT  │────►│  ACTIVE  │────►│ ARCHIVED  │               │
│   └────┬────┘     └────┬─────┘     └───────────┘               │
│        │               │                                        │
│        │               ▼                                        │
│        │          ┌──────────┐                                  │
│        └─────────►│ DISABLED │                                  │
│                   └──────────┘                                  │
│                                                                 │
└────────────────────────────────────────────────────────────────┘

States:
- DRAFT: Not yet published, invisible to storefront
- ACTIVE: Published and purchasable
- DISABLED: Temporarily hidden (e.g., out of stock handling)
- ARCHIVED: Permanently retired, kept for order history
```

---

## Visibility Control

Products have independent visibility settings:

| Setting | Description |
|---------|-------------|
| `visible_in_catalog` | Shows in category listings |
| `visible_in_search` | Indexed for search |
| `visible_individually` | Accessible by direct URL |
| `visible_in_recommendations` | Included in AI recommendations |

---

## Events

| Event | Triggered When |
|-------|----------------|
| `ProductCreated` | New product saved |
| `ProductUpdated` | Product details changed |
| `ProductDeleted` | Product removed |
| `ProductPublished` | Draft → Active |
| `ProductArchived` | Active → Archived |
| `VariantCreated` | New variant added |
| `VariantUpdated` | Variant details changed |
| `VariantOutOfStock` | All inventory exhausted (via Inventory listener) |

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-variant-system.md](03-variant-system.md)
