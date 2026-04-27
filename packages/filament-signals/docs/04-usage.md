---
title: Usage
---

# Usage

`filament-signals` is the admin UI for Signals analytics and alert management.

## Reports

Use the report pages for page views, acquisition, devices, content performance, funnels, journeys, retention, goals, and live activity.

## Resources

- `TrackedPropertyResource`
- `SignalGoalResource`
- `SignalSegmentResource`
- `SavedSignalReportResource`
- `SignalAlertRuleResource`
- `SignalAlertLogResource`

## Generic alert rules

Alert rules can be configured with:

- metric key,
- threshold/operator,
- timeframe and cooldown,
- event-name filters,
- event-category filters,
- event property conditions,
- database/email/webhook/Slack channels,
- named destination keys.

Use cart event names such as `cart.abandoned` or `cart.high_value.detected` when Signals cart integrations are enabled.

## Alert logs

Alert logs show matched metric values, threshold values, event/filter context, delivery results, channels notified, read state, owner-safe tracked property context, and linked rule context.

## Monetary mode

When `signals.features.monetary.enabled` is false, monetary fields and metrics are hidden where applicable.
