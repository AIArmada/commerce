---
title: Monitoring
---

# Monitoring

Filament Cart monitoring is intentionally operational and lightweight.

## Live monitor

The live monitor shows:

- active carts,
- carts with items,
- checkouts in progress,
- recent abandonments,
- total cart value,
- high-value cart counts.

## Abandonment command

```bash
php artisan cart:mark-abandoned
php artisan cart:mark-abandoned --minutes=45
php artisan cart:mark-abandoned --dry-run
```

This command only updates `checkout_abandoned_at` and emits `CartAbandoned`.

If `filament-cart.notifications.abandoned_cart.enabled` is true, the package also sends the abandoned-cart recovery notification from the event listener. Alert rules still remain separate and are handled by Signals.

## Alerts

Use Signals for alerting:

```bash
php artisan signals:process-alerts
php artisan signals:process-alerts --dry-run
```

Manage alert rules, generic event filters, channels, destinations, and logs from `filament-signals`.
