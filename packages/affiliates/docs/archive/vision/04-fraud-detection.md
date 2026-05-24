# Fraud Detection & Prevention

> **Document:** 04 of 11  
> **Package:** `aiarmada/affiliates`  
> **Status:** 🟡 50% Implemented  
> **Last Updated:** December 5, 2025

---

## Overview

Implement comprehensive **fraud detection and prevention** mechanisms to protect affiliate programs from fake conversions, click fraud, self-referrals, and other malicious activities.

---

## Current State ✅

### Implemented Features

1. **IP Rate Limiting**
   - `attributions.rate_limit.enabled` config
   - `attributions.rate_limit.max_per_minute` (default: 60)
   - Per-IP throttling on attribution endpoint
   - Returns 429 Too Many Requests when exceeded

2. **Fingerprint Blocking**
   - `conversions.fingerprint_blocking.enabled` config
   - `conversions.fingerprint_blocking.threshold` (default: 5)
   - Browser fingerprint tracking via AffiliateTouchpoint
   - Blocks conversions from flagged fingerprints

3. **Self-Referral Blocking**
   - `conversions.self_referral_blocking` config (default: true)
   - Checks if conversion user_id matches affiliate user_id
   - Prevents affiliates from earning on their own purchases

4. **Touchpoint Tracking**
   - AffiliateTouchpoint model captures: IP, user_agent, fingerprint, referrer
   - Full request metadata stored for audit
   - Configurable touchpoint retention

5. **Config Structure**
   ```php
   'attributions' => [
       'rate_limit' => [
           'enabled' => true,
           'max_per_minute' => 60,
       ],
   ],
   'conversions' => [
       'fingerprint_blocking' => [
           'enabled' => true,
           'threshold' => 5,
       ],
       'self_referral_blocking' => true,
   ],
   ```

### Limitations (To Be Addressed)

- No geo-anomaly detection (impossible travel)
- No composite fraud scoring
- No machine learning / pattern detection
- No velocity checks (rapid-fire conversions)
- No referrer domain validation
- No click-to-conversion time analysis
- No alerting or auto-suspension

---

## Vision Architecture

### Multi-Layer Detection

```
┌─────────────────────────────────────────────────────────────┐
│                  FRAUD DETECTION LAYERS                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Layer 1: REAL-TIME BLOCKING                                │
│  ├── IP Rate Limiting ✅                                    │
│  ├── Fingerprint Blocking ✅                                │
│  ├── Self-Referral Block ✅                                 │
│  ├── Referrer Domain Validation ⬜                          │
│  └── Geo-fencing ⬜                                         │
│                                                              │
│  Layer 2: VELOCITY & PATTERN DETECTION                      │
│  ├── Click velocity analysis ⬜                             │
│  ├── Conversion velocity analysis ⬜                        │
│  ├── Device cluster detection ⬜                            │
│  └── Time-of-day patterns ⬜                                │
│                                                              │
│  Layer 3: ANOMALY DETECTION                                 │
│  ├── Geo-anomaly (impossible travel) ⬜                     │
│  ├── Statistical outliers ⬜                                │
│  └── Behavioral fingerprinting ⬜                           │
│                                                              │
│  Layer 4: SCORING & DECISIONS                               │
│  ├── Composite fraud score ⬜                               │
│  ├── Risk thresholds ⬜                                     │
│  └── Auto-suspension rules ⬜                               │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Proposed Detection Rules

### Rule Categories

| Category | Rule | Risk Points | Status |
|----------|------|-------------|--------|
| **Rate** | High IP request rate | 10 | ✅ Implemented |
| **Rate** | Rapid-fire clicks (<1s apart) | 25 | ⬜ Todo |
| **Identity** | Self-referral attempt | Block | ✅ Implemented |
| **Identity** | Same fingerprint, different affiliates | 40 | ⬜ Todo |
| **Identity** | Repeated fingerprint (>threshold) | 30 | ✅ Implemented |
| **Geo** | VPN/Proxy detected | 20 | ⬜ Todo |
| **Geo** | Impossible travel (>500km/hr) | 50 | ⬜ Todo |
| **Geo** | Geo mismatch (user vs affiliate) | 15 | ⬜ Todo |
| **Timing** | Click-to-conversion <5 seconds | 35 | ⬜ Todo |
| **Timing** | Click-to-conversion >cookie lifetime | 20 | ⬜ Todo |
| **Referrer** | Invalid/spoofed referrer | 30 | ⬜ Todo |
| **Referrer** | Direct traffic only | 10 | ⬜ Todo |
| **Pattern** | Same products repeatedly | 15 | ⬜ Todo |
| **Pattern** | Always max cart value | 25 | ⬜ Todo |

---

## Proposed Models

### AffiliateFraudSignal

```php
/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $conversion_id (nullable)
 * @property string $touchpoint_id (nullable)
 * @property string $rule_code (e.g., 'SELF_REFERRAL', 'GEO_ANOMALY')
 * @property int $risk_points
 * @property string $severity (low, medium, high, critical)
 * @property string $description
 * @property array $evidence (IP, fingerprint, timestamps, etc.)
 * @property string $status (detected, reviewed, dismissed, confirmed)
 * @property Carbon $detected_at
 * @property Carbon $reviewed_at
 * @property string $reviewed_by
 */
class AffiliateFraudSignal extends Model
{
    use HasUuids;
    
    public function affiliate(): BelongsTo;
    public function conversion(): BelongsTo;
    public function touchpoint(): BelongsTo;
}
```

### AffiliateFraudRule

```php
/**
 * @property string $id
 * @property string $code (unique identifier)
 * @property string $name
 * @property string $description
 * @property string $category (rate, identity, geo, timing, referrer, pattern)
 * @property bool $is_enabled
 * @property bool $is_blocking (prevent conversion vs flag only)
 * @property int $risk_points
 * @property array $config (rule-specific thresholds)
 */
class AffiliateFraudRule extends Model
{
    use HasUuids;
    
    public function evaluate(FraudContext $context): ?AffiliateFraudSignal;
}
```

---

## Enhanced Configuration

```php
// config/affiliates.php
'fraud' => [
    'enabled' => true,
    
    // Layer 1: Real-time blocking
    'blocking' => [
        'ip_rate_limit' => [
            'enabled' => true,
            'max_per_minute' => 60,
            'action' => 'block', // block, flag, log
        ],
        'fingerprint' => [
            'enabled' => true,
            'threshold' => 5,
            'window_hours' => 24,
        ],
        'self_referral' => true,
        'vpn_proxy' => [
            'enabled' => false,
            'provider' => 'ipinfo', // ipinfo, maxmind
            'api_key' => env('FRAUD_VPN_API_KEY'),
        ],
        'geo_fence' => [
            'enabled' => false,
            'allowed_countries' => ['US', 'CA', 'GB', 'MY'],
        ],
    ],
    
    // Layer 2: Velocity detection
    'velocity' => [
        'click_interval_seconds' => 1, // Flag if clicks <1s apart
        'max_clicks_per_hour' => 100,
        'max_conversions_per_day' => 50,
    ],
    
    // Layer 3: Anomaly detection
    'anomaly' => [
        'geo' => [
            'enabled' => true,
            'max_speed_kmh' => 500, // Impossible travel threshold
        ],
        'conversion_time' => [
            'min_seconds' => 5, // Too fast
            'max_hours' => 720, // 30 days max
        ],
    ],
    
    // Layer 4: Scoring & actions
    'scoring' => [
        'enabled' => true,
        'thresholds' => [
            'low' => 20,       // 0-19 = clean
            'medium' => 50,    // 20-49 = low risk
            'high' => 80,      // 50-79 = medium risk
            'critical' => 100, // 80-99 = high risk, 100+ = critical
        ],
        'auto_actions' => [
            'low' => 'none',
            'medium' => 'flag',
            'high' => 'hold_payout',
            'critical' => 'suspend_affiliate',
        ],
    ],
    
    // Alerting
    'alerts' => [
        'enabled' => true,
        'channels' => ['mail', 'slack'],
        'recipients' => ['admin@example.com'],
        'threshold' => 'high', // Alert on high+ severity
    ],
],
```

---

## Services

### FraudDetectionService

```php
class FraudDetectionService
{
    public function __construct(
        private FraudRuleEngine $ruleEngine,
        private FraudScoreCalculator $scoreCalculator,
        private FraudAlertService $alertService,
    ) {}
    
    public function analyzeClick(
        Affiliate $affiliate,
        Request $request
    ): FraudAnalysisResult {
        $context = FraudContext::fromClick($affiliate, $request);
        
        $signals = $this->ruleEngine->evaluate($context);
        $score = $this->scoreCalculator->calculate($signals);
        
        if ($score->severity === 'critical') {
            $this->alertService->sendAlert($affiliate, $signals);
        }
        
        return new FraudAnalysisResult(
            allowed: $score->score < $this->blockThreshold,
            score: $score,
            signals: $signals,
            action: $this->determineAction($score),
        );
    }
    
    public function analyzeConversion(
        AffiliateConversion $conversion,
    ): FraudAnalysisResult;
    
    public function getAffiliateRiskProfile(
        Affiliate $affiliate
    ): RiskProfile;
}
```

### FraudRuleEngine

```php
class FraudRuleEngine
{
    /** @var array<FraudRuleInterface> */
    private array $rules = [];
    
    public function register(FraudRuleInterface $rule): void;
    
    public function evaluate(FraudContext $context): Collection
    {
        return collect($this->rules)
            ->filter(fn ($rule) => $rule->isEnabled())
            ->map(fn ($rule) => $rule->evaluate($context))
            ->filter()
            ->values();
    }
}
```

### Rule Interface

```php
interface FraudRuleInterface
{
    public function code(): string;
    public function category(): string;
    public function isEnabled(): bool;
    public function isBlocking(): bool;
    public function riskPoints(): int;
    public function evaluate(FraudContext $context): ?AffiliateFraudSignal;
}
```

---

## Built-in Rule Implementations

### GeoAnomalyRule

```php
class GeoAnomalyRule implements FraudRuleInterface
{
    public function code(): string => 'GEO_ANOMALY';
    
    public function evaluate(FraudContext $context): ?AffiliateFraudSignal
    {
        $lastTouchpoint = $context->affiliate->touchpoints()
            ->latest('visited_at')
            ->first();
            
        if (!$lastTouchpoint) {
            return null;
        }
        
        $distance = $this->calculateDistance(
            $lastTouchpoint->latitude,
            $lastTouchpoint->longitude,
            $context->latitude,
            $context->longitude,
        );
        
        $hours = $lastTouchpoint->visited_at->diffInHours(now());
        $speedKmh = $hours > 0 ? $distance / $hours : PHP_INT_MAX;
        
        if ($speedKmh > config('affiliates.fraud.anomaly.geo.max_speed_kmh')) {
            return new AffiliateFraudSignal([
                'rule_code' => $this->code(),
                'risk_points' => $this->riskPoints(),
                'severity' => 'high',
                'description' => "Impossible travel: {$speedKmh} km/h",
                'evidence' => [
                    'from' => [$lastTouchpoint->latitude, $lastTouchpoint->longitude],
                    'to' => [$context->latitude, $context->longitude],
                    'distance_km' => $distance,
                    'hours' => $hours,
                    'speed_kmh' => $speedKmh,
                ],
            ]);
        }
        
        return null;
    }
}
```

### ConversionVelocityRule

```php
class ConversionVelocityRule implements FraudRuleInterface
{
    public function code(): string => 'CONVERSION_VELOCITY';
    
    public function evaluate(FraudContext $context): ?AffiliateFraudSignal
    {
        $conversionsToday = $context->affiliate->conversions()
            ->whereDate('occurred_at', today())
            ->count();
            
        $maxDaily = config('affiliates.fraud.velocity.max_conversions_per_day');
        
        if ($conversionsToday >= $maxDaily) {
            return new AffiliateFraudSignal([
                'rule_code' => $this->code(),
                'risk_points' => 30,
                'severity' => 'medium',
                'description' => "Exceeded daily conversion limit: {$conversionsToday}/{$maxDaily}",
                'evidence' => [
                    'count' => $conversionsToday,
                    'limit' => $maxDaily,
                    'date' => today()->toDateString(),
                ],
            ]);
        }
        
        return null;
    }
}
```

---

## Database Schema

```php
// affiliate_fraud_signals table
Schema::create('affiliate_fraud_signals', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->foreignUuid('conversion_id')->nullable();
    $table->foreignUuid('touchpoint_id')->nullable();
    $table->string('rule_code', 50);
    $table->integer('risk_points');
    $table->string('severity', 20);
    $table->string('description');
    $table->json('evidence')->nullable();
    $table->string('status', 20)->default('detected');
    $table->timestamp('detected_at');
    $table->timestamp('reviewed_at')->nullable();
    $table->foreignUuid('reviewed_by')->nullable();
    $table->timestamps();
    
    $table->index(['affiliate_id', 'detected_at']);
    $table->index(['rule_code', 'severity']);
    $table->index('status');
});

// affiliate_fraud_rules table (for dynamic configuration)
Schema::create('affiliate_fraud_rules', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('code', 50)->unique();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('category', 30);
    $table->boolean('is_enabled')->default(true);
    $table->boolean('is_blocking')->default(false);
    $table->integer('risk_points');
    $table->json('config')->nullable();
    $table->timestamps();
});
```

---

## Events

| Event | Trigger | Payload |
|-------|---------|---------|
| `FraudSignalDetected` | New fraud signal created | signal, affiliate, context |
| `FraudScoreThresholdExceeded` | Score crosses threshold | affiliate, score, signals |
| `AffiliateSuspendedForFraud` | Auto-suspension triggered | affiliate, signals, score |
| `FraudSignalReviewed` | Admin reviews signal | signal, reviewer, decision |

---

## Filament Integration

### FraudSignalsResource

```php
class FraudSignalsResource extends Resource
{
    protected static ?string $model = AffiliateFraudSignal::class;
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate.name')->searchable(),
                TextColumn::make('rule_code')->badge(),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'low' => 'gray',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'critical' => 'danger',
                    }),
                TextColumn::make('risk_points'),
                TextColumn::make('status')->badge(),
                TextColumn::make('detected_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('severity'),
                SelectFilter::make('status'),
                SelectFilter::make('rule_code'),
            ])
            ->actions([
                Action::make('dismiss')->action(...),
                Action::make('confirm')->action(...),
            ]);
    }
}
```

### AffiliateResource Enhancement

```php
// Add fraud risk indicator to affiliate list
TextColumn::make('fraud_score')
    ->label('Risk')
    ->badge()
    ->color(fn ($record) => $record->getRiskLevel());

// Add fraud signals relation manager
FraudSignalsRelationManager::class
```

---

## Implementation Priority

| Phase | Deliverable | Effort | Status |
|-------|-------------|--------|--------|
| 1 | IP rate limiting | 0.5 days | ✅ Done |
| 2 | Fingerprint blocking | 1 day | ✅ Done |
| 3 | Self-referral blocking | 0.5 days | ✅ Done |
| 4 | AffiliateFraudSignal model | 1 day | ⬜ Todo |
| 5 | FraudRuleEngine + interface | 2 days | ⬜ Todo |
| 6 | Velocity rules | 1 day | ⬜ Todo |
| 7 | Geo-anomaly detection | 2 days | ⬜ Todo |
| 8 | Fraud scoring system | 2 days | ⬜ Todo |
| 9 | Auto-suspension logic | 1 day | ⬜ Todo |
| 10 | Filament fraud dashboard | 2 days | ⬜ Todo |
| 11 | Alert notifications | 1 day | ⬜ Todo |

**Remaining Effort:** ~2 weeks

---

## Navigation

**Previous:** [03-affiliate-programs.md](03-affiliate-programs.md)  
**Next:** [05-analytics-reporting.md](05-analytics-reporting.md)
