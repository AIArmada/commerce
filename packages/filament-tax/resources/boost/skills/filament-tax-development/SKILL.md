---
name: filament-tax-development
description: Develop owner-safe filament-tax integrations following Laravel Dune boundaries.
---

# FilamentTax

Filament Tax is an administration entry point, not a data owner. Delegate tax mutations to Tax actions, resolve records inside strict owner scope, authorize each administrative operation, and never mutate global records from a tenant context. Keep settings mutations in UpdateTaxSettingsAction.

## Verification

From the Commerce monorepo root, run `composer --working-dir=packages/filament-tax arch:check`, scoped Pest, PHPStan and Pint. Update callers and tests when moving APIs; remove superseded code in the same change.
