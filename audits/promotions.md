# Promotions Audit

## Implementation outcome (migration track, 2026-09-07)

The dead strategy and unsupported promotion-type claims were confirmed. Migration `2026_09_07_110000_deactivate_buy_x_get_y_promotions.php` canonicalizes legacy BOGO rows to inactive fixed-zero rows before the enum case is removed; the separate voucher BOGO flow remains live.

## Packages Reviewed (bullets)

- `packages/promotions` (`aiarmada/promotions`) — domain owner: automatic/code-based discount campaigns, targeting evaluation, voucher issuance bridge, expiry commands.
- `packages/filament-promotions` (`aiarmada/filament-promotions`) — Filament v5 admin adapter: `PromotionResource`, two issue-vouchers actions, two widgets.

Source layout inspected: `src/` (Actions ×5, Console/Commands ×2, Contracts ×2, Enums, Events ×4, Listeners ×2, Models, Services, Strategies ×3, Support), `config/promotions.php`, `config/filament-promotions.php`, `database/migrations` (1 file, 2 tables), `database/factories` (`PromotionFactory`), `composer.json` × 2, providers, `CONTEXT.md`/`README.md`/`docs`. No `routes/` and no `tests/` in either package (verified: no test files; only the factory exists).

## Overall Assessment (quality, health, risks, refactor size)

The core discount math (`PromotionService`, `Promotion::calculateDiscount`) is small and money-clean (integer cents), and owner-scoping on the model follows the monorepo contract. But the package carries an unusual amount of dead or inert code for its size: an unregistered stub console command, a never-callable strategy subsystem (would throw if called), a listener that is both unregistered and effect-free, and an advertised `BuyXGetY` type that silently discounts zero everywhere. The one live cross-package flow — usage counting via `OrderPaid` → `CheckoutSession.discount_data` — depends on an undocumented checkout payload shape and fails silently. Refactor size: S (mostly deletions + small enforcement fixes + one data migration if the dead discount type is removed). Highest risk is semantic, not structural: things that look like features and do nothing.

## Migration Impact

**Migration Required: YES**

| Table | Change | Type |
|---|---|---|
| `promotions` | Data migration: convert existing `type = 'buy_x_get_y'` rows to `is_active = false` (or operator-chosen `fixed` equivalent) then remove the enum case from code. No schema change. | Data-only migration (new file). The type is inert (discounts 0 everywhere, see A3) — leaving rows selectable under a removed case breaks reads. |
| `promotions` | No column/index/constraint change (`code` unique stays; lookup becomes exact-match on normalized code, A7) | — |
| `promotionables` | No change (composite PK, no constraints — compliant) | — |

If the team instead implements real BuyXGetY (alternative noted in A3), migration is NO. All other findings are code/docs-only.

## Package Responsibilities

- Owns: `Promotion` lifecycle (CRUD actions, deactivation, expiry sweep), automatic/code promotion matching via commerce-support targeting engine, discount math per type, usage counting, voucher issuance from a promotion.
- Does NOT own: price-list pricing (pricing), coupon redemption/wallets (vouchers), cart/checkout orchestration (consumers via `PromotionServiceInterface`).
- Filament adapter owns: promotion CRUD UI, issue-vouchers actions, stats widgets. Must stay UI-only.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### A1 — Strategy subsystem is dead AND would throw if invoked

- Severity: High
- Location: `src/Actions/ApplyPromotionToCart.php:14-18,29-37` (`#[Tag('promotions.strategy')] iterable $strategies`), `src/Strategies/PercentageStrategy.php`, `src/Strategies/FixedStrategy.php`, `src/Strategies/BuyXGetYStrategy.php`, `src/Contracts/PromotionStrategyInterface.php`.
- Re-check (hardening pass): verified no `#[Tagged]`/tag registration exists repo-wide (only the `#[Tag]` consumer) and `ApplyPromotionToCart` has zero code callers (only docs/CONTEXT); demoted Critical → High — dead trap with major maintainability cost, but zero live callers so no runtime/data impact.
- Problem: Nothing in the repo ever tags `promotions.strategy` (verified repo-wide), so `$strategies` is always empty and `resolveStrategy()` always throws `RuntimeException("No strategy found…")`. `ApplyPromotionToCart::handle()` itself has zero callers (verified). The live discount path is `PromotionService::calculateDiscounts()` + `Promotion::calculateDiscount()`, which duplicates the percentage/fixed math inline.
- Why It Matters: A whole "extensible strategy" architecture exists only as a trap: anyone calling the public Action gets an exception, and the real math lives elsewhere.
- Recommended Fix: Delete all five files (`ApplyPromotionToCart.php`, the three strategies, `PromotionStrategyInterface.php`). The `match` in `Promotion::calculateDiscount()` is the single discount implementation. Update `docs/` (`05-promotion-service.md`, `01-overview.md`) and `CONTEXT.md` surface lists.
- Breaking Change: YES (removes public classes) — no internal consumers; update any external references (none in-repo).
- Affected Packages: none internally. `checkout` does not use it (uses `PromotionServiceInterface`).
- Required Dependent Changes: none.
- Migration Required: NO.

### A2 — `BuyXGetY` is advertised but discounts zero on every path

- Severity: High
- Location: `src/Enums/PromotionType.php:16` (case + label/icon/describe helpers), `src/Models/Promotion.php:337` (`BuyXGetY => 0, // Handled separately`), `src/Services/PromotionService.php:107` (calls the same `calculateDiscount`), `src/Actions/IssueVouchersFromPromotion.php:134,143` (maps to voucher `buy_x_get_y` with value 0).
- Problem: "Handled separately" is false — repo-wide grep shows no handler: checkout adapters contain zero `buy_x_get_y` references, so a BuyXGetY promotion matches, applies, records usage, and discounts 0. Operators see applied promotions with no effect.
- Why It Matters: Silent wrong-discount is worse than a missing feature; usage counts burn on zero-value applications.
- Recommended Fix: Remove the `BuyXGetY` case from `PromotionType` (and its label/icon/describe arms), remove the `match` arm, and remove the voucher-mapping arms (map to `fixed` 0 is meaningless — instead reject issuance for that type before removal lands). Data-migrate existing `buy_x_get_y` rows to `is_active = false` first (Migration Impact). If product insists on BOGO, that is new feature work requiring cart-line-aware evaluation in `PromotionService` — do not half-keep the case.
- Breaking Change: YES (enum case removal; rows deactivated via migration).
- Affected Packages: `checkout` (`PromotionsAdapter`, `ValidatePromoCodeAction` — must handle rows disappearing / type gone), `filament-promotions` (form type options), `vouchers` (issuance mapping), `pricing` (`ApplyPromotionalAdjustment` calls `calculateDiscount`).
- Required Dependent Changes: `checkout/src/Integrations/PromotionsAdapter.php` — no logic change needed (iterates live promotions), but verify no `match` exhaustiveness on `PromotionType`; same for `filament-promotions` form schema and `promotions` docs `05-promotion-service.md`.
- Migration Required: YES (data migration, see block).

### A3 — `RecomputePromotionEligibilityCommand` is an unregistered stub

- Severity: Medium
- Location: `src/Console/Commands/RecomputePromotionEligibilityCommand.php` (lists names, changes nothing; name promises recomputation), `src/PromotionsServiceProvider.php:58-63` (registers only `DeactivateExpiredPromotionsCommand`).
- Re-check (hardening pass): verified file exists and provider registers only `DeactivateExpiredPromotionsCommand`; demoted High → Medium — doubly-dead phantom ops surface with zero runtime effect (pure maintainability).
- Problem: Doubly dead — does nothing AND is not registered, so `promotions:recompute-eligibility` does not even exist. Anyone scheduling it gets "command not defined".
- Why It Matters: Phantom ops surface; expiry/eligibility hygiene looks covered but is not.
- Recommended Fix: Delete the file. If eligibility recomputation is ever real, reintroduce with actual targeting re-evaluation. Document that `promotions:deactivate-expired` is the only command and must be scheduled (it is not self-scheduling — see A9).
- Breaking Change: NO (command never existed at runtime).
- Affected Packages: none.
- Required Dependent Changes: none.
- Migration Required: NO.

### A4 — `ReevaluatePromotionsOnCartUpdated` is unregistered and effect-free

- Severity: Medium
- Location: `src/Listeners/ReevaluatePromotionsOnCartUpdated.php:20-37`, `src/Actions/EvaluatePromotionForCart.php`; provider `registerEventListeners()` (`PromotionsServiceProvider.php:35-40`) only wires `OrderPaid`.
- Re-check (hardening pass): verified provider wires only `OrderPaid → MarkPromotionAsUsedOnOrderPlaced` and no `Event::listen` references the cart listener; demoted High → Medium — dead file misdescribing the architecture, zero runtime effect.
- Problem: Listener is never registered (verified: no `Event::listen` references it, no DiscoverEvents for it), and even if registered it discards the result — `$this->evaluateAction->handle(…)` returns bool, ignored, no cart mutation, no event. `EvaluatePromotionForCart`'s only caller is this listener.
- Why It Matters: Looks like live cart integration; is none. Cart promotion state is actually driven by checkout's `PromotionsAdapter`, so this file misdescribes the architecture.
- Recommended Fix: Delete both files. Cart-time evaluation stays in `checkout` via `PromotionServiceInterface::getApplicablePromotions()` (already the live path).
- Breaking Change: NO.
- Affected Packages: `cart` (none — listener never ran).
- Required Dependent Changes: none.
- Migration Required: NO.

### A5 — `per_customer_limit` column is never enforced

- Severity: High
- Location: column `per_customer_limit` (`database/migrations/…:33`, fillable `Models/Promotion.php:86`, casts `:104`, audit `:390`); zero reads in `src/` (verified — only model/config surface).
- Problem: Operators set per-customer caps that do nothing; `isActive()`, `PromotionService::matchesContext()`, and checkout adapters never check it.
- Why It Matters: Abuse vector: single customer drains `usage_limit` campaigns meant to be spread.
- Recommended Fix: Enforce in `PromotionService::matchesContext()` (and therefore all callers): when `$promotion->per_customer_limit !== null` and the `orders` package is installed (`class_exists(Order::class)`), count the customer's paid orders carrying this promotion's id in their discount metadata and reject at cap. `TargetingContext` must carry the customer id — extend it if absent (commerce-support change, additive). When orders is absent, skip (documented limitation), do not throw.
- Breaking Change: NO (previously-unlimited behavior only narrows for capped rows).
- Affected Packages: `orders` (read-only count), `checkout` (must pass customer id into `TargetingContext::fromCart` if not already present), `commerce-support` (context field, additive).
- Required Dependent Changes: `checkout/src/Integrations/PromotionsAdapter.php` — pass customer context through (verify current `TargetingContext::fromCart` payload first).
- Migration Required: NO.

### A6 — Usage counting races the limit and depends on an undocumented checkout payload

- Severity: Medium
- Location: `src/Listeners/MarkPromotionAsUsedOnOrderPlaced.php:21-65` (session lookup with `withoutGlobalScope(OwnerScope::class)`, `discount_data['allocations']` shape, `provider_key === 'promotions'`, `meta.promotion_id`); `src/Models/Promotion.php:344-349` (`increment()` atomic but uncapped); `src/PromotionsServiceProvider.php:35-40` (wires `OrderPaid`).
- Problem: (a) `incrementUsage()` is atomic but the cap check (`isActive()`/`scopeActive`) is check-then-act — concurrent redemptions overshoot `usage_limit`. (b) The listener silently skips when checkout's `discount_data` shape differs (any `continue` path) — usage undercounts with no log. (c) The cross-tenant session read (`withoutGlobalScope`) is justified inline by a `ponytail:` comment but trusts `metadata['checkout_session_id']` from the order without verifying the session belongs to the order's owner.
- Why It Matters: Overshoot burns campaign budgets; silent skips corrupt ROI stats (`PromotionPerformanceInsights`, filament widgets).
- Recommended Fix: (1) Cap-aware increment: `Promotion::incrementUsage()` → single-statement `whereKey()->where(fn: usage_limit null OR usage_count < usage_limit)->increment('usage_count')` returning bool; listener ignores false. (2) After loading the session, verify `$session->getAttribute('owner_*')` matches `$event->owner_*` (or the order's owner) before counting; `continue` with a `Log::warning` carrying promotion/order ids on every skip path. (3) Document the `discount_data` contract (`provider_key`, `meta.promotion_id`) in `docs/05-promotion-service.md` and mirror it in checkout's `DiscountCommitment` docs.
- Breaking Change: NO.
- Affected Packages: `checkout` (payload contract), `orders` (event shape).
- Required Dependent Changes: `checkout` — confirm `PromotionsAdapter::formatAppliedPromotions()` emits `provider_key: 'promotions'` + `meta.promotion_id` (it must; add a contract test).
- Migration Required: NO.

### A7 — Code lookup is case-insensitive but storage is not normalized

- Severity: Medium
- Location: `src/Services/PromotionService.php:49-67` (`whereRaw('LOWER(code) = LOWER(?)')`), migration `code->nullable()->unique()` (case-sensitive unique on most collations).
- Problem: Full-table function scan per code validation; `SAVE10` and `save10` can coexist as separate rows while lookup treats them as one (first by priority wins — arbitrary).
- Why It Matters: Promo-code validation runs on the checkout hot path; duplicates cause customer-facing "wrong promotion applied".
- Recommended Fix: Normalize on save (`Promotion::saving()`: `code = mb_trim(mb_strtoupper(code))`, null stays null) and change lookup to exact `where('code', $normalizedCode)`. Document uppercase canonical form. No schema change (existing unique index now matches lookup semantics).
- Breaking Change: NO for new/consistent data; existing mixed-case rows resolve to their uppercase form (behavior change only where duplicates existed — those were already ambiguous).
- Affected Packages: `checkout` (`ValidatePromoCodeAction`, `DiscountCodeResolver` — benefit automatically).
- Required Dependent Changes: none.
- Migration Required: NO.

### A8 — `getApplicablePromotions` loads the whole active set into memory

- Severity: Medium
- Location: `src/Services/PromotionService.php:27-36` (`->get()->filter(...)`), `getStackablePromotions()`/`calculateDiscounts()` inherit it; `ReevaluatePromotionsOnCartUpdated` (to be deleted) did the same per cart event.
- Problem: Every cart/checkout evaluation hydrates ALL active automatic promotions, then evaluates targeting in PHP. Fine at 10 rows, pathological at 10k.
- Why It Matters: Checkout latency scales with campaign count, not cart size.
- Recommended Fix: Short-term (this pass): push cheap pre-filters into SQL — date window + usage-cap are already in `scopeActive()`; additionally filter `min_purchase_amount <= subtotal` and `min_quantity <= lines` in the query when the context carries them, keeping only targeting-expression evaluation in PHP. Document the evaluation order. Do NOT build a rules-to-SQL compiler now (speculative).
- Breaking Change: NO.
- Affected Packages: `checkout` (faster `PromotionsAdapter` calls, same results).
- Required Dependent Changes: none.
- Migration Required: NO.

### A9 — `products()`/`categories()` relations throw when `products` is absent

- Severity: Medium
- Location: `src/Models/Promotion.php:133-164` (`throw new RuntimeException('Products package is not installed.')`); contrast `issuedVouchers()` (`:171-180`) which degrades to an always-empty relation.
- Problem: `composer.json` only `suggest`s products, yet touching either relation fatals. Filament form/infolist product pickers (verify `PromotionForm`) would fatal on a standalone install.
- Why It Matters: Standalone-install violation; inconsistent with the file's own graceful pattern two methods below.
- Recommended Fix: Mirror the `issuedVouchers` pattern: when the class is missing, return an always-empty `morphedByMany`-compatible relation — simplest: `return $this->morphedByMany(Model::class, 'promotionable', $table)->whereRaw('1 = 0')` — or gate the form fields with `class_exists` in filament-promotions. Do both: model degrades, UI hides.
- Breaking Change: NO.
- Affected Packages: `products` (optional), `filament-promotions` (form gating).
- Required Dependent Changes: `filament-promotions/src/Resources/PromotionResource/Schemas/PromotionForm.php` — wrap product/category pickers in `class_exists` checks.
- Migration Required: NO.

### A10 — Expiry sweep exists but is not scheduled anywhere

- Severity: Low
- Location: `src/Console/Commands/DeactivateExpiredPromotionsCommand.php` (correct, dry-run capable), provider `:58-63` (registers, never schedules).
- Problem: Expired promotions rely on `scopeActive()` date checks at read time (correct), but `is_active` stays true forever unless an operator schedules the command. `getNavigationBadge` and stats count `is_active` rows — stale-true rows pollute the admin.
- Why It Matters: Operational, not correctness — but the command's existence implies hygiene that isn't wired.
- Recommended Fix: Document the required schedule entry (`->daily()` for `promotions:deactivate-expired`) in `docs/02-installation.md`; do not auto-schedule from the package (host app owns the schedule).
- Breaking Change: NO. Affected: none. Migration: NO.

## Code Quality Findings (same finding format)

### C1 — `CreatePromotion`/`DeactivatePromotion` actions are transaction/event-correct but bypassable

- Severity: Low
- Location: `src/Actions/CreatePromotion.php` (transaction + `PromotionCreated`), `src/Actions/DeactivatePromotion.php`, `src/Events/*`.
- Problem: Not a bug — but the Filament `CreatePromotion`/`EditPromotion` pages and console command must all funnel through these actions, otherwise events (`PromotionCreated`, `PromotionDeactivated`) fire inconsistently. Verified the command uses `DeactivatePromotion`; verify the Filament pages use the actions (not bare `::create()`).
- Why It Matters: Listeners (audit, webhooks) keyed on events miss direct-model writes.
- Recommended Fix: Audit `filament-promotions` pages to call `CreatePromotion::handle()`/`DeactivatePromotion::handle()`; add a one-line note in each Action docblock that it is the only sanctioned write path.
- Breaking Change: NO. Affected: `filament-promotions`. Migration: NO.

### C2 — `Promotion::supportsIssuedVoucherTracking()` hits schema on every call

- Severity: Low
- Location: `src/Models/Promotion.php:182-199` (`Schema::hasTable` + `hasColumn` per call), called from `PromotionResource::getRelations()` per request.
- Problem: Two schema queries per admin request.
- Why It Matters: Minor latency; also couples domain model to migration state.
- Recommended Fix: Memoize per-request in a static (`static::$voucherTracking ??= …`). Keep semantics.
- Breaking Change: NO. Affected: `filament-promotions`, `vouchers`. Migration: NO.

## Laravel-Specific Findings

- PHP 8.4: PASS (`composer.json` requires `^8.4`; enums, constructor promotion, first-class callables fine).
- PKs: PASS (`uuid('id')->primary()`).
- FK constraints/cascades: PASS — `foreignUuid('promotion_id')` on the pivot with NO constraint (verified, no `constrained()`/`cascadeOnDelete()` in `database/`); pivot cleanup is app-level (`Promotion::deleting()` deletes `promotionables` rows — correct). `down()` drops pivot before parent — correct order.
- Owner scoping: PASS with notes — model uses `HasOwner` + `HasOwnerScopeConfig` (`promotions.features.owner`, disabled by default per CONTEXT) + `nullableMorphs('owner')` + `getTable()` from config + write guards. `PromotionService` queries use `->forOwner()` (lines 32, 61) — correct; when owner is disabled these are pass-throughs.
- Config: PASS structure (Database → Defaults → Features) with `json_column_type` present as required for JSON columns (`conditions` uses `commerce_json_column_type`). One nit: owner config nests under `features.owner` while sibling packages use top-level `owner` — cosmetic inconsistency; do not churn keys for it (would break published configs for zero benefit). Note only.
- Money: PASS — `discount_value`/`min_purchase_amount` integer cents; `calculateDiscount` rounds once with `(int) round(...)`.
- Events: `PromotionCreated/Deactivated/Applied/Removed` — check `PromotionRemoved` dispatch sites (grep before relying on it; `DeactivatePromotion` dispatches Deactivated, not Removed — confirm naming intent in docs).
- Provider uses classic `ServiceProvider` with manual `mergeConfigFrom`/`loadMigrationsFrom`/publishes instead of spatie package-tools like the rest of the monorepo — functional, keep (no churn without benefit).

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)

- Navigation: PASS — nested `navigation.group` (`Marketing`), `getNavigationGroup()`/`getNavigationSort()` from config, no static `$navigationGroup` (verified).
- Owner/UI scoping: PASS — `PromotionResource::getEloquentQuery()` uses `parent::` + `OwnerUiScope::apply(…, includeGlobal: false)`; permission gates (`FilamentPermission::hasAbility`) + `shouldRegisterNavigation` are correct.
- F1 — Duplicate issue-vouchers actions (Medium): `Actions/IssuePromotionVouchersAction.php` and `Actions/IssuePromotionVouchersFromListAction.php` both wrap `IssueVouchersFromPromotion::run($promotion, $count, $codePrefix)` with near-identical forms (verified lines 58/61). Delete the list variant; register the remaining action in both header and table contexts (Filament actions are context-agnostic). Update docs `04-usage.md`.
- F2 — Badge query per render (Low): `getNavigationBadge()` counts active promotions on every navigation render. Acceptable at this table size; revisit only with measured slowness (do not cache prematurely — Octane staleness).
- F3 — Widgets correctly delegate math to `Support/PromotionPerformanceInsights` (thin). Confirm that class is container-resolvable (constructor deps auto-wire `TargetingEngineInterface`?) and that widget queries apply `forOwner` — verify before shipping, else owner leakage in stats.
- Dependency direction: PASS — `filament-promotions` requires `promotions`; domain never references Filament. `suggest` blocks for vouchers/filament-cart are accurate.

## Database Findings

- `promotions`: uuid PK, `nullableMorphs('owner')`, `code` unique nullable, integer money columns, `usage_count` default 0, `conditions` jsonb via helper, indexes on `(is_active, priority)` and `(starts_at, ends_at)` serving `scopeActive()` — good shape.
- `promotionables`: composite PK `(promotion_id, promotionable_id, promotionable_type)`, `foreignUuid` without constraint — compliant; no timestamps (fine for a pure pivot).
- Missing: nothing structural. `usage_count` has no index — it is only compared to `usage_limit` in `scopeActive` (already covered by the select, no extra index needed).
- Race note: see A6 (app-level, not schema).

## Model / Domain Findings

- `Promotion::calculateDiscount()` is the single live math implementation after A1 — keep it there; `PromotionService::calculateDiscounts()` handles stacking order (non-stackable short-circuit) correctly for fixed/percentage.
- `is_stackable` + `priority` semantics are implemented in exactly one place (`calculateDiscounts`) — good; `pricing`'s bridge (pricing audit A4) must call this instead of reimplementing.
- `conditions` validation in `saving()` via `TargetingEngineInterface` with `[]` → null normalization is correct fail-fast behavior.
- `issuedVouchers()` graceful-degradation pattern is the file's best idiom — extend it to `products()`/`categories()` (A9).

## Security Findings

- Owner write guards (update/save/delete) mirror the monorepo contract — good. `IssueVouchersFromPromotion` correctly requires explicit global context for global promotions (`NoCurrentOwnerException`) and re-enters owner context per issuance — good; keep as the reference pattern.
- A5 (`per_customer_limit` unenforced) is the open abuse hole — fix per A5.
- A6(c) session-ownership check missing — fix per A6.
- `whereRaw('LOWER(code)…')` uses bindings — safe; A7 removes it anyway.
- No mass-assignment of `usage_count`? `usage_count` is NOT in `$fillable` (verified list) — good; only `increment()` mutates it.

## Performance Findings

- A8 (full-table hydration) is the main perf item; A7 (function predicate) second. Both fixed without schema change.
- C2 (schema hits per request) is minor.
- `calculateDiscounts()` iterates in priority order with early non-stackable exclusion — O(n) in active promotions, fine after A8 pre-filters.

## Testing Findings

- Zero tests; one factory (`PromotionFactory`) with no consumers. First tests to add (Pest, `--parallel`): `calculateDiscounts` stacking/priority/cap behavior; code normalization + exact lookup (A7); `incrementUsage` cap race (two concurrent increments past limit → count stays capped); cross-owner isolation via `OwnerScopingContractTests`; `BuyXGetY` removal migration (rows deactivated, enum gone); `MarkPromotionAsUsedOnOrderPlaced` with a fixture `discount_data` payload (A6 contract); command test for `deactivate-expired --dry-run`.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `checkout` (`PromotionsAdapter`, `ValidatePromoCodeAction`, `DiscountCodeResolver`, `RegisterCheckoutOptionalSteps`) | `PromotionServiceInterface`, `Promotion` model, `promotions:deactivate-expired` hygiene | A2 (type removal), A5 (new enforcement may reject previously-accepted redemptions), A6 (payload contract documented), A7 (code canonicalization) | Exhaustiveness checks on `PromotionType`; pass customer id into targeting context; contract-test `discount_data` shape; schedule the deactivate command |
| `pricing` (`ApplyPromotionalAdjustment`) | `Promotion::calculateDiscount`, promotions tables/config | A1/A2 change the math it calls; pricing audit A4 rewrites the bridge anyway | Coordinate: land pricing-A4 delegation on the post-cleanup `PromotionService` API |
| `vouchers` (`IssueVouchersFromPromotion` target) | `Promotion` model fields | A2 removes the `buy_x_get_y` voucher mapping arm | None after mapping-arm removal (issuance for other types unchanged) |
| `filament-promotions` | `Promotion` model, actions | A2 (form options), F1 (action merge), C1 (write-path funnel) | Update `PromotionForm` type select + gate product pickers (A9); replace list-action registration |
| `filament-pricing` (`PricingStatsWidget`) | `Promotion::query()->active()` counts | A6/A10 change what "active" contains over time | None (reads live state); fix its owner-default separately (pricing audit F2) |
| `products` | optional (`products()`/`categories()` relations) | A9 changes missing-package behavior throw → empty | None (strictly more forgiving) |

## Recommended Refactor Plan (ordered steps)

1. Delete: `ApplyPromotionToCart.php`, `Strategies/*` (3), `Contracts/PromotionStrategyInterface.php`, `Console/Commands/RecomputePromotionEligibilityCommand.php`, `Listeners/ReevaluatePromotionsOnCartUpdated.php`, `Actions/EvaluatePromotionForCart.php`. Update `CONTEXT.md` + docs surface lists.
2. Remove `BuyXGetY` (A2) + data migration deactivating existing rows; update `PromotionType` helpers, issuance mapping, docs, filament form options.
3. Enforce `per_customer_limit` (A5); harden `incrementUsage` + listener skip logging + session-ownership check (A6); normalize codes (A7); SQL pre-filters (A8).
4. Graceful-degrade `products()`/`categories()` (A9); gate filament pickers; memoize `supportsIssuedVoucherTracking` (C2); funnel filament writes through actions (C1); merge duplicate filament issue actions (F1); document the deactivate schedule (A10).
5. Add the Pest coverage in Testing Findings (`./vendor/bin/pest --parallel` scoped).

## Files Likely to Change

- `packages/promotions/src/Models/Promotion.php` (code normalization, capped increment, graceful relations, BuyXGetY arm removal)
- `packages/promotions/src/Services/PromotionService.php` (per-customer enforcement, pre-filters, exact code lookup)
- `packages/promotions/src/Enums/PromotionType.php` (case removal)
- `packages/promotions/src/Actions/IssueVouchersFromPromotion.php` (mapping arm removal)
- `packages/promotions/src/Listeners/MarkPromotionAsUsedOnOrderPlaced.php` (ownership check + logging)
- `packages/promotions/database/migrations/` (new data migration)
- `packages/promotions/docs/*`, `CONTEXT.md`
- `packages/filament-promotions/src/Actions/*`, `Resources/PromotionResource/Schemas/PromotionForm.php`, docs

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/promotions/src/Actions/ApplyPromotionToCart.php` — zero callers; tag wiring absent so it always throws.
- `packages/promotions/src/Strategies/PercentageStrategy.php`, `FixedStrategy.php`, `BuyXGetYStrategy.php` — only referenced by the dead action.
- `packages/promotions/src/Contracts/PromotionStrategyInterface.php` — only referenced by the dead action/strategies.
- `packages/promotions/src/Console/Commands/RecomputePromotionEligibilityCommand.php` — unregistered stub that changes nothing.
- `packages/promotions/src/Listeners/ReevaluatePromotionsOnCartUpdated.php` — unregistered and discards its own result.
- `packages/promotions/src/Actions/EvaluatePromotionForCart.php` — sole caller is the deleted listener.
- `packages/filament-promotions/src/Actions/IssuePromotionVouchersFromListAction.php` — duplicate of `IssuePromotionVouchersAction`; one action serves both contexts.

## Final Recommended Architecture

One model (`Promotion`), one math site (`Promotion::calculateDiscount`), one orchestration site (`PromotionService` over `PromotionServiceInterface`), two write actions (`Create`/`Deactivate`), one issuance bridge (`IssueVouchersFromPromotion`), one expiry command (scheduled by the host), one usage listener (ownership-checked, skip-logged, cap-atomic). No strategy layer, no cart listener, no BOGO case until it is real feature work with cart-line-aware evaluation. Filament remains a thin CRUD + single issue-action + widgets adapter over the service, with product pickers hidden when `products` is absent.
