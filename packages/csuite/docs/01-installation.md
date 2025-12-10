# Installation

Complete installation guide for AIArmada Commerce.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.4+ |
| Laravel | 12.0+ |
| Filament | 5.0+ (for admin panels) |
| PostgreSQL or MySQL | 8.0+ |

## Full Suite Installation

Install all Commerce packages at once:

```bash
composer require aiarmada/commerce
```

This includes all core packages, payment integrations, and Filament admin panels.

## Individual Package Installation

Install only what you need:

```bash
# Shopping cart
composer require aiarmada/cart

# With Filament admin
composer require aiarmada/cart aiarmada/filament-cart

# Vouchers/discounts
composer require aiarmada/vouchers aiarmada/filament-vouchers

# Payment processing
composer require aiarmada/chip aiarmada/cashier aiarmada/cashier-chip

# Document generation
composer require aiarmada/docs aiarmada/filament-docs

# Shipping
composer require aiarmada/jnt aiarmada/filament-jnt

# Inventory management
composer require aiarmada/inventory aiarmada/filament-inventory
```

## Post-Installation Steps

### 1. Publish Configurations

```bash
# All configs at once
php artisan vendor:publish --provider="AIArmada\\Cart\\CartServiceProvider"
php artisan vendor:publish --provider="AIArmada\\Vouchers\\VouchersServiceProvider"
php artisan vendor:publish --provider="AIArmada\\Chip\\ChipServiceProvider"
php artisan vendor:publish --provider="AIArmada\\Docs\\DocsServiceProvider"

# Or use tags
php artisan vendor:publish --tag=cart-config
php artisan vendor:publish --tag=vouchers-config
php artisan vendor:publish --tag=chip-config
php artisan vendor:publish --tag=docs-config
```

### 2. Run Migrations

```bash
php artisan migrate
```

### 3. Configure Environment

Use the setup wizard:

```bash
php artisan commerce:setup
```

Or manually add to `.env`:

```env
# CHIP Payment Gateway
CHIP_BRAND_ID=your-brand-id
CHIP_SECRET_KEY=your-secret-key
CHIP_MODE=sandbox

# J&T Express
JNT_API_KEY=your-api-key
JNT_CUSTOMER_CODE=your-customer-code

# Database (PostgreSQL users)
COMMERCE_JSON_COLUMN_TYPE=jsonb
```

### 4. Register Filament Plugins

In your `AdminPanelProvider.php`:

```php
use AIArmada\FilamentCart\FilamentCartPlugin;
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;
use AIArmada\FilamentDocs\FilamentDocsPlugin;
use AIArmada\FilamentChip\FilamentChipPlugin;
use AIArmada\FilamentInventory\FilamentInventoryPlugin;
use AIArmada\FilamentPermissions\FilamentPermissionsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->plugins([
            FilamentCartPlugin::make(),
            FilamentVouchersPlugin::make(),
            FilamentDocsPlugin::make(),
            FilamentChipPlugin::make(),
            FilamentInventoryPlugin::make(),
            FilamentPermissionsPlugin::make(),
        ]);
}
```

## Verification

After installation, verify everything is working:

```bash
# Check migrations
php artisan migrate:status

# Clear caches
php artisan config:clear
php artisan cache:clear

# Regenerate Filament components
php artisan filament:cache-components
```

Visit your Filament panel to see the new resources.

## Troubleshooting

### Migrations Not Running

Ensure package discovery is enabled in `composer.json`:

```json
{
    "extra": {
        "laravel": {
            "dont-discover": []
        }
    }
}
```

### Resources Not Showing in Filament

1. Check plugin registration in panel provider
2. Clear component cache: `php artisan filament:cache-components`
3. Clear config cache: `php artisan config:clear`

### PostgreSQL JSONB Issues

Set the environment variable before running migrations:

```env
COMMERCE_JSON_COLUMN_TYPE=jsonb
```

If migrations already ran with `json`, create a migration to alter columns.
