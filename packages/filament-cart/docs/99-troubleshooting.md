---
title: Troubleshooting
---

# Troubleshooting

## Cart snapshots are not updating

- Ensure `FilamentCartServiceProvider` is loaded.
- Confirm cart events are enabled via `cart.events`.
- Check that queue sync is configured correctly if `filament-cart.synchronization.queue_sync` is enabled.
- Verify the source cart storage contains the expected identifier and instance.

## Owner scoped data is missing

- Confirm `cart.owner.enabled` and `filament-cart.owner.enabled` are synchronized.
- Ensure an `OwnerResolverInterface` binding resolves the current owner.
- Use `OwnerContext::withOwner(null, ...)` only for explicit global operations.
- Do not query or authorize using internal `owner_scope` columns.

## Abandoned carts are not marked

Run the command manually first:

```bash
php artisan cart:mark-abandoned --dry-run
php artisan cart:mark-abandoned
```

Check that snapshots have:

- `items_count > 0`,
- `checkout_started_at` set,
- `checkout_abandoned_at` still null,
- stale `last_activity_at` or stale checkout start time.

## No analytics or alerts appear in Filament Cart

This is expected. Filament Cart no longer owns local analytics or alerting UI.

Install and enable Signals instead:

```bash
composer require aiarmada/signals aiarmada/filament-signals
php artisan signals:process-alerts --dry-run
```

Then manage reports, alert rules, alert logs, channels, and destinations from `filament-signals`.

## High value cart events are not emitted

Check the threshold:

```php
'analytics' => [
    'high_value_threshold_minor' => 10000,
],
```

The event is emitted when a synced snapshot total crosses from below the threshold to at/above it.

## Snapshot downloads include owner internals

Downloads should include `owner_type` and `owner_id` only. Hidden uniqueness helpers such as `owner_scope` should not be exported.
