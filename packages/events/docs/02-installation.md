---
title: Installation
---

# Installation

Install the package through Composer:

```bash
composer require aiarmada/events
```

This installs the core events package. Product, customer, and order integrations are first-party optional integrations.

To enable commerce-backed event sales and order fulfillment, install the matching AIArmada packages in the same application:

```bash
composer require aiarmada/products aiarmada/customers aiarmada/orders
```

When those packages are present, `aiarmada/events` automatically registers order fulfillment actions, the ended-event order finalization command, and the registration check-in listener that syncs eligible orders.

Order fulfillment still needs an application resolver configured at `events.integrations.order_item_fulfillment_resolver`. Without one, fulfillment intentionally no-ops.

Then run migrations:

```bash
php artisan migrate
```

If you want to customize table names or integrations, publish the config:

```bash
php artisan vendor:publish --tag=events-config
```

## Table-name upgrade note

Fresh installs use the following default table names:

- `event_series`
- `events`
- `event_speakers`
- `event_venues`
- `event_occurrences`
- `event_registrations`

Older package versions defaulted to `commerce_event_series`, `commerce_events`, `commerce_event_venues`, `commerce_event_occurrences`, and `commerce_event_registrations`.

If an existing app already migrated those old defaults, publish the config before migrating and set `events.database.tables.*` back to the installed table names. Do not rerun fresh package migrations against a production database with different table names unless you have a forward migration plan for copying or renaming the existing data.

> In the AIArmada monorepo, the package is auto-discovered once Composer autoloads are refreshed.
