---
title: Contracts & DTOs
---

# Contracts & DTOs

## Contracts

### PriceCalculatorInterface

The main contract for price calculation services:

```php
namespace AIArmada\Pricing\Contracts;

use AIArmada\Pricing\Data\PriceResultData;

interface PriceCalculatorInterface
{
    /**
     * Calculate the final price for a priceable item.
     *
     * @param  Priceable  $item  The item to calculate price for
     * @param  int  $quantity  The quantity being purchased
     * @param  array<string, mixed>  $context  Additional context
     */
    public function calculate(
        Priceable $item, 
        int $quantity = 1, 
        array $context = []
    ): PriceResultData;
}
```

#### Context Parameters

| Key | Type | Description |
|-----|------|-------------|
| `customer_id` | string | Customer UUID for customer-specific pricing |
| `segment_ids` | array | Customer segment UUIDs |
| `price_list_id` | string | Specific price list UUID to use |
| `currency` | string | Override default currency |
| `effective_at` | DateTimeInterface\|string\|int | Calculate price at specific date |

### Priceable

Interface for items that can have dynamic pricing:

```php
namespace AIArmada\Pricing\Contracts;

interface Priceable
{
    /**
     * Get the unique identifier for the priceable item.
     */
    public function getBuyableIdentifier(): string;

    /**
     * Get the base price in cents.
     */
    public function getBasePrice(): int;

    /**
     * Get the compare price (original/MSRP) in cents.
     */
    public function getComparePrice(): ?int;

    /**
     * Check if the item is on sale.
     */
    public function isOnSale(): bool;

    /**
     * Get the discount percentage if on sale.
     */
    public function getDiscountPercentage(): ?float;
}
```

#### Implementation Example

```php
use AIArmada\Pricing\Contracts\Priceable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements Priceable
{
    public function getBuyableIdentifier(): string
    {
        return (string) $this->getKey();
    }

    public function getBasePrice(): int
    {
        return (int) $this->price;
    }

    public function getComparePrice(): ?int
    {
        return $this->compare_at_price;
    }

    public function isOnSale(): bool
    {
        $comparePrice = $this->getComparePrice();
        
        return $comparePrice !== null && $comparePrice > $this->getBasePrice();
    }

    public function getDiscountPercentage(): ?float
    {
        if (!$this->isOnSale()) {
            return null;
        }

        $compare = $this->getComparePrice();
        $base = $this->getBasePrice();

        return round((($compare - $base) / $compare) * 100, 1);
    }
}
```

---

## Data Transfer Objects

### PriceResultData

The DTO returned by price calculations:

```php
namespace AIArmada\Pricing\Data;

use Spatie\LaravelData\Data;

final class PriceResultData extends Data
{
    public function __construct(
        public int $originalPrice,
        public int $finalPrice,
        public int $discountAmount,
        public ?string $discountSource = null,
        public ?float $discountPercentage = null,
        public ?string $priceListName = null,
        public ?string $tierDescription = null,
        public ?string $promotionName = null,
        public string $currency = 'MYR',
        /** @var array<int, array<string, mixed>> */
        public array $breakdown = [],
    ) {}
}
```

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `originalPrice` | int | Original base price in cents |
| `finalPrice` | int | Calculated final price in cents |
| `discountAmount` | int | Discount amount in cents |
| `discountSource` | string\|null | Description of discount source |
| `discountPercentage` | float\|null | Discount as percentage |
| `priceListName` | string\|null | Applied price list name |
| `tierDescription` | string\|null | Applied tier description |
| `promotionName` | string\|null | Applied promotion name |
| `currency` | string | Currency code |
| `breakdown` | array | Detailed calculation breakdown |

#### Methods

```php
$result = $calculator->calculate($product, 1);

// Check for discount
$result->hasDiscount(); // bool

// Formatted prices
$result->getFormattedOriginalPrice(); // "RM 50.00"
$result->getFormattedFinalPrice();    // "RM 45.00"
$result->getFormattedSavings();       // "RM 5.00"
```

#### Breakdown Structure

The `breakdown` array contains the calculation steps:

```php
[
    [
        'type' => 'customer_specific',
        'price' => 4500,
    ],
    // OR
    [
        'type' => 'segment',
        'price' => 4500,
    ],
    // OR
    [
        'type' => 'tier',
        'price' => 4000,
        'tier' => '10-49 units',
    ],
    // OR
    [
        'type' => 'promotion',
        'price' => 4250,
        'promotion' => 'Summer Sale',
    ],
    // OR
    [
        'type' => 'price_list',
        'price' => 4500,
        'list' => 'Wholesale',
    ],
    // OR
    [
        'type' => 'base',
        'price' => 5000,
    ],
]
```

---

## Service Binding

The package registers the following bindings:

```php
// Singleton binding
$this->app->singleton(Services\PriceCalculator::class);

// Interface alias
$this->app->alias(
    Services\PriceCalculator::class, 
    Contracts\PriceCalculatorInterface::class
);
```

### Resolving the Service

```php
// Via interface (recommended)
$calculator = app(PriceCalculatorInterface::class);

// Via concrete class
$calculator = app(\AIArmada\Pricing\Services\PriceCalculator::class);

// Via dependency injection
public function __construct(
    private PriceCalculatorInterface $calculator
) {}
```

### Custom Implementation

You can replace the default calculator with your own:

```php
// In a service provider
$this->app->singleton(
    PriceCalculatorInterface::class,
    CustomPriceCalculator::class
);
```
