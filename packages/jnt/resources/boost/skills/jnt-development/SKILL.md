---
name: jnt-development
description: Develop and integrate the jnt package using its Dune boundaries and owner-safe APIs.
---

# J&T Express

J&T uses Dune features Orders, Tracking, Waybills and Notifications. External transport belongs in Drivers; carrier data belongs in Data. Use the package's public facade or feature API rather than internal action, query or persistence classes.

- Resolve `AIArmada\Jnt\Features\Orders\Api\CancelStoredOrder` and `SyncStoredTracking` for identifier-based operations on stored orders.
- Resolve `AIArmada\Jnt\Features\Waybills\Api\PrintableWaybill` to issue an authorized signed download URL using the order identifier and authenticated user identifier.
- Verify webhook digests over the original bizContent string, enforce payload limits and preserve owner identity when synchronizing tracking. Never disable verification in production or log secrets.
- Preserve integer minor-unit money within the domain and convert to carrier formats at the integration boundary. Keep tracking event writes idempotent.

## Verification

Run the package architecture checks, affected monorepo Pest tests, PHPStan and Pint. Update consumers directly and remove superseded code. Do not introduce compatibility aliases, legacy wrappers or backfills.
