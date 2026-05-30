---
title: Widgets
---

# Widgets

`FilamentAffiliatesPlugin` registers widget surfaces for affiliate analytics and operations.

## Registration behavior

### Always registered

- `AffiliateStatsWidget`
- `PerformanceOverviewWidget`
- `RealTimeActivityWidget`

### Feature-gated

- `FraudAlertWidget` (`filament-affiliates.features.admin.fraud_monitoring`)
- `PayoutQueueWidget` (`filament-affiliates.features.admin.payouts`)
- `NetworkVisualizationWidget` (`filament-affiliates.features.admin.network_visualization`)

## AffiliateStatsWidget

Stats overview based on `AffiliateStatsAggregator`.

Current cards include:

- Affiliates (active/total)
- Pending Affiliates
- Pending Commission
- Paid Commission
- Conversion Rate

Currency display uses:

```php
config('filament-affiliates.widgets.currency', 'USD')
```

## PerformanceOverviewWidget

Stats overview widget (not a chart widget) with monthly comparisons.

Current cards include:

- Conversions This Month
- Revenue This Month
- Commission Earned
- Active Affiliates

## FraudAlertWidget

Table widget for detected fraud signals.

Current actions:

- `review` (opens fraud signal view)
- `dismiss` (status update, permission-gated)

## PayoutQueueWidget

Table widget for pending/processing payouts.

Current actions:

- `process` (pending only; owner-scoped + policy-gated)
- `view`

## RealTimeActivityWidget

Live conversion activity table.

### Features

- Auto-refresh every 10 seconds
- Affiliate + reference + value + commission + status columns
- Owner-aware query when owner mode is enabled

## NetworkVisualizationWidget

Network tree visualization widget (Blade-backed widget view).

### Features

- Tree view of affiliate hierarchy
- Downline depth indicators
- Expand/collapse nodes

Provides network summary stats and nested node data for the widget view.

## Notes on customization

- The plugin does not expose per-widget fluent registration methods.
- Enable/disable plugin widgets through `filament-affiliates.features.admin.*`.
- For additional app-specific widgets, register those in your panel provider as usual.

