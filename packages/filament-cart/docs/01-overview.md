---
title: Overview
---

# Filament Cart

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
