# AI-Powered Optimization

> **Document:** 07-ai-optimization.md  
> **Status:** Vision  
> **Priority:** P3 (Long-term)

---

## Overview

AI-powered optimization transforms vouchers from static discounts into **intelligent, self-optimizing promotional instruments** that maximize business outcomes while minimizing margin erosion.

---

## Vision: Intelligent Promotions Engine

### 7.1 AI Capabilities

```
┌─────────────────────────────────────────────────────────────────────┐
│                    AI OPTIMIZATION DOMAINS                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  PREDICTIVE                                                          │
│  ├── Conversion Probability - Will this voucher close the sale?     │
│  ├── Abandonment Risk - Is this cart at risk of being abandoned?    │
│  ├── Customer Lifetime Value - What's this customer worth?          │
│  └── Price Sensitivity - How discount-sensitive is this customer?   │
│                                                                      │
│  PRESCRIPTIVE                                                        │
│  ├── Optimal Discount - What's the minimum discount to convert?     │
│  ├── Best Voucher Match - Which voucher works best for this user?   │
│  ├── Timing Optimization - When to show/send the voucher?           │
│  └── Channel Selection - Email, push, or on-site?                   │
│                                                                      │
│  PROTECTIVE                                                          │
│  ├── Fraud Detection - Is this usage pattern suspicious?            │
│  ├── Abuse Prevention - Is this user gaming the system?             │
│  ├── Margin Protection - Will this voucher hurt profitability?      │
│  └── Cannibalization Alert - Would they buy without discount?       │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Predictive Models

### 7.2 Conversion Prediction

```php
interface ConversionPredictorInterface
{
    public function predictConversion(
        Cart $cart,
        ?VoucherCondition $voucher = null,
        ?Model $user = null
    ): ConversionPrediction;
}

class ConversionPrediction
{
    public function __construct(
        public readonly float $probability,      // 0.0 to 1.0
        public readonly float $confidence,       // Model confidence
        public readonly array $factors,          // Contributing factors
        public readonly ?float $withVoucher,     // Probability with voucher
        public readonly ?float $withoutVoucher,  // Probability without
        public readonly float $incrementalLift,  // Voucher's incremental value
    ) {}
    
    public function isHighProbability(): bool
    {
        return $this->probability >= 0.7;
    }
    
    public function voucherWorthIt(): bool
    {
        // Voucher only worth it if it provides significant lift
        return $this->incrementalLift >= 0.15;
    }
}
```

### 7.3 Abandonment Risk Scoring

```php
interface AbandonmentPredictorInterface
{
    public function predictAbandonment(
        Cart $cart,
        ?Model $user = null
    ): AbandonmentRisk;
}

class AbandonmentRisk
{
    public function __construct(
        public readonly float $riskScore,        // 0.0 to 1.0
        public readonly string $riskLevel,       // low, medium, high, critical
        public readonly array $riskFactors,      // Why at risk
        public readonly ?Carbon $predictedTime,  // When likely to abandon
        public readonly ?string $suggestedAction, // Recommended intervention
    ) {}
}
```

### Feature Engineering

```php
class CartFeatureExtractor
{
    public function extract(Cart $cart, ?Model $user = null): array
    {
        return [
            // Cart features
            'cart_value' => $cart->getRawSubtotalWithoutConditions(),
            'item_count' => $cart->countItems(),
            'unique_items' => $cart->getItems()->count(),
            'has_high_margin_items' => $this->hasHighMarginItems($cart),
            'avg_item_price' => $this->averageItemPrice($cart),
            'cart_age_minutes' => $cart->created_at->diffInMinutes(now()),
            'modifications_count' => $cart->metadata['modifications'] ?? 0,
            
            // User features (if available)
            'is_authenticated' => $user !== null,
            'user_order_count' => $user?->orders_count ?? 0,
            'user_lifetime_value' => $user?->lifetime_value ?? 0,
            'user_avg_order_value' => $user?->average_order_value ?? 0,
            'days_since_last_order' => $user?->last_order_at?->diffInDays(now()),
            'user_segment' => $user?->segment ?? 'unknown',
            'voucher_usage_rate' => $this->voucherUsageRate($user),
            
            // Session features
            'session_duration' => session()->get('duration', 0),
            'pages_viewed' => session()->get('pages_viewed', 0),
            'device_type' => request()->header('X-Device-Type', 'unknown'),
            'is_returning_visitor' => session()->has('returning'),
            
            // Time features
            'hour_of_day' => now()->hour,
            'day_of_week' => now()->dayOfWeek,
            'is_weekend' => now()->isWeekend(),
        ];
    }
}
```

---

## Prescriptive Engine

### 7.4 Optimal Discount Calculator

```php
interface DiscountOptimizerInterface
{
    public function findOptimalDiscount(
        Cart $cart,
        ?Model $user = null,
        array $constraints = []
    ): DiscountRecommendation;
}

class DiscountRecommendation
{
    public function __construct(
        public readonly int $recommendedDiscountCents,
        public readonly string $recommendedType,     // percentage, fixed
        public readonly float $expectedConversionLift,
        public readonly float $expectedMarginImpact,
        public readonly float $expectedROI,
        public readonly array $alternatives,
    ) {}
}

class MLDiscountOptimizer implements DiscountOptimizerInterface
{
    public function findOptimalDiscount(
        Cart $cart,
        ?Model $user = null,
        array $constraints = []
    ): DiscountRecommendation {
        $features = $this->featureExtractor->extract($cart, $user);
        
        // Model predicts conversion probability at different discount levels
        $discountLevels = [0, 5, 10, 15, 20, 25, 30]; // percentages
        $predictions = [];
        
        foreach ($discountLevels as $discount) {
            $features['discount_percentage'] = $discount;
            $predictions[$discount] = [
                'conversion_prob' => $this->model->predict($features),
                'margin' => $this->calculateMargin($cart, $discount),
                'expected_value' => $this->calculateExpectedValue($cart, $discount, $predictions[$discount]['conversion_prob']),
            ];
        }
        
        // Find discount level that maximizes expected value
        $optimal = collect($predictions)
            ->sortByDesc('expected_value')
            ->first();
        
        return new DiscountRecommendation(
            recommendedDiscountCents: $this->percentToCents($cart, $optimal['discount']),
            recommendedType: 'percentage',
            expectedConversionLift: $optimal['conversion_prob'] - $predictions[0]['conversion_prob'],
            expectedMarginImpact: $predictions[0]['margin'] - $optimal['margin'],
            expectedROI: $optimal['expected_value'] / $this->percentToCents($cart, $optimal['discount']),
            alternatives: $this->getAlternatives($predictions)
        );
    }
}
```

### 7.5 Voucher Matching

```php
interface VoucherMatcherInterface
{
    public function findBestVoucher(
        Cart $cart,
        Collection $availableVouchers,
        ?Model $user = null
    ): VoucherMatch;
}

class VoucherMatch
{
    public function __construct(
        public readonly ?Voucher $voucher,
        public readonly float $matchScore,
        public readonly array $matchReasons,
        public readonly array $alternatives,
    ) {}
}
```

---

## Fraud Detection

### 7.6 Fraud Detection Engine

```php
interface VoucherFraudDetectorInterface
{
    public function analyze(
        string $code,
        Cart $cart,
        ?Model $user = null
    ): FraudAnalysis;
}

class FraudAnalysis
{
    public function __construct(
        public readonly float $fraudScore,       // 0.0 to 1.0
        public readonly string $riskLevel,       // low, medium, high, block
        public readonly array $signals,          // Detected fraud signals
        public readonly bool $shouldBlock,       // Block this redemption?
        public readonly ?string $blockReason,
    ) {}
}
```

### Fraud Signals

```php
enum FraudSignal: string
{
    // Velocity signals
    case HighRedemptionVelocity = 'high_redemption_velocity';
    case MultipleAccountsAttempt = 'multiple_accounts_attempt';
    case RapidCodeGeneration = 'rapid_code_generation';
    
    // Pattern signals
    case UnusualTimePattern = 'unusual_time_pattern';
    case GeoAnomalyDetected = 'geo_anomaly_detected';
    case DeviceFingerprintMismatch = 'device_fingerprint_mismatch';
    
    // Behavioral signals
    case OnlyDiscountedPurchases = 'only_discounted_purchases';
    case HighRefundRate = 'high_refund_rate';
    case CartManipulation = 'cart_manipulation';
    
    // Code signals
    case CodeSharingDetected = 'code_sharing_detected';
    case LeakedCodeUsage = 'leaked_code_usage';
    case SequentialCodeAttempts = 'sequential_code_attempts';
}
```

### Fraud Detection Implementation

```php
class VoucherFraudDetector implements VoucherFraudDetectorInterface
{
    private array $detectors = [];
    
    public function analyze(
        string $code,
        Cart $cart,
        ?Model $user = null
    ): FraudAnalysis {
        $signals = [];
        $totalScore = 0;
        
        foreach ($this->detectors as $detector) {
            $result = $detector->detect($code, $cart, $user);
            
            if ($result->detected) {
                $signals[] = [
                    'signal' => $result->signal,
                    'severity' => $result->severity,
                    'details' => $result->details,
                ];
                $totalScore += $result->score;
            }
        }
        
        $normalizedScore = min(1.0, $totalScore / 100);
        
        return new FraudAnalysis(
            fraudScore: $normalizedScore,
            riskLevel: $this->scoreToRiskLevel($normalizedScore),
            signals: $signals,
            shouldBlock: $normalizedScore >= 0.8,
            blockReason: $normalizedScore >= 0.8 
                ? $this->summarizeBlockReason($signals) 
                : null
        );
    }
}

class VelocityFraudDetector implements FraudDetectorInterface
{
    public function detect(string $code, Cart $cart, ?Model $user): FraudDetectorResult
    {
        $voucher = Voucher::findByCode($code);
        
        // Check redemption velocity in last hour
        $recentRedemptions = VoucherUsage::where('voucher_id', $voucher->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        
        $threshold = config('vouchers.fraud.velocity_threshold', 100);
        
        if ($recentRedemptions > $threshold) {
            return new FraudDetectorResult(
                detected: true,
                signal: FraudSignal::HighRedemptionVelocity,
                severity: 'high',
                score: 40,
                details: "Voucher redeemed {$recentRedemptions} times in last hour"
            );
        }
        
        return FraudDetectorResult::clean();
    }
}
```

---

## Integration Points

### Cart Checkout Hook

```php
// In CheckoutPipeline
class AIOptimizationStage implements CheckoutStageInterface
{
    public function process(CheckoutContext $context): CheckoutContext
    {
        $cart = $context->cart;
        $user = $context->user;
        
        // Fraud check on applied vouchers
        foreach ($cart->getAppliedVouchers() as $voucher) {
            $analysis = $this->fraudDetector->analyze(
                $voucher->getVoucherCode(),
                $cart,
                $user
            );
            
            if ($analysis->shouldBlock) {
                $cart->removeVoucher($voucher->getVoucherCode());
                $context->addWarning("Voucher {$voucher->getVoucherCode()} removed: {$analysis->blockReason}");
            }
        }
        
        return $context;
    }
}
```

### Abandonment Recovery

```php
// Scheduled job
class ProcessAbandonedCarts
{
    public function handle(): void
    {
        Cart::abandoned()
            ->whereNull('recovery_voucher_sent_at')
            ->chunk(100, function ($carts) {
                foreach ($carts as $cart) {
                    $risk = $this->abandonmentPredictor->predictAbandonment($cart);
                    
                    if ($risk->riskLevel === 'high') {
                        $recommendation = $this->discountOptimizer->findOptimalDiscount($cart);
                        
                        $this->sendRecoveryEmail($cart, $recommendation);
                        
                        $cart->update(['recovery_voucher_sent_at' => now()]);
                    }
                }
            });
    }
}
```

---

## Model Training Pipeline

### Training Data Collection

```php
class VoucherMLDataCollector
{
    public function collectTrainingData(Carbon $from, Carbon $to): Collection
    {
        return DB::table('voucher_applications')
            ->join('carts', 'carts.id', '=', 'voucher_applications.cart_id')
            ->leftJoin('orders', 'orders.cart_id', '=', 'carts.id')
            ->whereBetween('voucher_applications.created_at', [$from, $to])
            ->select([
                // Features
                'carts.total_cents',
                'carts.item_count',
                'voucher_applications.discount_cents',
                'voucher_applications.discount_percentage',
                // ... other features
                
                // Target
                DB::raw('CASE WHEN orders.id IS NOT NULL THEN 1 ELSE 0 END as converted'),
            ])
            ->get();
    }
}
```

---

## Implementation Phases

### Phase 1: Data Foundation
- [ ] Feature extraction pipeline
- [ ] Training data collection
- [ ] Analytics event infrastructure

### Phase 2: Fraud Detection
- [ ] Velocity detection
- [ ] Pattern detection
- [ ] Real-time blocking

### Phase 3: Predictive Models
- [ ] Abandonment prediction
- [ ] Conversion prediction
- [ ] Model training pipeline

### Phase 4: Prescriptive Engine
- [ ] Discount optimization
- [ ] Voucher matching
- [ ] Timing optimization

### Phase 5: Production Deployment
- [ ] Model serving infrastructure
- [ ] A/B testing integration
- [ ] Monitoring & alerting

---

## Navigation

**Previous:** [06-stacking-policies.md](06-stacking-policies.md)  
**Next:** [08-database-evolution.md](08-database-evolution.md)
