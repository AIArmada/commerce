# Filament CHIP

Filament admin plugin for exploring CHIP payment data.

## Installation

```bash
composer require aiarmada/filament-chip
```

Register the plugin in your Filament panel:

```php
use AIArmada\FilamentChip\FilamentChipPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(FilamentChipPlugin::make());
}
```

## Resources

The plugin provides three Filament resources under **CHIP Operations**:

| Resource | Description |
|----------|-------------|
| Purchases | View payment purchases with status, amounts, and client info |
| Payments | Track payment transactions, fees, and settlements |
| Clients | Browse customer profiles and contact information |

All resources are read-only with real-time polling.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag="filament-chip-config"
```

Key options in `config/filament-chip.php`:

```php
'navigation_group' => 'CHIP Operations',
'polling_interval' => '45s',
'tables' => [
    'created_on_format' => 'Y-m-d H:i:s',
    'amount_precision' => 2,
],
```

## Billing Portal (Optional)

A customer-facing billing portal for managing subscriptions and payment methods.

**Requires:** `aiarmada/cashier-chip`

Register the billing panel provider:

```php
// config/app.php
'providers' => [
    AIArmada\FilamentChip\BillingPanelProvider::class,
],
```

Access at `/billing` with pages for:
- Dashboard
- Subscriptions
- Payment Methods  
- Invoices

## Requirements

- PHP ^8.4
- Laravel ^12.0
- Filament ^5.0
- aiarmada/chip

## License

MIT
