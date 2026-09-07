# Pricing Audit

## Packages Reviewed (bullets)

- `packages/pricing` (`aiarmada/pricing`) — domain owner: price lists, prices, tiers, resolution engine, settings.
- `packages/filament-pricing` (`aiarmada/filament-pricing`) — Filament v5 admin adapter: `PriceListResource`, `PriceSimulator` page, `ManagePricingSettings` page, `PricingStatsWidget`.

Source layout inspected: `src/` (Actions, Contracts, Data, Events, Models, Services, Settings, Support), `config/pricing.php`, `config/filament-pricing.php`, `database/migrations` (3), `database/settings` (2), `composer.json` × 2, providers, `CONTEXT.md`/`README.md`/`docs`. No `routes/` and no `tests/` in either package (verified: `find … -iname "*test*"` returns nothing; zero Pest/PHPUnit coverage).

## Overall Assessment (quality, health, risks, refactor size)

Good money hygiene (integer cents + `MoneyNormalizer`/`FormatsMoney`/`MoneyFormatter` throughout) and consistent owner-scoping on all three models. Health is otherwise middling: the resolution engine duplicates tier logic in two places, the promotions bridge bypasses the promotions domain with raw queries, one lifecycle column (`deactivated_at`) is written but never read, and the Filament simulator fatals without the optional `products` package. Cross-package risk is concentrated in `checkout` (`CalculatePricingStep`) and `products` (`Priceable`), both of which consume only the stable surfaces (`PriceCalculatorInterface`, `Priceable`, models), so the refactor below is contained. Refactor size: S–M (deletions + one delegation rewrite + one additive index migration + Filament guards). No schema redesign needed.

## Migration Impact

**Migration Required: NO** — migration track completed 2026-09-07, see `migration-record.md#pricing`

## Package Responsibilities

- Owns: `Price`, `PriceList`, `PriceTier` persistence; base/tier/customer/segment/promotion price resolution order; `PriceResultData` DTO; `PricingSettings`/`PromotionalPricingSettings`; `Priceable` contract.
- Does NOT own (correctly): discount campaigns (promotions), coupons/wallets (vouchers), product catalog (products).
- Filament adapter owns: list CRUD UI, price simulator, settings page, stats widget. Must stay UI-only.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### A1 — Duplicated tier-resolution engines with divergent filters

- Severity: High
- Location: `packages/pricing/src/Actions/ResolveTierPrice.php` vs `packages/pricing/src/Support/TierResolver.php` (implements `Contracts/TierResolverInterface.php`); wired in `PricingServiceProvider.php:32`; consumed in `Services/PriceCalculator.php:126`.
- Problem: Two implementations of "find applicable tier". The Action filters `is_active = true`; the Support resolver does not. `PriceCalculator` uses only the Support one, so `is_active = false` tiers still price. `ResolveTierPrice` has zero code callers (only `docs/04-usage.md` references it) — verified repo-wide.
- Why It Matters: Inactive tiers silently discount; two sources of truth diverge further over time.
- Recommended Fix: Delete `src/Actions/ResolveTierPrice.php`. Add `->where('is_active', true)` to `Support/TierResolver::resolve()`. Update `docs/04-usage.md`, `docs/01-overview.md`, `CONTEXT.md` key-surfaces list.
- Breaking Change: NO (internal; no external callers).
- Affected Packages: none (docs only).
- Required Dependent Changes: none.
- Migration Required: NO.

### A2 — Trivial single-line action wrappers with zero callers

- Severity: Medium
- Location: `src/Actions/ResolveBasePrice.php` (`return $item->getBasePrice();`), `src/Actions/FormatPriceForDisplay.php` (delegates to `MoneyFormatter::formatMinor`).
- Problem: Indirection with no logic; repo-wide grep shows no code callers, only docs (`docs/04-usage.md:11-42`, `docs/01-overview.md:35`).
- Why It Matters: API surface suggests stability guarantees for one-liners; new code will depend on them, freezing trivialities.
- Recommended Fix: Delete both files. Update `docs/04-usage.md` examples to call `$item->getBasePrice()` and `MoneyFormatter::formatMinor()` directly. Remove from `CONTEXT.md` and `docs/01-overview.md` surface lists.
- Breaking Change: NO (no internal consumers; external use unlikely — grep shows none in-repo).
- Affected Packages: none.
- Required Dependent Changes: none.
- Migration Required: NO.

### A3 — `PricingIntegrationRegistrar` is a registered no-op

- Severity: Medium
- Location: `src/Support/PricingIntegrationRegistrar.php` (`boot()` body is a "Future:" comment); `PricingServiceProvider.php:37`; `docs/03-configuration.md:104`.
- Problem: Singleton registered, documented as "how downstream packages wire into pricing", but `boot()` is never called by anyone (verified repo-wide) and contains no logic.
- Why It Matters: Dead extension seam misleads integrators; `checkout`/`cart` instead hard-code `class_exists` checks.
- Recommended Fix: Delete the class, the provider binding, and the docs section. If a seam is wanted later, reintroduce it with real registrants.
- Breaking Change: NO.
- Affected Packages: none.
- Required Dependent Changes: none.
- Migration Required: NO.

### A4 — Promotions bridge bypasses the promotions domain

- Severity: High
- Location: `src/Actions/ApplyPromotionalAdjustment.php:16-60`, called from `Support/PromotionalPriceResolver.php:30`, called from `Services/PriceCalculator.php:143`.
- Re-check (hardening pass): code verified present (string class name `:18`, raw `whereExists` on `config('promotions.database.tables.promotionables')` `:27-37`, `ORDER BY priority DESC LIMIT 1` `:38-39`, unit-price `calculateDiscount($basePrice)` `:53`); demoted Critical → High — duplicated domain + incorrect behavior, no security/data-loss/transaction defect.
- Problem: Uses a string class name (`'\\AIArmada\\Promotions\\Models\\Promotion'`), raw `whereExists` on `config('promotions.database.tables.promotionables')`, reimplements active-scope + `ORDER BY priority DESC LIMIT 1`, then calls `$promotion->calculateDiscount($basePrice)` on the unit price. It therefore ignores: strategy layer, `BuyXGetY` semantics, targeting/conditions evaluation, `is_stackable`, `per_customer_limit`, and promotions owner scoping (relies only on the global scope applying to the string-resolved model). Quantity is only checked against `min_quantity`/`min_purchase_amount`, not against tiered promotion evaluation in `PromotionService`.
- Why It Matters: Every priced item flows through this when `pricing.features.promotional.enabled` (default true). Promotion semantics in checkout (via `PromotionService`) and in pricing disagree — same catalog, two discount truths.
- Recommended Fix: Rewrite `ApplyPromotionalAdjustment::apply()` to resolve `PromotionServiceInterface` from the container (guarded by `interface_exists`) and delegate: build a `TargetingContext` for the priceable/quantity and call `getBestPromotion()` / `calculateDiscounts()`. Keep the `class_exists` early-return when promotions is absent. Delete the raw `promotionables` query and the local `applyPromotionActiveAt()`.
- Breaking Change: NO (same return shape `array{price:int,name:string}|null`; behavior becomes *correct*).
- Affected Packages: `promotions` (now consumed via its public contract), `checkout` (receives consistent promotion prices via `CalculatePricingStep`).
- Required Dependent Changes: `checkout/src/Steps/CalculatePricingStep.php` — none code-wise, but its pricing output changes for promotion-covered items; verify its snapshot expectations.
- Migration Required: NO.

### A5 — `deactivated_at` is written but never read

- Severity: High
- Location: columns in `database/migrations/2000_12_01_000001…` (`price_lists`) and `…000002…` (`prices`); fillable/casts in `Models/Price.php:61,73`, `Models/PriceList.php:63,77`. Absent from: `Price::scopeActive()`, `PriceList::scopeActive()`, `PriceCalculator::applyPriceActiveAt()/applyPriceListActiveAt()`, `Price::isActive()`, `PriceList::isActive()`.
- Problem: Lifecycle column with zero readers. "Deactivated" rows keep pricing — operators think they pulled a price but did not.
- Why It Matters: Silent wrong-price risk; the column is worse than absent because it implies a kill-switch that does not work.
- Recommended Fix: Add `->whereNull('deactivated_at')` to both `scopeActive()`s, both calculator active-at helpers, and both `isActive()` helpers. Keep the column.
- Breaking Change: NO.
- Affected Packages: `checkout` (deactivated rows stop pricing — intended), `filament-pricing` (lists show fewer active rows).
- Required Dependent Changes: none.
- Migration Required: NO.

### A6 — Multiple `is_default` price lists allowed; winner undocumented

- Severity: Medium
- Location: `Models/PriceList.php:143-146` (`scopeDefault`), `Services/PriceCalculator.php:198-202` (`default()->orderByDesc('priority')->first()`).
- Problem: Nothing enforces a single default per owner scope. Ties on `priority` resolve by arbitrary DB order.
- Why It Matters: Two "defaults" → nondeterministic catalog pricing.
- Recommended Fix: In `PriceList::saving()`, when `is_default` is set true, demote other defaults in the same owner scope (`where('id','!=',$id)->where('is_default',true)->update(['is_default'=>false])`, owner-scoped query). Document "highest priority default wins; ties by earliest created" — add `->orderBy('created_at')` tiebreak in `getPriceListPrice()`.
- Breaking Change: NO.
- Affected Packages: none.
- Required Dependent Changes: none.
- Migration Required: NO (app-level; no partial unique index so MySQL stays supported).

### A7 — `effective_at` parsing triplicated across resolvers

- Severity: Medium
- Location: `Services/PriceCalculator.php:34-54`, `Support/CustomerPriceResolver.php:62-82`, `Support/SegmentPriceResolver.php:63-83` (identical `resolveEffectiveAt`).
- Problem: Same 20-line parse block copied 3×; fixes (e.g. timezone handling) must land thrice.
- Why It Matters: Drift risk in time-sensitive pricing.
- Recommended Fix: Add `Support/ResolvesEffectiveAt.php` trait (or static on a new `Support/EffectiveAt` final class) and use it in all three. One behavior, one test point.
- Breaking Change: NO.
- Affected Packages: none.
- Required Dependent Changes: none.
- Migration Required: NO.

### A8 — No validation that `customer_id`/`segment_id` lists belong to the current owner

- Severity: Medium
- Location: `Models/PriceList.php:64-65` (fillable), `Support/CustomerPriceResolver.php:50`, `Support/SegmentPriceResolver.php:50`.
- Problem: Any `customer_id`/`segment_id` UUID is accepted on save; resolvers match them without confirming the referenced customer/segment is visible in the current owner scope (unlike `Price::saving()`, which does validate its `priceable`).
- Why It Matters: Cross-owner price leakage: list scoped to owner A can key off owner B's customer id.
- Recommended Fix: In `PriceList::saving()`, when owner mode is enabled and `customer_id`/`segment_id` are set with `class_exists` on the target models, verify existence via owner-scoped query and throw `AuthorizationException` otherwise (mirror `Price::saving()` lines 151-168).
- Breaking Change: NO (rejects only previously-invalid rows).
- Affected Packages: `customers` (read-only existence check), `products` if segments live there.
- Required Dependent Changes: none.
- Migration Required: NO.

## Code Quality Findings (same finding format)

### C1 — `PriceList::scopeForOwner` custom override is confusing and redundant

- Severity: Low
- Location: `Models/PriceList.php:156-174` (uses `OwnerContext::CURRENT` sentinel, `func_num_args()` tricks); `Models/Price.php` and `Models/PriceTier.php` use the trait default.
- Problem: Three models, two scoping idioms. The override ANDs caller `includeGlobal=true` with config `false`, so the parameter is decorative.
- Why It Matters: Reader must learn two contracts; future edits will pick the wrong one.
- Recommended Fix: Delete the override; rely on `HasOwner`/`HasOwnerScopeConfig` default like the sibling models.
- Breaking Change: NO.
- Affected Packages: none.
- Required Dependent Changes: `filament-pricing` `PriceListResource::getEloquentQuery()` keeps passing `($owner, includeGlobal)` explicitly — still valid against the trait signature.
- Migration Required: NO.

### C2 — `PriceResultData::getMoney()` dynamic currency call throws on bad currency

- Severity: Low
- Location: `src/Data/PriceResultData.php:47-59` (`Money::{$currency}(…)` × 3).
- Problem: Unknown/empty `currency` (comes from `$context['currency']`, line 98-101 of calculator — any string accepted) → `Error`.
- Why It Matters: Caller-controlled string reaches a dynamic static call.
- Recommended Fix: Validate `currency` in `PriceCalculator::calculate()` against a 3-letter uppercase allowlist (fallback to `config('pricing.defaults.currency')`); or wrap `getMoney*()` with `method_exists` guard throwing `InvalidArgumentException`.
- Breaking Change: NO.
- Affected Packages: `checkout` (passes context currency through).
- Required Dependent Changes: none.
- Migration Required: NO.

### C3 — `PriceList::deleting` and `Price::saving` do unguarded multi-query writes

- Severity: Low
- Location: `Models/PriceList.php:288-303` (deletes prices + tiers, no transaction); `Models/Price.php:141-168` (1–2 `EXISTS` queries per save).
- Problem: Partial cascade on failure; per-save existence checks are N+1 in bulk imports.
- Why It Matters: Correctness under failure; import throughput.
- Recommended Fix: Wrap the deleting body in `DB::transaction()`; leave the saving checks (correctness first) but document them as the reason bulk imports should use a dedicated importer later.
- Breaking Change: NO.
- Affected Packages: none.
- Required Dependent Changes: none.
- Migration Required: NO.

## Laravel-Specific Findings

- PHP 8.4: PASS (`composer.json: php ^8.4`; constructor promotion, enums, readonly used correctly).
- PKs: PASS (`uuid('id')->primary()` in all 3 migrations).
- FK constraints/cascades: PASS — `foreignUuid()` used with NO `constrained()`/`cascadeOnDelete()` (verified via rg); cascades are app-level (`PriceList::deleting`). Compliant with repo rules.
- `down()` methods present on all 3 migrations although repo guidelines say none required — harmless, keep.
- Owner scoping: PASS with the A8 gap — all 3 models use `HasOwner` + `HasOwnerScopeConfig` with key `pricing.features.owner`, `nullableMorphs('owner')` in migrations, `getTable()` from config. `Price::saving()`/`PriceList::saving()`/`PriceTier::saving()` enforce cross-owner writes. Good.
- Money: PASS — integer minor units (`unsignedBigInteger amount`), `MoneyNormalizer::toCents` at the calculator boundary, `FormatsMoney`/`MoneyFormatter` for display. One nit: `Settings/PricingSettings.php:79-84` divides by `$scale` into float before `formatMajor` — float precision on huge amounts; prefer `MoneyFormatter::formatMinor`. Low.
- Events: `PriceCalculated` dispatched on ALL paths including the no-discount fallback (`PriceCalculator.php:180`) — noisy for listeners; consider dispatching only when `discountAmount > 0`, or document the fallback dispatch. Low.
- Settings: `database/settings/*` publishing via `bootingPackage` + `settings.migrations_paths` merge is correct per spatie/laravel-settings.

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)

### F1 — `PriceSimulator` fatals without the optional `products` package (High)

- Severity: High
- Location: `packages/filament-pricing/src/Pages/PriceSimulator.php:13-14,93-97,152,330-348` (`Product::query()`, `Variant::query()` unconditional); `composer.json` lists `products`/`customers` only under `suggest`.
- Re-check (hardening pass): verified `Product::query()` (`:94,122,331`) and `Variant::query()` (`:152,202,339`) unguarded while `Customer` paths are `class_exists`-guarded (`:237,239,270,360`); demoted Critical → High — undeclared hard dependency / broken package boundary, no security or data-loss defect.
- Problem: `customers` usage is guarded (`class_exists(Customer::class)` at lines 234, 240, 360); `Product`/`Variant` are not. Installing `filament-pricing` without `products` → fatal on page open. The adapter therefore has a hard dependency it does not declare.
- Why It Matters: Standalone-install rule violated; the "suggest" block lies.
- Recommended Fix: Either (a) promote `aiarmada/products` to `require`, or (b) guard all product/variant paths with `class_exists` and render an empty state ("Products package not installed"). Option (b) keeps the adapter standalone; do (b) and mirror the existing customer guards.
- Breaking Change: NO.
- Affected Packages: `products`, `customers` (read-only).
- Required Dependent Changes: none.
- Migration Required: NO.

### F2 — `PricingStatsWidget` inverts the promotions owner default (Medium)

- Severity: Medium
- Location: `packages/filament-pricing/src/Widgets/PricingStatsWidget.php:31` — `if (config('promotions.owner.enabled', true))`.
- Re-check (hardening pass): verified both key path AND default are wrong — owning package ships `features.owner.enabled => false` (`packages/promotions/config/promotions.php:33-39`); demoted High → Medium — display-only stats undercount, not a money-path defect.
- Problem: `promotions` config defaults `features.owner.enabled` to `false`, but the widget defaults the lookup to `true`. With promotions installed and owner never configured, the widget applies `forOwner()` scoping against a null owner and undercounts (typically to zero).
- Why It Matters: Wrong dashboard numbers; cross-package default must match the owning package.
- Recommended Fix: Change default to `false` and pass include-global explicitly: `->forOwner(OwnerContext::resolve(), (bool) config('promotions.features.owner.include_global', false))`. Note the key path also differs (`promotions.owner.enabled` vs owning package's `promotions.features.owner`) — use the owning package's key.
- Breaking Change: NO.
- Affected Packages: `promotions` (read-only counts).
- Required Dependent Changes: none.
- Migration Required: NO.

### F3 — Docs contradict the (correct) navigation implementation

- Severity: Low
- Location: `packages/filament-pricing/docs/05-resources.md:21`, `docs/06-pages-widgets.md:21,109` show `protected static … $navigationGroup = '…'`; code correctly uses nested `navigation.group` + `getNavigationGroup()` from config (`PriceListResource.php:28`, `PriceSimulator.php:45`, `ManagePricingSettings.php:31`), and no static `$navigationGroup` exists in `src` (verified).
- Problem: Copy-paste docs teach the forbidden pattern.
- Why It Matters: Next contributor follows the docs and breaks the `CommerceNavigation` runtime-override contract.
- Recommended Fix: Rewrite the three doc snippets to the `getNavigationGroup(): … { return config('filament-pricing.navigation.group'); }` form.
- Breaking Change: NO. Affected: none. Migration: NO.

### F4 — Adapter otherwise thin and correctly directed

`PriceListResource::getEloquentQuery()` uses `parent::` + `forOwner()` (correct). Relation managers resolve the owner via `OwnerContext` (defense-in-depth on top of parent scoping — keep). No domain calculations in the adapter except `PricingStatsWidget` promotion counts (read-only aggregation, acceptable after F2). Dependency direction correct: `filament-pricing` requires `pricing`; `pricing` does not know the adapter. `getNavigationSort()` reads config with numeric guard — correct.

## Database Findings

- Rules: uuid PKs PASS; no FK constraints/cascades PASS; `nullableMorphs('owner')` PASS; `timestampsTz` + `timestampTz` PASS; `json_column_type` N/A (no JSON columns — fine).
- `prices_unique_per_quantity` unique on `(price_list_id, priceable_type, priceable_id, min_quantity)` is correct and matches the `orderByDesc('min_quantity')` resolution.
- `price_lists.slug` global unique: with owner mode on, two owners cannot reuse a slug — acceptable, note only.
- `customer_id`/`segment_id` on `price_lists` are `foreignUuid()->nullable()` with indexes and no constraints — compliant; existence validation is the A8 app-level gap.

## Model / Domain Findings

- `Priceable` contract (`getBuyableIdentifier`, `getBasePrice`, `getComparePrice`, `isOnSale`, `getDiscountPercentage`) is minimal and well-consumed (`products` models implement it; checkout type-checks against it). Keep.
- Resolution precedence (customer → segment → tier → promotion → list → base) is hardcoded in `PriceCalculator::calculate()` with early returns and a `breakdown[]` trail — understandable, but precedence is not configurable and later rules never combine with earlier ones (first match wins). Document the precedence in `docs/04-usage.md`; do not add a configurable pipeline now (speculative).
- `TierPriceResultData` vs `PriceResultData` overlap is fine (tier result feeds the main result).
- `deactivated_at`/`is_active` dual flags on lists (A5 covers the dead one).

## Security Findings

- Owner enforcement on writes is real (throw on cross-owner update/save/delete in all three models) — good. Filament resource re-scopes reads; relation managers resolve owner — adequate defense-in-depth after F1.
- `Price::priceable_type` / `PriceTier::tierable_type` accept any class string via fillable, but the only sink is `class_exists` + `is_a(Model)` + scoped `whereKey()->exists()` — no instantiation, no gadget risk. Note only.
- A8 (unvalidated customer/segment ids) is the one owner-leak vector — fix per A8.
- No raw SQL with interpolated input except `orderByRaw('CASE WHEN price_list_id IS NULL…')` with no bindings — safe.

## Performance Findings

- Per-item fan-out: up to 5 sequential queries (customer price, segment price, tier, promotion+exists, list+price) per `calculate()` call. Cart/checkout loops multiply by line count. No caching layer. Do NOT add a cache now (no measured hotspot; long-lived Octane workers make stale-price bugs likely).
- `Price::saving()` adds 1–2 `EXISTS` queries per write — fine for admin writes, note for bulk importers.
- `PromotionResource`-style badge counts N/A here (`PriceListResource` has no badge — good).

## Testing Findings

- Zero tests, zero factories in both packages. Highest-value first tests to add (Pest, `--parallel` per repo rules): `TierResolver` active-filter + quantity windows; `PriceCalculator` precedence (customer beats tier beats list); `deactivated_at` exclusion (A5 regression); cross-owner read/write isolation per model (reuse `commerce-support` `OwnerScopingContractTests`); `PriceSimulator` without `products` installed (F1 regression); widget owner-default (F2 regression).
- `docs/04-usage.md` examples reference classes being deleted (A1/A2) — update in the same pass.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `products` (`Product`, `Variant` implement `Priceable`; read `Price` model) | `aiarmada/pricing` (suggest) | A4/A5 change computed prices for promotion/deactivated rows | None code-wise; re-verify product price display expectations |
| `checkout` (`CalculatePricingStep`, `EnsureCheckoutOfferProduct`) | `PriceCalculatorInterface`, `Priceable`, `Price`/`PriceList` models | Same as above; promotion prices become consistent with `PromotionService` | Verify checkout snapshots/totals in tests after A4 |
| `filament-products` (`PricesRelationManager`, `ProductsTable`) | `Pricing` models | Read-only; unaffected by deletions | None |
| `filament-pricing` | `pricing` (require) | A1–A8 land here as UI behavior changes | Apply F1/F2; update relation-manager surface usage if any referenced deleted Actions (none do — verified) |
| `promotions` | none (pricing reads it via string class + config) | A4 converts this to a clean interface dependency | Add `aiarmada/promotions` to `suggest` (already there) — no composer change; ensure `PromotionServiceInterface` stays stable |

## Recommended Refactor Plan (ordered steps)

1. Delete dead code: `Actions/ResolveTierPrice.php`, `Actions/ResolveBasePrice.php`, `Actions/FormatPriceForDisplay.php`, `Support/PricingIntegrationRegistrar.php` + provider binding. Update `docs/01-overview.md`, `docs/04-usage.md`, `CONTEXT.md`.
2. Rewrite `ApplyPromotionalAdjustment` to delegate to `PromotionServiceInterface` (A4).
3. Add `deactivated_at` filters (A5) + single-default demotion + tiebreak (A6) + owner validation of customer/segment ids (A8).
4. Extract `ResolvesEffectiveAt` helper (A7); simplify `PriceList::scopeForOwner` (C1); transaction-wrap `PriceList::deleting` (C3); harden `PriceResultData` currency (C2).
5. Filament: guard `PriceSimulator` without products (F1); fix widget owner default (F2); fix nav docs (F3).
6. Add the Pest coverage listed in Testing Findings (run `./vendor/bin/pest --parallel` scoped to new tests + any root suite covering pricing).

## Files Likely to Change

- `packages/pricing/src/Support/TierResolver.php` (active filter)
- `packages/pricing/src/Actions/ApplyPromotionalAdjustment.php` (rewrite)
- `packages/pricing/src/Services/PriceCalculator.php` (deactivated filters, tiebreak, currency validation)
- `packages/pricing/src/Models/Price.php`, `PriceList.php` (scopes, saving validation, transaction, scope simplification)
- `packages/pricing/src/Support/*.php` (new `ResolvesEffectiveAt`, resolver adoption)
- `packages/pricing/src/Data/PriceResultData.php` (currency guard)
- `packages/pricing/docs/*.md`, `packages/pricing/CONTEXT.md`
- `packages/filament-pricing/src/Pages/PriceSimulator.php`, `src/Widgets/PricingStatsWidget.php`, `docs/05-resources.md`, `docs/06-pages-widgets.md`

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/pricing/src/Actions/ResolveTierPrice.php` — superseded by `Support/TierResolver` (verified zero code callers).
- `packages/pricing/src/Actions/ResolveBasePrice.php` — one-liner, zero code callers.
- `packages/pricing/src/Actions/FormatPriceForDisplay.php` — thin wrapper, zero code callers.
- `packages/pricing/src/Support/PricingIntegrationRegistrar.php` + `PricingServiceProvider.php:37` binding + `docs/03-configuration.md` registrar section — never invoked.
- `PriceList::scopeForOwner()` override body (`Models/PriceList.php:156-174`) — use trait default.

## Final Recommended Architecture

Keep the current shape: three models + calculator with injected resolvers + `Priceable` contract + settings. Collapse to ONE tier path (`Support/TierResolver` with active filter), ONE promotion path (delegate to `promotions`' public contract — pricing never queries promotions tables), shared `ResolvesEffectiveAt`, trait-default owner scoping on all models, and the additive `price_tiers` index. Filament stays a thin guarded adapter: `parent::` queries + owner revalidation, no hard dependency on optional packages, no cross-package defaults of its own.
