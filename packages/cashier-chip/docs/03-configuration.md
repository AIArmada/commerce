---
title: Configuration
---

# Configuration

`aiarmada/cashier-chip` publishes `config/cashier-chip.php` for its schema, billing defaults,
owner-scoping behavior, webhook handling, invoice rendering, and logging.

## Configuration file

Publish the config when you need to customize the package defaults:

```bash
php artisan vendor:publish --tag=cashier-chip-config
```

## Database

These keys control package-owned schema naming:

| Key | Purpose |
| --- | --- |
| `database.table_prefix` | Prefix used for the package-owned billing tables |
| `database.tables.subscriptions` | Subscription table name |
| `database.tables.subscription_items` | Subscription items table name |
| `database.tables.renewal_attempts` | Renewal attempts table name |

## Defaults

| Key | Purpose |
| --- | --- |
| `currency` | Default billing currency |
| `currency_locale` | Locale for money display helpers |

## Features

The owner-scope settings mirror the multitenancy contract from `commerce-support`:

| Key | Purpose |
| --- | --- |
| `features.owner.enabled` | Turn owner scoping on or off |
| `features.owner.include_global` | Include global rows when owner mode is enabled |
| `features.owner.auto_assign_on_create` | Auto-assign the current owner on create |
| `features.owner.validate_billable_owner` | Re-validate billable ownership on write flows |
| `features.owner.customer_resolver` | Custom `CustomerOwnerResolverInterface` class for customer scoping (`null` uses the default) |

When owner scoping is enabled, `RenewalAttempt` records inherit `owner_type` and `owner_id` from
their parent subscription. Direct renewal-attempt queries and writes therefore require the same
owner context as the subscription. The package migrations add the owner columns and backfill
legacy attempts from their parent subscriptions.

Billable customer models (`User`, `Team`) usually carry no owner tuple, so customer lists
cannot always use owner-column constraints. The default customer resolver proves
ownership via the model's own owner tuple when it defines one, self-identity
(owner IS the customer), an owned CHIP customer link, or an owned subscription,
and fails closed with no rows when none apply. Explicit global context sees
global-only rows on tuple models and all rows on billables without an owner
tuple. Point `customer_resolver` at a custom
`AIArmada\CashierChip\Contracts\CustomerOwnerResolverInterface` implementation to map
customers to owners differently:

```php
use App\Billing\TeamCustomerResolver;

'features' => [
    'owner' => [
        'customer_resolver' => TeamCustomerResolver::class,
    ],
],
```

## Rate limits

| Key | Purpose | Default |
| --- | --- | --- |
| `rate_limits.charges_per_minute` | Maximum charge attempts accepted per minute by package throttles | `30` |

## Integrations

Coupon and promotion-code paths require `aiarmada/vouchers`. The default `null` value
auto-detects the optional package and throws a clear `LogicException` when a coupon path is
used without it. Set `integrations.vouchers.enabled` to `false` to disable the integration;
coupon paths still fail loudly rather than silently skipping validation or usage recording.

| Key | Purpose | Default |
| --- | --- | --- |
| `integrations.vouchers.enabled` | Enable the vouchers-backed coupon integration | `null` (auto-detect, fail loudly) |

## HTTP and webhooks

| Key | Purpose |
| --- | --- |
| `path` | Webhook route prefix |
| `webhooks.secret` | CHIP webhook secret |
| `redirects.allowed_hosts` | Optional allowlist for redirect/callback URL hosts (empty allows any host) |

Signature verification is owned by the `chip` package: use `chip.webhooks.verify_signature`
(`CHIP_WEBHOOK_VERIFY_SIGNATURE`). `cashier-chip` only stores the shared secret for display.

## Invoices

| Key | Purpose |
| --- | --- |
| `invoices.renderer` | Optional invoice renderer service |
| `invoices.paper` | Paper size for rendered invoices |
| `invoices.vendor_address` | Vendor address rendered on invoices |

The package currently does not expose additional notification-specific settings.

## Example environment values

```env
CASHIER_CHIP_TABLE_PREFIX=cashier_chip_
CASHIER_CHIP_JSON_COLUMN_TYPE=jsonb

CASHIER_CHIP_CURRENCY=MYR
CASHIER_CHIP_CURRENCY_LOCALE=ms_MY

CASHIER_CHIP_OWNER_ENABLED=true
CASHIER_CHIP_OWNER_INCLUDE_GLOBAL=false
CASHIER_CHIP_OWNER_AUTO_ASSIGN_ON_CREATE=true
CASHIER_CHIP_OWNER_VALIDATE_BILLABLE_OWNER=true

CASHIER_CHIP_PATH=chip
CHIP_BRAND_ID=your-brand-id
CHIP_COLLECT_API_KEY=your-collect-api-key
CHIP_WEBHOOK_SECRET=your-webhook-secret
CHIP_WEBHOOK_VERIFY_SIGNATURE=true
```

## Related docs

- [Usage](04-usage.md)
- [Customers](05-customers.md)
- [Charges](06-charges.md)
- [Checkout](07-checkout.md)
- [Payment methods](08-payment-methods.md)
- [Subscriptions](09-subscriptions.md)
- [Webhooks](10-webhooks.md)
