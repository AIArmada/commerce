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

## Interaction Rule Workflows

Use `filament-signals.features.interaction_rules` to enable or hide the interaction-rule resource entirely.

When enabled, operators can build rules manually or through the scan workflow powered by `InteractionRuleScanner`:

- scan a live URL,
- scan local Blade sources under `resources/views`,
- scan Livewire sources under `app/Livewire` and `app/Livewire/Volt`,
- preview candidates before creation,
- rescan a route pattern and replace the preview.

The preview is stored per signed-in operator, so one user’s scan candidates do not overwrite another user’s working set.

## Navigation Order

Use `filament-signals.resources.navigation_sort.*` to align menu order with your panel IA.

This includes `filament-signals.resources.navigation_sort.interaction_rules` for the interaction-rule resource.

## Label Overrides

Use:

- `filament-signals.resources.labels.outcomes`
- `filament-signals.resources.labels.monetary_value`

These labels are consumed by UI helpers and widgets.

## Policy-Based Authorization

The following policies gate resource access:

| Policy | Model |
|--------|-------|
| `SignalAlertRulePolicy` | Signal alert rules |
| `SignalGoalPolicy` | Signal goals |
| `SignalInteractionRulePolicy` | Signal interaction rules |
| `SignalSegmentPolicy` | Signal segments |
| `TrackedPropertyPolicy` | Tracked properties |

Override any policy in your `AuthServiceProvider` to customize authorization logic.

## Extending Plugin Registration

If you need additional app-specific pages/resources, register them in your panel provider alongside `FilamentSignalsPlugin::make()`.
