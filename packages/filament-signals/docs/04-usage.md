---
title: Usage
---

# Usage

`filament-signals` is the admin UI for Signals analytics and alert management.

## Dashboard

`SignalsDashboard` is the landing page for the package.

Depending on feature flags, it mounts:

- `SignalsStatsWidget` — tracked properties, identities, sessions, events, outcomes, alerts, and monetary totals
- `EventTrendWidget` — event and outcome trend lines
- `PendingSignalAlertsWidget` — unread alerts table with quick mark-read actions

## Reports

Use the report pages for:

- page views
- conversion funnels
- acquisition
- journeys
- retention
- content performance
- live activity
- goals
- devices and technology

The acquisition, conversion-funnel, content-performance, journey, and retention pages can preload a compatible `SavedSignalReport`.

Saved-report ids, tracked-property ids, and segment ids are sanitized against the current owner scope before the page applies them.

## Resources

- `TrackedPropertyResource`
- `SignalGoalResource`
- `SignalSegmentResource`
- `SavedSignalReportResource`
- `SignalAlertRuleResource`
- `SignalAlertLogResource`
- `SignalInteractionRuleResource`

## Interaction rules

`SignalInteractionRuleResource` manages browser-side interaction tracking rules that map UI events to Signals events.

Each rule can target:

- an optional tracked property,
- a trigger type (`click`, `accordion`, `media`, or `youtube`),
- a CSS selector,
- an optional page-path wildcard,
- an event name and category,
- optional advanced settings,
- active state and sort order.

The list page also includes scan-assisted workflows:

- **Scan page** scans a live URL or local Blade / Livewire source files and stores a preview of candidate rules.
- **Create from preview** turns selected preview candidates into disabled or active rules.
- **Rescan route** refreshes preview candidates from a local route pattern.
- **Clear preview** removes the cached scan preview for the current operator.

Preview candidates expose selector, path pattern, and scanner-confidence metadata so operators can review the guess before promoting it to a real rule.

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

`PendingSignalAlertsWidget` links operators back to `SignalAlertLogResource` for the full history.

## Monetary mode

When `signals.features.monetary.enabled` is false, monetary fields and metrics are hidden where applicable.
