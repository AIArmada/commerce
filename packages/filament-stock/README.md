# Filament Stock

A Filament v5 plugin for administering stock management provided by the AIArmada Stock package.

## Installation

```bash
composer require aiarmada/filament-stock
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=filament-stock-config
```

## Usage

Register the plugin in your Filament panel provider:

```php
use AIArmada\FilamentStock\FilamentStockPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentStockPlugin::make(),
        ]);
}
```

## Features

- **Stock Transactions Resource**: View and manage all stock transactions (inbound/outbound)
- **Stock Reservations Resource**: Monitor active reservations during checkout
- **Stock Stats Widget**: Overview of stock levels, low stock alerts, and reservation metrics
- **Cart Integration**: Deep linking to cart records when `aiarmada/filament-cart` is installed

## License

MIT License - see [LICENSE](LICENSE) file.
