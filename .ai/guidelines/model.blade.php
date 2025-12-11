<?php /** @var \Illuminate\View\ComponentAttributeBag $attributes */ ?>
## Model Guidelines

- No DB-level FK constraints or cascades; handle all cascades in application code.
- Required structure: use `HasUuids`; no `$table` property; `getTable()` pulls from config with prefix fallback; fillables match migration.
- Relations typed with generics and PHPDoc properties.
- `booted()` must implement application-level cascades (delete children or null FK as appropriate).
- `casts()` set for arrays/booleans/datetimes as needed.
- Migration reminder: use `foreignUuid()` without `constrained()`/cascades.
