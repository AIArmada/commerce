---
name: filament-cashier-chip-development
description: Develop Filament Cashier CHIP integrations with Laravel Dune feature boundaries and owner isolation.
---

# Filament Cashier CHIP development

- This package is a Filament v5 adapter for Cashier CHIP billing. It does not own subscription, renewal or payment-method tables.
- Delegate mutations to the Cashier CHIP feature actions and use owner-scoped resources and widgets.
- Capture an explicit owner context when dispatching customer sync; never convert an unresolved tenant to global access.
- Do not expose recurring tokens or provider exceptions in the UI.

```php
use AIArmada\FilamentCashierChip\EntryPoints\Jobs\SyncCustomersToChipJob;
use AIArmada\CommerceSupport\Support\OwnerJobContextMapper;

SyncCustomersToChipJob::dispatch(OwnerJobContextMapper::fromModel($owner));
```

## Verification

From the commerce monorepo root:

```sh
composer --working-dir=packages/filament-cashier-chip arch:check
./vendor/bin/pint --test packages/filament-cashier-chip
./vendor/bin/pest tests/src/FilamentCashierChip
```

Do not add compatibility aliases, migration backfills, host application imports, or inline static-analysis suppressions.
