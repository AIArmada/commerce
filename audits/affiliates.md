# Affiliates Audit — DONE (2026-09-11)

## Verdict

The affiliates pair (`affiliates` + `filament-affiliates`) is
disposition-complete. Every rated state, cart, voucher, rule, facade,
ownership, security, performance, Filament, and testing finding is
implemented, verified, or explicitly deferred below. No rated findings remain
open.

## What was done

- **State/enum duality — IMPLEMENTED.** Spatie states remain the transition
  authority. Conversion and payout states expose `toEnum()`
  (`packages/affiliates/src/States/ConversionStatus.php:25-28`,
  `packages/affiliates/src/States/PayoutStatus.php:25-28`), and unknown state
  strings now throw instead of silently becoming pending
  (`packages/affiliates/src/States/ConversionStatus.php:115-125`,
  `packages/affiliates/src/States/PayoutStatus.php:115-125`,
  `packages/affiliates/src/States/AffiliateStatus.php:131-151`). Filament tables, portals, and infolists route
  labels and colors through `fromString()` (for example,
  `packages/filament-affiliates/src/Resources/AffiliateConversionResource/Tables/AffiliateConversionsTable.php:51-55,122-129`).
  The vouchers reporting resolver was inspected and has no lifecycle-status
  read to migrate.
- **Cart integration layers — IMPLEMENTED.** The obsolete decorator pair,
  `CartWithAffiliates` and `CartManagerWithAffiliates`, the old registrar, and
  the unused `HasAffiliates` trait were removed. `CartBridge` is now the
  single integration point and hydrates cookie attribution
  (`packages/affiliates/src/Support/Integrations/CartBridge.php:25-46`),
  while the affiliates condition provider remains only as the live condition
  provider. The core provider binds the bridge and no longer registers the
  deleted decorator registrar (`packages/affiliates/src/AffiliatesServiceProvider.php:105-143`).
  `tests/src/Affiliates/Unit/CartBridgeTest.php` covers the current cart shape.
- **Voucher wiring — IMPLEMENTED / DEPENDENCY RECORDED.** Affiliates owns
  `AffiliateLookup` and `VoucherBridge`; the affiliate-side voucher listener
  remains registered in the affiliates provider. The vouchers-side
  `AffiliateIntegrationRegistrar` is not a duplicate: it creates voucher
  records for affiliate lifecycle events (`packages/vouchers/src/Support/AffiliateIntegrationRegistrar.php:21-68`),
  while `VoucherAffiliateOwnershipGuard` remains on the vouchers side. No
  out-of-scope vouchers file was changed.
- **Commission rules — EXPLICIT DEFERRAL.** Commission match rules remain in
  `CommissionRuleEngine`/`CommissionRuleType`, performance awards retain the
  `PerformanceBonusRule` contract, and fraud remains a separate lifecycle
  under `FraudRule`. These are intentionally not collapsed without changing
  public extension contracts and award semantics; no generic framework was
  introduced. Evidence: `packages/affiliates/src/Services/Commissions/CommissionRuleEngine.php`,
  `Contracts/PerformanceBonusRule.php:9-16`, and `Contracts/FraudRule.php:12-18`.
- **Facade alias — IMPLEMENTED.** The missing facade now resolves the existing
  lookup binding (`packages/affiliates/src/Facades/Affiliate.php:15-20`),
  matching the Composer alias and documented `Affiliate::` usage
  (`packages/affiliates/docs/04-usage.md:382-387`).
- **Owner dialects — IMPLEMENTED / VERIFIED.** Raw analytics queries now use
  `OwnerQuery::applyToQueryBuilder` in `CohortAnalyzer` and
  `PerformanceBonusService` (for example,
  `packages/affiliates/src/Services/CohortAnalyzer.php:305-315,360-373,420-433`).
  The three `ScopesBy*` concerns were retained because they guard relational
  ownership through parent relations rather than duplicate `HasOwner`; the
  ownership split is documented in `packages/affiliates/docs/10-multi-tenancy.md:105-111`.
- **Filament payout boundary — IMPLEMENTED.** `ProcessAffiliatePayout`
  revalidates the payout for the current owner and delegates lifecycle
  transitions to core `UpdatePayoutStatus`
  (`packages/filament-affiliates/src/Actions/ProcessAffiliatePayout.php:31-45,72-99,144-174,213-237`).
  Other payout actions and resources retain owner-safe re-resolution.
- **Security/performance/small items — VERIFIED.** Cookie attribution checks
  active, owner-scoped records in
  `packages/affiliates/src/Resolvers/DatabaseAffiliateLookup.php:58-75,95-133`,
  and forged/inactive cookie regression coverage is in
  `tests/src/Affiliates/Unit/CartBridgeTest.php:52-90`. Stripe/PayPal error
  paths log operation metadata rather than payout secrets
  (`packages/affiliates/src/Services/Payouts/PayPalProcessor.php:200`). Existing
  attribution, conversion, and payout indexes were verified in their
  migrations (`packages/affiliates/database/migrations/2000_01_01_000002_create_affiliate_attributions_table.php:57-63`,
  `000003_create_affiliate_conversions_table.php:53-59`,
  `000004_create_affiliate_payouts_table.php:34-36`). Aggregation is chunked,
  widgets inherit Filament's lazy default, and the catalog registry remains a
  justified extension seam (`PromotableRegistry.php:17`).
- **Testing finding — IMPLEMENTED.** Core and Filament coverage now includes
  commission, conversion maturity, payout transitions, fraud, cart/cookie
  security, and owner isolation; the Area results are recorded below.

## Verification

- Affiliates Area: `./vendor/bin/pest --parallel tests/src/Affiliates` — **1,139 passed, 5 skipped, 2,611 assertions**.
- FilamentAffiliates Area: `./vendor/bin/pest --parallel tests/src/FilamentAffiliates` — **317 passed, 879 assertions**.
- The Area suites were escalated after targeted checks because the state API
  changes, deleted cart layers, provider rewiring, and payout-boundary change
  cross the core/Filament surfaces.
- Targeted affiliates checks, each with `--parallel`: `CartBridgeTest.php` —
  **2 passed, 4 assertions**; the payout-batch, bulk-payout, and queue test
  files — **6/25**, **7/29**, and **2/9** passed/assertions respectively.
- Targeted provider check: `FilamentAffiliatesServiceProviderTest.php` —
  **7 passed, 7 assertions**.
- PHPStan: `./vendor/bin/phpstan analyse packages/affiliates/src packages/filament-affiliates/src --level=6` — **clean**.
- Pint: `./vendor/bin/pint --test packages/affiliates/src packages/filament-affiliates/src tests/src/Affiliates tests/src/FilamentAffiliates` — **passed**.
- Events canary: `./vendor/bin/pest --parallel tests/src/Events/EventLifecycleWorkflowTest.php` — **4 passed, 4 assertions**.
- Cart canary: `./vendor/bin/pest --parallel tests/src/Cart/Feature/Conditions/ConditionProviderRegistryTest.php` — **1 passed, 2 assertions**.

## Audit deviations

- The prescribed `.ai/rules/index.md` is absent in this checkout; no matching
  path rules could be loaded.
- Commission-rule consolidation is an explicit deferral: the current rule
  contracts have distinct calculation, award, and fraud lifecycles, so merging
  them would be a public behavioral redesign rather than a safe cleanup.
- No new `(owner_type, owner_id, created_at)` migration was added. Existing
  hot-path indexes were verified; adding a speculative index without measured
  workload evidence was deferred. The optional `body_json` normalization was
  likewise not a rated required change.
- The cart rewrite changed the old decorator/test shapes; the owned tests were
  migrated to `CartBridge` and the old integration files were deleted.
- The vouchers registrar and reporting resolver are outside the affiliates
  write set and were inspected but left untouched. The current generic cookie
  attribution has no mandatory program-membership invariant; active-record and
  owner validation remain enforced.
- Stale generated evidence references outside the write set were not edited.
  No full repository test suite was run.

## Residual notes

No unrecorded rated finding remains. Re-open the audit if commission-rule
lifecycles are intentionally unified or if measured analytics workload shows a
missing composite index.
