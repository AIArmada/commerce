# Customers Audit — DONE (2026-09-08)

## Verdict

The CRM package (`customers` + `filament-customers`) has passed full
review and implementation. Tuple-scoped segments, shared normalizer,
core merge action, `HasOwner`-only auto-assign, `person_id` linkage in
both write actions, and the backward-compat native contact layer
removed (Contacting-only) — with zero rated findings remaining.

## What was done

- **Segment identity:** slug checks scoped by owner tuple; legacy
  unique kept as guard; cross-tenant regression — see
  `code-fixes-record.md`.
- **Normalizer:** `CustomerProfileNormalizer` replaces three private
  copies — see `code-fixes-record.md`.
- **Merge:** core `Actions/MergeCustomers` (transactional + event);
  resolver and filament action are thin callers — see
  `code-fixes-record.md`.
- **Ownership:** duplicated concerns deleted; `HasOwner` auto-assign
  only; secure default wired — see `code-fixes-record.md`.
- **`person_id` linkage:** optional trailing args on both actions via
  `LinkCustomerToPerson::executeByKey`; checkout/orders positional
  callers unaffected; link/no-link/unknown-id/keep-link regressions —
  see `code-fixes-record.md`.
- **Native layer removed (dev-only migration edit):**
  `customers.email/phone` dropped via guarded cutover migration, no
  backfill; model + all owned paths Contacting-only; stale checkout
  expectation rewritten to the doctrine — see `code-fixes-record.md`.
- **Kept by policy:** hard `contacting` require (canonical doctrine,
  demotion overruled); payment-subject driver stays (cashier-track
  dependency, logged).
- Suites: Customers 242 passed (421 assertions), FilamentCustomers 28
  passed (63 assertions); PHPStan level 6 clean. Orders (287/664) +
  Checkout (253/931) canaries green.

## Residual notes

- Pivot customer-first indexes implemented (`2026_09_11_000002`,
  guarded/idempotent).
- Downstream reads (`cashier` Stripe email, checkout fallbacks,
  events recipient resolution) degrade to null, null-safe — logged
  dependencies for the owning tracks.
- `CustomerResolver`/segmentation decomposition and default-address
  action remain future simplifications, not findings.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
