---
title: Models Reference
---

# Models Reference

## Product

The core product entity implementing `Buyable`, `HasMedia`, `Inventoryable`, and `Priceable` interfaces.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `name` | `string` | Product name |
| `slug` | `string` | SEO-friendly URL slug (auto-generated) |
| `sku` | `string\|null` | Stock Keeping Unit |
| `type` | `ProductType` | Simple, Configurable, Virtual, etc. |
| `status` | `ProductStatus` | Draft, Active, Archived |
| `visibility` | `ProductVisibility` | Visible, Catalog, Search, Hidden |
| `price` | `int` | Price in cents |
| `compare_price` | `int\|null` | Original/compare price in cents |
| `cost` | `int\|null` | Cost in cents |
| `short_description` | `string\|null` | Brief description |
| `description` | `string\|null` | Full description |
| `weight` | `int\|null` | Weight in grams |
| `length`, `width`, `height` | `int\|null` | Dimensions |
| `is_featured` | `bool` | Featured product flag |
| `is_taxable` | `bool` | Subject to tax |
| `meta_title` | `string\|null` | SEO title |
| `meta_description` | `string\|null` | SEO description |
| `available_at` | `datetime\|null` | Availability date |
| `metadata` | `array` | JSON metadata |

### Relationships

```php
// Collections
$product->variants;       // HasMany<Variant>
$product->options;        // BelongsToMany<Option>
$product->categories;     // BelongsToMany<Category>
$product->collections;    // BelongsToMany<Collection>
$product->attributeSet;   // BelongsTo<AttributeSet>
$product->attributeValues; // HasMany<AttributeValue>
```

### Key Methods

```php
// Money formatting
$product->getFormattedPrice(): string
$product->getFormattedComparePrice(): ?string
$product->getFormattedCost(): ?string

// Status checks
$product->isDraft(): bool
$product->isActive(): bool
$product->isArchived(): bool
$product->isVisible(): bool
$product->isFeatured(): bool
$product->isOnSale(): bool

// Pricing
$product->getDiscountPercentage(): ?int

// Buyable interface
$product->getBuyableIdentifier(): string
$product->getBuyableDescription(): string
$product->getBuyablePrice(): int
$product->getBuyableWeight(): ?int

// Inventoryable interface
$product->getInventoryIdentifier(): string
$product->isInventoryEnabled(): bool

// Priceable interface
$product->getPriceableIdentifier(): string
$product->getBasePriceInCents(): int
```

---

## Variant

Product variants for configurable products.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `product_id` | `string` | Parent product UUID |
| `sku` | `string\|null` | Variant SKU |
| `price` | `int\|null` | Override price (cents) |
| `cost` | `int\|null` | Override cost (cents) |
| `weight` | `int\|null` | Override weight (grams) |
| `length`, `width`, `height` | `int\|null` | Override dimensions |
| `is_enabled` | `bool` | Variant enabled flag |
| `is_default` | `bool` | Default variant flag |

### Relationships

```php
$variant->product;       // BelongsTo<Product>
$variant->optionValues;  // BelongsToMany<OptionValue>
```

### Key Methods

```php
$variant->getEffectivePrice(): int  // Returns variant price or parent product price
```

---

## Category

Hierarchical product categories with nested parent-child relationships.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `parent_id` | `string\|null` | Parent category UUID |
| `name` | `string` | Category name |
| `slug` | `string` | SEO-friendly slug |
| `description` | `string\|null` | Category description |
| `is_active` | `bool` | Active flag |
| `position` | `int` | Sort order |
| `metadata` | `array` | JSON metadata |

### Relationships

```php
$category->parent;    // BelongsTo<Category>
$category->children;  // HasMany<Category>
$category->products;  // BelongsToMany<Product>
```

---

## Collection

Product collections (manual or rule-based automatic).

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `name` | `string` | Collection name |
| `slug` | `string` | SEO-friendly slug |
| `description` | `string\|null` | Collection description |
| `type` | `string` | 'manual' or 'automatic' |
| `conditions` | `array` | Rules for automatic collections |
| `is_active` | `bool` | Active flag |
| `metadata` | `array` | JSON metadata |

### Relationships

```php
$collection->products;  // BelongsToMany<Product>
```

### Key Methods

```php
// For automatic collections, apply conditions and return query builder
$collection->applyConditions(): Builder
```

---

## Option / OptionValue

Options define variant dimensions (Size, Color). OptionValues are the actual choices (S, M, L).

### Option Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `name` | `string` | Option name (e.g., "Size") |
| `position` | `int` | Sort order |

### OptionValue Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `option_id` | `string` | Parent option UUID |
| `value` | `string` | Value label (e.g., "Large") |
| `position` | `int` | Sort order |
| `metadata` | `array` | Extra data (color hex, etc.) |

### Relationships

```php
// Option
$option->values;    // HasMany<OptionValue>
$option->products;  // BelongsToMany<Product>

// OptionValue
$optionValue->option;    // BelongsTo<Option>
$optionValue->variants;  // BelongsToMany<Variant>
```

---

## Attribute / AttributeGroup / AttributeSet / AttributeValue

EAV (Entity-Attribute-Value) system for extensible product attributes.

### Attribute Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `code` | `string` | Unique attribute code |
| `name` | `string` | Display name |
| `type` | `AttributeType` | Text, Textarea, Number, Boolean, Select, MultiSelect, Date, DateTime |
| `options` | `array` | Options for Select/MultiSelect |
| `validation_rules` | `array` | Validation constraints |
| `is_required` | `bool` | Required flag |
| `is_filterable` | `bool` | Can be used as filter |
| `is_visible` | `bool` | Visible on frontend |
| `position` | `int` | Sort order |

### AttributeGroup Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `name` | `string` | Group name |
| `position` | `int` | Sort order |

### AttributeSet Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `name` | `string` | Set name |

### AttributeValue Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | `string` | UUID primary key |
| `attribute_id` | `string` | Attribute UUID |
| `product_id` | `string` | Product UUID |
| `value` | `mixed` | The actual value (JSON) |

### Relationships

```php
// Attribute
$attribute->groups;  // BelongsToMany<AttributeGroup>
$attribute->values;  // HasMany<AttributeValue>

// AttributeGroup
$group->groupAttributes;  // BelongsToMany<Attribute>
$group->sets;             // BelongsToMany<AttributeSet>

// AttributeSet
$set->groups;             // BelongsToMany<AttributeGroup>
$set->products;           // HasMany<Product>

// AttributeValue
$attributeValue->attribute;  // BelongsTo<Attribute>
$attributeValue->product;    // BelongsTo<Product>
```

### Key Methods (HasAttributes trait)

```php
// Set attribute value
$product->setAttributeValue(string $code, mixed $value): void

// Get attribute value
$product->getAttributeValue(string $code): mixed

// Get all attribute values as array
$product->getAttributes(): array
```
