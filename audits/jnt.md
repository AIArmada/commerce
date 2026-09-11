# J&T Audit — DONE (2026-09-11)

## Verdict

The `jnt` + `filament-jnt` pair is cleared. Every rated finding is
implemented or explicitly recorded below: the tracking event vocabulary is
canonical, money stays in integer minor units, cart integration has one path,
shared webhook rows are provider-scoped, retries are asynchronous, and
Filament actions use real authorization. No migration is required.

## What was done

- **E1 — one tracking-event vocabulary:** `TrackingUpdatedEvent` carries the
  typed tracking DTO, and both webhook paths dispatch it
  (`packages/jnt/src/Webhooks/ProcessJntWebhook.php:229-235`). The flat
  tracking event and superseded parcel/status event classes were deleted;
  order-level and waybill events remain distinct. The event docs and coverage
  now describe the canonical shape.
- **E2 — integer money end to end:** the driver reads integer basis-point
  multipliers and rounds with integer arithmetic
  (`packages/jnt/src/Shipping/JntShippingDriver.php:366-398`). The cart
  calculator uses the same minor-unit rule (`packages/jnt/src/Cart/JntShippingCalculator.php:151-191`),
  while carrier-required weight conversion remains a separate decimal
  boundary. Fractional-sen, half-up, and just-below-half-unit tests cover the
  money boundary (`tests/src/Jnt/Unit/Shipping/JntShippingDriverTest.php:189-224`,
  `tests/src/Jnt/Unit/Cart/JntShippingCalculatorTest.php:319-379`).
- **E3 — one cart path:** `JntShippingCalculator` is the sole cart condition
  provider and keeps the quote/cache behavior in one class
  (`packages/jnt/src/Cart/JntShippingCalculator.php:22-125`). The old manager
  and provider layers were removed; registrar wiring registers the calculator
  directly (`packages/jnt/src/JntServiceProvider.php:232-244`).
- **E4 — shared webhook-table scope:** `JntWebhookLog` filters the shared
  table to `jnt.webhooks.status` rows and validates the parent order through
  `OwnerWriteGuard` before inheriting its owner tuple
  (`packages/jnt/src/Models/JntWebhookLog.php:50-97`). The provider-leakage
  regression proves non-J&T rows remain invisible to this model
  (`tests/src/Jnt/Feature/WebhookScopeTest.php:8-27`).
- **Q1 — non-blocking retry:** `JntClient` performs one request without
  in-request sleeping (`packages/jnt/src/Http/JntClient.php:42-75`), while
  the webhook processor owns queued tries and backoff
  (`packages/jnt/src/Webhooks/ProcessJntWebhook.php:27-40`).
- **F1 / F2 — Filament authorization and dashboard behavior:** the action
  authorization now reaches the registered `JntOrderPolicy` and its owner
  mutation check (`packages/filament-jnt/src/Actions/SyncTrackingAction.php:20-27`,
  `packages/filament-jnt/src/Policies/JntOrderPolicy.php:18-40`). The stale
  visibility test now asserts both denied and granted directions; aggregate
  stats remain non-hydrating and owner-safe.
- **Security follow-through:** missing webhook secrets fail closed
  (`packages/jnt/src/Webhooks/JntSpatieSignatureValidator.php:17-39`), and
  signed AWB access checks the authenticated user and owner tuple. These
  verified surfaces required no additional migration.

## Audit deviations

- **Demo configuration residual — closed:** `demo/config/jnt.php:81` now uses
  the migrated key per `packages/jnt/docs/03-configuration.md:202-205`.
- **Polling debounce remains an explicit deferral:** “`TrackParcel` +
  `SyncTrackingAction` should debounce carrier polling per tracking number
  (cache `last_polled_at`, skip if <N minutes) — carrier-rate-limit
  protection; small additive change in `JntTrackingService`.”
- Tracking-event volume remains an EXPLAIN-gated follow-up: “`jnt_tracking_events`
  grows per parcel per scan; confirm index on `['order_id'/'parcel_id',
  'created_at']` supports the tracking timeline query (spot-check showed
  `tracking_number` indexes; add composite only with EXPLAIN evidence — not
  recommended blindly).”
- Deleted event classes and cart layers are an intentional breaking change;
  external subscribers or host applications must migrate to the canonical
  contracts. No read-only package was edited here.

## Residual notes

The demo key, polling debounce, and EXPLAIN-gated index question are explicit
follow-ups. The `shipping`, `cart`, `checkout`, and `orders` contracts remain
read-only dependencies for integration verification. No compatibility aliases
were retained.

## Verification

- Jnt Area: **568 passed, 1,729 assertions**.
- FilamentJnt Area after the stale-test fix: **34 passed, 124 assertions**.
- `SyncTrackingActionTest.php` independently targeted: **5 passed, 9
  assertions**.
- All Pest commands used `--parallel`; no full-suite run was performed.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
