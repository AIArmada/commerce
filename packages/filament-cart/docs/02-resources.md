---
title: Resources
---

# Resources

Filament Cart registers only cart-management resources.

## CartResource

Manages normalized cart snapshots.

Important fields:

| Field | Type | Purpose |
| --- | --- | --- |
| `identifier` | string | Cart/customer/session identifier |
| `instance` | string | Cart instance, such as `default` |
| `items_count` | integer | Unique item count |
| `quantity` | integer | Total item quantity |
| `subtotal` / `total` / `savings` | integer | Money in minor units |
| `currency` | string | ISO currency code |
| `last_activity_at` | datetime | Last detected activity |
| `checkout_started_at` | datetime | Checkout start marker |
| `checkout_abandoned_at` | datetime | Abandonment marker |
| `owner_type` / `owner_id` | nullable morph | Tenant owner boundary |

`owner_scope` may exist in the database for nullable-owner uniqueness, but it is an internal implementation detail.

## CartItemResource

Displays normalized items for visible cart snapshots. Queries are scoped through the parent cart owner boundary.

## CartConditionResource

Displays normalized cart/item conditions for visible cart snapshots.

## ConditionResource

Manages reusable stored conditions from `aiarmada/cart`. Global rows are read-only from tenant owner contexts.

## Removed resources

Recovery campaign/template resources and cart-local alert resources were removed. Use `filament-signals` to manage analytics, alert rules, alert logs, destinations, and event filters.
