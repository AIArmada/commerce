# Installation

## Requirements

- PHP ^8.4
- Laravel ^12.0
- Filament ^5.0
- [aiarmada/chip](../../../chip)

## Install

```bash
composer require aiarmada/filament-chip
```

## Register Plugin

Add to your Filament panel provider:

```php
use AIArmada\FilamentChip\FilamentChipPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(FilamentChipPlugin::make());
}
```

## Publish Config (Optional)

```bash
php artisan vendor:publish --tag="filament-chip-config"
```

## Billing Portal (Optional)

Requires `aiarmada/cashier-chip`:

```bash
composer require aiarmada/cashier-chip
```

Register the billing panel:

```php
// config/app.php
'providers' => [
    AIArmada\FilamentChip\BillingPanelProvider::class,
],
```
