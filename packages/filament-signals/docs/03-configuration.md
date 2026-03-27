---
title: Configuration
---

# Configuration

Configuration is defined in `config/filament-signals.php`.

## Navigation

```php
'navigation_group' => 'Insights',
```

## Features

Each feature flag controls whether corresponding pages/resources/widgets are registered.

```php
'features' => [
    'dashboard' => true,
    'page_views' => true,
    'conversion_funnel' => true,
    'acquisition' => true,
    'journeys' => true,
    'retention' => true,
    'content_performance' => true,
    'live_activity' => true,
    'goals_report' => true,
    'devices_report' => true,
    'properties' => true,
    'goals' => true,
    'segments' => true,
    'saved_reports' => true,
    'alert_rules' => true,
    'alert_logs' => true,
    'widgets' => true,
    'trend_chart' => true,
    'pending_alerts_widget' => true,
],
```

`devices_report` controls registration of the Devices & Technology page.

Feature flags in this plugin only control whether the Filament UI is registered. Some data surfaces still depend on upstream Signals package settings such as `signals.features.ua_parsing.enabled`, `signals.features.ip_tracking.enabled`, and `signals.features.monetary.enabled`.

## Resources

```php
'resources' => [
    'labels' => [
        'outcomes' => 'Outcomes',
        'monetary_value' => 'Monetary Value',
    ],
    'navigation_sort' => [
        'dashboard' => 10,
        'page_views' => 15,
        'conversion_funnel' => 16,
        'acquisition' => 17,
        'journeys' => 18,
        'retention' => 19,
        'content_performance' => 20,
        'live_activity' => 21,
        'goals_report' => 22,
        'devices_report' => 23,
        'properties' => 30,
        'goals' => 31,
        'segments' => 31,
        'saved_reports' => 32,
        'alert_rules' => 33,
        'alert_logs' => 34,
    ],
],
```

`labels` are consumed by UI helpers such as `SignalsUiConfig`.

When `signals.features.monetary.enabled` is false, the `monetary_value` label remains configurable but the related UI is intentionally hidden.
