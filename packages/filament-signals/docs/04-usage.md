---
title: Usage
---

# Usage

## Registered Pages

When enabled, the plugin registers:

- `SignalsDashboard`
- `PageViewsReport` — includes **Exclude Bots** toggle filter (default on)
- `ConversionFunnelReport`
- `AcquisitionReport` — includes **Exclude Bots** toggle filter (default on)
- `JourneyReport`
- `RetentionReport`
- `ContentPerformanceReport`
- `LiveActivityReport` — exposes device/browser/OS/IP columns (toggleable, hidden by default)
- `GoalsReport`
- `DevicesReport` — Devices & Technology report (see below)

## Registered Resources

When enabled, the plugin registers:

- `TrackedPropertyResource`
- `SignalGoalResource`
- `SignalSegmentResource`
- `SavedSignalReportResource`
- `SignalAlertRuleResource`
- `SignalAlertLogResource`

## Dashboard Widgets

The package ships with widgets used by dashboard/report pages:

- `SignalsStatsWidget`
- `EventTrendWidget`
- `PendingSignalAlertsWidget`

## Monetary-Aware UI

When `signals.features.monetary.enabled` is false in the Signals package:

- dashboard stats omit monetary value cards
- report tables and summary cards hide monetary columns
- goal and alert forms omit monetary-only options
- tracked property forms hide currency fields that are only needed for revenue reporting

Outcome counts and non-monetary reporting continue to function normally.

## Saved Reports Workflow

1. Build report filters on report pages.
2. Save definition via `SavedSignalReportResource`.
3. Re-open saved report from report-page actions.

This is particularly useful for funnel/journey presets and team-shared analytics views.

## Devices & Technology Report

The `DevicesReport` page (`/signals/devices`) provides four sub-views with URL-driven tab switching:

| Tab | Groups by | Columns |
|-----|-----------|---------|
| **Devices** (default) | `device_type` | Device Type, Sessions, Visitors |
| **Browsers** | `browser` | Browser, Sessions, Visitors |
| **Operating Systems** | `os` | Operating System, Sessions, Visitors |
| **Brands** | `device_brand` | Brand, Sessions, Visitors |

A summary bar shows total session count, unique browser count, OS count, device brand count, and bot count for the selected date range.

Bots are excluded by default. Use the **Include/Exclude bots** button to toggle. All data depends on `signals.features.ua_parsing.enabled = true` in the signals package configuration.

## Bot Filtering

`PageViewsReport` and `AcquisitionReport` both register an `exclude_bots` toggle filter. It is **enabled by default**, filtering out `SignalEvent` rows where the associated session has `is_bot = true`. Disable it in the filter panel to inspect bot traffic.

## LiveActivityReport — Device & IP Columns

`LiveActivityReport` exposes the following additional columns (all hidden by default; toggle via the column visibility menu):

- **Device** — `device_type` (badge)
- **Browser** — `browser`
- **Browser Version** — `browser_version`
- **OS** — `os` (badge)
- **OS Version** — `os_version`
- **Brand** — `device_brand`
- **Model** — `device_model`
- **IP Address** — visible only when `signals.features.ip_tracking.enabled = true`

These columns depend on upstream Signals session enrichment. If `signals.features.ua_parsing.enabled` is false, the device/browser/OS columns will remain empty.
