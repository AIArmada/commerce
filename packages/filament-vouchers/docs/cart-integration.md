# Cart Integration

Integrating vouchers with Filament Cart for seamless discount application.

## Prerequisites

Install both packages:

```bash
composer require aiarmada/filament-cart aiarmada/filament-vouchers
```

When both packages are installed, Filament Vouchers automatically enables cart integration features.

---

## Available Actions

Use `CartVoucherActions` to add voucher functionality to cart pages:

```php
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;
```

### Apply Voucher

Opens a modal form to enter and apply a voucher code:

```php
CartVoucherActions::applyVoucher()
```

Features:
- Text input for voucher code
- Validation and error handling
- Success/failure notifications
- Automatic cart refresh

### Show Applied Vouchers

Displays currently applied vouchers in a modal:

```php
CartVoucherActions::showAppliedVouchers()
```

Features:
- Lists all applied voucher codes
- Shows voucher type (percentage, fixed, etc.)
- Empty state when no vouchers applied

### Remove Voucher

Creates an action to remove a specific voucher:

```php
CartVoucherActions::removeVoucher('SUMMER2024')
```

Features:
- Confirmation dialog
- Success notification
- Automatic cart refresh

---

## Usage in Cart Resource

### View Cart Page

```php
// app/Filament/Resources/CartResource/Pages/ViewCart.php
namespace App\Filament\Resources\CartResource\Pages;

use AIArmada\FilamentCart\Resources\CartResource\Pages\ViewCart as BaseViewCart;
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;

class ViewCart extends BaseViewCart
{
    protected function getHeaderActions(): array
    {
        return array_merge(parent::getHeaderActions(), [
            CartVoucherActions::applyVoucher(),
            CartVoucherActions::showAppliedVouchers(),
        ]);
    }
}
```

### Edit Cart Page

```php
// app/Filament/Resources/CartResource/Pages/EditCart.php
namespace App\Filament\Resources\CartResource\Pages;

use AIArmada\FilamentCart\Resources\CartResource\Pages\EditCart as BaseEditCart;
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;

class EditCart extends BaseEditCart
{
    protected function getHeaderActions(): array
    {
        return [
            CartVoucherActions::applyVoucher(),
            ...parent::getHeaderActions(),
        ];
    }
}
```

---

## Cart Widgets

Add voucher widgets to cart detail pages:

### Applied Voucher Badges

Shows badges for applied vouchers:

```php
use AIArmada\FilamentVouchers\Widgets\AppliedVoucherBadgesWidget;

protected function getHeaderWidgets(): array
{
    return [
        AppliedVoucherBadgesWidget::class,
    ];
}
```

### Quick Apply Widget

Inline voucher application form:

```php
use AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget;

protected function getFooterWidgets(): array
{
    return [
        QuickApplyVoucherWidget::class,
    ];
}
```

### Voucher Suggestions

Smart suggestions based on cart contents:

```php
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;

protected function getFooterWidgets(): array
{
    return [
        VoucherSuggestionsWidget::class,
    ];
}
```

---

## Error Handling

The integration handles common voucher errors gracefully:

| Error | User Message |
|-------|-------------|
| Invalid code | "Voucher Application Failed" with specific reason |
| Expired voucher | Shows expiration message |
| Usage limit reached | Shows limit exceeded message |
| Minimum not met | Shows minimum requirement |

All errors are logged for debugging:

```php
Log::warning('Voucher application failed', [
    'code' => $code,
    'cart_id' => $record->id,
    'error' => $exception->getMessage(),
]);
```

---

## Deep Linking

Voucher usage records automatically link to cart detail pages when `aiarmada/filament-cart` is installed. Click a cart reference in the usage table to view the full cart.

---

## Bridge Service

The `FilamentCartBridge` service manages integration between the two packages:

```php
use AIArmada\FilamentVouchers\Support\Integrations\FilamentCartBridge;

$bridge = app(FilamentCartBridge::class);

// Check if cart package is available
if ($bridge->isAvailable()) {
    // Integration features enabled
}
```

The bridge is warmed automatically during Filament serving, ensuring integration hooks are ready before rendering.
