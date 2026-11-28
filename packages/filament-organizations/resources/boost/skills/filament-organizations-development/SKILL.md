---
name: filament-organizations-development
description: Develop owner-safe filament-organizations integrations following Laravel Dune boundaries.
---

# FilamentOrganizations

Filament Organizations is an administration entry point, not a data owner. Delegate organization creation, editing and lifecycle changes to Organizations actions. Resolve the actor through Filament authentication and enforce organization authorization and membership invariants.

## Verification

From the Commerce monorepo root, run `composer --working-dir=packages/filament-organizations arch:check`, scoped Pest, PHPStan and Pint. Update callers and tests when moving APIs; remove superseded code in the same change.
