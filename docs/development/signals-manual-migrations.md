---
title: Signals Manual Migrations
---

# Signals Manual Migrations

This document records manual migration steps for existing beta deployments that already have Signals tables.

New installs can use the edited package migrations directly.

## Backup first

Before manual migration:

1. Back up the database.
2. Pause Signals ingestion workers/listeners.
3. Run the migration during a quiet traffic window.
4. Re-enable workers only after validation queries pass.

## Config changes

Move owner config to the top-level `signals.owner` section:

```php
'owner' => [
    'enabled' => true,
    'include_global' => false,
    'auto_assign_on_create' => true,
],
```

Cart capture is now explicit opt-in:

```php
'integrations' => [
    'cart' => [
        'enabled' => true,
    ],
    'filament_cart' => [
        'enabled' => true,
    ],
],
```

## Tracked properties

Add hidden owner uniqueness support:

- Add `owner_scope` string column, default `global`.
- Backfill using `global` when `owner_type` / `owner_id` are null.
- Backfill owned rows using the same hash as `OwnerScopeKey::forTypeAndId($ownerType, $ownerId)`.
- Replace unique `owner_type, owner_id, slug` with unique `owner_scope, slug`.

## Signal events

Add idempotency columns:

- `idempotency_key` nullable string.
- `source_event_id` nullable string.
- Unique index on `tracked_property_id, idempotency_key`.
- Index on `tracked_property_id, source_event_id`.

For existing rows, leave both columns null.

## Alert rules

Add generic alert fields:

- `owner_scope` string, default `global`.
- `event_filters` JSON nullable.
- `channels` JSON nullable.
- `destination_keys` JSON nullable.
- `inline_destinations` JSON nullable.

Backfill `owner_scope` as described for tracked properties. Replace unique `owner_type, owner_id, slug` with unique `owner_scope, slug`.

## Alert logs

Add:

- `delivery_results` JSON nullable.

Existing logs can leave this field null.

## Privacy review

Review `signals.features.privacy.property_allowlist`. Only operational fields should be stored. Do not allow raw email, phone, names, or full cart metadata unless your deployment has a documented privacy basis.

## Validation queries

Run equivalent queries for your database engine:

```sql
select count(*) from signal_tracked_properties where owner_scope is null;
select count(*) from signal_alert_rules where owner_scope is null;
select tracked_property_id, idempotency_key, count(*)
from signal_events
where idempotency_key is not null
group by tracked_property_id, idempotency_key
having count(*) > 1;
```

All counts should be zero before re-enabling ingestion.

## Backfill

Use the Signals-side backfill command only when it is explicitly implemented/enabled for the deployment. It must be run manually, owner-scoped, dry-run capable, and idempotent. Do not run backfills automatically during package install.

## Rollback notes

Rollback should restore the database backup if unique indexes or idempotency columns conflict with existing data. Avoid partial manual rollback of owner uniqueness because it can reintroduce duplicate nullable-owner rows.
