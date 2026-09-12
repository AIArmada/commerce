# Promotions Audit — DONE (2026-09-11)

## Verdict

`promotions` and `filament-promotions` have passed the implementation review.
All rated findings are implemented, falsified with evidence, or recorded as
explicit residual decisions below. No migration is required. The existing
pricing bridge receives the same effective evaluation semantics, now through
the single as-of core with the current instant.

## What was done

- **Customer limits:** `per_customer_limit` is enforced from
  `matchesContext()` through owner-scoped order reads. The optional Orders
  package is guarded with `class_exists`; when unavailable, the service logs a
  skip reason and does not throw. The coverage is in
  `tests/src/Promotions/PromotionServiceBehaviorTest.php:45-72`.
- **Redemption races and payload ownership:** usage redemption uses the
  atomic `tryIncrementUsage()` path and rejects a saturated limit; the paid
  order listener logs every skip path and verifies the checkout-session owner
  tuple before reading allocations. The model implementation is at
  `packages/promotions/src/Models/Promotion.php:383-402`.
- **Code lookup:** writes normalize codes to trimmed uppercase and reads use
  exact lookup. The behavior is covered at
  `tests/src/Promotions/PromotionServiceBehaviorTest.php:13-25`; the service
  lookup is `packages/promotions/src/Services/PromotionService.php:59-76`.
- **Targeting scale:** cheap purchase/quantity predicates are pushed into SQL,
  then the active set is evaluated in `chunkById(100)` batches rather than
  hydrated wholesale. See `packages/promotions/src/Services/PromotionService.php:175-210`.
- **Optional integrations and lifecycle:** missing product/category models
  degrade to empty relations; issued-voucher tracking is memoized; the host
  scheduler requirement for `promotions:deactivate-expired` is documented.
- **Filament write funnel:** create and deactivate pages use the domain
  actions. The two issue-voucher action entry points are deliberately retained
  for their distinct list and record UX; they are not an accidental duplicate.
- **As-of API (canonical):** `getApplicablePromotionsAsOf()` is the single
  evaluation core at
  `packages/promotions/src/Services/PromotionService.php:38-45`; the default
  path delegates to it with the current instant. Parity is asserted in
  `tests/src/Promotions/PromotionServiceBehaviorTest.php:28-43`, and the
  fake-clock boundary proof covers time-faked evaluation.
- **PHPStan optional-class narrowing:** the guarded Orders class is documented
  as a `class-string<Order>` near
  `packages/promotions/src/Services/PromotionService.php:229-247`; this
  changes only static analysis and leaves standalone runtime behavior intact.

## Audit deviations

- The original full-set hydration finding is closed with chunked evaluation;
  the requested rules-to-SQL compiler was intentionally not introduced.
- The original recommendation to remove one issue-voucher action is declined:
  both actions remain deliberately because record and list contexts expose
  different UX entry points.
- The wall-clock/as-of decision is closed: as-of is canonical, the default
  delegates with the current instant. The pricing bridge continues
  unchanged semantics through the single implementation; historical callers
  may still opt into the as-of method directly.

## Residual notes

- **Do NOT build a rules-to-SQL compiler now (speculative).** Cheap scalar
  pre-filters are implemented; targeting expressions remain in PHP.
- The package registers the expiry command but does not schedule it
  automatically. The host application owns the scheduler entry; read-time
  eligibility remains date-aware.
- Navigation badge counting remains acceptable at the current table size and
  should be revisited only with measured slowness; do not cache prematurely.
- The owner-config nesting difference from sibling packages is cosmetic; do
  not churn published keys for it.
- No schema change was made; `usage_count` remains an application-level
  atomic counter and code normalization relies on the existing unique code
  index.

## Verification

- `php -d memory_limit=1G ./vendor/bin/phpstan analyse packages/promotions/src --level=6` — **No errors**.
- Promotions: **75 passed, 134 assertions** (`tests/src/Promotions`,
  post as-of-canonical flip with fake-clock boundary proof).
- FilamentPromotions: **37 passed, 74 assertions** (`tests/src/FilamentPromotions`).

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
