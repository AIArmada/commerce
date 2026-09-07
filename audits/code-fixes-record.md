# Code Fixes Record
Completed 2026-09-08. Historical record of implemented code fixes from the
package audits (the code-only Criticals track, the checkout currency
follow-ups, the three-stream parallel track, and the inventory A1
unification). Per-package audit files (`audits/*.md`) describe remaining
work only.

Migrations live in [`migration-record.md`](migration-record.md) — including
one post-track addition (customers email unique, below). Review method for
everything here: independent re-verification against source (files, `rg`
repo-wide, call-chain tracing). Test/PHPStan/Pint counts are implementer-
reported and taken on trust; code correctness was verified directly.

## Orders — intake scoping (Critical, fixed)

- `CreateOrder::findExistingIntake()` now scopes with
  `forOwner(includeGlobal: config('orders.owner.include_global'))`.
- Both call paths route through it: happy path and `QueryException`
  duplicate-key fallback (verified `:37-44`, `:82-90`).
- Checkout already wrapped processing in `OwnerContext::withOwner()`
  (`CheckoutService::withSessionOwnerContext`) — the audit's call-site
  concern was not present.
- Tests: `tests/src/Orders/OrderIntakeTest.php` (9 passed per implementer).

## Checkout — amount + currency gates, backfill removal (Critical, fixed)

- Amount gate: `$paymentData['amount'] ?? grand_total` compared as `(int)`;
  mismatch records reconciliation metadata and returns `false` → step
  `failed()` → `PaymentConfirmed` never runs → `OrderPaid` (dispatched
  `afterCommit` in the transition) never fires. Chain traced end to end.
- Currency gate (commit `a60e3e2c6`): normalized (trim + uppercase) equality
  required; missing/unparseable rejected — no fallback. Mismatch taxonomy
  recorded (`amount_mismatch` / `currency_mismatch` / both) with expected +
  received values. Missing-amount session-total fallback intentionally
  retained (previously approved).
- Backfill removal (commit `df0fd32d2`): provider-result `currency` no longer
  backfilled from session in `ProcessPaymentStep`, `CheckoutService`, or the
  demo processor. Blast radius verified zero — every other currency consumer
  reads `$session->currency`; `PaymentResult::$currency` was already
  nullable by design.
- Residual (new, open): amounts are compared without currency-aware
  minor-unit handling — acceptable while all money is integer minor units
  in a single pipeline; revisit if multi-subunit currencies arrive.

## Cashier-chip — billing correctness (Critical, fixed)

- `period_key`: monthly keeps `Y-m`; sub-monthly uses ISO timestamps
  (`ClaimRenewalAttempt`). Real granularity fix, verified in source.
- Amount integrity: snapshotted at claim, mutation throws
  (`RenewalAttempt::updating` guard), bounded at claim
  (`Cashier::maximumAmount`) and at execution
  (`isAmountWithinBounds` in `RenewSubscriptionsCommand` and direct-charge
  paths).
- Fairness note: the "recomputed mutable totals" phrasing was half-stale
  (a snapshot column already existed) — but the added guards closed real
  gaps, so the fix is substantive, not cosmetic.

## Customers — resolver scoping + uniqueness (Critical, fixed)

- `findCustomerByEmail` scoped with `forOwner` + normalized email
  (`LOWER(TRIM)` semantics in SQL, `mb_*` in PHP — aligned).
- Model hooks (`creating`, email-dirty `updating`) enforce within-scope
  uniqueness with self-exclusion.
- Filament merge search was already owner-scoped (`OwnerUiScope`) — no
  change needed.
- Post-track migration (see `migration-record.md` appendix): owner-aware
  email unique (driver-aware partial/functional indexes, preflights).
  App-level race remains possible without it; the constraint is the real
  invariant.

## Checkout — compensation (follow-up, implemented)

- `CheckoutStepInterface::compensate()` with safe no-op default for steps that
  own no reversible side effects.
- Executor compensates in reverse order over completed/processing/failed
  states; payment selects refund vs void by status; inventory release honors
  its kill-switch.
- Events registration and pass steps now persist the IDs they create before
  later work can fail, cancel/revoke those IDs during compensation, restore
  marked order-item options, and return an explicit compensation result.
- External delivery/email cannot be unsent; the created pass is nevertheless
  durably captured and revoked, leaving an auditable compensation trail.

## Chip — hardening (implemented)

- `spatie/laravel-webhook-client` declared AND vendored. Six phantom
  Filament resources deleted (zero references). Send-vs-Collect separation
  pinned.
- Idempotency mechanism is real (cache replay + payload fingerprint +
  lock-protected double-check in `PurchasesApi`; `PurchaseBuilder::
  idempotencyKey()`), and the fake-subclass signature incident was handled
  correctly (revert + alternate path).
- Checkout's direct and Cashier CHIP processors now use the persisted checkout
  session ID as the stable idempotency key; billable charges receive the same
  key through `PerformsCharges`. Setup purchases require an explicit key,
  intentionally making missing setup-attempt identity a breaking error.
- The unified Cashier CHIP gateway now rejects missing setup keys before
  billable dispatch. Filament's portal, customer page, and relation-manager
  callers retain one explicit key per Livewire setup attempt; resource actions
  reset it only after a URL is created, while the portal also caches the URL.
  Keyless setup rejection no longer creates a CHIP customer as a side effect.
- The official CHIP create-purchase documentation does not document a native
  idempotency header or field. The key is therefore consumed by the local
  cache/lock/fingerprint mechanism before the gateway request; no native
  gateway guarantee is claimed.
- **Setup-intent callers closed (follow-through):** `createSetupPurchase`
  and cashier's `ChipGateway::createSetupIntent` both reject a missing/
  blank `idempotency_key` before dispatch (fail-fast, documented message).
  Filament callers (portal page, relation manager, customer view) use a new
  `HasSetupPaymentMethodIdempotencyKey` trait — memoized UUID persisted as
  Livewire component state, so retries reuse the key; explicit reset starts
  a new attempt. Cashier + cashier-chip docs specify the contract with
  examples. External keyless callers must now supply and retain their own
  key (breaking, intentional — a silent fallback key would defeat
  idempotency).

## Inventory — A1 serial unification (implemented)

- Falsification first: vendor `State::getMorphClass()` returns the `$name`
  slug and the deleted enum carried byte-identical values — the audit's
  FQCN-corruption mechanism could not occur. Genuine defect was duplicated
  vocabulary only.
- Fix is behavior-preserving: badge/filter/form moved to state classes with
  identical string values; zero remaining enum references; docs updated.
- Full evidence chain in `migration-record.md`.

## Commerce-support foundation settling (implemented)

- **Money strictness:** `MoneyFormatter` (`formatMinor`, `formatMajor`,
  variants) and `MoneyNormalizer::toCents()` narrowed to `int`; every
  repo-wide caller passing `float|string` converted with explicit
  half-up rounding (centralized in a `ChipIntegerModel` helper where the
  pattern repeated). No shims or overloads retained. J&T float-math
  rerouting deferred — equivalence unproven, logged as follow-up.
- **Single navigation engine:** `CommerceNavigation` canonical;
  `ManageCommerceNavigation` reduced to a settings Page that consumes the
  engine for all discovery/resolution (verified call sites). Runtime
  overrides preserved with tests. Legitimate adapter layering, not a
  retained shim.
- **Authz models moved:** `Role`, `Permission`, `AuthzScope` now live under
  `AIArmada\Authz\Models`; originals deleted with zero remaining
  old-namespace references repo-wide (including the heavy filament
  consumer surface); `AuthzServiceProvider` owns registration. Stronger
  than the audit asked (no alias release cycle).
- **Octane lifecycle (follow-up, implemented):** `OwnerContext::flushState()`
  plus registry `flush()` methods, guarded `RequestReceived`/
  `RequestTerminated` listener registration (`class_exists` checks — safe
  without Octane installed), filament `Authz` binding scoped, leak
  regression tests. Registries verified to be unbound instance arrays, so
  no bindings were invented; flush is invoked only where bound.
- **Helpers/stubs (follow-up, implemented with correction):** shipping call
  sites moved to `MoneyFormatter::symbol()`; `commerce_morph_key()` helper
  created and both stubs shrunk to straight-line calls; helpers grouped by
  concern. Correction: `currency_symbol()` RETAINED, not deleted — the
  audit had the dependency backwards (`MoneyFormatter::symbol()` falls
  back TO the helper; delegating would recurse). Likewise the audit's
  "delegate to `ConditionalMigrationLoader`" was wrong-headed (it is purely
  a migration file loader) — grouping was the right fix.
- **Dependency-direction guard (new, permanent):**
  `tests/src/CommerceSupport/Architecture/
  CommerceSupportArchitectureTest.php` derives downstream namespaces from
  composer manifests and asserts `commerce-support/src` references none of
  them, plus a lean composer require. Keeps the foundation honest going
  forward.

## Fairness log

- Orders checkout-context concern: not present, dropped correctly.
- Cashier "mutable totals": half-stale, fix substantive (above).
- Filament customer search scoping: already correct, kept.
- "Zero tests" layout claims: repo-root suites existed; taken into account.
- Serial FQCN storage: false — the one audit mechanism overturned by
  vendor source in the entire review program.
- Direct CHIP checkout was partly overstated: `PurchasesApi` already fell back
  to the checkout `reference` when no explicit key was supplied. The explicit
  session key still closes the billable/setup adoption gap and makes the
  contract visible at each money-path caller.
- The Cashier CHIP billable integration also exposed a pre-existing wrapper
  accessor mismatch (`Payment::checkoutUrl()` / `Payment::id()` versus the
  nested purchase object); it was corrected because the new billable-path
  regression test exercised the real return path.
