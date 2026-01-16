# Filament Promotions

A Filament v5 plugin for managing promotional discounts in your admin panel.

## Features

- **Full CRUD** — Create, view, edit, and delete promotions
- **Rich Table** — Sortable, filterable promotion list with badges
- **Promotion Types** — Percentage, fixed amount, and Buy X Get Y
- **Usage Tracking** — Monitor promotion usage statistics
- **Owner Scoping** — Multi-tenant support out of the box
- **Stats Widget** — Dashboard overview of promotion metrics

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5.0+
- aiarmada/promotions package

## Installation

```bash
composer require aiarmada/filament-promotions
```

Register the plugin in your Filament panel:

```php
use AIArmada\FilamentPromotions\FilamentPromotionsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentPromotionsPlugin::make(),
        ]);
}
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=filament-promotions-config
```

## Configuration

```php
// config/filament-promotions.php
return [
    'navigation_group' => 'Marketing',

    'resources' => [
        'navigation_sort' => [
            'promotions' => 10,
        ],
    ],

    'tables' => [
        'poll' => null,
    ],

    'features' => [
        'widgets' => true,
    ],
];
```

## Usage

### Managing Promotions

The plugin provides a `PromotionResource` with:

- **List View** — Table with search, filters, and bulk actions
- **Create View** — Form to create new promotions
- **Edit View** — Update existing promotions
- **View View** — Detailed promotion information

### Promotion Types

The Filament-aware `PromotionType` enum includes:

| Type | Label | Icon | Color |
|------|-------|------|-------|
| `Percentage` | Percentage Off | receipt-percent | success |
| `Fixed` | Fixed Amount | currency-dollar | info |
| `BuyXGetY` | Buy X Get Y | gift | warning |

### Stats Widget

Add the stats widget to your dashboard:

```php
use AIArmada\FilamentPromotions\Widgets\PromotionStatsWidget;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            PromotionStatsWidget::class,
        ]);
}
```

## Multi-tenancy

The resource respects owner scoping from the promotions package. Configure in `config/promotions.php`:

```php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => true,
    ],
],
```

## License

MIT License. See [LICENSE](LICENSE) for details.
