---
title: Fraud Detection
---

# Fraud Detection

The package includes a comprehensive fraud detection system to protect against click fraud, conversion manipulation, and other abuse patterns.

## Overview

Fraud detection operates at multiple levels:

1. **Velocity Checks** - Rate limiting for clicks and conversions
2. **Anomaly Detection** - Unusual patterns in behavior
3. **Fingerprinting** - Duplicate detection across sessions
4. **Geographic Analysis** - Location-based fraud signals
5. **Conversion Time Analysis** - Suspiciously fast conversions

## Configuration

```php
// config/affiliates.php
'fraud' => [
    'enabled' => env('AFFILIATES_FRAUD_ENABLED', true),
    'blocking_threshold' => env('AFFILIATES_FRAUD_BLOCK_THRESHOLD', 100),

    'velocity' => [
        'enabled' => env('AFFILIATES_FRAUD_VELOCITY_ENABLED', true),
        'max_clicks_per_hour' => env('AFFILIATES_FRAUD_MAX_CLICKS_HOUR', 100),
        'max_conversions_per_day' => env('AFFILIATES_FRAUD_MAX_CONVERSIONS_DAY', 50),
    ],

    'anomaly' => [
        'geo' => [
            'enabled' => env('AFFILIATES_FRAUD_GEO_ENABLED', false),
        ],
        'conversion_time' => [
            'min_seconds' => env('AFFILIATES_FRAUD_MIN_CONVERSION_SECONDS', 5),
        ],
    ],
],
```

## Using FraudDetectionService

```php
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Services\FraudDetectionService;

$service = app(FraudDetectionService::class);
```

Both entrypoints return the same array shape:

```php
['allowed' => bool, 'score' => int, 'signals' => AffiliateFraudSignal[]]
```

`allowed` is `false` once the summed `risk_points` reach
`affiliates.fraud.blocking_threshold`. Rules are registered under the
`affiliates.fraud_rule` tag, so you can add your own.

### Analyzing Clicks

```php
$result = $service->analyzeClick($affiliate, request());

foreach ($result['signals'] as $signal) {
    // Each signal is a persisted AffiliateFraudSignal model
    echo $signal->rule_code;    // e.g. 'CLICK_VELOCITY'
    echo $signal->severity;     // FraudSeverity enum
    echo $signal->risk_points;  // points this rule contributed
}
```

### Analyzing Conversions

```php
// Check conversion for suspicious patterns
$result = $service->analyzeConversion($conversion);
```

### Getting the Risk Profile

```php
// Rolling 30-day fraud profile for an affiliate
$profile = $service->getRiskProfile($affiliate);
// ['total_score', 'severity', 'signal_count', 'by_rule', 'pending_review', 'confirmed']

if ($profile['severity'] === FraudSeverity::Critical) {
    // Consider pausing or disabling this affiliate in your application workflow
}
```

## Fraud Rules

Six rules ship in `AIArmada\Affiliates\Rules`, identified by `rule_code`:

| `rule_code` | Rule | Risk points |
|-------------|------|-------------|
| `CLICK_VELOCITY` | `ClickVelocityRule` — too many clicks in the hour | 30 |
| `CONVERSION_VELOCITY` | `ConversionVelocityRule` — too many conversions in the day | 35 |
| `GEO_ANOMALY` | `GeoAnomalyRule` — geography looks anomalous | 40 |
| `FAST_CONVERSION` | `FastConversionRule` — conversion too soon after attribution | 45 |
| `FINGERPRINT_REPEAT` | `FingerprintRepeatRule` — fingerprint seen too many times | 25 |
| `SELF_REFERRAL` | `SelfReferralRule` — affiliate crediting themselves | 100 |

## Fraud Severity Levels

```php
use AIArmada\Affiliates\Enums\FraudSeverity;

FraudSeverity::Low;       // riskThreshold() 20 — minor concern
FraudSeverity::Medium;    // riskThreshold() 50 — investigate
FraudSeverity::High;      // riskThreshold() 80 — likely fraud
FraudSeverity::Critical;  // riskThreshold() 100 — immediate action needed
```

`FraudSeverity::fromScore($score)` bands a cumulative score: `>= 100` critical,
`>= 80` high, `>= 50` medium, otherwise low.

## Fraud Signal Statuses

```php
use AIArmada\Affiliates\Enums\FraudSignalStatus;

FraudSignalStatus::Detected;  // Newly detected and awaiting review
FraudSignalStatus::Reviewed;  // Reviewed by admin
FraudSignalStatus::Dismissed; // False positive
FraudSignalStatus::Confirmed; // Fraud confirmed
```

Reviewed and Dismissed both clear the signal: gates that count unresolved
fraud (such as open-registration auto-approval) only treat Detected and
Confirmed as blocking.

## Recording Signals Manually

There is no `FraudDetectionService::recordSignal()`. Persist the row directly —
the `FraudSignalDetected` event is dispatched by the detection service, not by
the model, so fire it yourself if listeners must run.

```php
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;

$signal = AffiliateFraudSignal::create([
    'affiliate_id' => $affiliate->id,
    'rule_code' => 'CUSTOM_PATTERN',
    'description' => 'Unusual conversion pattern detected',
    'severity' => FraudSeverity::High,
    'status' => FraudSignalStatus::Detected,
    'risk_points' => 50,
    'detected_at' => now(),
    'evidence' => [
        'conversions_today' => 47,
        'average_daily' => 5,
    ],
]);
```

## Fingerprint Detection

Enable fingerprint-based duplicate detection:

```php
'tracking' => [
    'fingerprint' => [
        'enabled' => env('AFFILIATES_FINGERPRINT_ENABLED', true),
        'block_duplicates' => env('AFFILIATES_FINGERPRINT_BLOCK_DUPLICATES', false),
        'threshold' => env('AFFILIATES_FINGERPRINT_THRESHOLD', 5),
    ],
],
```

The system generates fingerprints from:
- User agent
- IP address (hashed via `AIArmada\Affiliates\Support\IpHasher`)

## IP Rate Limiting

```php
'tracking' => [
    'ip_rate_limit' => [
        'enabled' => env('AFFILIATES_IP_RATE_LIMIT_ENABLED', false),
        'max' => env('AFFILIATES_IP_RATE_LIMIT_MAX', 20),
        'decay_minutes' => env('AFFILIATES_IP_RATE_LIMIT_DECAY', 30),
    ],
],
```

When enabled, the same IP can only generate a limited number of attributions within the decay window.

## Blocking Threshold

When an affiliate's cumulative fraud score reaches the blocking threshold, automatic actions can be triggered:

```php
'fraud' => [
    'blocking_threshold' => 100,
],
```

Respond to individual detections:

```php
use AIArmada\Affiliates\Events\FraudSignalDetected;

// In your EventServiceProvider
protected $listen = [
    FraudSignalDetected::class => [
        SuspendAffiliate::class,
        NotifyFraudTeam::class,
    ],
];
```

The event carries the `AffiliateFraudSignal` (reach the affiliate and severity through it). It fires from the model on every create — detection rules, manual analyst flags, and host integrations — and implements `ShouldDispatchAfterCommit`, so listeners run after the signal transaction commits.

## Fraud Review in Filament

The `filament-affiliates` package includes:

1. **FraudReviewPage** - Dedicated page for reviewing fraud signals
2. **FraudAlertWidget** - Dashboard widget showing recent alerts
3. **AffiliateFraudSignalResource** - CRUD for fraud signals

### Reviewing Signals

```php
// Mark as reviewed
$signal->update([
    'status' => FraudSignalStatus::Reviewed,
    'reviewed_at' => now(),
    'reviewed_by' => auth()->id(),
    'description' => 'Investigated - appears legitimate',
]);

// Confirm fraud
$signal->update([
    'status' => FraudSignalStatus::Confirmed,
    'confirmed_at' => now(),
]);

// Optionally reject the linked conversion
$signal->conversion?->update(['status' => RejectedConversion::class]);
```

> **warning:**
> `AffiliateFraudSignal` has no `notes` column. Free-text goes in `description`
> (set at creation) or `evidence`; a `notes` key would be dropped silently.

## Self-Referral Protection

Prevent affiliates from crediting their own purchases:

```php
'tracking' => [
    'block_self_referral' => true,
],
```

When enabled, the system checks if the current authenticated user/owner matches the affiliate's owner and blocks the attribution.

## Conversion Time Analysis

Flag conversions that happen too quickly after attribution:

```php
'fraud' => [
    'anomaly' => [
        'conversion_time' => [
            'min_seconds' => 5, // Conversions under 5 seconds are flagged
        ],
    ],
],
```

## Best Practices

1. **Start with monitoring** - Enable fraud detection but don't auto-block initially
2. **Review signals regularly** - Set up daily review of pending signals
3. **Tune thresholds** - Adjust based on your traffic patterns
4. **Whitelist trusted affiliates** - Reduce false positives for known partners
5. **Combine with manual review** - Automated detection + human judgment
6. **Track refund rates** - High refund rates often indicate fraud
7. **Monitor geographic patterns** - Unexpected locations may indicate VPN abuse
