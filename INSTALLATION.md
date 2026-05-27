# Installation Guide

This file is the quick, repository-level install guide for AIArmada Commerce.

For package-specific setup, configuration, webhooks, and Filament registration details, use the canonical docs in `packages/*/docs/02-installation.md`.

## Requirements

- PHP 8.4+
- Laravel 13+
- Composer 2.7+

## Choose your install path

### Install the curated commerce suite

Use the suite when you want the baseline Commerce stack in one go:

```bash
composer require aiarmada/commerce
php artisan commerce:setup
php artisan migrate
```

The `aiarmada/commerce` metapackage is a curated bundle, not the entire monorepo. It currently pulls in:

- `aiarmada/commerce-support`
- `aiarmada/cart` + `aiarmada/filament-cart`
- `aiarmada/cashier`
- `aiarmada/cashier-chip`
- `aiarmada/chip` + `aiarmada/filament-chip`
- `aiarmada/docs` + `aiarmada/filament-docs`
- `aiarmada/inventory` + `aiarmada/filament-inventory`
- `aiarmada/jnt` + `aiarmada/filament-jnt`
- `aiarmada/vouchers` + `aiarmada/filament-vouchers`
- `aiarmada/filament-authz`

### Install individual packages

If you only need part of the stack, require the owning package and its paired Filament package explicitly.

Common examples:

```bash
composer require aiarmada/cart aiarmada/vouchers
composer require aiarmada/chip aiarmada/jnt
composer require aiarmada/affiliates aiarmada/filament-affiliates
```

Other packages such as `checkout`, `orders`, `products`, `pricing`, `tax`, `promotions`, `signals`, and their Filament adapters are also installed individually.

## Configure the application

### Run the setup wizard

For suite installs, the setup wizard is the fastest way to configure supported packages:

```bash
php artisan commerce:setup
```

It helps with:

- CHIP credentials
- J&T credentials
- JSON vs JSONB defaults for PostgreSQL
- generated environment placeholders

You can rerun it safely and use `--force` when you intentionally want to overwrite generated values.

### Or configure manually

Typical environment variables look like this:

```env
# Cart / defaults
CART_DEFAULT_CURRENCY=MYR

# CHIP
CHIP_ENVIRONMENT=sandbox
CHIP_COLLECT_API_KEY=your_api_key
CHIP_COLLECT_BRAND_ID=your_brand_id
CHIP_SEND_API_KEY=your_send_api_key
CHIP_SEND_API_SECRET=your_send_secret

# J&T
JNT_ENVIRONMENT=testing
JNT_CUSTOMER_CODE=your_customer_code
JNT_PASSWORD=your_password

# PostgreSQL JSON columns
COMMERCE_JSON_COLUMN_TYPE=jsonb
```

## Publish config only when needed

Publish only the config files you actually plan to customize:

```bash
php artisan vendor:publish --tag=commerce-config
php artisan vendor:publish --tag=cart-config
php artisan vendor:publish --tag=chip-config
php artisan vendor:publish --tag=jnt-config
php artisan vendor:publish --tag=vouchers-config
```

For package-specific tags, check the package installation page in `packages/<package>/docs/02-installation.md`.

## Run migrations

```bash
php artisan migrate
```

If you are using PostgreSQL and want `jsonb`, set the JSON column type before the first migration run.

## Register Filament plugins

If you installed Filament packages, register them in your panel provider:

```php
use AIArmada\FilamentCart\FilamentCartPlugin;
use AIArmada\FilamentChip\FilamentChipPlugin;
use AIArmada\FilamentInventory\FilamentInventoryPlugin;
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCartPlugin::make(),
            FilamentChipPlugin::make(),
            FilamentInventoryPlugin::make(),
            FilamentVouchersPlugin::make(),
        ]);
}
```

Register additional plugins only for the packages you actually installed.

## Helpful follow-ups

- Create an admin user with `php artisan make:filament-user`
- Start from `docs/index.md` for repo-level guides
- Use `packages/<package>/CONTEXT.md` and `packages/<package>/docs/*.md` as the canonical package references

## Support

- Documentation: [docs/index.md](docs/index.md)
- Issues: [github.com/aiarmada/commerce/issues](https://github.com/aiarmada/commerce/issues)
- Discussions: [github.com/aiarmada/commerce/discussions](https://github.com/aiarmada/commerce/discussions)
