# AIArmada Commerce Documentation

**Version**: 0.1.0  
**Laravel**: ^12.0 | **PHP**: ^8.4 | **Filament**: ^5.0

---

## Welcome 👋

AIArmada Commerce is a comprehensive Laravel e-commerce monorepo providing production-ready packages for shopping carts, payment processing, vouchers, shipping, and inventory management.

**Key Features:**
- 🛒 **Shopping Cart** with session/cache/database storage
- 💳 **CHIP Payment Gateway** with webhook support
- 🎟️ **Voucher System** with flexible conditions
- 📦 **J&T Shipping** integration
- 📊 **Stock Management** with reservations
- 🎨 **Filament Admin Panels** for all packages
- 🧪 **Comprehensive Testing** with Pest v4
- 📚 **Extensive Documentation** with code examples

---

## Quick Start

### Installation

```bash
# Install complete ecosystem
composer require aiarmada/commerce

# Or install individual packages
composer require aiarmada/cart
composer require aiarmada/chip
composer require aiarmada/vouchers
```

### Configuration

```bash
# Publish configs
php artisan vendor:publish --tag=commerce-config

# Run migrations
php artisan migrate

# Create admin user (for Filament)
php artisan make:filament-user
```

### First Cart

```php
use AIArmada\Cart\Conditions\TargetPresets;
use AIArmada\Cart\Facades\Cart;

// Add item
Cart::add('prod-001', 'T-Shirt', 2999, 2);

// Apply discount
Cart::applyCondition([
    'name' => 'Holiday Sale',
    'type' => 'discount',
    'target_definition' => TargetPresets::cartSubtotal()->toArray(),
    'value' => '-10%', // strings keep operator intent
]);

// Get total
$total = Cart::total(); // Money instance
echo $total->format(); // "RM53.98"
```

---

## Documentation Structure

### 📖 Introduction

Get started with AIArmada Commerce:

- **[Overview](01-introduction/01-overview.md)** - Architecture, philosophy, use cases
- **[Installation](01-introduction/02-installation.md)** - Setup guide, configuration, verification

### 🚀 Getting Started

Build your first e-commerce features:

- **[Cart Basics](02-getting-started/01-cart-basics.md)** - Add items, apply conditions, calculate totals
- **[Payment Integration](02-getting-started/02-payment-integration.md)** - Accept payments with CHIP
- **[Voucher System](02-getting-started/03-voucher-system.md)** - Create and validate discount codes
- **[Shipping Integration](02-getting-started/04-shipping-integration.md)** - J&T Express shipments
- **[Stock Management](02-getting-started/05-stock-management.md)** - Track inventory and reservations

### 📦 Package Reference

Deep dive into each package:

| Package | Description | Documentation |
|---------|-------------|---------------|
| **aiarmada/cart** | Shopping cart system | [Cart Docs](03-packages/01-cart.md) |
| **aiarmada/chip** | CHIP payment gateway | [CHIP Docs](03-packages/02-chip.md) |
| **aiarmada/vouchers** | Voucher/coupon system | [Vouchers Docs](03-packages/03-vouchers.md) |
| **aiarmada/jnt** | J&T Express shipping | [JNT Docs](03-packages/04-jnt.md) |
| **aiarmada/stock** | Inventory management | [Stock Docs](03-packages/05-stock.md) |
| **aiarmada/docs** | Documentation package | [Docs Docs](03-packages/06-docs.md) |
| **aiarmada/filament-cart** | Cart admin panel | [Filament Cart Docs](03-packages/07-filament-cart.md) |
| **aiarmada/filament-chip** | Payment admin panel | [Filament CHIP Docs](03-packages/08-filament-chip.md) |
| **aiarmada/filament-vouchers** | Voucher admin panel | [Filament Vouchers Docs](03-packages/09-filament-vouchers.md) |
| **aiarmada/commerce-support** | Shared utilities | [Support Docs](03-packages/10-support.md) |

### 🛠️ Support Utilities

Shared tools across packages:

- **[Support Utilities](04-support-utilities.md)** - Exceptions, HTTP client, MoneyHelper, enum concerns

### 📈 Guides

Advanced topics and maintenance:

- **[Upgrade Guide](05-upgrade-guide.md)** - Migrate between versions
- **[Deployment Guide](06-deployment.md)** - Production deployment checklist

---

## Package Matrix

### Core Packages

| Package | Cart | Payments | Vouchers | Shipping | Inventory |
|---------|:----:|:--------:|:--------:|:--------:|:---------:|
| **aiarmada/cart** | ✅ | | | | |
| **aiarmada/chip** | | ✅ | | | |
| **aiarmada/vouchers** | | | ✅ | | |
| **aiarmada/jnt** | | | | ✅ | |
| **aiarmada/stock** | | | | | ✅ |

### Filament Plugins

| Package | Purpose | Dependencies |
|---------|---------|--------------|
| **aiarmada/filament-cart** | Cart admin panel | cart, filament ^5.0 |
| **aiarmada/filament-chip** | Payment admin panel | chip, filament ^5.0 |
| **aiarmada/filament-vouchers** | Voucher admin panel | vouchers, filament ^5.0 |

### Infrastructure

| Package | Purpose |
|---------|---------|
| **aiarmada/commerce-support** | Shared utilities (exceptions, HTTP client, helpers) |
| **aiarmada/docs** | Documentation package |
| **aiarmada/commerce** | Meta-package (installs all packages) |

---

## Key Features by Package

### 🛒 Cart (`aiarmada/cart`)

- Multiple storage drivers (session, cache, database)
- Dynamic conditions (discounts, taxes, shipping)
- Cart instances (cart, wishlist, saved-for-later)
- Money handling with Akaunting Money
- Optimistic locking for database storage

### 💳 CHIP (`aiarmada/chip`)

- CHIP Collect & Send integration
- Webhook signature verification
- Recurring payment tokens
- Client management
- Refunds and captures

### 🎟️ Vouchers (`aiarmada/vouchers`)

- Fixed, percentage, free shipping types
- Usage limits (per user, total)
- Date range restrictions
- Minimum purchase requirements
- Multi-staff support with store assignments

### 📦 J&T Express (`aiarmada/jnt`)

- Shipment creation and tracking
- Rate calculation
- Label generation
- Webhook support
- Multi-origin support

### 📊 Stock (`aiarmada/stock`)

- Product-level inventory tracking
- Stock reservations during checkout
- Low stock alerts
- Multi-location support
- Stock movement history

---

## Common Workflows

### E-Commerce Checkout

```php
// 1. Add items to cart
Cart::add('prod-001', 'Product', 2999, 2);

// 2. Apply voucher
$voucher = Voucher::findByCode('SAVE10');
Cart::applyVoucher($voucher);

// 3. Calculate shipping
$shipping = JNT::calculateRate($address);
Cart::applyCondition([
    'name' => 'Shipping',
    'type' => 'shipping',
    'target' => 'cart@grand_total/aggregate',
    'value' => $shipping,
    'is_percentage' => false,
]);

// 4. Create payment
$purchase = Chip::createPurchase([
    'amount' => Cart::total()->getAmount(),
    'reference' => Cart::identifier(),
]);

// 5. Redirect to checkout
return redirect($purchase->checkout_url);
```

### Admin Panel Access

After installation:

1. Create admin user: `php artisan make:filament-user`
2. Navigate to: `https://your-app.test/admin`
3. Manage:
   - **Commerce > Carts**: View shopping carts
   - **Payments > Purchases**: Manage CHIP purchases
   - **Vouchers > Vouchers**: Create discount codes
   - **Shipping > Shipments**: Track J&T shipments
   - **Inventory > Stock**: Monitor stock levels

---

## Testing

All packages include comprehensive Pest v4 tests:

```bash
# Run all tests
composer test

# Test specific package
vendor/bin/pest packages/cart/tests

# Browser tests (Filament plugins)
vendor/bin/pest packages/filament-cart/tests
```

---

## Version Information

**Current Version**: 0.1.0 (Initial Release)

**Requirements**:
- PHP: ^8.4
- Laravel: ^12.0
- Filament: ^5.0 (for admin panels)
- PostgreSQL: 14+ (recommended)

**Release Date**: November 2025

---

## Getting Help

### Documentation

- **GitHub**: [github.com/aiarmada/commerce](https://github.com/aiarmada/commerce)
- **Issues**: [github.com/aiarmada/commerce/issues](https://github.com/aiarmada/commerce/issues)
- **Discussions**: [github.com/aiarmada/commerce/discussions](https://github.com/aiarmada/commerce/discussions)

### Support Channels

- **Bug Reports**: Use GitHub Issues with bug report template
- **Feature Requests**: Use GitHub Issues with feature request template
- **Documentation Issues**: Use GitHub Issues with documentation template
- **Questions**: Use GitHub Discussions
- **Commercial Support**: info@aiarmada.com
- **Security Issues**: security@aiarmada.com (private disclosure)

### Contributing

We welcome contributions! See:
- **CONTRIBUTING.md**: Contribution guidelines
- **CODE_OF_CONDUCT.md**: Community standards
- **SECURITY.md**: Security policy

---

## Quick Links

### Popular Pages

- [Installation Guide](01-introduction/02-installation.md)
- [Cart Basics Tutorial](02-getting-started/01-cart-basics.md)
- [Payment Integration Tutorial](02-getting-started/02-payment-integration.md)
- [Support Utilities Reference](04-support-utilities.md)
- [Upgrade Guide](05-upgrade-guide.md)

### Package Documentation

- [Cart Package](03-packages/01-cart.md)
- [CHIP Package](03-packages/02-chip.md)
- [Vouchers Package](03-packages/03-vouchers.md)

### External Resources

- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Filament Documentation](https://filamentphp.com/docs/5.x)
- [CHIP API Documentation](https://developer.chip-in.asia/)
- [J&T Express API](https://developer.jet.co.id/)

---

## License

AIArmada Commerce is open-sourced software licensed under the [MIT license](LICENSE.md).

## Credits

Built with ❤️ by [AIArmada](https://aiarmada.com)

Powered by:
- [Laravel](https://laravel.com)
- [Filament](https://filamentphp.com)
- [Livewire](https://livewire.laravel.com)
- [Pest](https://pestphp.com)

---

**Ready to build?** Start with the [Installation Guide](01-introduction/02-installation.md) →
