# Variant System

> **Document:** 03 of 08  
> **Package:** `aiarmada/products`  
> **Status:** Vision

---

## Overview

The variant system enables products to have multiple purchasable variations based on configurable options. Each unique combination of option values creates a distinct variant with its own SKU, price, and inventory.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                       VARIANT ARCHITECTURE                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │                      PRODUCT                             │   │
│   │              "Premium T-Shirt"                           │   │
│   └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│              ┌───────────────┼───────────────┐                  │
│              ▼               ▼               ▼                  │
│   ┌──────────────┐   ┌──────────────┐   ┌──────────────┐       │
│   │    OPTION    │   │    OPTION    │   │    OPTION    │       │
│   │    "Size"    │   │   "Color"    │   │  "Material"  │       │
│   └──────┬───────┘   └──────┬───────┘   └──────┬───────┘       │
│          │                  │                  │                │
│   ┌──────┴──────┐    ┌──────┴──────┐    ┌──────┴──────┐        │
│   │ S │ M │ L   │    │ Red │ Blue  │    │Cotton│Poly │        │
│   │ XL│ XXL     │    │ Black│White │    │Blend │     │        │
│   └─────────────┘    └─────────────┘    └─────────────┘        │
│                                                                  │
│   VARIANTS (Cartesian Product):                                  │
│   - TSH-001-S-RED-COTTON, TSH-001-S-RED-POLY, ...               │
│   - TSH-001-M-RED-COTTON, TSH-001-M-RED-POLY, ...               │
│   - (Up to 5 × 4 × 2 = 40 variants)                             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Core Models

### Option Model
Defines attribute types that create variants.

```php
namespace AIArmada\Products\Models;

class Option extends Model
{
    protected $fillable = [
        'name',        // "Size", "Color"
        'slug',        // "size", "color"
        'type',        // OptionType::Select, Swatch, Text
        'position',    // Display order
    ];
    
    public function values(): HasMany;
    public function products(): BelongsToMany;
}
```

### OptionValue Model
Individual values within an option.

```php
namespace AIArmada\Products\Models;

class OptionValue extends Model
{
    protected $fillable = [
        'option_id',
        'label',       // "Small", "Red"
        'value',       // "s", "#FF0000"
        'position',
    ];
    
    public function option(): BelongsTo;
    public function variants(): BelongsToMany;
}
```

### Variant Model
The purchasable unit with unique attribute combination.

```php
namespace AIArmada\Products\Models;

class Variant extends Model implements BuyableInterface, InventoryableInterface
{
    protected $fillable = [
        'product_id',
        'sku',
        'price',           // Override parent price (nullable)
        'compare_at_price', // Original price for "on sale"
        'cost',            // Cost of goods
        'weight',
        'barcode',
        'is_default',
        'position',
    ];
    
    public function product(): BelongsTo;
    public function optionValues(): BelongsToMany;
    
    // Inventory integration (when package present)
    public function inventoryLevels(): MorphMany;
    
    // Price resolution
    public function getEffectivePrice(): Money
    {
        return $this->price 
            ? Money::of($this->price, $this->product->currency)
            : $this->product->getBuyablePrice();
    }
}
```

---

## Variant Generation Strategies

### 1. Automatic Generation
Create all possible combinations automatically.

```php
$product->generateVariants();

// Creates:
// S-Red, S-Blue, S-Black
// M-Red, M-Blue, M-Black
// L-Red, L-Blue, L-Black
```

### 2. Selective Generation
Create only specific combinations.

```php
$product->variants()->create([
    'sku' => 'TSH-001-S-RED',
    'option_values' => [$small->id, $red->id],
]);
```

### 3. Lazy Generation
Generate variants only when inventory is received.

```php
$product->setVariantStrategy(VariantStrategy::Lazy);

// Variant created when:
$product->receiveInventory(['size' => 'S', 'color' => 'Red'], quantity: 100);
```

---

## SKU Generation

### Default Pattern
`{PARENT_SKU}-{OPTION1}-{OPTION2}`

```php
// Product SKU: TSH-001
// Size: L, Color: BLU
// Generated: TSH-001-L-BLU
```

### Custom Patterns
```php
config/products.php:
'sku_pattern' => '{parent}-{options}',
'sku_separator' => '-',
'sku_case' => 'upper',
```

### Manual Override
```php
$variant->update(['sku' => 'CUSTOM-SKU-12345']);
```

---

## Variant Pricing

### Price Hierarchy

1. **Variant Price** (if set) → Use variant-specific price
2. **Price Rule** (from Pricing package) → Apply dynamic pricing
3. **Parent Price** → Fall back to product base price

```php
class Variant extends Model
{
    public function getEffectivePrice(): Money
    {
        // 1. Check variant override
        if ($this->price !== null) {
            return Money::of($this->price, $this->currency);
        }
        
        // 2. Check pricing rules (if package present)
        if (class_exists(\AIArmada\Pricing\PricingEngine::class)) {
            $dynamicPrice = app(PricingEngine::class)->calculate($this);
            if ($dynamicPrice) {
                return $dynamicPrice;
            }
        }
        
        // 3. Fall back to parent
        return $this->product->getBuyablePrice();
    }
}
```

---

## Variant Selection UI

### Matrix Display
For products with 2 options, show a size/color matrix.

```
         | Red  | Blue | Black
---------|------|------|-------
Small    |  ✓   |  ✓   |  ✗
Medium   |  ✓   |  ✓   |  ✓
Large    |  ✗   |  ✓   |  ✓
```

### Sequential Selection
For products with 3+ options, use stepped selection.

```
Step 1: Select Size    → [S] [M] [L] [XL]
Step 2: Select Color   → [Red] [Blue] [Black]
Step 3: Select Material → [Cotton] [Polyester]
```

---

## Swatch Support

Options can display as visual swatches instead of text.

```php
OptionValue::create([
    'option_id' => $colorOption->id,
    'label' => 'Navy Blue',
    'value' => '#1e3a5f',      // Hex color
    'swatch_type' => 'color',
]);

OptionValue::create([
    'option_id' => $materialOption->id,
    'label' => 'Denim',
    'swatch_type' => 'image',
])->addMedia($denimTexture)->toMediaCollection('swatch');
```

---

## Events

| Event | Triggered When |
|-------|----------------|
| `VariantCreated` | New variant added |
| `VariantUpdated` | Variant details changed |
| `VariantDeleted` | Variant removed |
| `VariantsGenerated` | Bulk generation completed |
| `VariantOutOfStock` | Inventory depleted (from Inventory package) |

---

## Navigation

**Previous:** [02-product-architecture.md](02-product-architecture.md)  
**Next:** [04-categories-collections.md](04-categories-collections.md)
