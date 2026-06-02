---
title: Usage
---

# Usage

Working with Filament Vouchers resources, pages, and actions.

## Resources

### VoucherResource

The main resource for managing vouchers.

**Features:**
- Create vouchers with various discount types (fixed, percentage, free shipping)
- Configure usage limits, date ranges, and minimum order amounts
- Target specific products, categories, or customer segments
- View usage history and statistics

**Form Fields:**
- Code and name
- Type (fixed, percentage, free shipping)
- Value (in cents for fixed, basis points for percentage)
- Currency
- Usage limits (total and per-user)
- Date range (starts_at, expires_at)
- Minimum order amount
- Stacking rules
- Targeting configuration
- **Affiliate linking** — when `aiarmada/affiliates` is installed, a collapsible "Affiliate" section appears for linking a voucher to an affiliate for conversion tracking

### Condition Targeting

Voucher create and edit forms use a preset selector for the cart condition target. The raw DSL input is hidden by default and only appears when selecting **Custom target**.

Common presets include cart subtotal, grand total, shipments / shipping, taxable amount, and each cart item. Use a custom target only when you need an advanced cart condition expression such as `cart@cart_subtotal/aggregate` or `items@item_discount/per-item`.

### VoucherUsageResource

Read-only resource for viewing voucher redemption history.

**Columns:**
- User identifier
- Voucher code
- Affiliate label with source / medium description (when affiliates are installed)
- Affiliate campaign
- Discount amount
- Channel
- Resolved order number (from the linked order or usage metadata)
- Used at timestamp

**Filters:**
- Channel
- Affiliate
- Source
- Medium
- Campaign

**Header actions:**
- Export voucher usage data with affiliate and order columns via `VoucherUsageExporter`

Affiliate-aware voucher reporting is resolved by `AffiliateReportingContextResolver`, which matches voucher usages against affiliate conversions and attributions using the voucher code plus the resolved order reference/order number when available.

### VoucherWalletResource

Manage vouchers saved to user wallets.

**Columns:**
- Voucher details
- Holder information
- Claim status
- Redemption status

## Pages

### StackingConfigurationPage

Configure how vouchers interact when multiple are applied:

- Define exclusive voucher categories
- Set stacking priority rules
- Configure combination limits

### TargetingConfigurationPage

Create and manage targeting presets:

- Cart value conditions
- User segment targeting
- Product category targeting
- First purchase rules

## Actions

### ActivateVoucherAction

Activate a paused or draft voucher:

```php
use AIArmada\FilamentVouchers\Actions\ActivateVoucherAction;

ActivateVoucherAction::make()
```

### PauseVoucherAction

Temporarily pause an active voucher:

```php
use AIArmada\FilamentVouchers\Actions\PauseVoucherAction;

PauseVoucherAction::make()
```

### ManualRedeemVoucherAction

Manually redeem a voucher for a customer:

```php
use AIArmada\FilamentVouchers\Actions\ManualRedeemVoucherAction;

ManualRedeemVoucherAction::make()
```

### AddToMyWalletAction

Save a voucher to the current user's wallet:

```php
use AIArmada\FilamentVouchers\Actions\AddToMyWalletAction;

AddToMyWalletAction::make()
```

### BulkGenerateVouchersAction

Generate multiple vouchers with unique codes:

```php
use AIArmada\FilamentVouchers\Actions\BulkGenerateVouchersAction;

BulkGenerateVouchersAction::make()
```

### ApplyVoucherToCartAction

Apply a voucher to a cart (requires filament-cart):

```php
use AIArmada\FilamentVouchers\Actions\ApplyVoucherToCartAction;

ApplyVoucherToCartAction::make()
```

## Customizing Resources

### Extending VoucherResource

```php
namespace App\Filament\Resources;

use AIArmada\FilamentVouchers\Resources\VoucherResource as BaseVoucherResource;

class VoucherResource extends BaseVoucherResource
{
    protected static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    protected static function getNavigationSort(): ?int
    {
        return 5;
    }
}
```

### Custom Table Actions

Add custom actions to the voucher table:

```php
// In your extended resource
public static function table(Table $table): Table
{
    return parent::table($table)
        ->actions([
            ...parent::table($table)->getActions(),
            // Add your custom actions
        ]);
}
```

## Multi-tenant Usage

When owner scoping is enabled (`vouchers.owner.enabled = true`), resources automatically filter to the current owner:

```php
// Resources only show vouchers belonging to the resolved owner
VoucherResource::getEloquentQuery()
    // Automatically scoped by OwnerScope
```

Configure owner types in the config to enable owner selection in forms.

## Money Helper

The `MoneyHelper` class provides utilities for money and percentage conversion, integrating with `commerce-support`'s `MoneyNormalizer`:

```php
use AIArmada\FilamentVouchers\Support\MoneyHelper;
```

### Cents Conversion

Convert between cents (storage) and display format (forms):

```php
// Storage → Display (for form fields)
MoneyHelper::centsToDisplay(1000);    // "10.00"
MoneyHelper::centsToDisplay(1999);    // "19.99"

// Display → Storage (for dehydration)
MoneyHelper::displayToCents('10.00'); // 1000
MoneyHelper::displayToCents('19.99'); // 1999
```

### Basis Points Conversion

Convert between basis points (storage) and percentage display:

```php
// Storage → Display
MoneyHelper::basisPointsToDisplay(1000);    // "10.00" (10%)
MoneyHelper::basisPointsToDisplay(2575);    // "25.75" (25.75%)

// Display → Storage
MoneyHelper::displayToBasisPoints('10.00'); // 1000
MoneyHelper::displayToBasisPoints('25.75'); // 2575
```

### Formatting for Display

```php
// Format money with currency symbol
MoneyHelper::formatMoney(1000);        // "RM10.00"
MoneyHelper::formatMoney(1999, 'USD'); // "$19.99"

// Format percentage
MoneyHelper::formatPercentage(1000);   // "10%"
MoneyHelper::formatPercentage(1050);   // "10.5%"

// Get default currency
MoneyHelper::defaultCurrency();        // "MYR" (from config)
```

### Usage in Filament Forms

```php
use Filament\Forms\Components\TextInput;
use AIArmada\FilamentVouchers\Support\MoneyHelper;

TextInput::make('value')
    ->label('Discount Amount')
    ->suffix(fn () => MoneyHelper::defaultCurrency())
    ->formatStateUsing(fn (?int $state) => MoneyHelper::centsToDisplay($state))
    ->dehydrateStateUsing(fn (?string $state) => MoneyHelper::displayToCents($state))
```
