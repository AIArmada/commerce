# Vouchers Audit

## Packages Reviewed (bullets)

- `packages/vouchers` (`aiarmada/vouchers`) — domain owner: issuance, cart-condition redemption, wallets, stacking engine, compound conditions/matchers, usage tracking, affiliate bridge.
- `packages/filament-vouchers` (`aiarmada/filament-vouchers`) — Filament v5 admin adapter: 3 resources, 6 actions, 2 config pages, 8 widgets.

Source layout inspected: `src/` (Actions ×8, Cart, Compound ×12, Concerns, Conditions, Contracts, Data ×2, Enums, Events ×6, Exceptions ×9, Facades, Filament/{Exports,Extensions,Integrations}, Listeners ×2, Models ×5, Services ×3, Stacking ×11, States ×5, Support ×7, Traits ×3), `config/vouchers.php`, `config/filament-vouchers.php`, `database/migrations` (6), `examples/usage.php`, providers, `CONTEXT.md`/`README.md`/`docs` (11 files). No `routes/`, no `tests/` in either package (verified).

## Overall Assessment (quality, health, risks, refactor size)

The strongest domain modeling of the four pairs (basis-points percentages, minor-units money, state machine, stacking rules engine, fail-closed targeting) — and the leakiest boundaries. The flagship `VoucherService::isValid()` bypasses the real validator, and stacking defaults disagree while unused factories rot. Refactor size: M (one validity-API consolidation, deletion of dead stacking factories). No schema redesign.

## Migration Impact

**Migration Required: NO** — migration track completed 2026-09-07, see `migration-record.md#vouchers`

## Package Responsibilities

- Owns: voucher CRUD + validation + cart application/removal, discount calculation (simple + compound), stacking policy evaluation, wallets, usage records + idempotency, manual redemption, affiliate linkage columns.
- Does NOT own: cart engine (cart), checkout orchestration (checkout), affiliate domain (affiliates), promotion campaigns (promotions — consumed read-only + issuance target).
- Filament adapter owns: voucher/usage/wallet CRUD UI, stacking/targeting config pages, cart-assist widgets/actions. Must stay UI-only (mostly true; violations in F-sections).

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### A4 — `VoucherService::isValid()` is a weaker parallel validator

- Severity: High
- Location: `src/Services/VoucherService.php:101-116` (`isValid`: active + started + !expired + global-usage only) vs `src/Services/VoucherValidator.php:34-133` (adds per-user limit, min-cart value, targeting rules, paused/depleted messaging).
- Problem: Two validity definitions. Any caller using the cheap one (`isValid`, and its sibling `canBeUsedBy` — which only checks `usage_limit_per_user`, verified `VoucherService.php:118-135`) accepts vouchers the real validator rejects (below-min-cart, wrong segment, over per-user cap, paused/depleted).
- Why It Matters: Correctness fork on a money path; reviewers must check which one each caller chose.
- Recommended Fix: Delete `isValid()` and `canBeUsedBy()` from `VoucherService` AND their `VoucherServiceInterface` (`:65`) + `Facades/Voucher` docblock entries; all call sites use `validate($code, $cart)->isValid`. Re-check (hardening pass): repo-wide grep finds zero code callers of either method (only interface/facade/docs) — `cashier-chip` `->isValid()` hits are `Coupon::isValid`, a different class — so deletion is safe in-repo with no caller migration.
- Breaking Change: YES (public service methods removed) — zero in-repo consumers; remove the interface + facade entries in the same pass; no schema impact.
- Affected Packages: none in-repo (interface consumers re-resolve to `validate()`).
- Required Dependent Changes: none found in-repo; external consumers switch to `VoucherServiceInterface::validate()`.
- Migration Required: NO.

### A5 — `getTimesUsedAttribute` is a per-model COUNT query (N+1 by design)

- Severity: High
- Location: `src/Models/Voucher.php:362-375` (falls through to `$this->usages()->count()` unless `usages_count` attribute or loaded relation present); consumed by `hasUsageLimitRemaining()`, `getRemainingUses()`, `checkIfDepleted()`, statistics accessors, and thereby by the validator hot path.
- Problem: Every voucher touch without an explicit `withCount('usages')` costs a query; lists validating many vouchers (suggestions widget, stacking evaluation, checkout multi-code) fan out. Filament tables already `withCount('usages')` (`VouchersTable.php:39`, `VoucherSuggestionsWidget.php:81`) — the domain does not.
- Why It Matters: Validation is per-checkout; N+1 scales with codes × redeemers.
- Recommended Fix: In `Concerns/QueriesVouchers.php::voucherQuery()`, add `->withCount('usages')` by default (single extra column, no behavior change — the accessor already prefers `usages_count`). Audit other `Voucher::query()` entry points (`VoucherService::find/isValid-paths`, stacking, compound matchers) to route through `voucherQuery()`. Do NOT add a `times_used` counter column now (dual-counter drift with `applied_count` is worse; measure first).
- Breaking Change: NO.
- Affected Packages: `checkout` (faster validation, same results).
- Required Dependent Changes: none.
- Migration Required: NO.

### A7 — Status state-machine vs wall-clock checks diverge; transitions bypass guards

- Severity: Medium
- Location: `src/States/*` (spatie `VoucherStatus`: `Active/Paused/Expired/Depleted`), `Models/Voucher.php:583-611` (boot hooks set `paused_at/depleted_at/last_activated_at`), `checkIfDepleted()` (`:299-306` uses `->update(['status' => Depleted::class])`), `Actions/ExpireVoucher.php` (zero callers repo-wide — verified).
- Problem: (a) `isActive()/isExpired()/hasStarted()/canBeRedeemed()` mix state-object checks with wall-clock checks, so `status=Active` + past `expires_at` is simultaneously "active" and "expired" depending on caller — the validator orders time-checks first, but nothing else is forced to. (b) `checkIfDepleted` writes status via mass-update, bypassing any spatie transition guards. (c) Nothing ever calls `ExpireVoucher` — no command, no schedule, no listener — so `Expired` state is reachable only by manual action while `isExpired()` is time-based; the two never reconcile.
- Why It Matters: Status column cannot be trusted for queries (`where status = Active` includes time-expired rows); the Filament `status` index/filter is misleading.
- Recommended Fix: Canonicalize: wall-clock is truth for expiry (`isExpired()`), state column is truth for admin intent (paused/depleted). (1) Register `ExpireVoucher` in a new `vouchers:expire` command + document scheduling (mirror promotions A10), OR delete the action and the `Expired` state case if time-checks are deemed sufficient — pick one, do not keep both half-alive. (2) Route `checkIfDepleted` through the state transition (`$voucher->status->transitionTo(Depleted::class)`) instead of `update()`. (3) Add a `scopeLive()` combining `Active + started + !expired + has-limit` and migrate internal queries to it.
- Breaking Change: NO (query narrowing only excludes rows that should already be excluded).
- Affected Packages: `filament-vouchers` (status filters/badges adopt `scopeLive` where "redeemable" is meant), `checkout` (validator already time-first — unchanged).
- Required Dependent Changes: none code-wise; document the chosen expiry story in `docs/09-usage-tracking.md`.
- Migration Required: NO.

### A8 — `Voucher::deleting` cascades three relations without a transaction

- Severity: Medium
- Location: `Models/Voucher.php:606-610` (`usages()->delete(); walletEntries()->delete(); transactions()->delete();` — and note `transactions()` targets a table whose only writers are… nothing, see A9).
- Problem: Partial cascade on mid-way failure orphans usage/wallet rows; also pays for deleting from the dead `voucher_transactions` table.
- Why It Matters: Orphaned `voucher_usage` rows corrupt `times_used` counts and per-user limits.
- Recommended Fix: Wrap in `DB::transaction()`.
- Breaking Change: NO. Affected: none. Migration: NO.

### A10 — Stacking defaults disagree; unused factories rot

- Severity: Medium
- Location: `config/vouchers.php:45-70` (`mode: sequential`, `max_vouchers: 1` via env, `max_discount_percentage: 50`, `auto_replace: true`) vs `Stacking/StackingPolicy.php:46-62` (`default()`: max 3) vs `singleVoucher()`/`unlimited()` factories — container binds `fromConfig()` (`VoucherServiceProvider.php:52-54`); `default()/singleVoucher()/unlimited()` have zero callers (verified).
- Problem: Reader cannot tell the effective policy without tracing the provider; `default()` (max 3) contradicts config (max 1) and would silently widen stacking if anyone called it.
- Why It Matters: Stacking width is money — ambiguous defaults invite the wrong one.
- Recommended Fix: Delete `default()`, `singleVoucher()`, `unlimited()`; keep `fromConfig()` as the sole constructor path. Document the effective default (sequential, 1 voucher, 50% cap, auto-replace) in `docs/03-configuration.md`.
- Breaking Change: NO (unreachable code).
- Affected Packages: `checkout` (reads effective policy only — unchanged), `filament-vouchers` stacking config page (verify it edits `vouchers.stacking` config keys, not the deleted factories).
- Required Dependent Changes: none.
- Migration Required: NO.

### A11 — `applied_count` vs `times_used` dual counters with split increment paths

- Severity: Medium
- Location: `applied_count` incremented by `Listeners/IncrementVoucherAppliedCount.php` on `VoucherApplied` event; `times_used` derived from `voucher_usage` rows (written by `RecordVoucherUsage`); conversion stats divide them (`getConversionRate()`, `getStatistics()`).
- Problem: Two writers, two meanings ("applied to cart" vs "redeemed"), reconciled only in accessors. If any apply/remove path skips the event (e.g. compound conditions, `ApplyVoucherToCartAction` in filament — verify each dispatches `VoucherApplied`), conversion rates skew silently.
- Why It Matters: Dashboard stats (`VoucherStatsWidget`, `RedemptionTrendChart`) are only as honest as event coverage.
- Recommended Fix: Grep every `VoucherCondition` application site for `VoucherApplied::dispatch`; add the dispatch where missing (or centralize: dispatch inside `VoucherCondition` application itself so all paths inherit it). Document the two counters' definitions in `docs/09-usage-tracking.md`.
- Breaking Change: NO.
- Affected Packages: `checkout` (`VouchersAdapter` apply path), `filament-vouchers` (`ApplyVoucherToCartAction`).
- Required Dependent Changes: verify-and-fix dispatch coverage in the same pass.
- Migration Required: NO.

## Code Quality Findings (same finding format)

### C1 — `VoucherDiscountCalculator` launders minor units through float

- Severity: Low
- Location: `src/Services/VoucherDiscountCalculator.php:36` (`abs($condition->getCalculatedValue((float) $subtotal))` then `(int) round(…)`).
- Problem: int cents → float → int round-trip; precision loss past 2^53 and double-rounding vs the condition's own rounding.
- Why It Matters: Off-by-one-cent risk on large subtotals; obscures the money contract.
- Recommended Fix: Keep the boundary int: change `VoucherCondition::getCalculatedValue` to accept int minor units (or add an int overload) and round once at the edge. Small, mechanical.
- Breaking Change: NO (internal precision fix). Affected: `checkout` totals (more correct). Migration: NO.

### C2 — `VoucherData` float-accepting fields with runtime rejection

- Severity: Low
- Location: `src/Data/VoucherData.php:46,142-146,329` (docblock allows `int|float`, constructor throws `InvalidVoucherDataException` on non-integer floats).
- Problem: Type says maybe-float, runtime says integer — the honest type is `int`.
- Why It Matters: Callers (notably `promotions` issuance mapping) must guess.
- Recommended Fix: Type the fields `int`, coerce at the DTO boundary (`fromModel`/issuance) with explicit rounding, and drop the exception path. Verify `promotions` `mapVoucherValue` (percentage×100 → int, verified correct: 20% → 2000bp) still typechecks.
- Breaking Change: YES (DTO signature) — update `promotions` issuance + filament forms in-pass.
- Affected Packages: `promotions`, `filament-vouchers` (form dehydration via `MoneyHelper` already yields ints — verify).
- Migration Required: NO.

### C3 — `QueriesVouchers::voucherQuery()` owner semantics are implicit

- Severity: Low
- Location: `src/Concerns/QueriesVouchers.php:7,36` (`OwnerContext::resolve()` inside the shared query).
- Problem: Every `find/validate/delete` inherits ambient-owner filtering — correct per the monorepo contract, but callers doing cross-owner work (issuance from a promotion of another owner, admin tools) must know to re-enter context. `IssueVouchersFromPromotion` does; document the rule on the trait itself.
- Why It Matters: Next cross-owner feature will rediscover this at runtime.
- Recommended Fix: Docblock on `voucherQuery()` stating the ambient-owner contract + pointing at `OwnerContext::withOwner`. No behavior change.
- Breaking Change: NO. Migration: NO.

## Laravel-Specific Findings

- PHP 8.4: PASS (`^8.4`, enums, readonly DTOs, constructor promotion).
- PKs: PASS (all six migrations `uuid('id')->primary()`).
- FK constraints/cascades: PASS — `foreignUuid()` without `constrained()`/`cascadeOnDelete()` everywhere (verified); cascades app-level in `Voucher::deleting` (A8 hardens with a transaction).
- Owner scoping: PASS — `HasOwner` + `HasOwnerScopeConfig` (`vouchers.owner`), `nullableUuidMorphs('owner')`, `getTable()` from config with prefix fallback, `scopeForOwner` override ANDing caller flag with config (same decorative-parameter wart as pricing C1 — normalize to trait default when touching the file; not worth a standalone change).
- Config: has `json_column_type` + `table_prefix`/`tables` (compliant); `database.tables` + `table_prefix` dual source in `Voucher::getTable()` is redundant but harmless.
- Money: PASS — cents/basis-points ints, `MoneyFormatter` at display edges. Filament `MoneyHelper` correctly wraps `MoneyNormalizer`/`MoneyFormatter` for form dehydration (keep it — it is adapter-specific, not duplication); one nit: `displayToCents`/`displayToBasisPoints` do `(float)$display * 100` — route through `MoneyNormalizer::toCents()` for consistency.
- Facade (`Voucher` alias) + `'voucher'` container binding + `provides()` list are coherent.
- `examples/usage.php` — verify it still matches the post-cleanup API (it references service methods; update if it touches `isValid`).

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)

### F1 — Domain package hosts Filament UI (`src/Filament/`)

- Severity: Medium
- Location: `packages/vouchers/src/Filament/{Exports/VoucherUsageExporter.php, Extensions/CartVoucherActions.php (279 lines), Integrations/FilamentCartBridge.php}`.
- Problem: Filament actions, tables, notifications, and `FilamentCartBridge` (which references `FilamentCart\*` models/services) live in the domain package, which does not require `filament/*`. Any `filament/*` API drift breaks the domain package's class loading surface; the adapter boundary (`filament-vouchers` owns UI) is violated in both directions (two candidate homes for cart actions).
- Why It Matters: Standalone domain installs carry UI dead weight; the next Filament major bumps the wrong package.
- Recommended Fix: Move all three files to `filament-vouchers` (`Actions/` or `Support/` + exporter namespace), keeping class names where possible; leave no alias (per no-legacy rule — update the single consumer `filament-cart` ViewCart import in the same pass). `vouchers` keeps zero Filament references afterwards (verify with `rg -l 'Filament\\' packages/vouchers/src` → empty).
- Breaking Change: YES (class moves) — consumers updated in-pass (`filament-cart`, `filament-vouchers` provider which singletons `FilamentCartBridge`).
- Affected Packages: `filament-cart`, `filament-vouchers`.
- Required Dependent Changes: `FilamentVouchersServiceProvider` singleton registration moves with the class; `ViewCart` import updated.
- Migration Required: NO.

### F2 — `VoucherStatsAggregator` vs domain statistics duplication

- Severity: Low
- Location: `packages/filament-vouchers/src/Support/VoucherStatsAggregator.php` vs `Voucher::getStatistics()/getConversionRate()/getAbandonedCount()`.
- Problem: Two stats implementations (model accessors + aggregator service) — verify they share definitions (applied vs redeemed vs abandoned) or dashboards and model badges disagree.
- Why It Matters: Same-number-different-place bugs erode trust in reporting.
- Recommended Fix: Make the aggregator call the model accessors per voucher for per-row numbers and keep SQL only for collection rollups; add one test pinning `applied - redeemed = abandoned` on both paths.
- Breaking Change: NO. Migration: NO.

### F3 — Navigation/config compliance, with two nits

- PASS: nested `navigation.group` (`Vouchers & Discounts`), `getNavigationGroup()`/`getNavigationSort()` from config on all resources/pages (verified), no static `$navigationGroup` in `src`.
- Nit 1 (Low): `VoucherResource::getNavigationSort()` falls back to `40` while config ships `10` — align the code default to the shipped config value so behavior without published config matches behavior with it.
- Nit 2 (Low): `config/filament-vouchers.php` `owners => []` + `OwnerTypeRegistry` builds per-request collections from config — fine, but document the expected `owners` entry shape (model/label) in `docs/03-configuration.md`; today only code defines it.
- Dependency direction: PASS — `filament-vouchers` requires `vouchers`; domain→adapter references exist only via the `src/Filament/` violation (F1).

## Database Findings

- Six migrations, uuid PKs, `nullableUuidMorphs('owner')`, jsonb via `commerce_json_column_type`, GIN indexes on pgsql for metadata/targeting/stacking/exclusion — best index discipline of the four pairs. `voucher_usage.idempotency_key` (000006) is the right call.
- `promotion_id`/`affiliate_id`/`affiliate_program_id` indexed without constraints — compliant.
- `applied_count` default 0 unsigned — good; no counter cache for `times_used` by design (A5 keeps it that way, fixes the read path instead).
- `status` stored as FQCN string with index — consistent with spatie-model-states; `scopeLive` (A7) should be the query habit, not raw `status =` filters.

## Model / Domain Findings

- Money representation is exemplary: cents/basis-points documented on the DTO, the model, the migration, and the filament helper. `promotions` issuance mapping (`discount_value × 100` for percentages) verified correct against this contract.
- `VoucherCondition`/`CompoundVoucherCondition`/matchers form a real rules engine — keep; the A11 dispatch-coverage check is its only debt.
- `target_definition` fail-closed parsing in the validator (`__empty_rules` sentinel) is the right security posture — keep and pin with tests.
- Manual redemption gating (`allows_manual_redemption` + `manual_requires_flag`) is coherent; `ValidateVoucherCode` action vs `VoucherValidator` service naming overlap is confusing — canonicalize on the service, keep the action as a thin `AsAction` entry if the filament actions call it (else delete the action).

## Security Findings

- Fail-closed targeting, owner-scoped `voucherQuery()`, cross-owner issuance guard (`VoucherAffiliateOwnershipGuard::sanitize` on create/update paths — verified wired in `CreateVoucher`, `UpdateVoucher`, and both filament pages) — good depth.
- Per-user limits enforced in the validator via `voucher_usage (redeemed_by_type/id)` counts — live, unlike promotions' dead `per_customer_limit`. Keep.
- Open items: A4 (weak parallel validator); `VoucherService::delete()` deletes by code through the owner-scoped query (safe) but is not wrapped in the same ownership double-check as create/update — add the guard for symmetry. Low.
- `NormalizesVoucherCodes` shared normalization — verify validator, service, and filament quick-apply all use it (else `SAVE10` vs `save10` bypasses per-user counts). Pin with a test.

## Performance Findings

- A5 (withCount-by-default) is the main fix. Secondary: `getNavigationBadge()` counts on every render (`VoucherResource`); `VoucherUsageTimelineWidget` + `AffiliateReportingContextResolver` per-row affiliate lookups — eager-load affiliate relations in timeline queries. All Low except A5.
- Cart-condition resolution (`VoucherServiceProvider::packageRegistered` resolver closure) runs `Voucher::find($code)` per array/string payload — acceptable (single indexed lookup), but confirm no per-item loop in `VoucherConditionProvider` re-resolves the same code per line (cache within request if so).

## Testing Findings

- Zero tests across both packages despite the largest domain surface. Priority order (Pest, `--parallel`): validator matrix (expired/paused/depleted/limit/min-cart/targeting-fail-closed); stacking policy decisions (max count, type restriction, exclusion groups); `IssueVouchersFromPromotion` owner-context behavior incl. global-promotion exception; `scopeLive` vs legacy status filters; code-normalization symmetry; idempotent usage recording (double-submit same `idempotency_key` → one row).

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `filament-cart` (`ViewCart`) | `CartVoucherActions`, voucher widgets | F1 moves their home | Follow the move (F1); add smoke test |
| `affiliates` (`VoucherBridge`, `VoucherIntegrationRegistrar`, `AttachAffiliateFromVoucher`) | `Voucher` model, `VoucherApplied` event | A11 may add dispatches (more affiliate attributions — intended) | Verify attribution counts after A11 |
| `checkout` (`VouchersAdapter`, `ValidatePromoCodeAction`, `DiscountCodeResolver`, `RedeemVouchersOnCheckoutCompleted`) | `VoucherServiceInterface`, `VoucherData`, `VoucherValidationResult`, `VoucherDiscountCalculator`, stacking | A4 removes dead methods with zero in-repo callers; A10 clarifies stacking width | Re-run checkout discount tests |
| `promotions` (`IssueVouchersFromPromotion`) | `VoucherServiceInterface::create`, voucher columns | C2 int-typed DTO fields | Update issuance mapping in the same pass as C2 |
| `filament-promotions` (`IssuedVouchersRelationManager`) | `promotion_id` column | Backfill makes the relation complete | None (benefits automatically) |
| `cashier-chip` (coupons) | none (its `->isValid()` hits are `Coupon::isValid`, a different class — verified) | None | None |
| `csuite`, `signals` | none (zero voucher references in `src` — verified) | None | None |
| `filament-vouchers` | domain models/services | A4, A5, A7, A8, A10, A11, F1–F3 | Move-in of `src/Filament/*`; adopt `scopeLive`; align nav-sort default; `MoneyHelper` float→normalizer nit |

## Recommended Refactor Plan (ordered steps)

1. Consolidate validity API — delete `isValid`/`canBeUsedBy`, migrate checkout/cashier callers (A4).
2. `withCount('usages')` by default in `voucherQuery()` (A5); transaction-wrap `Voucher::deleting` (A8).
3. Expiry story decision (command vs time-checks) + `scopeLive` + transition-routed depletion (A7).
4. Delete dead stacking factories (A10); dispatch-coverage audit for `applied_count` (A11); float→int calculator edge (C1); DTO int types (C2).
5. Move `src/Filament/*` → `filament-vouchers` (F1); stats parity test (F2); nav-sort default + owners docs (F3); update `examples/usage.php`.
6. Full Pest suite per Testing Findings (`./vendor/bin/pest --parallel` scoped).

## Files Likely to Change

- `packages/vouchers/src/Concerns/QueriesVouchers.php` (withCount default + contract docblock)
- `packages/vouchers/src/Services/VoucherService.php` (delete `isValid`/`canBeUsedBy`)
- `packages/vouchers/src/Models/Voucher.php` (accessors, `scopeLive`, transition-routed depletion, transactional delete)
- `packages/vouchers/src/Stacking/StackingPolicy.php` (delete factories)
- `packages/vouchers/src/Data/VoucherData.php` (int types)
- `packages/vouchers/src/Services/VoucherDiscountCalculator.php`, `src/Conditions/VoucherCondition.php` (int boundary)
- `packages/vouchers/docs/*`, `examples/usage.php`
- `packages/filament-cart/src/Resources/CartResource/Pages/ViewCart.php`
- `packages/checkout/src/Integrations/VouchersAdapter.php`, `Actions/ValidatePromoCodeAction.php`, `Integrations/DiscountCodeResolver.php`, `Listeners/RedeemVouchersOnCheckoutCompleted.php`
- `packages/promotions/src/Actions/IssueVouchersFromPromotion.php`
- `packages/filament-vouchers/src/**/*` (move-in target + pages/widgets adoption)

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/vouchers/src/Traits/HasVoucherOwnership.php` — verify callers; no external users found (same grep class as `HasVouchers`); delete if the in-package grep confirms zero use.
- `packages/vouchers/src/Support/CartWithVouchers.php` — superseded by `CartManagerWithVouchers` decorator (verify no remaining refs beyond the trait's `instanceof` checks, then delete both the class and the checks).
- `packages/vouchers/src/Stacking/StackingPolicy.php::default()/singleVoucher()/unlimited()` — zero callers; `fromConfig()` is the live path.
- `packages/vouchers/src/Services/VoucherService.php::isValid()/canBeUsedBy()` — weaker parallel validators (A4).
- `packages/vouchers/src/Actions/ValidateVoucherCode.php` — if filament/checkout call sites all use `VoucherValidator` service directly (verify); else keep as the single thin entry and delete the ambiguity by documenting it.
- `packages/vouchers/src/Filament/*` (3 files) — moved, not duplicated (F1).

## Final Recommended Architecture

Domain owns: integer-money models + fail-closed validator as the SOLE validity entry + `VoucherService` (CRUD/apply/remove/validate passthrough) + stacking engine constructed only via `fromConfig()` + wallet/usage tracking with idempotency + affiliate columns with guard. One validity API, one stats definition shared by model accessors and the filament aggregator, wall-clock expiry truth with an explicit scheduled sweeper, transactional deletes. All Filament code (including cart-assist actions and the cart bridge) lives in `filament-vouchers`; `filament-cart` and `affiliates` reference real FQCNs with regression tests pinning them.
