---
name: docs-development
description: Develop Docs integrations using Laravel Dune boundaries. Document creation, versioning, delivery, and rendering.
---

# Docs development

- Use `Data\DocData` for document creation and integer minor-unit values.
- Use `Contracts\DocumentPaymentRecorder`, `CreateDocVersion`, and the document service for changes. Do not mutate document versions or payment totals directly.
- Use `Contracts\DocumentApprovals` for approval decisions; resolve actor permissions at the authenticated entry point.
- Keep rendering and delivery tenant-scoped; public share and tracking endpoints must validate their tokens and prevent sensitive caching.
- Use the canonical `EntryPoints` namespaces for jobs and HTTP controllers.

```php
use AIArmada\Docs\Data\DocData;

$data = DocData::from(['doc_type' => 'invoice', 'items' => [['quantity' => 1, 'unit_price_minor' => 1000]]]);
```

## Verification

From the commerce monorepo root:

```sh
composer --working-dir=packages/docs arch:check
./vendor/bin/pint --test packages/docs
./vendor/bin/pest tests/src/Docs
```

Do not add compatibility aliases, migration backfills, host application imports, or inline static-analysis suppressions.
