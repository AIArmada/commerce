---
title: Models
---

# Models

## PriceList

The `PriceList` model represents a collection of prices, such as "Retail", "Wholesale", or "VIP".

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string | UUID primary key |
| `owner_type` | string\|null | Owner model class for multitenancy |
| `owner_id` | string\|null | Owner model ID |
| `name` | string | Display name |
| `slug` | string | Unique slug identifier |
| `description` | string\|null | Optional description |
| `currency` | string | ISO 4217 currency code |
| `priority` | int | Priority (higher = more priority) |
| `is_default` | bool | Whether this is the default price list |
| `is_active` | bool | Active status |
| `customer_id` | string\|null | Assigned to specific customer |
| `segment_id` | string\|null | Assigned to customer segment |
| `starts_at` | Carbon\|null | Activation start date |
| `ends_at` | Carbon\|null | Activation end date |

### Relationships

```php
// Prices in this list
$priceList->prices;

// Price tiers in this list
$priceList->tiers;
```

### Scopes

```php
// Active price lists (is_active AND within date range)
PriceList::active()->get();

// Default price list only
PriceList::default()->get();

// Owner scoped (multitenancy)
PriceList::forOwner($owner, includeGlobal: true)->get();
```

### Methods

```php
$priceList = PriceList::find($id);

// Check if currently active
$priceList->isActive(); // Considers is_active, starts_at, ends_at
```

### Activity Logging

Price lists are logged with these fields: `name`, `priority`, `is_active`, `starts_at`, `ends_at`

---

## Price

The `Price` model represents an individual price for a priceable item within a price list.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string | UUID primary key |
| `owner_type` | string\|null | Owner model class |
| `owner_id` | string\|null | Owner model ID |
| `price_list_id` | string | Foreign key to price list |
| `priceable_type` | string | Polymorphic type |
| `priceable_id` | string | Polymorphic ID |
| `amount` | int | Price in minor units (cents) |
| `compare_amount` | int\|null | Original/compare price |
| `currency` | string | Currency code |
| `min_quantity` | int | Minimum quantity for this price |
| `starts_at` | Carbon\|null | Price start date |
| `ends_at` | Carbon\|null | Price end date |

### Relationships

```php
// The priceable item (Product, Variant, etc.)
$price->priceable;

// The price list
$price->priceList;
```

### Scopes

```php
// Active prices (within date range)
Price::active()->get();

// Prices applicable for quantity
Price::forQuantity(10)->get();
```

### Methods

```php
$price = Price::find($id);

// Check if currently active
$price->isActive();

// Check if has discount (compare_amount > amount)
$price->hasDiscount();

// Get discount percentage
$price->getDiscountPercentage(); // e.g., 10.0

// Format price
$price->getFormattedAmount(); // "RM 45.00"
```

### Activity Logging

Prices are logged with these fields: `amount`, `compare_amount`, `min_quantity`, `starts_at`, `ends_at`

---

## PriceTier

The `PriceTier` model represents quantity-based tiered pricing.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string | UUID primary key |
| `owner_type` | string\|null | Owner model class |
| `owner_id` | string\|null | Owner model ID |
| `price_list_id` | string\|null | Optional price list assignment |
| `tierable_type` | string | Polymorphic type |
| `tierable_id` | string | Polymorphic ID |
| `min_quantity` | int | Tier minimum quantity |
| `max_quantity` | int\|null | Tier maximum (null = unlimited) |
| `amount` | int | Price for this tier |
| `discount_type` | string\|null | 'percentage' or 'fixed' |
| `discount_value` | int\|null | Discount value |
| `currency` | string | Currency code |

### Relationships

```php
// The tierable item
$tier->tierable;

// Optional price list
$tier->priceList;
```

### Scopes

```php
// Tiers applicable for quantity
PriceTier::forQuantity(25)->get();

// Ordered by min_quantity ascending
PriceTier::ordered()->get();
```

### Methods

```php
$tier = PriceTier::find($id);

// Check if quantity falls within tier
$tier->appliesTo(25); // true/false

// Get tier description
$tier->getDescription(); // "10-49 units" or "50+ units"

// Get discount description
$tier->getDiscountDescription(); // "10% off" or "RM 5.00 off"
```

### Activity Logging

Price tiers are logged with these fields: `min_quantity`, `max_quantity`, `amount`, `discount_type`, `discount_value`

---

## Table Name Configuration

All models use configurable table names:

```php
// In PriceList model
public function getTable(): string
{
    return config('pricing.database.tables.price_lists', 'price_lists');
}

// In Price model
public function getTable(): string
{
    return config('pricing.database.tables.prices', 'prices');
}

// In PriceTier model
public function getTable(): string
{
    return config('pricing.database.tables.price_tiers', 'price_tiers');
}
```

Configure in `config/pricing.php`:

```php
'database' => [
    'tables' => [
        'prices' => 'custom_prices',
        'price_lists' => 'custom_price_lists',
        'price_tiers' => 'custom_price_tiers',
    ],
],
```
