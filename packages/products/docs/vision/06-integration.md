# Cross-Package Integration

> **Document:** 06 of 08  
> **Package:** `aiarmada/products`  
> **Status:** Vision

---

## Integration Philosophy

The Products package is designed as a **hub** that other packages integrate with. It implements shared interfaces and emits events that other packages can listen to. Integration is **automatic** when packages are detected as installed.

---

## Interface Implementation

### BuyableInterface (Cart Package)

When `aiarmada/cart` is installed, Products automatically satisfy the cart's requirements.

```php
namespace AIArmada\Products\Models;

use AIArmada\Cart\Contracts\BuyableInterface;
use Akaunting\Money\Money;

class Product extends Model implements BuyableInterface
{
    public function getBuyableIdentifier(): string
    {
        return (string) $this->id;
    }
    
    public function getBuyableName(): string
    {
        return $this->name;
    }
    
    public function getBuyablePrice(): Money
    {
        return Money::of($this->price, $this->currency ?? 'MYR');
    }
    
    public function canBePurchased(?int $quantity = null): bool
    {
        if ($this->status !== ProductStatus::Active) {
            return false;
        }
        
        if ($quantity && $this->hasInventoryIntegration()) {
            return $this->getTotalAvailable() >= $quantity;
        }
        
        return true;
    }
    
    public function getBuyableStock(): ?int
    {
        return $this->hasInventoryIntegration() 
            ? $this->getTotalAvailable() 
            : null;
    }
    
    public function getBuyableWeight(): ?int
    {
        return $this->weight; // grams
    }
    
    public function isTaxable(): bool
    {
        return $this->is_taxable ?? true;
    }
    
    public function getTaxCategory(): ?string
    {
        return $this->tax_class;
    }
}
```

### InventoryableInterface (Inventory Package)

When `aiarmada/inventory` is installed, Variants gain stock tracking.

```php
namespace AIArmada\Products\Models;

use AIArmada\Inventory\Contracts\InventoryableInterface;

class Variant extends Model implements InventoryableInterface
{
    use \AIArmada\Inventory\Traits\Inventoryable;
    
    public function inventoryLevels(): MorphMany
    {
        return $this->morphMany(InventoryLevel::class, 'inventoryable');
    }
    
    public function inventoryMovements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'inventoryable');
    }
    
    public function getTotalOnHand(): int
    {
        return $this->inventoryLevels()->sum('quantity_on_hand');
    }
    
    public function getTotalAvailable(): int
    {
        return $this->inventoryLevels()->sum('quantity_available');
    }
}
```

---

## Event-Driven Integration

### Events Emitted by Products

```php
namespace AIArmada\Products\Events;

// Lifecycle events
class ProductCreated extends BaseProductEvent {}
class ProductUpdated extends BaseProductEvent {}
class ProductDeleted extends BaseProductEvent {}
class ProductPublished extends BaseProductEvent {}
class ProductArchived extends BaseProductEvent {}

// Variant events
class VariantCreated extends BaseVariantEvent {}
class VariantUpdated extends BaseVariantEvent {}
class VariantDeleted extends BaseVariantEvent {}

// Category events
class CategoryCreated extends BaseCategoryEvent {}
class CategoryMoved extends BaseCategoryEvent {}
```

### Listeners in Other Packages

**Inventory Package** listens to:
```php
// When variant is deleted, clean up inventory records
ProductDeleted::class => CleanupInventoryRecords::class
```

**Search Package** listens to:
```php
// Re-index product when updated
ProductUpdated::class => ReindexProduct::class
VariantCreated::class => IndexVariant::class
```

**Affiliates Package** listens to:
```php
// Update commission cache when product price changes
ProductUpdated::class => InvalidateCommissionCache::class
```

---

## Cashier Integration

When `aiarmada/cashier` is installed, subscription products sync with payment gateways.

```php
namespace AIArmada\Products\Concerns;

trait HasSubscriptionIntegration
{
    public function isSubscription(): bool
    {
        return $this->type === ProductType::Subscription;
    }
    
    public function syncToStripe(): void
    {
        if (!class_exists(\Laravel\Cashier\Cashier::class)) {
            return;
        }
        
        $stripe = new \Stripe\StripeClient(config('cashier.secret'));
        
        $product = $stripe->products->create([
            'name' => $this->name,
            'metadata' => ['local_id' => $this->id],
        ]);
        
        $this->update(['stripe_product_id' => $product->id]);
    }
    
    public function getStripePrice(?string $interval = 'month'): ?string
    {
        return $this->stripe_prices[$interval] ?? null;
    }
    
    public function getChipPrice(?string $interval = 'month'): ?string
    {
        return $this->chip_prices[$interval] ?? null;
    }
}
```

---

## Pricing Package Integration

When `aiarmada/pricing` is installed, product prices become dynamic.

```php
namespace AIArmada\Products\Concerns;

trait HasDynamicPricing
{
    public function getEffectivePrice(?Customer $customer = null, int $quantity = 1): Money
    {
        if (!class_exists(\AIArmada\Pricing\PricingEngine::class)) {
            return $this->getBuyablePrice();
        }
        
        return app(\AIArmada\Pricing\PricingEngine::class)
            ->calculate($this, $customer, $quantity);
    }
    
    public function getPriceBreaks(): array
    {
        if (!class_exists(\AIArmada\Pricing\PricingEngine::class)) {
            return [];
        }
        
        return app(\AIArmada\Pricing\PricingEngine::class)
            ->getPriceBreaks($this);
    }
}
```

---

## Tax Package Integration

When `aiarmada/tax` is installed, products use tax classes.

```php
namespace AIArmada\Products\Concerns;

trait HasTaxIntegration
{
    public function taxClass(): BelongsTo
    {
        if (!class_exists(\AIArmada\Tax\Models\TaxClass::class)) {
            throw new \RuntimeException('Tax package not installed');
        }
        
        return $this->belongsTo(\AIArmada\Tax\Models\TaxClass::class, 'tax_class_id');
    }
    
    public function getTaxRate(?Address $address = null): float
    {
        if (!class_exists(\AIArmada\Tax\TaxEngine::class)) {
            return 0.0;
        }
        
        return app(\AIArmada\Tax\TaxEngine::class)
            ->getRateFor($this, $address);
    }
}
```

---

## Auto-Discovery Pattern

The service provider automatically detects installed packages and registers integrations.

```php
namespace AIArmada\Products;

class ProductsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerIntegrations();
    }
    
    protected function registerIntegrations(): void
    {
        // Inventory integration
        if (class_exists(\AIArmada\Inventory\InventoryServiceProvider::class)) {
            $this->app->register(Integrations\InventoryIntegration::class);
        }
        
        // Cart integration
        if (interface_exists(\AIArmada\Cart\Contracts\BuyableInterface::class)) {
            $this->app->register(Integrations\CartIntegration::class);
        }
        
        // Pricing integration
        if (class_exists(\AIArmada\Pricing\PricingEngine::class)) {
            $this->app->register(Integrations\PricingIntegration::class);
        }
        
        // Tax integration
        if (class_exists(\AIArmada\Tax\TaxEngine::class)) {
            $this->app->register(Integrations\TaxIntegration::class);
        }
        
        // Cashier integration
        if (class_exists(\AIArmada\Cashier\CashierServiceProvider::class)) {
            $this->app->register(Integrations\CashierIntegration::class);
        }
    }
}
```

---

## Navigation

**Previous:** [05-attributes.md](05-attributes.md)  
**Next:** [07-database-schema.md](07-database-schema.md)
