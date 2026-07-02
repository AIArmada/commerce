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
| `database.json_column_type` | JSON column type used in migrations |
| `database.tables.subscriptions` | Subscription table name |
| `database.tables.subscription_items` | Subscription items table name |

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

## Behavior

| Key | Purpose |
| --- | --- |
| `subscriptions.retry_days` | Retry window for failed renewals |
| `subscriptions.max_retries` | Maximum renewal attempts |
| `subscriptions.grace_days` | Grace period after cancellation or failure |

## HTTP and webhooks

| Key | Purpose |
| --- | --- |
| `path` | Webhook route prefix |
| `webhooks.secret` | CHIP webhook secret |
| `webhooks.verify_signature` | Enable or disable webhook signature verification |

## Invoices and logging

| Key | Purpose |
| --- | --- |
| `invoices.renderer` | Optional invoice renderer service |
| `invoices.paper` | Paper size for rendered invoices |
| `invoices.vendor_address` | Vendor address rendered on invoices |
| `logger` | Optional logger channel override |

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

CASHIER_CHIP_RETRY_DAYS=3
CASHIER_CHIP_MAX_RETRIES=3
CASHIER_CHIP_GRACE_DAYS=7

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