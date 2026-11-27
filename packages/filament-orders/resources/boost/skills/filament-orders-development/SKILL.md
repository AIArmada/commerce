---
name: filament-orders-development
description: Develop Filament Orders integrations using Laravel Dune boundaries. Filament v5 adapter for Orders.
---

# Filament Orders development

- Use the Orders actions or service for lifecycle changes rather than direct financial writes.
- Revalidate tenant scope and actor authorization before actions and invoice downloads.
- Keep dashboard cache queries in `Persistence/`; use the actual `RefundStatus` enum for refunds.

```php
use AIArmada\Orders\Actions\AssignOrderInvoiceNumber;

$number = app(AssignOrderInvoiceNumber::class)->execute($order);
```

## Verification

From the commerce monorepo root:

```sh
composer --working-dir=packages/filament-orders arch:check
./vendor/bin/pint --test packages/filament-orders
./vendor/bin/pest tests/src/FilamentOrders
```

Do not add compatibility aliases, migration backfills, host application imports, or inline static-analysis suppressions.
