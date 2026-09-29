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

### Analyzing Clicks

```php
// Analyze a click for fraud signals; returns ['allowed', 'score', 'signals']
$result = $service->analyzeClick($affiliate, $request);

foreach ($result['signals'] as $signal) {
    // Each signal is an AffiliateFraudSignal model
    echo $signal->rule_code;   // e.g., 'velocity_exceeded'
    echo $signal->severity;    // Low, Medium, High, Critical
    echo $signal->risk_points; // points contributed
}
```

### Analyzing Conversions

```php
// Check conversion for suspicious patterns (persists signals)
$result = $service->analyzeConversion($affiliate, $conversion);
```

### Getting the Risk Profile

```php
// Aggregated risk profile for affiliate
$profile = $service->getRiskProfile($affiliate);
// ['total_score', 'severity', 'signal_count', 'by_rule', 'pending_review', 'confirmed']

if (($profile['severity'] ?? null) === FraudSeverity::Critical) {
    // Consider pausing or disabling this affiliate in your application workflow
}
```

Velocity, self-referral, and the other built-in checks run inside
`analyzeClick()`/`analyzeConversion()`; individual rules live in `Rules/`.

## Fraud Signal Types

| Type | Description |
|------|-------------|
| `velocity_exceeded` | Too many clicks/conversions in time window |
| `ip_duplicate` | Same IP generating multiple attributions |
| `fingerprint_duplicate` | Browser fingerprint seen too many times |
| `geo_mismatch` | Geographic location doesn't match expected |
| `fast_conversion` | Conversion happened suspiciously fast |
| `self_referral` | Affiliate trying to credit themselves |
| `bot_detected` | User agent indicates bot traffic |
| `refund_pattern` | High refund rate on conversions |

## Fraud Severity Levels

```php
use AIArmada\Affiliates\Enums\FraudSeverity;

FraudSeverity::Low;       // Threshold: 20 - Minor concern
FraudSeverity::Medium;    // Threshold: 50 - Investigate
FraudSeverity::High;      // Threshold: 80 - Likely fraud
FraudSeverity::Critical;  // Threshold: 100 - Immediate action needed
```

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

```php
use AIArmada\Affiliates\Models\AffiliateFraudSignal;

$signal = AffiliateFraudSignal::create([
    'affiliate_id' => $affiliate->id,
    'rule_code' => 'suspicious_pattern',
    'severity' => FraudSeverity::High,
    'risk_points' => 50,
    'description' => 'Unusual conversion pattern detected',
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
- IP address
- Accept-Language header
- Screen resolution (if available)
- Timezone

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

Implement automatic suspension:

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
    'reviewed_at' => now(),
]);

// Optionally reject the linked conversion
$signal->conversion?->update(['status' => RejectedConversion::class]);
```

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
