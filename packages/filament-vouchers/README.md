# Filament Vouchers

A Filament plugin for managing vouchers powered by the `aiarmada/vouchers` package.

## Installation

```bash
composer require aiarmada/filament-vouchers
```

Register the plugin in your Filament panel provider:

```php
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentVouchersPlugin::make(),
        ]);
}
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=filament-vouchers-config
```

### Available Options

```php
return [
    // Navigation group for resources
    'navigation_group' => 'E-commerce',

    // Default currency for monetary displays
    'default_currency' => 'MYR',

    // Table polling interval (seconds or null to disable)
    'polling_interval' => 60,

    // Resource navigation ordering
    'resources' => [
        'navigation_sort' => [
            'vouchers' => 40,
            'voucher_usage' => 41,
        ],
    ],

    // Order resource for linking (set class-string or null)
    'order_resource' => null,

    // Owner types for multi-tenant voucher assignment
    'owners' => [
        // [
        //     'label' => 'Vendors',
        //     'model' => App\Models\Vendor::class,
        //     'title_attribute' => 'name',
        //     'subtitle_attribute' => 'email',
        //     'search_attributes' => ['name', 'email'],
        // ],
    ],
];
```

## Resources

### Voucher Resource

Manage vouchers with:

- Code, name, description
- Type (percentage, fixed, free shipping)
- Value with currency
- Usage limits (global and per-user)
- Date scheduling (starts_at, expires_at)
- Condition targeting via DSL presets
- Owner assignment for multi-tenant setups

### Voucher Usage Resource

Track redemptions with:

- User/redeemer information
- Discount amounts applied
- Redemption channel (automatic, manual, API)
- Order linking (when configured)

## Widgets

The plugin registers these widgets:

- **VoucherStatsWidget** – Overview stats (total, active, upcoming, manual redemptions, discounts)
- **VoucherCartStatsWidget** – Cart-specific voucher metrics
- **VoucherWalletStatsWidget** – Wallet entry statistics
- **VoucherUsageTimelineWidget** – Redemption timeline for a voucher
- **AppliedVoucherBadgesWidget** – Shows applied vouchers on a cart
- **QuickApplyVoucherWidget** – Inline voucher application form
- **VoucherSuggestionsWidget** – Smart voucher suggestions for carts

## Cart Integration

When `aiarmada/filament-cart` is installed, the plugin automatically enables:

- Deep linking from usage records to cart views
- Voucher application actions on cart pages
- Applied voucher widgets on cart detail pages

Use the provided actions in your cart resource:

```php
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;

protected function getHeaderActions(): array
{
    return [
        CartVoucherActions::applyVoucher(),
        CartVoucherActions::showAppliedVouchers(),
    ];
}
```

## Multi-Tenant Ownership

Configure owner types in the config to enable voucher assignment to specific entities (vendors, stores, teams):

```php
'owners' => [
    [
        'label' => 'Store',
        'model' => App\Models\Store::class,
        'title_attribute' => 'name',
        'search_attributes' => ['name', 'code'],
    ],
],
```

## License

MIT License. See [LICENSE](LICENSE) for details.
