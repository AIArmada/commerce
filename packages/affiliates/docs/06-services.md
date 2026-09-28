---
title: Services Reference
---

# Services Reference

## Canonical API: Actions

The canonical orchestration surface for affiliates is the `Actions` tree. Prefer these over direct service calls:

### Affiliates Actions (`Actions/Affiliates/`)

| Action | Purpose |
|--------|---------|
| `ApproveAffiliate::run($affiliate)` | Approve a pending affiliate |
| `AttachAffiliateToCart::run($affiliate, $cart, $context)` | Attach an affiliate to a cart |
| `AttachAffiliateFromCookie::run($cart, $cookieValue, $context)` | Attach from cookie tracking |
| `CapturePublicAffiliateReferral::run($request, $affiliateCode)` | Capture public referral |
| `CreateAffiliate::run($data, $owner)` | Create a new affiliate |
| `CreateTrackingLink::run($affiliate, $url, $attributes)` | Create a tracking link |
| `GenerateAffiliateCode::run($name)` | Generate a unique code |
| `RejectAffiliate::run($affiliate)` | Reject an affiliate |
| `ResolvePublicAffiliateReferralContext::run($request)` | Resolve referral context |
| `TouchAffiliateAttribution::run($cookieValue, $context)` | Touch cookie attribution |
| `TrackAffiliateVisit::run($code, $context, $cookieValue)` | Track a visit by code |

### Conversions Actions (`Actions/Conversions/`)

| Action | Purpose |
|--------|---------|
| `AllocateUplineCommissions::run($baseConversions, $autoApprove, $status, $attributionId)` | Distribute upline commissions |
| `MatureConversion::run($conversion)` | Mature a single conversion |
| `ProcessConversionMaturity::run()` | Process batch maturity |
| `RecordAffiliateConversion::run($cart, $payload)` | Record a conversion |
| `ReverseAffiliateConversion::run($conversion, $reason)` | Reverse a conversion (negated companion) |
| `VoidAffiliateConversion::run($conversion, $reason)` | Settle a conversion as not payable |

`RecordAffiliateConversion` accepts `origin` and `source_ref` in the
payload to stamp provenance (e.g. `origin: network` with the network
link code in `source_ref`). `ReverseAffiliateConversion` is idempotent
on (conversion, reason): the original is marked reversed and a negated
companion conversion posts, so sum-based readers stay correct.
`VoidAffiliateConversion` rejects holding/approved conversions
(holding/available voided) and routes paid conversions through
reversal (clawback leg); terminal states are idempotent no-ops. Both
refuse conversions reserved by an open payout — see
`AffiliateConversion::assertNotReservedByOpenPayout()` in
[Models](05-models.md).

### Payouts Actions (`Actions/Payouts/`)

| Action | Purpose |
|--------|---------|
| `AssertPayoutCompletable::run($payout)` | Completion gate (shared by manual + reconcile paths) |
| `CreatePayout::run($conversionIds, $attributes)` | Create a payout batch |
| `UpdatePayoutStatus::run($payout, $status, $notes, $metadata)` | Update payout status |

Completion runs through a single eligibility gate.
`AssertPayoutCompletable` resolves the affiliate from the linked
conversions, or from the payout payee for manual-record payouts that
link none; payouts whose conversions span more than one affiliate are
refused outright, since a payout must belong to one affiliate. The gate
then refuses affiliates that can no longer receive payouts
(throwing `PayoutCompletionBlockedException`, an
`InvalidArgumentException`) — unless the payout was created with a
`payout_override_reason` for a disabled affiliate. The validated reason
is stamped onto `metadata.payout_override` at creation, either via
`CreatePayout::run($ids, ['payout_override_reason' => ...])` or via the
payout create form's override field (caller-supplied metadata can never
forge it), and the completion time is recorded as
`override_completed_at`. Completing a reserved payout also asserts
every linked conversion left `Approved` in the same write; a drifted
conversion aborts the whole completion instead of paying a partial
batch. Manual-record payouts complete as bookkeeping records: the
eligibility gate applies, but no balance is debited because no funds
were reserved — see the reconciliation caveat in
[Troubleshooting](99-troubleshooting.md).

## CommissionCalculator

Calculates commissions based on affiliate settings, rules, and tiers.

```php
use AIArmada\Affiliates\Services\CommissionCalculator;

$calculator = app(CommissionCalculator::class);
```

### Methods

```php
// Calculate commission for an order subtotal (affiliate rate applied)
$commission = $calculator->calculate($affiliate, 14000);
```

Per-program rules, volume tiers, and promotions are evaluated by
`CommissionRuleEngine::calculate()` with the affiliate's program rules;
see [Programs](07-programs.md).

## PayoutReconciliationService

Reconciles payouts with external payment providers.

```php
use AIArmada\Affiliates\Services\PayoutReconciliationService;

$service = app(PayoutReconciliationService::class);

// Reconcile with provider
$changed = $service->reconcilePayout($payout, $externalStatus, $externalData);

// Get payouts still needing reconciliation
$pending = $service->getPayoutsNeedingReconciliation();
```

## FraudDetectionService

Real-time fraud detection and scoring.

```php
use AIArmada\Affiliates\Services\FraudDetectionService;

$service = app(FraudDetectionService::class);
```

### Methods

```php
// Analyze a click for fraud; returns ['allowed', 'score', 'signals']
$result = $service->analyzeClick($affiliate, $request);

// Analyze a conversion for fraud; persists signals and returns the same shape
$result = $service->analyzeConversion($affiliate, $conversion);

// Aggregated risk profile for an affiliate
$profile = $service->getRiskProfile($affiliate);
// ['total_score', 'severity', 'signal_count', 'by_rule', 'pending_review', 'confirmed']
```

Individual checks live in `Rules/` (velocity, self-referral, device
fingerprint, geo anomaly, unusual amount, rapid conversion). See
[Fraud Detection](09-fraud-detection.md).

## UplineService

Manages affiliate upline hierarchies (MLM).

```php
use AIArmada\Affiliates\Services\UplineService;

$service = app(UplineService::class);
```

### Methods

```php
// Add an affiliate under a sponsor
$service->addToUpline($affiliate, $sponsor);

// Get upline affiliates
$uplines = $service->getUpline($affiliate);

// Get downline affiliates
$downlines = $service->getDownline($affiliate);

// Team sales for a period, measured in the affiliate currency
$teamSales = $service->getTeamSales($affiliate, $from, $to);
```

Revenue thresholds (team sales, rank metrics, volume tiers, program eligibility) measure volume in the affiliate's currency via `RevenueVolume`: legs convert when exchange rates exist, otherwise only the affiliate-currency leg counts. The top-performer leaderboard ranks raw sums when every affiliate earns one currency, and converts to `affiliates.currency.default` when currencies mix — entries without a rate sink below ranked ones and never earn the bonus.

## RankQualificationService

Manages affiliate rank progression.

```php
use AIArmada\Affiliates\Services\RankQualificationService;

$service = app(RankQualificationService::class);
```

### Methods

```php
// Highest rank the affiliate currently qualifies for (or null)
$rank = $service->evaluate($affiliate);

// Qualification metrics, measured in the affiliate currency
$metrics = $service->calculateMetrics($affiliate);
// ['personal_sales', 'team_sales', 'active_downlines', 'lifetime_value']

// Re-evaluate every affiliate (returns upgrade count)
$upgraded = $service->processAllRankUpgrades();

// Manually pin or clear an affiliate's rank
$service->assignRank($affiliate, $rank);
```

## ProgramService

Manages affiliate programs and memberships.

```php
use AIArmada\Affiliates\Services\ProgramService;

$service = app(ProgramService::class);
```

### Methods

```php
// Join a program (creates a membership; honors requires_approval)
$membership = $service->joinProgram($affiliate, $program);

// Check eligibility (open, not already a member, rules pass)
$eligible = $program->canJoin($affiliate);

// Get available programs
$programs = $service->getAvailablePrograms();

// Upgrade an affiliate to a new tier within a program
$service->upgradeTier($affiliate, $program, $newTier);
```

## DailyAggregationService

Aggregates daily statistics for reporting. Stats are stored one row per affiliate, date, and currency; money never blends across currencies.

```php
use AIArmada\Affiliates\Services\DailyAggregationService;
use Carbon\CarbonImmutable;

$service = app(DailyAggregationService::class);

// Aggregate every affiliate for one date (returns affiliate count).
$service->aggregate(CarbonImmutable::today());

// Aggregate one affiliate for one date (returns one stat per currency).
$stats = $service->aggregateForAffiliate($affiliate, CarbonImmutable::today());

// Backfill a date range (returns total affiliate-days processed).
$service->backfill($startDate, $endDate);

// Summarize an affiliate over a period.
$stats = $service->getAggregatedStats($affiliate, $startDate, $endDate);
```

Click-level counts live on the affiliate-currency row only, so period sums never double-count; conversion counts and money are per currency leg. `getAggregatedStats()` converts mixed money to the affiliate currency and nulls totals when an exchange rate is missing — `by_currency` always holds the exact legs:

```php
$stats['revenue_cents']; // int|null
$stats['currency'];      // denomination of the totals
$stats['converted'];     // true when FX math was applied
$stats['by_currency'];   // ['USD' => ['conversions' => ..., ...], ...]
```

## AffiliateReportService

Generates reports and analytics. Money legs group by conversion currency; totals convert to `affiliates.currency.default` only when legs span currencies, and null when an exchange rate is missing — `by_currency` always holds the exact legs.

```php
use AIArmada\Affiliates\Services\AffiliateReportService;
use Carbon\CarbonImmutable;

$service = app(AffiliateReportService::class);
$from = CarbonImmutable::now()->subMonth();
$to = CarbonImmutable::now();

$summary = $service->getSummary($from, $to);
// ['attributions', 'conversions', 'revenue_minor', 'commission_minor',
//  'currency', 'converted', 'by_currency']

$top = $service->getTopAffiliates($from, $to, 10);
// One row per affiliate + currency, ranked on converted commission.

$trend = $service->getConversionTrend($from, $to);
// One row per date + currency.

$sources = $service->getTrafficSources($from, $to);
// ['sources' => [...], 'campaigns' => [...]] (counts only)

$subjects = $service->getTopSubjects($from, $to, 10);
$one = $service->affiliateSummary($affiliateId);
// Totals carry 'currency', 'converted', and 'by_currency' like getSummary().
```

## CohortAnalyzer

Analyzes affiliate cohorts for trends.

```php
use AIArmada\Affiliates\Services\CohortAnalyzer;

$analyzer = app(CohortAnalyzer::class);

// Monthly cohort table for a period
$monthly = $analyzer->analyzeMonthly($from, $to);

// Retention curve across cohorts
$curve = $analyzer->calculateRetentionCurve($from, $to);

// Lifetime value by cohort
$ltv = $analyzer->calculateLtv($from, $to);

// Compare cohorts side by side
$comparison = $analyzer->compareCohorts($from, $to);

// Break cohorts down by acquisition source
$bySource = $analyzer->analyzeBySource($from, $to);
```

## PerformanceBonusService

Calculates performance bonuses, awards them as approved bonus conversions, and exposes leaderboards based on approved revenue.

```php
use AIArmada\Affiliates\Services\PerformanceBonusService;

$service = app(PerformanceBonusService::class);

// Calculate all configured bonuses for a period
$bonuses = $service->calculateBonuses($from, $to);

// Award the calculated bonuses as approved conversions
$awarded = $service->awardBonuses($bonuses);

// Get the owner-scoped approved-revenue leaderboard
$leaderboard = $service->getLeaderboard($from, $to, 10);
```

The service uses approved conversions and treats `value_minor` as the revenue field.

## AttributionModel

Implements attribution logic (first-touch, last-touch, linear).

```php
use AIArmada\Affiliates\Services\AttributionModel;

$model = app(AttributionModel::class);

// Resolve the configured strategy (first_touch, last_touch, linear)
$strategy = $model->resolve(); // defaults to affiliates.tracking.attribution_model

// Distribute credit across touchpoints with the configured strategy
$distribution = $model->distribute($touches);
```

## Merchant Seam Contracts

The network talks to merchants only through merchant-vocabulary seams in
`Contracts/` — the engine never names the external system beyond an opaque
source key:

- `MerchantLedger` — post externally-attributed conversions into merchant
  books. Idempotent on (source, source ref); `postingsForExternalReference`
  feeds the dual-reporting collision report (the same sale posted by two
  origins means two systems paid it).
- `MerchantIdentity` — resolve merchant affiliates by id or verified email
  without assuming the network's user model.
- `MerchantCatalog` — read-only program snapshots for catalog sync; no
  commission or payout rows are ever written through it.

`aiarmada/affiliate-network` binds its adapters (`AffiliatesLedgerPoster`,
`AffiliatesIdentityReader`, `AffiliatesCatalogReader`) onto these contracts
at boot when the engine is installed.
