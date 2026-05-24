---
title: Overview
---

# Cashier Package

## Purpose

The `aiarmada/cashier` package is the unified multi-gateway billing abstraction for Commerce.

## What this package owns

- Gateway-agnostic billing abstractions across Stripe and CHIP-style providers
- Unified billing helpers, wrapper traits, and common subscription or payment APIs
- Cross-gateway coordination for subscriptions, invoices, charges, checkout, and customer models

## What this package does not own

- Stripe gateway implementation details or tables; those belong to `laravel/cashier`
- CHIP-specific billing persistence; that belongs to `aiarmada/cashier-chip`
- Filament admin surfaces; those belong to `aiarmada/filament-cashier`
- Direct payment gateway APIs; it delegates to installed gateway packages

## Related packages

- [`aiarmada/filament-cashier`](../../filament-cashier/docs/01-overview.md) — Filament admin interface for unified billing
- [`aiarmada/cashier-chip`](../../cashier-chip/docs/01-overview.md) — CHIP billing driver and persistence layer
- `laravel/cashier` — Stripe gateway support
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared primitives

## Main models services or surfaces

- **Core surfaces** — unified subscription, invoice, checkout, and one-off payment APIs across installed gateways
- **Wrapper traits** — billable helpers that compose gateway-specific traits into one interface
- **Integration seams** — customer-model registration and gateway detection or delegation

## Owner scoping and security notes

- The package should honor owner scoping through the underlying gateway packages and `commerce-support`
- Gateway selection and billing actions should still validate the owning billable model server-side before charges or subscription mutations are attempted

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Subscriptions](05-subscriptions.md)
- [Payments](06-payments.md)
- [Multi-gateway](07-multi-gateway.md)
- [Webhooks](08-webhooks.md)
- [Troubleshooting](99-troubleshooting.md)
- [Filament Cashier overview](../../filament-cashier/docs/01-overview.md)