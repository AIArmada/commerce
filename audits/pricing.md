# Pricing Audit — DONE (2026-09-09)

## Verdict

The price-resolution package (`pricing` + `filament-pricing`) has
passed full review and implementation. One tier engine, no dead
wrappers or registrars, promotions bridged through the public
contract, lifecycle hygiene enforced, owner validation fail-closed,
and the simulator guarded — with zero rated findings remaining.

## What was done

- **Tier engine (A1):** canonical active-only `TierResolver`;
  divergent duplicate deleted after pre-delete characterization —
  see `code-fixes-record.md`.
- **Dead code (A2/A3):** three dead actions + dead registrar
  deleted; docs on direct primitives — see `code-fixes-record.md`.
- **Promotions bridge (A4):** `PromotionServiceInterface::
  calculateDiscounts()`; raw queries gone; parity-tested — see
  `code-fixes-record.md`. Checkout canary green (no snapshot drift).
- **Lifecycle (A5/A6/A7):** deactivation enforced at model +
  calculator; owner-scoped transactional demotion with deterministic
  ordering; shared `effective_at` parser — see `code-fixes-record.md`.
- **Validation (A8/C1/C2/C3):** tuple-scoped customer/segment
  checks; redundant override deleted; named currency exception;
  transactional deletes — see `code-fixes-record.md`.
- **Simulator + adapter (F1–F4):** optional-products guard with
  both-branch tests; promotions-owner widget default; docs match
  implementation; thin adapter confirmed — see
  `code-fixes-record.md`.
- **Testing:** stale "zero tests" recalibrated and expanded — see
  `code-fixes-record.md`.
- Suites: Pricing 145 passed (290 assertions), FilamentPricing 39
  passed (116 assertions); PHPStan level 6 clean. No migration
  required (existing tier index covers).

## Residual notes

- Promotion evaluation resolves through promotions' public contract, whose
  as-of core is canonical (default delegates with the current instant) —
  promotions-track decision, now closed; not this package.
- Checkout `class_exists` workaround removal is a checkout-track
  follow-up.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
