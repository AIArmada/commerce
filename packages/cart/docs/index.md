# AIArmada Cart Documentation

Welcome to the complete documentation for AIArmada Cart—a modern, production-grade shopping cart engine for Laravel 12. This documentation guides you from installation through advanced deployment scenarios.

## 📖 About This Documentation

This documentation is organized into four main sections:

1. **Essentials** – Get started and understand core concepts
2. **Core Features** – Master day-to-day cart operations
3. **Advanced Topics** – Handle complex scenarios and scaling
4. **Reference** – Quick lookups and troubleshooting

## 🎯 Choose Your Path

### New to AIArmada Cart?
Start here to get up and running quickly:
1. [Installation & Setup](getting-started.md) – Install and configure the package
2. [Cart Operations](cart-operations.md) – Learn the core API
3. [Quick Examples](examples.md) – Copy-paste common scenarios

### Building a Feature?
Jump directly to the relevant guide:
- **Pricing & Discounts** → [Conditions & Pricing](conditions.md)
- **User Login Flow** → [User Migration](identifiers-and-migration.md)
- **Multi-Device Carts** → [Storage Drivers](storage.md)
- **High Traffic** → [Concurrency Control](concurrency.md)

### Deploying to Production?
Review these essential guides:
1. [Storage Drivers](storage.md) – Choose the right storage backend
2. [Concurrency Control](concurrency.md) – Handle concurrent access

## 📚 Complete Documentation Index

### Essentials

Get started and understand the fundamentals.

| Guide | What You'll Learn |
|-------|------------------|
| **[Installation & Setup](getting-started.md)** | Install the package, run your first cart operation, verify everything works |
| **[Configuration](configuration.md)** | Complete reference of all configuration options with examples |

### Core Features

Master the day-to-day operations you'll use most frequently.

| Guide | What You'll Learn |
|-------|------------------|
| **[Cart Operations](cart-operations.md)** | Add, update, remove items; calculate totals; manage metadata; work with collections |
| **[Conditions & Pricing](conditions.md)** | Create discounts, taxes, fees, shipping rules; build dynamic conditions; understand calculation order |
| **[Storage Drivers](storage.md)** | Choose between session, cache, or database; understand trade-offs; implement custom drivers |
| **[Money & Currency](money-and-currency.md)** | Work with Money objects; format for display; handle multi-currency scenarios |

### Advanced Topics

Handle complex scenarios, scaling, and production deployments.

| Guide | What You'll Learn |
|-------|------------------|
| **[User Migration](identifiers-and-migration.md)** | Migrate guest carts to authenticated users; understand merge strategies; handle edge cases |
| **[Concurrency Control](concurrency.md)** | Prevent race conditions; handle conflicts; implement retry logic; use optimistic locking |
| **[Event System](events.md)** | Listen to cart events; build audit trails; integrate with external systems; test events |

### Reference

Quick lookups, troubleshooting, and complete API documentation.

| Guide | What You'll Learn |
|-------|------------------|
| **[API Reference](api-reference.md)** | Complete method signatures; facade methods; collections; exceptions |
| **[Quick Examples](examples.md)** | Copy-paste recipes for common scenarios and patterns |
| **[Troubleshooting](troubleshooting.md)** | Solutions to common issues; debugging tips |

## 💡 Key Concepts

Before diving into the guides, familiarize yourself with these core concepts:

### Carts vs Instances
- A **cart** is a collection of items for a specific user
- An **instance** is a named cart bucket (e.g., `'default'`, `'wishlist'`, `'quote'`)
- Users can have multiple instances simultaneously without collision

### Identifiers
- **Identifier** determines *who* owns the cart (user ID or session ID)
- Automatically resolved from authenticated user or session
- Can be explicitly set for custom scenarios

### Storage Drivers
- **Session**: Fast, ephemeral, single-device
- **Cache**: Fast, distributed, TTL-based
- **Database**: Persistent, cross-device, analytics-ready

### Conditions
- Modify prices at three levels: **item**, **subtotal**, **total**
- Support percentages, fixed amounts, and dynamic rules
- Execute in predictable order based on target and priority

### Money Objects
All monetary values use [`Akaunting\Money`](https://github.com/akaunting/money) for precision:
- `->format()` for display: `"$1,234.56"`
- `->getAmount()` for calculations: `1234.56`
- Avoids floating-point precision issues

## 🎓 Learning Paths

### For E-commerce Developers
1. [Installation & Setup](getting-started.md)
2. [Cart Operations](cart-operations.md)
3. [Conditions & Pricing](conditions.md)
4. [User Migration](identifiers-and-migration.md)
5. [Quick Examples](examples.md)

### For API Developers
1. [Installation & Setup](getting-started.md)
2. [Storage Drivers](storage.md) (use cache or database)
3. [API Reference](api-reference.md)
4. [Concurrency Control](concurrency.md)

### For Enterprise Teams
1. [Storage Drivers](storage.md) (use database)
2. [Concurrency Control](concurrency.md)
3. [Event System](events.md)
4. [Examples](examples.md)

## 🔍 Quick Reference

### Common Tasks

| Task | Method |
|------|--------|
| Add item | `Cart::add($id, $name, $price, $qty, $attributes)` |
| Update quantity | `Cart::update($id, ['quantity' => $qty])` |
| Remove item | `Cart::remove($id)` |
| Get total | `Cart::total()->format()` |
| Apply discount | `Cart::addDiscount($name, '-10%')` |
| Add tax | `Cart::addTax($name, '8%')` |
| Switch instance | `Cart::instance('wishlist')` |
| Clear cart | `Cart::clear()` |

### Storage Configuration

```php
// config/cart.php
'storage' => 'database', // session, cache, or database
```

### Migration Configuration

```php
// config/cart.php
'migration' => [
    'auto_migrate_on_login' => true,
    'merge_strategy' => 'add_quantities',
],
```

## 🆘 Need Help?

- **Can't find what you're looking for?** Check the [Troubleshooting](troubleshooting.md) guide
- **Found a bug?** Open an issue on [GitHub](https://github.com/aiarmada/cart/issues)
- **Have a question?** Start a [discussion](https://github.com/aiarmada/cart/discussions)
- **Security concern?** Email security@aiarmada.dev

## 📝 Contributing to Documentation

Found an error or want to improve the docs? Contributions are welcome!

1. Fork the repository
2. Edit files in the `docs/` directory
3. Follow the existing structure and style
4. Submit a pull request

---

**Ready to start?** Begin with [Installation & Setup](getting-started.md) →
