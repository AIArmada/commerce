# Filament Vouchers

> Filament 4 plugin for managing vouchers powered by the `aiarmada/vouchers` package.

## Features

- **Complete Voucher Management** – create, edit, view, and delete vouchers with a rich Filament UI.
- **Usage Tracking** – monitor redemptions, discounts applied, and user activity.
- **Dashboard Widgets** – stats overview, cart metrics, wallet stats, and usage timelines.
- **Cart Integration** – seamless voucher application when `aiarmada/filament-cart` is installed.
- **Multi-Tenant Support** – assign vouchers to specific owners (vendors, stores, teams).

## Installation

```bash
composer require aiarmada/filament-vouchers
```

Register the plugin in your panel provider:

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

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-vouchers-config
```

---

## Configuration

```php
// config/filament-vouchers.php
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
    'owners' => [],
];
```

---

## Resources

### Voucher Resource

Manage vouchers with full CRUD operations:

- **Code & Name** – unique voucher identifier and display name
- **Type** – percentage, fixed amount, or free shipping
- **Value** – discount amount with currency support
- **Usage Limits** – global and per-user redemption limits
- **Scheduling** – start and expiry dates
- **Conditions** – target rules via DSL presets
- **Owner Assignment** – multi-tenant voucher ownership

### Voucher Usage Resource

Track all voucher redemptions:

- User/redeemer information
- Discount amounts applied
- Redemption channel (automatic, manual, API)
- Order linking when configured

---

## Widgets

The plugin provides these dashboard widgets:

| Widget | Description |
|--------|-------------|
| `VoucherStatsWidget` | Overview stats (total, active, upcoming, redemptions) |
| `VoucherCartStatsWidget` | Cart-specific voucher metrics |
| `VoucherWalletStatsWidget` | Wallet entry statistics |
| `VoucherUsageTimelineWidget` | Redemption timeline for a voucher |
| `AppliedVoucherBadgesWidget` | Shows applied vouchers on a cart |
| `QuickApplyVoucherWidget` | Inline voucher application form |
| `VoucherSuggestionsWidget` | Smart voucher suggestions for carts |

---

## Cart Integration

When `aiarmada/filament-cart` is installed, the plugin automatically enables:

- Deep linking from usage records to cart views
- Voucher application actions on cart pages
- Applied voucher widgets on cart detail pages

### Using Cart Actions

Add voucher actions to your cart resource pages:

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

**Available actions:**

- `applyVoucher()` – modal form to enter and apply a voucher code
- `showAppliedVouchers()` – modal displaying currently applied vouchers
- `removeVoucher($code)` – remove a specific voucher from cart

---

## Multi-Tenant Ownership

Configure owner types to enable voucher assignment to specific entities:

```php
// config/filament-vouchers.php
'owners' => [
    [
        'label' => 'Store',
        'model' => App\Models\Store::class,
        'title_attribute' => 'name',
        'subtitle_attribute' => 'email',      // Optional
        'search_attributes' => ['name', 'code'],
    ],
    [
        'label' => 'Vendor',
        'model' => App\Models\Vendor::class,
        'title_attribute' => 'company_name',
        'search_attributes' => ['company_name', 'email'],
    ],
],
```

When configured, a searchable owner selector appears in the voucher form.

---

## Documentation

- [Configuration Reference](docs/configuration.md) – All configuration options
- [Widgets](docs/widgets.md) – Available widgets and customization
- [Cart Integration](docs/cart-integration.md) – Voucher-cart integration guide

---

## License

MIT License. See [LICENSE](LICENSE) for details.
