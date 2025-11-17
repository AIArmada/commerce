# Filament Cart Plugin

Filament Cart brings the aiarmada/cart ecosystem into Filament with blazing-fast, normalized cart data, a delightful admin experience, and dynamic pricing insights built for high-volume commerce teams.

<p align="center">
    <strong>Laravel&nbsp;12 · Filament&nbsp;5 · aiarmada/cart · Tailwind&nbsp;4</strong>
</p>

[![Packagist](https://img.shields.io/packagist/v/aiarmada/filament-cart.svg?style=flat-square)](https://packagist.org/packages/aiarmada/filament-cart)
[![Tests](https://img.shields.io/github/actions/workflow/status/aiarmada/filament-cart/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/aiarmada/filament-cart/actions)

---

## At a glance

| Why teams love it | What you get |
| --- | --- |
| 🧭 Clear visibility | Normalized `Cart`, `CartItem`, and `CartCondition` resources with instant search |
| ⚡ Operates at scale | Event-driven sync keeps data fresh without expensive JSON queries (100× faster) |
| 🧰 Built for builders | Dynamic condition tooling, analytics-ready tables, and extensible actions |
| 📊 Production ready | Comprehensive test suite, PHPStan level 6, PHP 8.4 types |

---

## Quick start

```bash
composer require aiarmada/filament-cart
```

Register the plugin with your Filament panel (requires Laravel 12+, Filament 5+, and aiarmada/cart):

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use AIArmada\FilamentCart\FilamentCart;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...your existing configuration
            ->plugins([
                FilamentCart::make(),
            ]);
    }
}
```

That's it—no additional migration or configuration steps. The plugin auto-discovers the normalized cart tables provided by aiarmada/cart.

---

## Resources

### 1. Cart Resource

**Location:** Commerce → Carts

Explore all carts in your system with powerful filtering:

**Table Columns:**
- Cart ID (UUID with copy action)
- Instance badge (default, wishlist, quote, layaway)
- Items count
- Conditions count  
- Subtotal
- Total (with condition calculations)
- Created/updated timestamps

**Filters:**
- Instance (default, wishlist, quote, layaway)
- Date range (created, updated)
- Item count range
- Price range (subtotal, total)
- Has conditions toggle

**Actions:**
- View cart details (infolist with nested items and conditions)
- Clear cart
- Export to CSV/Excel
- Delete cart

**Search:** Cart ID, metadata (supports JSON search)

### 2. Cart Item Resource

**Location:** Commerce → Cart Items

Deep dive into individual cart items:

**Table Columns:**
- Item ID (UUID)
- Cart ID (with link to parent cart)
- Product ID / Buyable identifier
- Name
- Quantity
- Price (unit price)
- Total (quantity × price)
- Conditions count
- Created/updated timestamps

**Filters:**
- Cart instance
- Price range
- Quantity range
- Date range
- Has conditions

**Actions:**
- View item details with attributes and conditions
- Update quantity
- Remove item
- Apply item-level condition

**Search:** Product ID, name, attributes (JSON)

### 3. Cart Condition Resource

**Location:** Commerce → Cart Conditions

Manage pricing rules and discounts:

**Table Columns:**
- Condition ID (UUID)
- Cart ID (with link)
- Name
- Type badge (discount, fee, tax, shipping, coupon)
- Target (subtotal, total, item)
- Value (percentage or fixed amount)
- Order (calculation sequence)
- Created timestamp

**Filters:**
- Type (discount, fee, tax, shipping, coupon)
- Target (subtotal, total, item)
- Cart instance
- Date range

**Actions:**
- View condition details with rules
- Edit condition value
- Remove condition
- Reorder conditions

**Search:** Name, condition type

---

## Dashboard Widget

### Cart Statistics Overview

Displays key metrics:

- Total active carts
- Total cart items
- Average cart value
- Total cart value
- Abandoned carts (no update > 24hrs)
- Carts by instance (pie chart)

**Widget Configuration:**
```php
FilamentCart::make()
    ->widgets([
        CartStatsWidget::class,
    ])
    ->widgetOptions([
        'abandoned_threshold_hours' => 24,
        'show_instance_breakdown' => true,
    ]);
```

---

## Feature highlights

### 1. Normalized cart intelligence
- Dedicated `Cart`, `CartItem`, and `CartCondition` records for analytics-grade querying
- Up to **100× faster lookups** compared to searching JSON payloads
- Rich filtering by instance (default, wishlist, quote, layaway), price range, quantities, and condition type
- Supports complex queries impossible with JSON storage

### 2. Filament-native admin surfaces
- Purpose-built resources for carts, items, and conditions with polished tables, filters, and widgets
- Bulk actions for clear, export, and housekeeping flows
- **Real-time synchronization** powered by aiarmada/cart events—no manual syncing required
- Instant drill-down from cart → items → conditions

### 3. Dynamic pricing that understands your rules
- Global conditions with rule-based application and auto-removal via `registerDynamicCondition()`
- Per-item rule support: 
  - `min_items` counts distinct items
  - `item_quantity` evaluates individual item quantities
  - `item_price` evaluates individual item prices
- Snapshot history for auditing which incentives were active at any moment
- Condition ordering controls calculation sequence

### 4. Operations & insights
- Dashboard widget summarizing total carts, active carts, item counts, and total value
- Instant drill-down from any cart to its underlying items and conditions
- Export-ready tables for BI teams with zero additional modeling
- JSON searchable metadata for custom fields

---

## Configuration & Customization

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag="filament-cart-config"
```

Customize in `config/filament-cart.php`:

```php
return [
    // Navigation settings
    'navigation' => [
        'group' => 'Commerce',
        'sort' => 10,
        'cart_icon' => 'heroicon-o-shopping-cart',
        'item_icon' => 'heroicon-o-cube',
        'condition_icon' => 'heroicon-o-tag',
    ],
    
    // Resource configuration
    'resources' => [
        'cart' => [
            'enabled' => true,
            'label' => 'Cart',
            'plural_label' => 'Carts',
        ],
        'item' => [
            'enabled' => true,
            'label' => 'Cart Item',
            'plural_label' => 'Cart Items',
        ],
        'condition' => [
            'enabled' => true,
            'label' => 'Cart Condition',
            'plural_label' => 'Cart Conditions',
        ],
    ],
    
    // Table settings
    'table' => [
        'records_per_page' => 25,
        'default_sort_cart' => 'updated_at',
        'default_sort_direction' => 'desc',
    ],
    
    // Widget settings
    'widget' => [
        'enabled' => true,
        'abandoned_threshold_hours' => 24,
        'refresh_interval' => '30s',
    ],
];
```

### Extending Resources

Create custom resources by extending the base classes:

```php
// app/Filament/Resources/CustomCartResource.php
namespace App\Filament\Resources;

use AIArmada\FilamentCart\Resources\CartResource as BaseCartResource;
use Filament\Tables;

class CustomCartResource extends BaseCartResource
{
    // Add custom columns
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                ...parent::getColumns(),
                Tables\Columns\TextColumn::make('metadata.source')
                    ->label('Source')
                    ->searchable(),
                Tables\Columns\TextColumn::make('metadata.campaign')
                    ->label('Campaign')
                    ->badge(),
            ]);
    }
    
    // Add custom bulk actions
    public static function getBulkActions(): array
    {
        return [
            ...parent::getBulkActions(),
            Tables\Actions\BulkAction::make('tag_abandoned')
                ->label('Tag as Abandoned')
                ->action(fn ($records) => /* your logic */),
        ];
    }
}
```

Then register your custom resource:

```php
FilamentCart::make()
    ->resources([
        CustomCartResource::class,
    ]);
```

---

## Working with dynamic conditions

The plugin surfaces the full power of aiarmada/cart's dynamic pricing engine:

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Conditions\CartCondition;

// Register a dynamic condition that auto-applies and auto-removes
$condition = CartCondition::fromArray([
    'name' => 'free-shipping',
    'type' => 'shipping',
    'target' => 'cart@grand_total/aggregate',
    'value' => '-1000', // Remove RM10.00 shipping fee
    'rules' => [
        'min_total' => '10000',  // Requires RM100+ total
        'min_items' => '3',       // Requires 3+ distinct items
    ],
]);

Cart::registerDynamicCondition($condition);
```

**Rule Evaluation:**
- `min_total` - Minimum cart subtotal (cents)
- `min_items` - Minimum distinct items (not quantity)
- `item_quantity` - Evaluates per-item quantity
- `item_price` - Evaluates per-item price

**Viewing in Filament:**
- All conditions appear in Cart Condition resource
- Rule details shown in condition infolist
- Edit conditions directly or via cart detail view
- Reorder conditions to control calculation sequence

---

## Real-Time Synchronization

The plugin listens to cart events from aiarmada/cart:

- `CartCreated` → Creates `Cart` record
- `ItemAdded` → Creates `CartItem` record
- `ConditionApplied` → Creates `CartCondition` record
- `CartCleared` → Removes all items and conditions
- `CartDestroyed` → Soft deletes cart record

**Manual Sync:**

If you need to force-sync existing carts:

```bash
php artisan filament-cart:sync

# Options
--instance=default     # Sync specific instance
--from=2025-01-01     # Sync from date
--force               # Recreate existing records
```

---

## Authorization

The plugin respects Filament's authorization system. Define policies for granular control:

```php
// app/Policies/CartPolicy.php
class CartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_carts');
    }
    
    public function view(User $user, Cart $cart): bool
    {
        // Allow viewing own carts or admin role
        return $user->id === $cart->user_id 
            || $user->hasRole('admin');
    }
    
    public function delete(User $user, Cart $cart): bool
    {
        return $user->can('delete_carts');
    }
    
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_cart');
    }
}
```

Register in `AuthServiceProvider`:

```php
protected $policies = [
    \AIArmada\FilamentCart\Models\Cart::class => CartPolicy::class,
    \AIArmada\FilamentCart\Models\CartItem::class => CartItemPolicy::class,
    \AIArmada\FilamentCart\Models\CartCondition::class => CartConditionPolicy::class,
];
```

---

## Testing

The plugin includes comprehensive tests using Pest v4:

```bash
# Run all tests
vendor/bin/pest

# Run specific test suite
vendor/bin/pest --filter=CartResource

# Run with parallel execution
vendor/bin/pest --parallel

# Run with coverage
vendor/bin/pest --coverage
```

**Test Coverage:**
- Cart resource CRUD operations ✅
- Cart item resource operations ✅
- Cart condition resource operations ✅
- Dashboard widget calculations ✅
- Event synchronization ✅
- Authorization policies ✅
- Custom bulk actions ⚠️ (partial)
- Extended resources ⚠️ (partial)

---

## Troubleshooting

### Carts not appearing in Filament

1. Verify aiarmada/cart is properly configured
2. Check database tables exist: `carts`, `cart_items`, `cart_conditions`
3. Force sync: `php artisan filament-cart:sync --force`
4. Check event listeners are registered

### Condition calculations wrong

1. Verify condition order (lower order executes first)
2. Check condition target (subtotal vs total)
3. Review rule evaluation in cart detail view
4. Test condition logic: `Cart::testCondition($condition)`

### Widget not updating

1. Check `widget.refresh_interval` in config
2. Verify Livewire polling is enabled
3. Clear cache: `php artisan cache:clear`
4. Check for JavaScript errors in browser console

---

## Quality gates

Before submitting PRs:

```bash
vendor/bin/pint --dirty    # Format code
vendor/bin/pest --parallel # Run tests
vendor/bin/phpstan analyse # Static analysis
```

CI mirrors these checks to ensure production readiness.

---

## Contributing

Pull requests are welcome! Please:

1. Open an issue describing the enhancement or bug
2. Keep documentation changes alongside behavior changes
3. Include targeted tests for any observable behavior shift
4. Follow existing code style and patterns

---

## Security

If you discover security vulnerabilities, please email security@aiarmada.com instead of using the issue tracker.

---

## Credits

- [AIArmada Team](https://aiarmada.com)
- [All Contributors](https://github.com/aiarmada/commerce/contributors)

---

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
