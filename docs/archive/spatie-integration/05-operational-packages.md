# Operational Layer: Cart, Inventory, Vouchers

> **Packages:** `aiarmada/cart`, `aiarmada/inventory`, `aiarmada/vouchers`  
> **Status:** Built (Enhanceable)  
> **Role:** Operational Layer

---

## 📦 Cart Package

### Current State

The cart package is fully built with:
- Multiple storage drivers (session, cache, database)
- Multiple cart instances
- Conditions (discounts, taxes, fees)
- Money objects via akaunting/laravel-money
- CheckoutableInterface

### Recommended Spatie Integrations

#### 1. laravel-activitylog (HIGH)

Track cart activity for analytics and debugging.

```php
// cart/src/Models/Cart.php (if using database driver)

use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Cart extends Model
{
    use LogsCommerceActivity;

    protected function getLoggableAttributes(): array
    {
        return ['total', 'item_count', 'checkout_at'];
    }

    protected function getActivityLogName(): string
    {
        return 'carts';
    }
}
```

#### Cart Event Logging (Session-based)

```php
// cart/src/CartServiceProvider.php

use Spatie\Activitylog\ActivityLogger;

public function boot(): void
{
    // Log cart events even for session-based carts
    Event::listen(ItemAdded::class, function (ItemAdded $event) {
        activity('carts')
            ->causedBy(auth()->user())
            ->withProperties([
                'item_id' => $event->item->getId(),
                'quantity' => $event->quantity,
                'price' => $event->item->getPrice()->getAmount(),
            ])
            ->log('Item added to cart');
    });

    Event::listen(CartCheckedOut::class, function (CartCheckedOut $event) {
        activity('carts')
            ->causedBy(auth()->user())
            ->withProperties([
                'total' => $event->cart->getTotal()->getAmount(),
                'items_count' => $event->cart->getContent()->count(),
            ])
            ->log('Cart checked out');
    });
}
```

#### Value for Cart

- ✅ Track abandoned cart behavior
- ✅ Analyze cart modifications
- ✅ Debug customer issues
- ✅ Conversion funnel analytics

---

## 📦 Inventory Package

### Current State

The inventory package is fully built with:
- Multi-location stock tracking
- Allocation strategies (priority, FIFO, least-stock)
- Split allocation
- Cart integration
- Movement tracking

### Recommended Spatie Integrations

#### 1. laravel-activitylog (CRITICAL)

Inventory changes MUST be audited for compliance and debugging.

```php
// inventory/src/Models/InventoryLevel.php

use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class InventoryLevel extends Model
{
    use LogsCommerceActivity;

    protected function getLoggableAttributes(): array
    {
        return ['quantity', 'reserved', 'available'];
    }

    protected function getActivityLogName(): string
    {
        return 'inventory';
    }

    protected function getActivityDescription(string $eventName): string
    {
        return match($eventName) {
            'updated' => "Stock level changed: {$this->getOriginal('quantity')} → {$this->quantity}",
            default => parent::getActivityDescription($eventName),
        };
    }
}
```

```php
// inventory/src/Models/InventoryMovement.php

class InventoryMovement extends Model
{
    use LogsCommerceActivity;

    protected function getLoggableAttributes(): array
    {
        return ['type', 'quantity', 'reason', 'reference'];
    }

    protected function getActivityLogName(): string
    {
        return 'inventory';
    }
}
```

#### 2. laravel-model-states (MEDIUM)

For inventory movement status tracking.

```php
// inventory/src/States/MovementStatus.php

namespace AIArmada\Inventory\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class MovementStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Confirmed::class)
            ->allowTransition(Pending::class, Canceled::class)
            ->allowTransition(Confirmed::class, Reversed::class);
    }
}

class Pending extends MovementStatus {}
class Confirmed extends MovementStatus {}
class Canceled extends MovementStatus {}
class Reversed extends MovementStatus {}
```

#### Value for Inventory

- ✅ Complete audit trail of all stock changes
- ✅ Track who changed what, when
- ✅ Support for inventory adjustments
- ✅ Movement status management
- ✅ Compliance requirements met

---

## 📦 Vouchers Package

### Current State

The vouchers package is fully built with:
- Percentage and fixed discounts
- Free shipping vouchers
- Usage limits
- Wallet/credit system
- Multi-tenancy support
- Cart integration

### Recommended Spatie Integrations

#### 1. laravel-sluggable (HIGH)

Generate unique, memorable voucher codes.

```php
// vouchers/src/Models/Voucher.php

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Voucher extends Model
{
    use HasSlug;

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function ($model) {
                // Generate memorable codes like: SUMMER-2024-A7X9
                $prefix = strtoupper($model->name ?: 'PROMO');
                $suffix = strtoupper(substr(md5(uniqid()), 0, 4));
                return "{$prefix}-{$suffix}";
            })
            ->saveSlugsTo('code')
            ->slugsShouldBeNoLongerThan(20)
            ->usingSeparator('-');
    }
}
```

#### Alternative: Custom Code Generator

```php
// vouchers/src/Actions/GenerateVoucherCode.php

class GenerateVoucherCode
{
    public function handle(string $type = 'alphanumeric', int $length = 8): string
    {
        return match($type) {
            'alphanumeric' => strtoupper(Str::random($length)),
            'numeric' => str_pad(random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT),
            'words' => $this->generateWordCode(),
            'pattern' => $this->generatePatternCode(), // XXXX-XXXX-XXXX
        };
    }

    private function generateWordCode(): string
    {
        $adjectives = ['HAPPY', 'SUPER', 'MEGA', 'ULTRA', 'LUCKY'];
        $nouns = ['SALE', 'DEAL', 'SAVE', 'GIFT', 'BONUS'];
        return $adjectives[array_rand($adjectives)] . $nouns[array_rand($nouns)];
    }
}
```

#### 2. laravel-tags (MEDIUM)

Categorize vouchers for easier management.

```php
// vouchers/src/Models/Voucher.php

use Spatie\Tags\HasTags;

class Voucher extends Model
{
    use HasTags;
}

// Usage
$voucher->attachTags(['seasonal', 'vip-only'], 'campaign');
$voucher->attachTags(['electronics', 'clothing'], 'categories');

// Query
Voucher::withAnyTags(['vip-only'])->active()->get();
Voucher::withAllTags(['seasonal', 'electronics'])->get();
```

#### 3. laravel-activitylog (HIGH)

Track voucher usage and modifications.

```php
// vouchers/src/Models/Voucher.php

use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Voucher extends Model
{
    use LogsCommerceActivity;

    protected function getLoggableAttributes(): array
    {
        return [
            'code',
            'discount_type',
            'discount_value',
            'usage_limit',
            'used_count',
            'starts_at',
            'expires_at',
            'is_active',
        ];
    }
}

// Log redemptions
class RedeemVoucher
{
    public function handle(Voucher $voucher, Order $order): void
    {
        // ... redemption logic

        activity('vouchers')
            ->performedOn($voucher)
            ->withProperties([
                'order_id' => $order->id,
                'discount_applied' => $discountAmount,
            ])
            ->log('Voucher redeemed');
    }
}
```

#### Value for Vouchers

- ✅ Memorable, unique voucher codes
- ✅ Campaign categorization
- ✅ Usage audit trail
- ✅ Analytics on voucher performance

---

## 📊 Integration Summary Table

| Package | laravel-activitylog | laravel-model-states | laravel-sluggable | laravel-tags |
|---------|--------------------|--------------------|-------------------|--------------|
| cart | ✅ Event logging | ❌ | ❌ | ❌ |
| inventory | ✅ Stock changes | ✅ Movement status | ❌ | ❌ |
| vouchers | ✅ Usage tracking | ❌ | ✅ Code generation | ✅ Campaigns |

---

## 🔄 Cross-Package Activity Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    UNIFIED ACTIVITY LOG                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Cart Activities (log_name: carts)                               │
│  ├── Item added: "Nike Air Max" x 2                             │
│  ├── Voucher applied: SUMMER2024                                 │
│  └── Cart checked out (converted to Order #1234)                │
│                                                                  │
│  Inventory Activities (log_name: inventory)                      │
│  ├── Stock received: +100 units at Warehouse A                  │
│  ├── Reserved for Order #1234: 2 units                          │
│  └── Shipped for Order #1234: 2 units (deducted)                │
│                                                                  │
│  Voucher Activities (log_name: vouchers)                         │
│  ├── Voucher created: SUMMER2024 (20% off)                      │
│  ├── Voucher redeemed: Order #1234, saved $50                   │
│  └── Voucher expired: WINTER2023                                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## ✅ Implementation Checklist

### Cart Package

- [ ] Add activity logging for cart events
- [ ] Track abandoned carts
- [ ] Log checkout conversions

### Inventory Package

- [ ] Add LogsCommerceActivity to InventoryLevel
- [ ] Add LogsCommerceActivity to InventoryMovement
- [ ] Implement movement states (optional)
- [ ] Create Filament activity widget

### Vouchers Package

- [ ] Add HasSlug for code generation
- [ ] Add HasTags for campaigns
- [ ] Add LogsCommerceActivity
- [ ] Track redemption analytics

---

## 🔗 Related Documents

- [00-overview.md](00-overview.md) - Master overview
- [01-commerce-support.md](01-commerce-support.md) - Activity log foundation
- [04-orders-package.md](04-orders-package.md) - Order integration

---

*This blueprint was created by the Visionary Chief Architect.*
