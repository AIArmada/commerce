---
title: Installation
contents: false
---
import Aside from "@components/Aside.astro"
import RadioGroup from "@components/RadioGroup.astro"
import RadioGroupOption from "@components/RadioGroupOption.astro"

AIArmada Commerce requires the following to run:

- PHP 8.4+
- Laravel v12.0+
- Composer v2.7+

Installation comes in two flavors, depending on whether you want to bootstrap the entire commerce suite or pull in individual packages for custom flows:

<div x-data="{ package: (window.location.hash === '#components') ? 'components' : 'suite' }">

<RadioGroup model="package">
    <RadioGroupOption value="suite">
        Commerce suite

        <span slot="description">
            Install the full AIArmada Commerce stack with a single command, including carts, payments, vouchers, shipping, inventory, and Filament admin panels.
        </span>
    </RadioGroupOption>

    <RadioGroupOption value="components">
        Individual packages

        <span slot="description">
            Pull in only the packages you need. Compose your own commerce stack by mixing and matching carts, payments, vouchers, shipping, stock, and Filament plugins.
        </span>
    </RadioGroupOption>
</RadioGroup>

<div x-show="package === 'suite'" x-cloak>

## Installing the commerce suite

Require the meta-package and run the setup wizard:

```bash
composer require aiarmada/commerce

php artisan commerce:setup
php artisan migrate
```

<Aside variant="warning">
    Windows PowerShell ignores `^` characters in version constraints. If Composer errors, run `composer require aiarmada/commerce "~1.0"` instead.
</Aside>

The setup wizard helps you configure environment variables for CHIP payments, J&T shipping, and database settings. You can rerun it at any time:

```bash
php artisan commerce:setup
```

### Create an admin user

```bash
php artisan make:filament-user
```

Visit `/admin` in your browser to explore the Filament panels bundled with the suite.

</div>

<div x-show="package === 'components'" x-cloak>

## Installing individual packages

Install the core packages you need with Composer:

```bash
composer require \
    aiarmada/cart \
    aiarmada/chip \
    aiarmada/vouchers \
    aiarmada/jnt \
    aiarmada/stock
```

Add Filament admin panels when you are ready for back-office tooling:

```bash
composer require \
    aiarmada/filament-cart \
    aiarmada/filament-chip \
    aiarmada/filament-vouchers
```

The shared support utilities are included automatically when you install any package.

### Publishing configuration

Publish configuration files for the packages you installed:

```bash
php artisan vendor:publish --tag=cart-config
php artisan vendor:publish --tag=chip-config
php artisan vendor:publish --tag=vouchers-config
php artisan vendor:publish --tag=jnt-config
php artisan vendor:publish --tag=stock-config
```

### Registering Filament plugins

If you added Filament packages, register them inside your panel provider:

```php
use AIArmada\FilamentCart\FilamentCartPlugin;
use AIArmada\FilamentChip\FilamentChipPlugin;
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->plugins([
            FilamentCartPlugin::make(),
            FilamentChipPlugin::make(),
            FilamentVouchersPlugin::make(),
        ]);
}
```

</div>

</div>

## Post-install checklist

1. Publish configuration using `php artisan vendor:publish --tag=commerce-config` or the individual tags shown above.
2. Add the generated environment keys (CHIP, J&T, vouchers, stock) to your `.env` file.
3. Run database migrations with `php artisan migrate`.
4. Review the package docs for storage drivers, webhooks, and Filament setup.

### Database JSON configuration tips

- Replace-on-max: Control behavior when the cart already has the maximum number of vouchers.

    Set `vouchers.cart.replace_when_max_reached` (env: `VOUCHERS_REPLACE_WHEN_MAX_REACHED`) to `true` to automatically replace the existing voucher, or `false` to throw a validation error.

- JSON vs JSONB (PostgreSQL only): Migrations default to portable `json` columns. To use `jsonb` and create GIN indexes on a fresh install, set one of:

    ```env
    COMMERCE_JSON_COLUMN_TYPE=jsonb
    # or override per package
    VOUCHERS_JSON_COLUMN_TYPE=jsonb
    CART_JSON_COLUMN_TYPE=jsonb
    CHIP_JSON_COLUMN_TYPE=jsonb
    ```
    Or run the interactive setup:

    ```bash
    php artisan commerce:configure-database
    ```

    Enable this BEFORE running `php artisan migrate`. Existing installs should use a dedicated migration to convert types if needed.

    When `jsonb` is enabled and you're using PostgreSQL, GIN indexes are created automatically where beneficial:
    - Vouchers: `applicable_products`, `excluded_products`, `applicable_categories`, `metadata`
    - Cart: `items`, `conditions`, `metadata`
    - CHIP: `purchases.metadata`

    Other JSON fields remain unindexed by default to avoid unnecessary index bloat; add custom indexes based on your query patterns.

Need a refresher on the suite features? Head over to the [Getting Started](../02-getting-started/01-cart-basics.md) section.
