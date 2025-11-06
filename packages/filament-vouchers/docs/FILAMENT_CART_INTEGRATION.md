# Filament Cart Integration Guide

This guide explains how to integrate the `filament-vouchers` package with `filament-cart` to enable voucher management directly within the cart admin interface.

## Overview

The `filament-vouchers` package automatically detects when `filament-cart` is installed and provides seamless integration through:

1. **Automatic Detection**: `FilamentCartBridge` checks if FilamentCart classes exist
2. **Cart Actions**: Pre-built actions to apply/remove vouchers from carts
3. **Voucher Display**: Widgets and relation managers to show voucher usage

## Architecture

### Automatic Detection

The `FilamentCartBridge` service automatically detects if Filament Cart is available:

```php
// In FilamentVouchersServiceProvider
Filament::serving(static function (): void {
    app(FilamentCartBridge::class)->warm();
});
```

The bridge checks for these classes:
- `AIArmada\FilamentCart\Models\Cart`
- `AIArmada\FilamentCart\Resources\CartResource`

### Integration Points

#### 1. HasVouchers Trait Usage

**You DON'T need to manually use the `HasVouchers` trait!**

The voucher functionality is automatically available on all Cart instances through the `CartManagerWithVouchers` proxy pattern:

```php
use AIArmada\Cart\Facades\Cart;

// These methods are automatically available:
Cart::applyVoucher('SUMMER2024');        // Apply voucher by code
Cart::removeVoucher($voucherUuid);       // Remove by UUID
Cart::clearVouchers();                   // Remove all vouchers
Cart::hasVoucher($codeOrUuid);          // Check if voucher applied
Cart::getAppliedVouchers();             // Get all applied vouchers
```

**Behind the scenes:**
1. `VoucherServiceProvider` wraps `CartManager` with `CartManagerWithVouchers`
2. `CartManagerWithVouchers` proxies voucher methods to `CartWithVouchers`
3. `CartWithVouchers` uses the `HasVouchers` trait internally

**You never need to:**
- Add `HasVouchers` to your models
- Extend special cart classes
- Register voucher service providers manually

Everything is automatic!

#### 2. Applying Vouchers in Filament Cart UI

To add voucher functionality to the CartResource ViewCart page, update the page class:

**File:** `packages/filament-cart/src/Resources/CartResource/Pages/ViewCart.php`

```php
<?php

namespace AIArmada\FilamentCart\Resources\CartResource\Pages;

use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewCart extends ViewRecord
{
    protected static string $resource = CartResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\EditAction::make(),
            // ... other existing actions
        ];

        // Add voucher actions if filament-vouchers is available
        if (class_exists(CartVoucherActions::class)) {
            $actions[] = CartVoucherActions::applyVoucher();
            $actions[] = CartVoucherActions::showAppliedVouchers();
        }

        return $actions;
    }
}
```

This adds two buttons to the cart view page:
- **Apply Voucher**: Opens a modal to enter and apply a voucher code
- **View Vouchers**: Opens a modal showing all currently applied vouchers

#### 3. Available Actions

##### Apply Voucher Action

```php
CartVoucherActions::applyVoucher()
```

Features:
- Modal form with voucher code input
- Validation and error handling
- Success/failure notifications
- Logging for debugging

##### Show Applied Vouchers Action

```php
CartVoucherActions::showAppliedVouchers()
```

Features:
- Displays all vouchers currently applied to cart
- Shows voucher details (code, type, discount amount)
- Provides remove actions for each voucher
- Empty state when no vouchers applied

##### Remove Voucher Action

```php
CartVoucherActions::removeVoucher($voucherCode)
```

Features:
- Confirmation dialog
- Removes specific voucher from cart
- Success/failure notifications

## Manual Integration Steps

### Step 1: Update CartResource ViewCart Page

Add the voucher actions to your cart view page:

```php
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;

protected function getHeaderActions(): array
{
    return array_merge(parent::getHeaderActions(), [
        CartVoucherActions::applyVoucher(),
        CartVoucherActions::showAppliedVouchers(),
    ]);
}
```

### Step 2: (Optional) Add Voucher Widget to Cart Dashboard

If you have a cart dashboard, you can add the `AppliedVouchersWidget`:

**File:** `packages/filament-cart/src/Resources/CartResource/Pages/ViewCart.php`

```php
use AIArmada\FilamentVouchers\Widgets\AppliedVouchersWidget;

protected function getHeaderWidgets(): array
{
    return [
        AppliedVouchersWidget::class,
    ];
}

public function getWidgets(): array
{
    return [
        AppliedVouchersWidget::make(['record' => $this->record]),
    ];
}
```

### Step 3: (Optional) Show Carts on Voucher View Page

To see which carts are using a specific voucher, add the relation manager to `VoucherResource`:

**File:** `packages/filament-vouchers/src/Resources/VoucherResource.php`

```php
use AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers\CartsRelationManager;

public static function getRelations(): array
{
    return [
        VoucherUsagesRelationManager::class,
        
        // Add if FilamentCart is available
        ...(class_exists(\AIArmada\FilamentCart\Models\Cart::class) 
            ? [CartsRelationManager::class] 
            : []),
    ];
}
```

## How It Works

### Voucher Application Flow

1. **User Action**: Admin clicks "Apply Voucher" button in cart view
2. **Modal Display**: Form modal appears requesting voucher code
3. **Code Submission**: Admin enters code (e.g., "SUMMER2024") and clicks Apply
4. **Validation**: System validates:
   - Voucher exists and is active
   - Voucher hasn't expired
   - Usage limits not exceeded
   - Cart meets minimum requirements
5. **Application**: If valid, voucher is converted to a cart condition
6. **Notification**: Success or error message displayed
7. **Cart Update**: Cart totals automatically recalculated with discount

### Voucher Storage

Vouchers are applied as **cart conditions**, not as database relationships. This means:

- ✅ Vouchers work with any cart storage driver (session, cache, database)
- ✅ Voucher discounts are calculated in real-time
- ✅ Multiple vouchers can be applied (if configured)
- ✅ Vouchers are automatically removed when cart is cleared
- ❌ No direct Eloquent relationship between Cart and Voucher models

### Condition Resolution

When a voucher is applied:

```php
// Application code
Cart::applyVoucher('SUMMER2024');

// Behind the scenes:
1. VoucherService validates code
2. Voucher model retrieved from database
3. CartCondition created from voucher
4. Condition added to cart
5. Cart totals recalculated
6. VoucherUsage record created (if configured)
```

## Configuration

### Cart Integration Config

**File:** `config/vouchers.php`

```php
'cart_integration' => [
    // Maximum vouchers per cart
    'max_vouchers_per_cart' => 1,
    
    // Allow multiple vouchers to stack
    'allow_stacking' => false,
    
    // Condition order (higher = applied later)
    'condition_order' => 50,
    
    // Auto-track usage on cart checkout
    'track_usage_on_checkout' => true,
],
```

### Filament Vouchers Config

**File:** `config/filament-vouchers.php`

```php
'integrations' => [
    'cart' => [
        // Enable cart integration features
        'enabled' => true,
        
        // Show cart links in voucher usage records
        'show_cart_links' => true,
        
        // Add cart actions to CartResource
        'inject_cart_actions' => true,
    ],
],
```

## Testing

### Manual Testing

1. **Navigate to Cart**: Go to `/admin/carts/{cart-id}`
2. **Click Apply Voucher**: Should open modal with code input
3. **Enter Valid Code**: Enter an active voucher code
4. **Submit**: Should see success notification
5. **Check Cart Total**: Cart total should reflect discount
6. **Click View Vouchers**: Should show applied voucher in list
7. **Remove Voucher**: Click remove, confirm, see removal success

### Code Examples

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Vouchers\Models\Voucher;

// Create a test voucher
$voucher = Voucher::create([
    'code' => 'TEST10',
    'type' => 'percentage',
    'value' => 1000, // 10.00%
    'status' => 'active',
]);

// Create a test cart
Cart::instance('default', 'test-user-123')
    ->add('product-1', 'Test Product', 100_00, 1); // RM100.00

// Apply voucher
Cart::applyVoucher('TEST10');

// Check cart total
$total = Cart::total(); // Should be 90_00 (RM90.00)

// Verify voucher applied
$vouchers = Cart::getAppliedVouchers();
assert(count($vouchers) === 1);
assert($vouchers[0]->code === 'TEST10');
```

## Troubleshooting

### "Undefined method applyVoucher"

**Cause**: PHPStan doesn't recognize dynamically added methods

**Solution**: Methods are added via magic `__call()` in proxy. Add `@phpstan-ignore-next-line` comment:

```php
/** @phpstan-ignore-next-line */
$cart->applyVoucher($code);
```

### Voucher Not Applying

**Check**:
1. Voucher status is "active"
2. Voucher hasn't expired (valid_from/valid_until)
3. Usage limit not reached (usage_limit)
4. Cart meets minimum value (min_cart_value)
5. Max vouchers per cart not exceeded

**Debug**:
```php
use AIArmada\Vouchers\Services\VoucherService;

$service = app(VoucherService::class);
try {
    $voucher = $service->validateAndRetrieve('CODE123', Cart::instance());
    dd($voucher); // Should show voucher details
} catch (\Exception $e) {
    dd($e->getMessage()); // Shows validation error
}
```

### Cart Bridge Not Detecting FilamentCart

**Check**:
```php
use AIArmada\FilamentVouchers\Support\Integrations\FilamentCartBridge;

$bridge = app(FilamentCartBridge::class);
dd($bridge->isAvailable()); // Should return true
```

**If false**, ensure both packages are installed:
```bash
composer show aiarmada/filament-cart
composer show aiarmada/filament-vouchers
```

## Advanced Customization

### Custom Voucher Actions

Create your own actions by extending `CartVoucherActions`:

```php
namespace App\Filament\Actions;

use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;
use Filament\Actions\Action;

class CustomVoucherActions extends CartVoucherActions
{
    public static function bulkApplyVouchers(): Action
    {
        return Action::make('bulk_apply')
            ->label('Bulk Apply Vouchers')
            // ... your implementation
    }
}
```

### Custom Widget

Create custom voucher widgets:

```php
use AIArmada\FilamentVouchers\Widgets\AppliedVouchersWidget;

class CustomVoucherWidget extends AppliedVouchersWidget
{
    protected function getTableColumns(): array
    {
        return array_merge(parent::getTableColumns(), [
            // Add custom columns
        ]);
    }
}
```

## Summary

✅ **Automatic Integration**: No manual trait usage required
✅ **Pre-built Actions**: Apply/remove vouchers with one line of code
✅ **Flexible Display**: Widgets and modals for voucher management
✅ **Type-safe**: PHPStan comments for dynamic methods
✅ **Production Ready**: Error handling, logging, notifications included

The integration is designed to be:
- **Automatic**: Detected and configured via service providers
- **Optional**: Gracefully degrades if filament-cart not installed  
- **Extensible**: Easy to customize actions and widgets
- **Type-safe**: Clear documentation for dynamic methods
