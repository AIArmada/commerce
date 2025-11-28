# Widgets

Dashboard widgets for monitoring voucher activity.

## Available Widgets

### VoucherStatsWidget

Overview statistics for all vouchers:

- **Total Vouchers** – all vouchers in the system
- **Active Vouchers** – currently redeemable
- **Upcoming Launches** – scheduled to activate
- **Manual Redemptions** – processed by staff
- **Discount Granted** – total value redeemed

```php
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;

// Automatically registered by the plugin
// Or add to a custom dashboard:
public function getWidgets(): array
{
    return [
        VoucherStatsWidget::class,
    ];
}
```

---

### VoucherCartStatsWidget

Cart-specific voucher metrics showing how vouchers are used in shopping carts.

```php
use AIArmada\FilamentVouchers\Widgets\VoucherCartStatsWidget;
```

---

### VoucherWalletStatsWidget

Statistics for wallet-based voucher entries (saved vouchers for users).

```php
use AIArmada\FilamentVouchers\Widgets\VoucherWalletStatsWidget;
```

---

### VoucherUsageTimelineWidget

Visual timeline of voucher redemptions. Useful on voucher detail pages.

```php
use AIArmada\FilamentVouchers\Widgets\VoucherUsageTimelineWidget;

// Pass a voucher record to display its timeline
```

---

### AppliedVoucherBadgesWidget

Displays badges for vouchers currently applied to a cart. Best used on cart detail pages.

```php
use AIArmada\FilamentVouchers\Widgets\AppliedVoucherBadgesWidget;
```

---

### QuickApplyVoucherWidget

Inline form for quickly applying a voucher code to a cart.

```php
use AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget;
```

---

### VoucherSuggestionsWidget

Smart suggestions for applicable vouchers based on cart contents.

```php
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;
```

---

## Adding Widgets to Pages

### Dashboard

Register widgets in your panel provider:

```php
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            VoucherStatsWidget::class,
        ]);
}
```

### Resource Pages

Add widgets to resource page headers or footers:

```php
protected function getHeaderWidgets(): array
{
    return [
        VoucherStatsWidget::class,
    ];
}

protected function getFooterWidgets(): array
{
    return [
        VoucherUsageTimelineWidget::class,
    ];
}
```

---

## Widget Styling

Widgets use Filament's built-in styling and respect your panel's theme. The `VoucherStatsWidget` displays as a 5-column stats overview by default.

```php
// Override columns in a custom widget
protected function getColumns(): int
{
    return 4;
}
```

---

## Currency Formatting

Monetary values use the configured default currency:

```php
// config/filament-vouchers.php
'default_currency' => 'MYR',
```

Widgets use `Akaunting\Money\Money` for proper currency formatting.
