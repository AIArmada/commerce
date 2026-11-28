---
name: tax-development
description: Develop owner-safe tax integrations following Laravel Dune boundaries.
---

# Tax

Tax owns zones, rates, classes, exemptions and tax configuration. Use TaxCalculatorInterface and TaxResultData for calculations. TaxOwnerScope separates owner-scoped reads from strict write locks. Use DeleteTaxClassesAction, DeleteTaxRatesAction, DeleteTaxZonesAction and SetTaxRatesActiveAction for bulk writes; exemption lifecycle and settings writes belong in Actions. Never allow tenant mutation of global configuration records through include_global.

## Verification

From the Commerce monorepo root, run `composer --working-dir=packages/tax arch:check`, scoped Pest, PHPStan and Pint. Update callers and tests when moving APIs; remove superseded code in the same change.
