---
name: filament-growth-development
description: Develop filament-growth integrations using owner-safe Growth operations and Laravel Dune package boundaries.
---

# Filament Growth

Filament Growth provides administration and reporting for the Growth experiment aggregate; it owns no independent domain tables. Keep database mutations in named Actions, read-only analytics in Queries, and owner-scope decisions in Authorization. Resources, pages and widgets are presentation entry points. All mutable experiment and variant operations must respect owner scope and the configured authorization policies. Never re-open concluded or archived experiments through the running toggle.

## Verification

From the Commerce monorepo root, run `composer --working-dir=packages/filament-growth arch:check`, the affected Pest tests, PHPStan and Pint. Update callers directly when public APIs move; do not introduce compatibility wrappers.
