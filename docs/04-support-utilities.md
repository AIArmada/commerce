---
title: Support Utilities
status: current
---

# Support Utilities

This guide explains how the shared `aiarmada/commerce-support` package fits into the wider Commerce ecosystem.

Use this page when you need to understand **which shared foundation surface to reach for**. For API details, configuration, and implementation examples, prefer the canonical package docs in `packages/commerce-support/docs/*.md`.

## What belongs in `commerce-support`

`aiarmada/commerce-support` owns the cross-package seams that the rest of the monorepo builds on:

- owner scoping primitives and explicit global-context semantics,
- payment gateway and checkout contracts,
- money normalization and formatting helpers,
- webhook validation and processing foundations,
- health-check abstractions,
- targeting and eligibility evaluation infrastructure,
- owner-scoped cache, filesystem, route-binding, and write-guard helpers,
- shared actions, testing traits, and support utilities.

## What does not belong here

`commerce-support` is not the place for:

- cart, checkout, order, pricing, or voucher business rules,
- Filament resources, pages, widgets, or panel-specific behavior,
- gateway-specific implementation details for `chip`, `cashier`, or `cashier-chip`,
- package-local models, migrations, or config beyond the shared primitives it exposes.

When a task is package-specific, start with that package’s `01-overview.md` and only drop into `commerce-support` when the work crosses package boundaries.

## Common reasons to read the support docs

### Owner safety and multitenancy

Start here when a change touches tenant/owner boundaries, global records, route model binding, background jobs, exports, or webhook handling.

Canonical docs:

- [`Commerce Support overview`](../packages/commerce-support/docs/01-overview.md)
- [`Usage`](../packages/commerce-support/docs/04-usage.md)
- [`Multi-tenancy`](../packages/commerce-support/docs/04-multi-tenancy.md)
- [`Isolation Primitives`](../packages/commerce-support/docs/11-isolation-primitives.md)
- [`Actions`](../packages/commerce-support/docs/12-actions.md)

Representative helpers:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

OwnerContext::withOwner($owner, function () use ($payload): void {
    OwnerWriteGuard::findOrFailForOwner(Location::class, $payload['location_id']);
});
```

### Payment contracts and money handling

Read these when you are wiring a gateway, normalizing amounts, or working on payment abstractions shared by `chip`, `cashier`, or `checkout`.

Canonical docs:

- [`Payment Contracts`](../packages/commerce-support/docs/05-payment-contracts.md)
- [`Traits & Utilities`](../packages/commerce-support/docs/10-traits-utilities.md)

Representative helpers:

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Support\MoneyNormalizer;

$amount = MoneyNormalizer::toMinorUnits('99.90', 'MYR');
$gateway = app(PaymentGatewayInterface::class);
```

### Targeting and eligibility rules

Use the targeting docs when you need shared rule evaluation across promotions, vouchers, growth, or other eligibility-sensitive features.

Canonical docs:

- [`Targeting Engine`](../packages/commerce-support/docs/06-targeting-engine.md)

### Webhooks and health checks

Use these docs for shared webhook validation patterns, event ingestion safety, and health-reporting foundations used by gateway and operational packages.

Canonical docs:

- [`Webhooks`](../packages/commerce-support/docs/08-webhooks.md)
- [`Health Checks`](../packages/commerce-support/docs/09-health-checks.md)

## How this root guide should be used

This file is intentionally short. It exists to help humans and AI assistants decide **where to go next**.

For detailed examples, current namespaces, config keys, and extension points, prefer the package docs under `packages/commerce-support/docs/`.

## Read next

- [`Commerce Support overview`](../packages/commerce-support/docs/01-overview.md)
- [`Usage`](../packages/commerce-support/docs/04-usage.md)
- [`Multi-tenancy`](../packages/commerce-support/docs/04-multi-tenancy.md)
- [`Payment Contracts`](../packages/commerce-support/docs/05-payment-contracts.md)
- [`Traits & Utilities`](../packages/commerce-support/docs/10-traits-utilities.md)
- [`Isolation Primitives`](../packages/commerce-support/docs/11-isolation-primitives.md)
- [`AI Context`](../CONTEXT.md)
