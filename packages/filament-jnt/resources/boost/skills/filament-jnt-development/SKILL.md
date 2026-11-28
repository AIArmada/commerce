---
name: filament-jnt-development
description: Develop and integrate the filament-jnt package using its Dune boundaries and owner-safe APIs.
---

# Filament J&T

Register `AIArmada\FilamentJnt\FilamentJntPlugin::make()` on the panel. This package provides J&T order, tracking and webhook-log resources and a statistics widget.

- Use `AIArmada\Jnt\Features\Orders\Api\CancelStoredOrder` and `SyncStoredTracking` for mutations and `AIArmada\Jnt\Features\Waybills\Api\PrintableWaybill` for signed downloads.
- Preserve resource policies and tenant scope. Keep carrier credentials, raw transport calls, and tracking writes inside the J&T domain package. Use the Orders reporting API for aggregate statistics.

## Verification

Run the package architecture checks, affected monorepo Pest tests, PHPStan and Pint. Update consumers directly and remove superseded code. Do not introduce compatibility aliases, legacy wrappers or backfills.
