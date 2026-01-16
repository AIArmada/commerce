---
title: Services Reference
---

# Services Reference

The package includes 16 specialized services for affiliate management. All services are registered as singletons and can be resolved from the container.

## AffiliateService

The primary service for affiliate operations.

```php
use AIArmada\Affiliates\Services\AffiliateService;

$service = app(AffiliateService::class);
```

### Methods

```php
// Query affiliates (owner-scoped)
$query = $service->query();

// Find by code
$affiliate = $service->findByCode('PARTNER42');

// Find without owner scope (for cross-tenant lookups)
$affiliate = $service->findByCodeWithoutOwnerScope('PARTNER42');

// Find by default voucher code
$affiliate = $service->findByDefaultVoucherCode('SUMMER20');

// Attach to cart
$attribution = $service->attachToCartByCode('PARTNER42', $cart, [
    'source' => 'instagram',
]);

// Attach affiliate directly
$attribution = $service->attachAffiliate($affiliate, $cart, $context);

// Track visit by code (cookie-based)
$attribution = $service->trackVisitByCode('PARTNER42', $context, $cookieValue);

// Touch existing cookie attribution
$service->touchCookieAttribution($cookieValue, $context);

// Record conversion
$conversion = $service->recordConversion($affiliate, [
    'order_reference' => 'ORD-123',
    'total_minor' => 15000,
]);

// Record conversion from cart
$conversion = $service->recordConversionFromCart($cart, [
    'order_reference' => 'ORD-123',
]);
```

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

Manages commission maturity periods before payouts are allowed.

```php
use AIArmada\Affiliates\Services\CommissionMaturityService;

$service = app(CommissionMaturityService::class);

// Process matured commissions
$processed = $service->processMaturedConversions();

// Check if conversion is mature
$isMature = $service->isMature($conversion);

// Get maturity date
$maturesAt = $service->getMaturityDate($conversion);
```

## AffiliatePayoutService

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

Calculates and awards performance bonuses.

```php
use AIArmada\Affiliates\Services\PerformanceBonusService;

$service = app(PerformanceBonusService::class);

// Calculate top performer bonuses
$bonuses = $service->calculateTopPerformerBonuses($period);

// Calculate recruitment bonuses
$bonuses = $service->calculateRecruitmentBonuses($period);

// Award bonus to affiliate
$service->awardBonus($affiliate, $amount, $reason);
```

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
