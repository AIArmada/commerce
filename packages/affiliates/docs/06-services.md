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
| `CapturePublicAffiliateReferral::run($request)` | Capture public referral |
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
| `AllocateUplineCommissions::run($conversions, $config)` | Distribute upline commissions |
| `MatureConversion::run($conversion)` | Mature a single conversion |
| `ProcessConversionMaturity::run()` | Process batch maturity |
| `RecordAffiliateConversion::run($cart, $payload)` | Record a conversion |

### Payouts Actions (`Actions/Payouts/`)

| Action | Purpose |
|--------|---------|
| `CreatePayout::run($conversionIds, $attributes)` | Create a payout batch |
| `UpdatePayoutStatus::run($payout, $status, $notes, $metadata)` | Update payout status |

---

## Legacy Services (Deprecated)

The package also includes specialized services. These are kept as compatibility adapters. **Prefer Actions for new code.**

## AffiliateService

> Deprecated: Prefer individual Actions (`CreateTrackingLink`, `AttachAffiliateToCart`, `TrackAffiliateVisit`, `TouchAffiliateAttribution`, `RecordAffiliateConversion`, etc.). `AffiliateService` is kept as a compatibility facade.

The primary service for affiliate operations.

## CommissionCalculator

Calculates commissions based on affiliate settings, rules, and tiers.

```php
use AIArmada\Affiliates\Services\CommissionCalculator;

$calculator = app(CommissionCalculator::class);
```

### Methods

```php
// Calculate commission for an order
$commission = $calculator->calculate(
    affiliate: $affiliate,
    orderTotal: 15000,
    orderSubtotal: 14000,
    context: ['product_category' => 'electronics'],
);

// Calculate with specific program
$commission = $calculator->calculateForProgram(
    affiliate: $affiliate,
    program: $program,
    orderTotal: 15000,
);

// Get applicable volume tier bonus
$bonus = $calculator->getVolumeTierBonus($affiliate, $periodVolume);
```

## CommissionMaturityService

> Deprecated: Prefer `Actions/Conversions/ProcessConversionMaturity` and `Actions/Conversions/MatureConversion`.
> `CommissionMaturityService` is kept as a compatibility adapter until all downstream callers migrate.

Manages the maturity window that promotes qualified conversions into approved, payout-eligible conversions.

```php
use AIArmada\Affiliates\Services\CommissionMaturityService;

$service = app(CommissionMaturityService::class);
```

Only conversions in the `Qualified` state are processed by the maturity service.

## AffiliatePayoutService

> Deprecated: Prefer `Actions/Payouts/*` actions. `AffiliatePayoutService` is kept as a compatibility adapter until all downstream callers migrate.

Handles payout batch creation and processing.

```php
use AIArmada\Affiliates\Services\AffiliatePayoutService;

$service = app(AffiliatePayoutService::class);
```

### Methods

```php
// Create payout batch for affiliate
$payout = $service->createPayout($affiliate, [
    'method' => PayoutMethodType::PayPal,
]);

// Process pending payouts
$service->processPendingPayouts();

// Mark payout as completed
$service->completePayout($payout, [
    'transaction_id' => 'TXN-123',
]);

// Cancel payout
$service->cancelPayout($payout, 'Reason for cancellation');

// Get eligible conversions for payout
$conversions = $service->getPayableConversions($affiliate);
```

## PayoutReconciliationService

Reconciles payouts with external payment providers.

```php
use AIArmada\Affiliates\Services\PayoutReconciliationService;

$service = app(PayoutReconciliationService::class);

// Reconcile with provider
$result = $service->reconcile($payout, $providerData);

// Get unreconciled payouts
$pending = $service->getUnreconciledPayouts();
```

## FraudDetectionService

Real-time fraud detection and scoring.

```php
use AIArmada\Affiliates\Services\FraudDetectionService;

$service = app(FraudDetectionService::class);
```

### Methods

```php
// Analyze attribution for fraud
$signals = $service->analyzeAttribution($attribution);

// Analyze conversion for fraud
$signals = $service->analyzeConversion($conversion);

// Get fraud score for affiliate
$score = $service->getFraudScore($affiliate);

// Check velocity limits
$exceeded = $service->checkVelocityLimits($affiliate, 'clicks');

// Record fraud signal
$signal = $service->recordSignal($affiliate, [
    'type' => 'velocity_exceeded',
    'severity' => FraudSeverity::High,
    'details' => ['clicks_per_hour' => 150],
]);
```

## NetworkService

Manages affiliate network hierarchies (MLM).

```php
use AIArmada\Affiliates\Services\NetworkService;

$service = app(NetworkService::class);
```

### Methods

```php
// Get upline affiliates
$uplines = $service->getUpline($affiliate, $maxDepth = 10);

// Get downline affiliates
$downlines = $service->getDownline($affiliate, $maxDepth = 10);

// Calculate multi-level commissions
$commissions = $service->calculateMultiLevelCommissions(
    $conversion,
    $levels = [0.10, 0.05, 0.02], // 10%, 5%, 2%
);

// Get network depth
$depth = $service->getNetworkDepth($affiliate);

// Set parent affiliate
$service->setParent($affiliate, $parentAffiliate);
```

## RankQualificationService

Manages affiliate rank progression.

```php
use AIArmada\Affiliates\Services\RankQualificationService;

$service = app(RankQualificationService::class);
```

### Methods

```php
// Check rank qualification
$qualified = $service->checkQualification($affiliate, $rank);

// Process rank upgrades
$upgraded = $service->processRankUpgrades();

// Get next rank for affiliate
$nextRank = $service->getNextRank($affiliate);

// Get qualification progress
$progress = $service->getQualificationProgress($affiliate, $rank);
```

## ProgramService

Manages affiliate programs and memberships.

```php
use AIArmada\Affiliates\Services\ProgramService;

$service = app(ProgramService::class);
```

### Methods

```php
// Enroll affiliate in program
$membership = $service->enroll($affiliate, $program);

// Check eligibility
$eligible = $service->checkEligibility($affiliate, $program);

// Get available programs for affiliate
$programs = $service->getAvailablePrograms($affiliate);

// Upgrade membership tier
$service->upgradeTier($membership, $newTier);
```

## AffiliateRegistrationService

> Deprecated: Prefer `Actions/Affiliates/RegisterAffiliate`. `AffiliateRegistrationService` is kept as a compatibility adapter until all downstream callers migrate.

Handles affiliate self-registration.

```php
use AIArmada\Affiliates\Services\AffiliateRegistrationService;

$service = app(AffiliateRegistrationService::class);
```

### Methods

```php
// Register new affiliate
$affiliate = $service->register([
    'name' => 'John Partner',
    'email' => 'john@partner.com',
    'website_url' => 'https://partner.com',
]);

// Approve pending affiliate
$service->approve($affiliate);

// Reject pending affiliate
$service->reject($affiliate, 'Reason for rejection');
```

## DailyAggregationService

Aggregates daily statistics for reporting.

```php
use AIArmada\Affiliates\Services\DailyAggregationService;

$service = app(DailyAggregationService::class);

// Aggregate stats for date
$service->aggregateForDate(today());

// Aggregate for date range
$service->aggregateForRange($startDate, $endDate);

// Get aggregated stats
$stats = $service->getStats($affiliate, $startDate, $endDate);
```

## AffiliateReportService

Generates reports and analytics.

```php
use AIArmada\Affiliates\Services\AffiliateReportService;

$service = app(AffiliateReportService::class);

// Generate performance report
$report = $service->generatePerformanceReport($affiliate, $period);

// Get leaderboard
$leaderboard = $service->getLeaderboard($period, $limit);

// Get conversion funnel
$funnel = $service->getConversionFunnel($affiliate, $period);
```

## CohortAnalyzer

Analyzes affiliate cohorts for trends.

```php
use AIArmada\Affiliates\Services\CohortAnalyzer;

$analyzer = app(CohortAnalyzer::class);

// Analyze cohort retention
$retention = $analyzer->analyzeRetention($cohort, $periods);

// Compare cohorts
$comparison = $analyzer->compare($cohortA, $cohortB);
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

The service uses approved conversions only and treats `value_minor` as the canonical revenue field, falling back to `total_minor` for legacy records.

## AttributionModel

Implements attribution logic (first-touch, last-touch, linear).

```php
use AIArmada\Affiliates\Services\AttributionModel;

$model = app(AttributionModel::class);

// Get credited affiliate for conversion
$affiliate = $model->getCreditedAffiliate($cart);

// Distribute credit (for linear attribution)
$distribution = $model->distributeCredit($touchpoints, $total);
```
