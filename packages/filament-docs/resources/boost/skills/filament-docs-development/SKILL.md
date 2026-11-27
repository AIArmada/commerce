---
name: filament-docs-development
description: Develop Filament Docs integrations using Laravel Dune boundaries. Filament v5 adapter for Docs.
---

# Filament Docs development

- Use the Docs service and named actions for payment recording and document updates.
- Use the canonical `Data\DocData` and `Data\ShareLinkData` DTOs.
- Dispatch bulk PDF generation through `EntryPoints\Jobs\GenerateDocPdfsJob` and revalidate owner scope before rendering.

```php
use AIArmada\Docs\Data\DocData;

$data = DocData::from(['doc_type' => 'invoice']);
```

## Verification

From the commerce monorepo root:

```sh
composer --working-dir=packages/filament-docs arch:check
./vendor/bin/pint --test packages/filament-docs
./vendor/bin/pest tests/src/FilamentDocs
```

Do not add compatibility aliases, migration backfills, host application imports, or inline static-analysis suppressions.
