# AIArmada Commerce - Overview

## Introduction

AIArmada Commerce is a comprehensive **Laravel e-commerce monorepo** providing production-ready packages for shopping carts, payment processing, vouchers, shipping, and stock management. Built with **modern Laravel 12**, **Filament 5**, and **PHP 8.4**, it offers both headless APIs and beautiful admin panels.

## Philosophy

**Modular by Design**: Install only what you need. Each package works independently or together as a cohesive ecosystem.

**Developer Experience First**: Clean APIs, extensive documentation, comprehensive test coverage, and TypeScript-level type safety with PHPStan level 6.

**Production Ready**: Battle-tested exception handling, optimistic locking for carts, webhook verification, extensive validation, and comprehensive logging.

**Filament Integration**: Optional admin panels with zero configuration for managing purchases, carts, vouchers, and shipments.

## Architecture

### Package Structure

```
aiarmada/commerce (meta-package)
├── Core Packages
│   ├── cart          - Shopping cart with multiple storage drivers
│   ├── chip          - CHIP payment gateway integration
│   ├── vouchers      - Flexible voucher/coupon system
│   ├── jnt           - J&T Express shipping integration
│   └── stock         - Inventory management
├── Infrastructure
│   ├── support       - Shared utilities, exceptions, HTTP client
│   └── docs          - Documentation package
└── Filament Plugins
    ├── filament-cart     - Cart admin panel
    ├── filament-chip     - Payment admin panel
    └── filament-vouchers - Voucher admin panel
```

### Design Principles

1. **Single Responsibility**: Each package has a clear, focused purpose
2. **Dependency Inversion**: Packages depend on abstractions, not implementations
3. **Event-Driven**: Comprehensive event system for extensibility
4. **Type Safety**: Full PHP 8.4 types with PHPStan level 6
5. **Test Coverage**: Pest v4 tests with browser testing for Filament

## Core Capabilities

### Shopping Cart System

- **Multi-Storage**: Session, cache, or database with optimistic locking
- **Dynamic Conditions**: Apply discounts, taxes, fees at item/subtotal/total level
- **Cart Instances**: Multiple named carts per user (cart, wishlist, etc.)
- **Real-Time Sync**: Livewire integration for instant UI updates
- **Money Handling**: Akaunting Money integration for currency safety

### Payment Processing

- **CHIP Gateway**: Full CHIP Collect & Send API integration
- **Purchase Management**: Create, capture, refund, cancel purchases
- **Webhook Security**: RSA signature verification
- **Client Tracking**: Store customer payment profiles
- **Recurring Payments**: Token-based subscriptions

### Voucher System

- **Flexible Types**: Fixed, percentage, free shipping
- **Usage Controls**: Limit per user, total redemptions, date ranges
- **Conditions**: Minimum purchase, specific products/categories
- **Staff Management**: Multi-staff support with store assignments
- **Cart Integration**: Automatic application and validation

### Shipping Integration

- **J&T Express**: Shipment creation, tracking, label generation
- **Rate Calculation**: Real-time shipping cost estimates
- **Webhook Support**: Shipment status updates
- **Multi-Origin**: Support for multiple warehouse locations

### Inventory Management

- **Stock Tracking**: Product-level inventory management
- **Reservation System**: Hold stock during checkout
- **Low Stock Alerts**: Configurable thresholds
- **Multi-Location**: Track stock across warehouses

## Technology Stack

### Core Dependencies

- **PHP**: ^8.4 (constructor promotion, typed properties, enums)
- **Laravel**: ^12.0 (modern routing, eloquent, events)
- **Filament**: ^5.0 (admin panels, forms, tables)
- **Livewire**: ^3.0 (reactive components)
- **Pest**: ^4.0 (testing with browser support)

### Key Libraries

- **akaunting/laravel-money**: Currency handling
- **guzzlehttp/guzzle**: HTTP client for external APIs
- **spatie/laravel-sluggable**: URL-friendly identifiers
- **barryvdh/laravel-dompdf**: PDF generation

### Development Tools

- **PHPStan**: Level 6 static analysis
- **Laravel Pint**: Code formatting
- **Rector**: Automated refactoring
- **Monorepo Builder**: Package management

## Use Cases

### E-Commerce Platforms

Build complete online stores with cart, payments, vouchers, and shipping.

```php
// Add items to cart
Cart::add('prod-001', 'T-Shirt', 2999, 2, ['size' => 'L']);

// Apply voucher
$voucher = Voucher::findByCode('SAVE20');
Cart::applyVoucher($voucher);

// Create payment
$purchase = Chip::createPurchase([
    'amount' => Cart::total(),
    'reference' => Cart::identifier(),
]);

// Create shipment
$shipment = JNT::createShipment([
    'recipient' => $order->shipping_address,
    'items' => $order->items,
]);
```

### Subscription Services

Recurring payments with CHIP token system.

```php
// Store payment token
$purchase = Chip::createPurchase([
    'amount' => 4999,
    'recurring_token' => true,
]);

// Charge later
Chip::chargeRecurringToken($purchase->recurring_token, 4999);
```

### Multi-Vendor Marketplaces

Multiple carts with store-specific vouchers and shipping.

```php
// Vendor-specific cart
Cart::instance('store_123')->add(...);

// Store-assigned voucher
$voucher = Voucher::whereHas('stores', fn($q) => 
    $q->where('store_id', 123)
)->first();
```

### Digital Products

Cart without shipping, vouchers for promotions.

```php
Cart::add('digital-001', 'E-Book', 1999, 1, [
    'download_url' => '...',
    'license_key' => '...',
]);
```

## What's Next?

- **[Installation Guide](02-installation.md)**: Get started in 5 minutes
- **[Getting Started](../02-getting-started/)**: Build your first cart
- **[Package Reference](../03-packages/)**: Deep dive into each package
- **[Support Utilities](../04-support-utilities.md)**: Shared tools and helpers

## Community & Support

- **Documentation**: [github.com/aiarmada/commerce](https://github.com/aiarmada/commerce)
- **Issues**: [github.com/aiarmada/commerce/issues](https://github.com/aiarmada/commerce/issues)
- **Discussions**: [github.com/aiarmada/commerce/discussions](https://github.com/aiarmada/commerce/discussions)
- **Commercial Support**: info@aiarmada.com
- **Security**: security@aiarmada.com
