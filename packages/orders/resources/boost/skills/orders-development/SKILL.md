---
name: orders-development
description: Develop Orders integrations using Laravel Dune boundaries. Order lifecycle, payment/refund allocation, fulfillment, and order document issuance.
---

# Orders development

- Use the named Orders actions for payments, refunds, and document issuance; do not write payment or refund totals from integrations.
- Preserve tenant scope and use the persisted order identity when locking or issuing documents.
- Payment and refund amounts are integer minor units. Never replace completed payment evidence with the order total.
- Use `AssignOrderInvoiceNumber` for persistent invoice identity; never mint a new number on every download.

```php
use AIArmada\Orders\Actions\AssignOrderInvoiceNumber;

$number = app(AssignOrderInvoiceNumber::class)->execute($order);
```

## Verification

From the commerce monorepo root:

```sh
./vendor/bin/pint --test packages/orders
./vendor/bin/pest tests/src/Orders
```

Do not add compatibility aliases, migration backfills, host application imports, or inline static-analysis suppressions.
