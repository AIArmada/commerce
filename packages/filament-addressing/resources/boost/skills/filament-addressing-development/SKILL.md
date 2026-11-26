---
name: filament-addressing-development
description: Develop Filament Addressing integrations using the core addressing feature boundaries and Laravel Dune architecture.
---

# Filament Addressing development

- This package adapts the core addressing features to Filament v5. Do not duplicate formatting, geographic matching, owner authorization, or persistence rules.
- Resources and relation managers must check the current owner scope and resource permission. Never trust a submitted record identifier without server-side revalidation.
- Route country, state, city and address mutations through the core addressing contracts.
- Keep filter option caches in Persistence and invalidate them after reference-data edits.

```php
use AIArmada\Addressing\Contracts\GeographyReferenceEditor;

app(GeographyReferenceEditor::class)->city((string) $city->getKey(), $validatedData);
```

## Verification

From the commerce monorepo root:

```sh
composer --working-dir=packages/filament-addressing arch:check
./vendor/bin/pint --test packages/filament-addressing
./vendor/bin/pest tests/src/FilamentAddressing
```

Do not add compatibility aliases, migration backfills, host application imports, or inline static-analysis suppressions.
