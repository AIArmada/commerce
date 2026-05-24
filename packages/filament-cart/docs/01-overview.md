---
title: Overview
---

# Filament Cart

## Purpose

The `aiarmada/filament-cart` package is the Filament admin adapter for `aiarmada/cart`. It provides cart snapshots, item and condition resources, live monitoring, and operational cart UI surfaces.

## What this package owns

- Cart snapshot resources and read models
- Cart item and cart condition resources
- Stored condition management
- Live cart dashboard and recent activity widgets
- Snapshot synchronization from core cart events

## What this package does not own

- Recovery campaigns, local metrics tables, or alert-rule administration; those belong to `aiarmada/signals` and `aiarmada/filament-signals`
- Cart persistence, cart condition calculation, or checkout conversion rules; those stay in `aiarmada/cart`
- Tenant resolution itself; it consumes the owner context from the host app and `commerce-support`

## Related packages

- [`aiarmada/cart`](../../cart/docs/01-overview.md) — core cart models, storage, and events
- [`aiarmada/signals`](../../signals/docs/01-overview.md) and [`aiarmada/filament-signals`](../../filament-signals/docs/01-overview.md) — optional analytics and alerting UI
- [`aiarmada/checkout`](../../checkout/docs/01-overview.md) — downstream checkout orchestration

## Main models services or surfaces

- **Resources** — cart snapshots, cart items, cart conditions, and stored conditions
- **Widgets and monitoring** — live cart dashboard, recent activity, synchronization, analytics handoff, and recovery surfaces documented in the deeper docs pages
- **Events** — scalar operational events that Signals can consume when enabled

## Owner scoping and security notes

- The plugin should mirror the owner-scoping behavior of `aiarmada/cart`
- Admin filtering is not authorization; downstream mutations still rely on the core cart package to validate cart ownership and state before changes are persisted

`aiarmada/filament-cart` provides a lean Filament v5 admin UI for cart snapshots, cart items, cart conditions, condition management, live monitoring, and abandonment marking.

## What this package owns

- Cart snapshot resources and read models.
- Cart item and cart condition resources.
- Stored condition management.
- Live cart dashboard and recent activity widgets.
- Snapshot synchronization from `aiarmada/cart` events.
- Operational cart events for optional Signals ingestion.

## Optional analytics and alerts

Filament Cart no longer owns recovery campaigns, local metrics tables, alert rule resources, or alert log resources. Use these packages when intelligence is required:

```bash
composer require aiarmada/signals aiarmada/filament-signals
```

Enable `signals.integrations.cart` and/or `signals.integrations.filament_cart` explicitly. `filament-signals` provides the analytics and alert administration UI.

## Operational events

Filament Cart emits scalar payload events that Signals can listen to when enabled:

- `CartSnapshotSynced`
- `CartCheckoutStarted`
- `CartAbandoned`
- `HighValueCartDetected`

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Synchronization](05-synchronization.md)
- [Widgets](06-widgets.md)
- [Analytics](07-analytics.md)
- [Recovery](08-recovery.md)
- [Monitoring](09-monitoring.md)
- [Multitenancy](10-multitenancy.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core cart overview](../../cart/docs/01-overview.md)
