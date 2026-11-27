---
name: cashier-chip-development
description: Develop Cashier CHIP integrations using Laravel Dune feature boundaries and secure payment operations.
---

# Cashier CHIP development

- Subscription renewal claims, settlement and reconciliation belong to the Subscriptions feature. Preserve owner context and the frozen renewal amount.
- Use concrete Actions rather than writing subscription or renewal state from entry points.
- Customer sync runs in Cashier CHIP, not the Filament adapter. An unresolved owner must not become an implicit global job.
- Do not retry an ambiguous remote charge using a new idempotency key.

```php
use AIArmada\CashierChip\Features\Subscriptions\Actions\RenewSubscriptions;

$summary = app(RenewSubscriptions::class)->handle(dryRun: true, graceHours: 0);
```

## Verification

From the commerce monorepo root:

```sh
composer --working-dir=packages/cashier-chip arch:check
./vendor/bin/pint --test packages/cashier-chip
./vendor/bin/pest tests/src/CashierChip
```
