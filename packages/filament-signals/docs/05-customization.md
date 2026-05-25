---
title: Customization
---

# Customization

## Feature-Gated Rollout

Use `filament-signals.features.*` flags to progressively enable dashboard pages, report pages, resources, and widgets by environment or tenant.

## Dashboard Widgets

Use these flags to tailor the dashboard surface:

- `filament-signals.features.widgets`
- `filament-signals.features.trend_chart`
- `filament-signals.features.pending_alerts_widget`

This lets you keep the dashboard page while trimming individual widgets.

## Saved Reports

Use `filament-signals.features.saved_reports` if you want operators to create reusable report states.

Supported report pages automatically ignore saved reports that are inactive, inaccessible in the current owner scope, or created for a different report type.

## Navigation Order

Use `filament-signals.resources.navigation_sort.*` to align menu order with your panel IA.

## Label Overrides

Use:

- `filament-signals.resources.labels.outcomes`
- `filament-signals.resources.labels.monetary_value`

These labels are consumed by UI helpers and widgets.

## Extending Plugin Registration

If you need additional app-specific pages/resources, register them in your panel provider alongside `FilamentSignalsPlugin::make()`.
