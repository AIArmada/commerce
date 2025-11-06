# Installation Guide

Complete guide for installing AIArmada Commerce packages in your Laravel application.

## Requirements

- PHP 8.4+
- Laravel 12.0+
- Composer

## Installation Options

### Option 1: Install Complete Suite

Install all packages at once:

```bash
composer require aiarmada/commerce
```

This meta-package includes:
- `aiarmada/commerce-support` - Core utilities
- `aiarmada/cart` - Shopping cart
- `aiarmada/stock` - Inventory management
- `aiarmada/vouchers` - Voucher system
- `aiarmada/chip` - CHIP payment gateway
- `aiarmada/jnt` - J&T Express shipping
- `aiarmada/docs` - Documentation
- `aiarmada/filament-cart` - Filament cart admin
- `aiarmada/filament-chip` - Filament payment admin
- `aiarmada/filament-vouchers` - Filament voucher admin

### Option 2: Install Individual Packages

Pick only what you need:

```bash
# Core packages
composer require aiarmada/cart
composer require aiarmada/stock
composer require aiarmada/vouchers

# Payment gateway
composer require aiarmada/chip

# Shipping
composer require aiarmada/jnt

# Filament admin interfaces
composer require aiarmada/filament-cart
composer require aiarmada/filament-chip
composer require aiarmada/filament-vouchers
```

## Post-Installation

### 1. Run Interactive Setup (Recommended)

```bash
php artisan commerce:setup
```

This interactive wizard will guide you through:
- CHIP payment gateway configuration
- J&T Express shipping configuration
- Database settings (JSON vs JSONB for PostgreSQL)
- Environment variable setup

Use `--force` flag to overwrite existing values:

```bash
php artisan commerce:setup --force
```

### 2. Or Manually Configure Environment Variables

If you prefer manual configuration, add to your `.env` file:

```env
# Cart Configuration
CART_STORAGE_DRIVER=database
CART_DEFAULT_CURRENCY=MYR

# CHIP Payment Gateway (if using)
CHIP_ENVIRONMENT=sandbox
CHIP_COLLECT_API_KEY=your_api_key
CHIP_COLLECT_BRAND_ID=your_brand_id
CHIP_SEND_API_KEY=your_send_api_key
CHIP_SEND_API_SECRET=your_send_secret

# J&T Express Shipping (if using)
JNT_ENVIRONMENT=testing
JNT_CUSTOMER_CODE=your_customer_code
JNT_PASSWORD=your_password

# Database Configuration (PostgreSQL users)
COMMERCE_JSON_COLUMN_TYPE=jsonb
```

### 3. Publish Configuration Files (Optional)

### 3. Publish Configuration Files (Optional)

Only publish if you need to customize default behavior:

```bash
# Publish all commerce configs
php artisan vendor:publish --tag=commerce-config

# Or publish individually
php artisan vendor:publish --tag=cart-config
php artisan vendor:publish --tag=chip-config
php artisan vendor:publish --tag=jnt-config
php artisan vendor:publish --tag=stock-config
php artisan vendor:publish --tag=vouchers-config
```

### 4. Run Migrations

### 4. Run Migrations

```bash
php artisan migrate
```

## Quick Start Guide

After installation, the fastest way to get started:

```bash
# 1. Install the complete suite
composer require aiarmada/commerce

# 2. Run the interactive setup
php artisan commerce:setup

# 3. Run migrations
php artisan migrate

# 4. Start using it!
```

That's it! You're ready to use Commerce.

## Package-Specific Setup

### Cart

Basic usage:

```php
use AIArmada\Cart\Facades\Cart;

// Add item
Cart::add('product-1', 'Product Name', 99.99, 2);

// Get contents
$items = Cart::content();

// Get total
$total = Cart::total();
```

Configuration file: `config/cart.php`

### CHIP Payment Gateway

Process payments:

```php
use AIArmada\Chip\Facades\Chip;

$payment = Chip::collect()->createPurchase([
    'amount' => 10000, // in cents
    'currency' => 'MYR',
    'customer' => [...],
]);
```

Configuration file: `config/chip.php`

### J&T Express Shipping

Create shipments:

```php
use AIArmada\Jnt\Facades\Jnt;

$result = Jnt::createOrder([
    'orderNo' => 'ORDER-123',
    'receiver' => [...],
    'items' => [...],
]);
```

Configuration file: `config/jnt.php`

### Stock Management

Track inventory:

```php
use AIArmada\Stock\Facades\Stock;

// Add stock
Stock::add($product, 100, 'restock');

// Remove stock
Stock::remove($product, 5, 'sale');

// Check available
$available = Stock::available($product);
```

Configuration file: `config/stock.php`

### Vouchers

Apply discounts:

```php
use AIArmada\Cart\Facades\Cart;

// Apply voucher to cart
Cart::applyVoucher('SAVE20');

// Check applied vouchers
$vouchers = Cart::getAppliedVouchers();
```

Configuration file: `config/vouchers.php`

## Filament Integration

If using Filament admin panels, register the plugins in your panel provider:

```php
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(\AIArmada\FilamentCart\FilamentCartPlugin::make())
        ->plugin(\AIArmada\FilamentChip\FilamentChipPlugin::make())
        ->plugin(\AIArmada\FilamentVouchers\FilamentVouchersPlugin::make());
}
```

## Troubleshooting

### Package Not Found

```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Migration Issues

```bash
# Check status
php artisan migrate:status

# Fresh install
php artisan migrate:fresh
```

### Configuration Not Loading

```bash
php artisan config:clear
php artisan config:cache
```

## Uninstalling

Remove a package:

```bash
# 1. Remove via composer
composer remove aiarmada/chip

# 2. Delete published config
rm config/chip.php

# 3. Rollback migrations (CAUTION: deletes data)
php artisan migrate:rollback --path=vendor/aiarmada/chip/database/migrations
```

## Getting Help

- **Documentation**: [github.com/aiarmada/commerce](https://github.com/aiarmada/commerce)
- **Issues**: [github.com/aiarmada/commerce/issues](https://github.com/aiarmada/commerce/issues)
- **Discussions**: [github.com/aiarmada/commerce/discussions](https://github.com/aiarmada/commerce/discussions)

## Next Steps

- Read package-specific README files in `packages/*/README.md`
- Check example implementations in the docs
- Join the community discussions
