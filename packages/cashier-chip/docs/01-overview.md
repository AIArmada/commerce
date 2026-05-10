---
title: Cashier CHIP Documentation
---

# Cashier CHIP Documentation

Laravel Cashier-style billing for CHIP payment platform.

## Architecture

Cashier CHIP provides **Laravel Cashier patterns** adapted for the CHIP payment gateway:

- **Stripe-like API** – Familiar Cashier methods and patterns
- **Local Subscriptions** – Subscription management without native CHIP support
- **Recurring Tokens** – CHIP's equivalent of Stripe payment methods
- **Type-safe** – Uses CHIP package's Data for type safety

## Key Differences from Stripe Cashier

| Feature | Stripe | CHIP |
|---------|--------|------|
| Subscription Management | Stripe-managed | Application-managed |
| Payment Methods | PaymentMethods | Recurring Tokens |
| Webhooks | Stripe Events | CHIP Purchase Events |
| Billing Portal | Hosted by Stripe | Self-hosted (via filament-chip) |
| Preauthorization | SetupIntents | Zero-amount purchases |

## Quick Links

| Guide | Description |
|-------|-------------|
| [Installation](02-installation.md) | Setup and configuration |
| [Customers](03-customers.md) | Customer management |
| [Charges](04-charges.md) | One-off payments |
| [Checkout](05-checkout.md) | Hosted payment pages |
| [Payment Methods](06-payment-methods.md) | Recurring tokens |
| [Subscriptions](07-subscriptions.md) | Recurring billing |
| [Webhooks](08-webhooks.md) | Event handling |
| [Testing](09-testing.md) | Testing patterns |
| [API Reference](10-api-reference.md) | Complete method reference |

## Quick Start

```php
use AIArmada\CashierChip\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

```php
// Create a checkout
$checkout = $user->checkout(10000, [
    'reference' => 'Premium Plan',
]);

return $checkout->redirect();
```

## Installation

```bash
composer require aiarmada/cashier-chip
php artisan vendor:publish --tag=cashier-chip-config
php artisan migrate
```

Cashier CHIP auto-discovers its package migrations. Publish `cashier-chip-migrations` only when
you need to customize the migration files in your own app.

It always owns `chip_id`, `default_pm_id`, and the `cashier_chip_*` subscription tables. When
`laravel/cashier` is absent it also provisions shared standalone columns such as `pm_type`,
`pm_last_four`, and `trial_ends_at`; when Laravel Cashier is present, those shared columns remain
owned by Laravel Cashier.

## Configuration

```env
CHIP_BRAND_ID=your-brand-id
CHIP_COLLECT_API_KEY=your-collect-api-key
CHIP_WEBHOOK_SECRET=your-webhook-secret
```

---

**Ready?** Start with [Installation](02-installation.md) →
