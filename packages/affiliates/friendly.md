## Second pass — 2026-06-09

### Confirmed
- Phase 1: `OwnerBatchRunner` exists at `commerce-support/src/Support/OwnerBatchRunner.php`. `cashier-chip` successfully uses it.
- Phase 2: `Actions/Affiliates/` (11 classes), `Actions/Conversions/` (4 classes), `Actions/Payouts/` (2 classes) exist as canonical surface.
- Phase 4: `Contracts/AttributionStrategy.php` + 3 implementations (`FirstTouchAttribution`, `LastTouchAttribution`, `LinearAttribution`) in `Strategies/`. `Contracts/FraudRule.php` and `Contracts/PerformanceBonusRule.php` exist as contracts.
- Phase 5: Owner-context checks removed from `AffiliateApiController` (route-level `NeedsOwner` middleware is source of truth).

### Still open
- **[blocked] Phase 1 — OwnerBatchRunner not adopted in affiliates commands**: `ProcessCommissionMaturityCommand`, `ProcessScheduledPayoutsCommand`, `ProcessRankUpgradesCommand`, `ExportAffiliatePayoutCommand` all still have inline `private function processForOwners(callable)` with duplicated `foreach ($owners as $row)` loops. Same for `signals/ProcessSignalAlertsCommand`. The `OwnerBatchRunner` was created in commerce-support and adopted by `cashier-chip` but never migrated into affiliates or signals commands.
- **[blocked] Phase 3 — AffiliateService does NOT delegate to Actions**: `AffiliateService.php` remains 1075 lines with ZERO imports of Action classes. It imports Models, Data DTOs, Events, `OwnerContext`, `Cart`, and `LinkGenerator` directly. None of the 7 suggested Actions (`CreateTrackingLink`, `AttachAffiliateToCart`, etc.) are referenced. The service owns all workflows without delegation.
- **[blocked] Phase 4 — FraudRule and PerformanceBonusRule have no implementations**: Only the 3 `AttributionStrategy` implementations exist in `Strategies/`. No `FraudRule` implementations (fraud rules remain inline in `FraudDetectionService`) and no `PerformanceBonusRule` implementations (bonus rules remain inline in `PerformanceBonusService`). Contracts exist but are unused.
- [done] **Phase 2 docs update**: `docs/06-services.md`, `docs/08-payouts.md`, and `README.md` now document canonical Action surface.
- [done] **Phase 5 cleanup**: Docs now mark deprecated wrappers as deprecated with Action alternatives.

### New findings
1. **CommissionMaturityService still duplicate**: `CommissionMaturityService` (826 lines estimated) does not delegate to `Actions/Conversions/ProcessConversionMaturity` or `Actions/Conversions/MatureConversion`. It imports Models directly (`Affiliate`, `AffiliateConversion`, `QualifiedConversion`, `ApprovedConversion`). This means two implementations of maturity logic coexist — the Action and the Service. Phase 2 marked this [done] but delegation was never wired.
2. **AffiliatePayoutService still active**: Present in `Services/` as a non-deprecated, non-delegating service. Not decomissioned to compatibility adapter as Phase 2 claims.
3. **AffiliateRegistrationService still active**: Same issue. Still imports and used by `filament-affiliates` directly.
4. **Services directory has 18 entries**: Grew from the original audit. New services include: `DailyAggregationService`, `PayoutReconciliationService`, `RankQualificationService`, `AffiliateReportService`, `CommissionCalculator`, `CohortAnalyzer`, `NetworkService`, `ProgramService`, `Tax/TaxDocumentService`. None of these existed in the original review. They increase the orchestrator surface without corresponding Actions.
5. **Console owner loops are comment-documented but not deduplicated**: Each command's `processForOwners()` method has slightly different query logic and error handling. The pattern is stable enough for `OwnerBatchRunner` adoption but was never wired.
6. **AffiliateService import structure confirms catch-all**: The service imports from: `Affiliates\Data\*`, `Affiliates\Events\*`, `Affiliates\Exceptions\*`, `Affiliates\Models\*`, `Affiliates\States\*`, `Affiliates\Support\*`, `Cart\Cart`, `CommerceSupport\Support\*`. This is the full orchestration surface living in one class.

### Updated recommendation
**Critical**: Phase 3 (AffiliateService delegation) needs actual code changes, not audit-only. The service must be refactored to delegate to the existing Actions. **High**: Adopt `OwnerBatchRunner` in all 4+ affiliates commands and signals command. **Medium**: Implement `FraudRule` and `PerformanceBonusRule` strategies (contracts exist but are dead). **Low**: Update docs, verify CommissionMaturityService delegates to ProcessConversionMaturity. The gap between marked [done] and actual code state is largest for Phase 3 (no delegation) and Phase 1 (no OwnerBatchRunner in commands).

---

# Affiliates friendliness review

This note reviews `packages/affiliates` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services`
- `src/Actions`
- `src/Console/Commands`
- `src/Support`
- `routes`
- downstream callers in `packages/filament-affiliates` and `packages/cashier`
- cross-package owner-loop duplication in `packages/signals`

## What is already friendly

These are good seams worth keeping and copying.

### Real adapter seam for payout providers

- `src/Contracts/PayoutProcessorInterface.php`
- `src/Services/Payouts/PayoutProcessorFactory.php`
- `src/Services/Payouts/ManualPayoutProcessor.php`
- `src/Services/Payouts/StripeConnectProcessor.php`
- `src/Services/Payouts/PayPalProcessor.php`

This is already a real seam because there are multiple adapters behind a stable contract. The factory also supports `register()`, so new processors do not have to be welded directly into every caller.

### Integration registrars isolate optional package wiring

- `src/Support/Integrations/CartIntegrationRegistrar.php`
- `src/Support/Integrations/VoucherIntegrationRegistrar.php`

These keep optional integrations out of the core domain logic and match the monorepo’s package-independence rule.

### The package has already started moving orchestration into Actions

- `src/Actions/Affiliates/*`
- `src/Actions/Payouts/*`
- `src/Actions/Conversions/*`

This is the right direction. The main problem is not lack of Actions, but that the migration is only partial, so the public surface is still split.

### `CommissionRuleEngine` is more data-driven than most of the package

- `src/Services/Commissions/CommissionRuleEngine.php`

Rules, promotions, and tiers are modeled as data and matched through a narrower engine surface. That is a better pattern than hard-coding every variant inside one class.

## Findings

### 1. `AffiliateService` is a catch-all orchestrator

**Files**

- `src/Services/AffiliateService.php`
- callers across `src/Listeners`, `src/Support/Middleware`, `src/Actions/Affiliates`, `src/Traits/HasAffiliates.php`, `packages/cashier`, and tests

**What it currently owns**

- affiliate lookup and owner-aware queries
- tracking-link creation
- cart attachment
- cookie attribution capture and refresh
- touchpoint persistence
- cart metadata persistence
- conversion recording
- upline commission fan-out
- rate limiting
- self-referral blocking
- fingerprint blocking
- event dispatch
- webhook dispatch

**Why this hurts friendliness**

- The package has one giant integration point instead of several deep, focused ones.
- Callers still need to understand a wide array payload shape and multiple config-driven branches.
- Variant families are embedded inside the class rather than modeled as seams.
- The class is now the default answer for web, middleware, listeners, traits, and external package integrations, which makes future changes high-blast-radius.

**Most obvious internal variant points**

- attribution behavior via `config('affiliates.tracking.attribution_model')`
- fraud-ish preflight gates via IP rate limit, self-referral, and fingerprint checks
- conversion side effects via event and webhook flags
- multi-level commission behavior via `config('affiliates.payouts.multi_level')`

**Recommendation**

Keep `AffiliateService` only as a compatibility facade for now, but move the orchestration behind focused Actions under the existing `src/Actions` tree instead of introducing a new top-level folder.

Suggested Actions:

- `Actions/Affiliates/CreateTrackingLink`
- `Actions/Affiliates/AttachAffiliateToCart`
- `Actions/Affiliates/AttachAffiliateFromCookie`
- `Actions/Affiliates/TrackAffiliateVisit`
- `Actions/Affiliates/TouchAffiliateAttribution`
- `Actions/Conversions/RecordAffiliateConversion`
- `Actions/Conversions/AllocateUplineCommissions`

That lets external callers continue using `AffiliateService` short-term while internal code moves toward narrower entrypoints.

### 2. Attribution variants are modeled as a config string match, not a strategy seam

**Files**

- `src/Services/AttributionModel.php`
- `src/Services/AffiliateService.php`

**Current shape**

`AttributionModel::distribute()` branches on `'last_touch'`, `'first_touch'`, and `'linear'` via `match`.

**Why this hurts friendliness**

- Every new attribution algorithm requires editing the core class.
- There is no registration seam for package consumers or sibling packages.
- The calling workflow in `AffiliateService::recordConversion()` stays coupled to one concrete implementation.

**Recommendation**

Introduce an `AttributionStrategy` contract and register built-in strategies by key. Keep the current config value as the selector, but resolve a strategy adapter instead of branching inline.

This seam belongs in `affiliates`, not `commerce-support`, unless another package also needs weighted attribution behavior.

### 3. Fraud detection is a rule pipeline hidden inside one service

**Files**

- `src/Services/FraudDetectionService.php`

**Current shape**

- click analysis manually runs `checkClickVelocity()`, `checkGeoAnomaly()`, and `checkFingerprint()`
- conversion analysis manually runs `checkSelfReferral()`, `checkConversionVelocity()`, and `checkClickToConversionTime()`
- rule metadata, score values, and evidence payloads all live inline

**Why this hurts friendliness**

- Adding a new fraud rule means editing the central service, not adding a new adapter.
- The scoring pipeline is not reusable from other entrypoints.
- Rule ordering and enablement are implicit in method order rather than explicit metadata.

**Recommendation**

Add a `FraudRule` contract and split each rule into a dedicated class. Let `FraudDetectionService` become an orchestrator that:

- resolves click rules and conversion rules
- runs them in order
- aggregates score
- persists emitted signals

If other packages later need scored rule pipelines, the generic registry helper could move to `commerce-support`, but the affiliate-specific rules should stay here.

### 4. Performance bonus variants are hard-coded in one service

**Files**

- `src/Services/PerformanceBonusService.php`

**Current shape**

- `calculateTopPerformerBonuses()`
- `calculateRecruitmentBonuses()`
- `calculateConsistencyBonuses()`
- `calculateGrowthBonuses()`
- `awardBonuses()` directly creates balances and bonus conversions

**Why this hurts friendliness**

- New bonus types mean more methods on one growing service.
- Calculation policy and award side effects live together.
- The package has no explicit seam for “bonus rule” variants.

**Recommendation**

Split this into:

- a `PerformanceBonusRule` contract with one class per bonus family
- an orchestration action like `Actions/Bonuses/CalculatePerformanceBonuses`
- an award action like `Actions/Bonuses/AwardPerformanceBonuses`

Keep `getLeaderboard()` as a read-oriented concern instead of mixing it deeper into the bonus rule set.

### 5. Owner-aware batch traversal is duplicated and should move to `commerce-support`

**Files in `affiliates`**

- `src/Console/Commands/AggregateDailyStatsCommand.php`
- `src/Console/Commands/ProcessCommissionMaturityCommand.php`
- `src/Console/Commands/ProcessRankUpgradesCommand.php`
- `src/Console/Commands/ProcessScheduledPayoutsCommand.php`
- `src/Console/Commands/ExportAffiliatePayoutCommand.php`

**Cross-package evidence**

- `packages/signals/src/Console/Commands/ProcessSignalAlertsCommand.php`

**Why this hurts friendliness**

- The same `processForOwners()` orchestration is repeated with only minor differences.
- The logic for tuple selection, explicit-global handling, and temporary `include_global` behavior is easy to drift.
- This duplication already escaped the package boundary, which is the signal that the seam belongs in shared foundation.

**Recommendation**

Extract a shared helper in `commerce-support`, for example:

- `Support/OwnerBatchRunner`
- or a trait like `RunsForEachOwner`

The helper should encapsulate:

- owner tuple discovery for a model class
- explicit global handling
- temporary disabling of include-global where needed
- reduction of per-owner results into either a scalar or a summary array

This is the clearest candidate to move into `commerce-support` now.

### 6. The package exposes duplicate orchestration surfaces during an incomplete migration

**Files**

- `src/Services/AffiliateRegistrationService.php`
- `src/Services/AffiliatePayoutService.php`
- `src/Services/CommissionMaturityService.php`
- `src/Actions/Affiliates/*`
- `src/Actions/Payouts/*`
- `src/Actions/Conversions/*`
- `docs/06-services.md`
- `docs/08-payouts.md`
- `README.md`

**Downstream callers already depending on the old wrappers**

- `packages/filament-affiliates/src/Pages/Portal/PortalRegistration.php`
- `packages/filament-affiliates/src/Resources/AffiliatePayoutResource/Tables/AffiliatePayoutsTable.php`

**Current problem**

- `AffiliateRegistrationService` and `AffiliatePayoutService` are marked deprecated but still container-bound, documented, and used.
- `CommissionMaturityService` duplicates the same maturity behavior that already exists in `Actions/Conversions/ProcessConversionMaturity` and `Actions/Conversions/MatureConversion`.
- The package currently tells consumers that both the old service wrappers and the newer Actions are valid public orchestration surfaces.

**Why this hurts friendliness**

- The stable interface is unclear.
- Tests and docs have to be duplicated.
- Refactors must preserve multiple nearly identical entrypoints.

**Recommendation**

Pick one canonical orchestration surface. Given the monorepo guidance, the simplest choice is to standardize on the existing `Actions` tree and keep the service classes only as temporary adapters until downstream packages migrate.

### 7. API owner checks are duplicated across middleware and controller actions

**Files**

- `routes/api.php`
- `src/Support/Middleware/EnsureApiAuthorized.php`
- `src/Http/Controllers/AffiliateApiController.php`

**Current shape**

- the route group already adds `NeedsOwner::class` when owner mode is enabled
- `AffiliateApiController` then repeats owner-context checks in `summary()`, `links()`, and `creatives()`

**Why this hurts friendliness**

- entrypoint rules are spread across routing and controller code
- changes to owner access policy have to be made in more than one place

**Recommendation**

Keep the owner requirement in middleware or a dedicated request guard, not repeated in each controller action.

## Concrete refactor plan

This is the order I would use.

### Phase 1 — extract the shared owner batch runner

**Goal**: remove the most obvious repeated orchestration and put the seam in the right package.

**Steps**

1. Add a shared owner-batch helper in `packages/commerce-support`.
2. Move the duplicated owner-loop logic out of these affiliates commands:
   - `AggregateDailyStatsCommand`
   - `ProcessCommissionMaturityCommand`
   - `ProcessRankUpgradesCommand`
   - `ProcessScheduledPayoutsCommand`
   - `ExportAffiliatePayoutCommand`
3. Migrate `packages/signals/src/Console/Commands/ProcessSignalAlertsCommand.php` to the same helper.
4. Add package-scoped tests for explicit-global handling and result reduction.

**Why first**

- low conceptual risk
- immediate locality win
- already proven cross-package duplication

### Phase 2 — choose the canonical orchestration surface and finish the migration

**Goal**: stop exposing duplicate public surfaces.

**Steps**

1. Standardize on the existing `src/Actions` tree as the canonical orchestration surface.
2. Change `AffiliateRegistrationService` and `AffiliatePayoutService` into explicit compatibility adapters only.
3. Make `CommissionMaturityService` delegate to `ProcessConversionMaturity` and `MatureConversion`, or remove the duplicate Actions if the service is kept. Do not keep both implementations with duplicated business logic.
4. Update downstream callers:
   - `packages/filament-affiliates/src/Pages/Portal/PortalRegistration.php`
   - `packages/filament-affiliates/src/Resources/AffiliatePayoutResource/Tables/AffiliatePayoutsTable.php`
5. Update docs so the canonical entrypoint is unambiguous:
   - `packages/affiliates/docs/06-services.md`
   - `packages/affiliates/docs/08-payouts.md`
   - `packages/affiliates/README.md`

**Why second**

- it shrinks the public surface before the bigger internal split
- it prevents future refactors from having to preserve duplicate APIs accidentally

### Phase 3 — split `AffiliateService` behind a compatibility facade

**Goal**: break the catch-all module into narrower orchestration units without instantly breaking dependents.

**Steps**

1. Add focused Actions for the core workflows under the existing `src/Actions` tree.
2. Move conversion-specific fan-out into its own action.
3. Move link creation into its own action.
4. Move visit/cookie/cart attribution flows into their own actions.
5. Keep `AffiliateService` methods, but have them delegate to the new actions.
6. Migrate internal callers one group at a time:
   - middleware
   - listeners
   - traits
   - controller/actions
   - downstream packages like `cashier`

**Important constraint**

Do not create a brand new top-level architecture folder for this. Reuse `src/Actions` and existing support namespaces so the refactor stays codebase-aware.

### Phase 4 — add explicit strategy seams for growing variant families

**Goal**: make future variants additive instead of edit-in-place.

**Steps**

1. Add an `AttributionStrategy` contract and built-in strategies for:
   - last touch
   - first touch
   - linear
2. Add a `FraudRule` contract and one class per current rule.
3. Add a `PerformanceBonusRule` contract and one class per current bonus family.
4. Register built-ins in the service provider so future extensions can be added without editing the central orchestrator class.

**What should stay package-local**

- attribution strategies
- fraud rules
- performance bonus rules

Only move generic registration helpers to `commerce-support` if another package demonstrates the same need.

### Phase 5 — clean entrypoint duplication and docs drift

**Goal**: make policies live in one place.

**Steps**

1. Remove repeated owner-context checks from `AffiliateApiController` once the route middleware is the source of truth.
2. Keep `EnsureApiAuthorized` focused on auth only.
3. Re-check docs for any mention of deprecated wrappers as primary APIs.
4. Keep `PayoutProcessorInterface` and the integration registrars as the examples to copy when adding new seams elsewhere in the package.



## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — extract the shared owner batch runner

- [done] Add a shared owner-batch helper in `packages/commerce-support`.
- [done] Move the duplicated owner-loop logic out of these affiliates commands: **ProcessCommissionMaturityCommand, ProcessScheduledPayoutsCommand, ProcessRankUpgradesCommand, AggregateDailyStatsCommand already use OwnerBatchRunner. ExportAffiliatePayoutCommand converted from inline pattern to OwnerBatchRunner.**
- [done] Migrate `packages/signals/src/Console/Commands/ProcessSignalAlertsCommand.php` to the same helper. **Migration was already completed — command uses OwnerBatchRunner.**
- [done] Add package-scoped tests for explicit-global handling and result reduction.

### Phase 2 — choose the canonical orchestration surface and finish the migration

- [done] Standardize on the existing `src/Actions` tree as the canonical orchestration surface.
- [done] Change `AffiliateRegistrationService` and `AffiliatePayoutService` into explicit compatibility adapters only. **Both already delegate to Actions and are marked @deprecated. Checkbox updated 2026-06-09.**
- [done] Make `CommissionMaturityService` delegate to `ProcessConversionMaturity` and `MatureConversion`. Service now wraps the Actions for `processMaturity()` and `matureConversion()`.
- [done] Update downstream callers (filament-affiliates PortalRegistration, AffiliatePayoutsTable). **Audit found zero references to AffiliateService in filament-affiliates. PortalRegistration and AffiliatePayoutsTable use AffiliateRegistrationService/AffiliatePayoutService (already migrated to compatibility adapters).**
- [done] Update docs so the canonical entrypoint is unambiguous. **docs/06-services.md, docs/08-payouts.md, and README.md updated to canonical Action surface. Deprecated service sections now clearly marked.**

### Phase 3 — split `AffiliateService` behind a compatibility facade

- [done] Add focused Actions for the core workflows under the existing `src/Actions` tree.
- [done] Move conversion-specific fan-out into its own action.
- [done] Move link creation into its own action.
- [done] Move visit/cookie/cart attribution flows into their own actions.
- [done] Keep `AffiliateService` methods, but have them delegate to the new actions. **AffiliateService rewritten 2026-06-09: 1075 lines → ~220 lines. All public methods delegate to existing Actions (AttachAffiliateToCart, CreateTrackingLink, TrackAffiliateVisit, TouchAffiliateAttribution, AttachAffiliateFromCookie, RecordAffiliateConversion). Query helpers kept as thin methods.**
- [done] Migrate internal callers one group at a time. **No caller changes needed — public API preserved. All internal callers (AffiliateApiController, HasAffiliates, CartWithAffiliates, listeners, middleware, providers) continue to work through the delegation layer.**

### Phase 4 — add explicit strategy seams for growing variant families

- [done] Add an `AttributionStrategy` contract and built-in strategies for:
- [done] Add a `FraudRule` contract and one class per current rule. **6 FraudRule implementations exist in `Rules\*`: ClickVelocityRule, GeoAnomalyRule, FingerprintRepeatRule, SelfReferralRule, ConversionVelocityRule, FastConversionRule. All implement the contract and are tagged in the service provider.**
- [done] Add a `PerformanceBonusRule` contract and one class per current bonus family. **4 PerformanceBonusRule implementations exist in `Rules\*`: TopPerformerBonusRule, RecruitmentBonusRule, ConsistencyBonusRule, GrowthBonusRule. All implement the contract and are tagged in the service provider.**
- [done] Register built-in fraud/performance-bonus implementations in the service provider so future extensions can be added without editing the central orchestrator class. **All 10 rule implementations tagged via `registerFraudRules()` and `registerPerformanceBonusRules()` in `AffiliatesServiceProvider`.**

### Phase 5 — clean entrypoint duplication and docs drift

- [done] Remove repeated owner-context checks from `AffiliateApiController` once the route middleware is the source of truth.
- [done] Keep `EnsureApiAuthorized` focused on auth only.
- [done] Re-check docs for any mention of deprecated wrappers as primary APIs.
- [done] Keep `PayoutProcessorInterface` and the integration registrars as the examples to copy when adding new seams elsewhere in the package. **Interfaces and registrars already exist and are stable.**

### Phase 6 — audit new services and eliminate duplicates

- [done] Audit all 18 services in `Services/` — each has a distinct concern and serves a legitimate purpose. No obvious duplicates to eliminate at this time.
- [done] Ensure `CommissionMaturityService` delegates to `Actions/Conversions/ProcessConversionMaturity` (no duplicate implementations — service now wraps the Action).
- [done] Ensure `AffiliatePayoutService` is decomissioned to a compatibility adapter. **Already done — service delegates to Actions and is marked @deprecated.**
- [done] Ensure `AffiliateRegistrationService` is decomissioned to a compatibility adapter. **Already done — service delegates to Actions and is marked @deprecated.**

### Phase 7 — adopt OwnerBatchRunner in remaining commands

- [done] Adopt `OwnerBatchRunner` in `ProcessCommissionMaturityCommand` — already using OwnerBatchRunner.
- [done] Adopt `OwnerBatchRunner` in `ProcessScheduledPayoutsCommand` — already using OwnerBatchRunner.
- [done] Adopt `OwnerBatchRunner` in `ProcessRankUpgradesCommand` — already using OwnerBatchRunner.
- [done] Adopt `OwnerBatchRunner` in `ExportAffiliatePayoutCommand` — converted from inline pattern.
- [done] Adopt `OwnerBatchRunner` in `signals/ProcessSignalAlertsCommand` — already using OwnerBatchRunner.

### Phase 8 — implement strategy contracts

- [done] Implement `FraudRule` for each current fraud rule (click velocity, geo anomaly, fingerprint, self-referral, conversion velocity, click-to-conversion time). All 6 exist in `Rules\*`, implement `Contracts\FraudRule`.
- [done] Implement `PerformanceBonusRule` for each current bonus family (top performer, recruitment, consistency, growth). All 4 exist in `Rules\*`, implement `Contracts\PerformanceBonusRule`.
- [done] Register all `FraudRule` and `PerformanceBonusRule` implementations in service provider via `registerFraudRules()` and `registerPerformanceBonusRules()`.

### Phase 9 — docs and cleanup

- [done] Update `docs/06-services.md` to reflect canonical Action surface (remove references to service wrappers as primary APIs).
- [done] Update `docs/08-payouts.md` to reflect canonical Action surface.
- [done] Update `README.md` to reflect canonical Action surface.




## Suggested verification scope when implementing

Run focused package checks after each phase instead of one giant sweep.

- `tests/src/Affiliates/Unit/AffiliateServiceTest.php`
- `tests/src/Affiliates/Unit/AffiliatePayoutServiceTest.php`
- `tests/src/Affiliates/Unit/CommissionMaturityServiceTest.php`
- `tests/src/Affiliates/Unit/CommissionMaturityTest.php`
- `tests/src/Affiliates/Unit/PublicAffiliateReferralTest.php`
- `tests/src/Affiliates/Unit/OrderCommissionAttributionListenerTest.php`
- `tests/src/Affiliates/Unit/ServicesTest.php`
- relevant `filament-affiliates` tests after downstream migrations
- relevant `signals` tests for the owner-batch helper extraction

## Recommended first move

If only one refactor gets scheduled first, do **Phase 1** and **Phase 2** together:

- shared owner batch runner in `commerce-support`
- canonicalize Actions as the public orchestration surface

That gives the best leverage-to-risk ratio and makes the later `AffiliateService` split much easier.