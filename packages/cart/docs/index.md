# AIArmada Cart Documentation

Welcome to the documentation for AIArmada Cart—a modern shopping cart engine for Laravel 12.

## Quick Links

| Guide | Description |
|-------|-------------|
| [Getting Started](getting-started.md) | Installation, configuration, first cart operation |
| [Cart Operations](cart-operations.md) | Adding, updating, removing items; totals and metadata |
| [Conditions & Pricing](conditions.md) | Discounts, taxes, fees, shipping, dynamic rules |
| [Storage Drivers](storage.md) | Session, cache, database; choosing the right driver |
| [Configuration](configuration.md) | Complete configuration reference |
| [Payment Integration](payment-integration.md) | Connect to CHIP, Stripe, or custom gateways |
| [Money & Currency](money-and-currency.md) | Precise financial calculations |
| [User Migration](identifiers-and-migration.md) | Guest-to-user cart migration |
| [Concurrency](concurrency.md) | Handling concurrent modifications |
| [Events](events.md) | Cart lifecycle events |
| [Buyable Products](buyable-products.md) | Integrating product models |
| [API Reference](api-reference.md) | Complete method signatures |
| [Examples](examples.md) | Real-world code patterns |
| [Troubleshooting](troubleshooting.md) | Common issues and solutions |

## Key Concepts

### Instances
A cart **instance** is a named bucket (e.g., `default`, `wishlist`, `quote`). Users can have multiple instances simultaneously.

### Identifiers
The **identifier** determines cart ownership—automatically resolved from authenticated user or session.

### Storage Drivers
- **Session**: Fast, ephemeral, single-device
- **Cache**: Fast, distributed, TTL-based
- **Database**: Persistent, cross-device, analytics-ready

### Conditions
Modify prices at three levels: **item**, **subtotal**, **total**. Support percentages, fixed amounts, and dynamic rules.

### Money Objects
All monetary values use `Akaunting\Money` for precision—no floating-point errors.

## Quick Start

```php
use AIArmada\Cart\Facades\Cart;

// Add item
Cart::add('sku-001', 'Product Name', 29.99, 2, ['size' => 'L']);

// Apply discount
Cart::addDiscount('promo', '10%');

// Get total
echo Cart::total()->format(); // "$53.98"

// Switch instance
Cart::instance('wishlist')->add('sku-002', 'Another Product', 49.99);
```

## Installation

```bash
composer require aiarmada/cart
php artisan vendor:publish --tag=cart-config
php artisan vendor:publish --tag=cart-migrations
php artisan migrate
```

## Getting Help

- Check [Troubleshooting](troubleshooting.md) for common issues
- Review [Examples](examples.md) for code patterns
- Open an issue on GitHub for bugs

---

**Ready?** Start with [Getting Started](getting-started.md) →
