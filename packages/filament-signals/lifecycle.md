# Filament Signals — Lifecycle

## 1. Overview

**Package**: `aiarmada/filament-signals`
**Role**: Filament panel plugin providing analytics dashboards, reports, and CRUD resources for the Signals ecosystem.
**Family**: `analytics-and-events`
**Dependencies**: `commerce-support` (multitenancy, permissions), `signals` (models, services, actions), Filament v5 (Livewire 4)

The package owns the Filament UI layer: a dashboard with live stats and trend chart, ten analytics report pages, and seven Filament resources. It does **not** own data ingestion, persistence, alerting logic, or report computation — those belong to the `signals` package.

### Entry points

| Surface | What it provides |
|---------|-----------------|
| `FilamentSignalsPlugin` | Registers pages and resources on a Filament `Panel` using feature-flag config gates |
| `FilamentSignalsServiceProvider` | Publishes config, views; registers gate policies for 5 Signals models |
| `ReportPage` (abstract) | Base page with URL-bound date range, tracked property, and segment filters; mounts default 30-day window |
| `SignalsDashboard` | Extends Filament Dashboard with widgets |
| 7 Resource classes | Standard Filament CRUD with owner-scoped queries and mutation guards on write paths |
| `InteractionRuleScanner` | Scans live HTML or local Blade/Livewire source for interactive elements |

### Architecture principles

- **UI scoping is not security**: All inbound IDs are revalidated server-side via `OwnerWriteGuard` or `SignalsReportStateSanitizer`.
- **Config-gated features**: Every page, resource, and widget registers only when its feature flag is enabled.
- **Delegation to `signals`**: Report pages delegate data queries and summaries to service classes in the `signals` package.

## 2. Installation

```bash
composer require aiarmada/filament-signals
```

The service provider is auto-discovered. Requires `commerce-support`, `signals`, Filament v5, and an active owner resolver.

```bash
php artisan vendor:publish --tag=filament-signals-config
```

```php
use AIArmada\FilamentSignals\FilamentSignalsPlugin;
$panel->plugin(FilamentSignalsPlugin::class);
```

## 3. Configuration

All config in `config/filament-signals.php`.

### Navigation group

```php
'navigation_group' => 'Insights',
```

### Feature toggles

| Key | Controls |
|-----|----------|
| `dashboard` | `SignalsDashboard` page and its widgets |
| `page_views` | `PageViewsReport` page |
| `conversion_funnel` | `ConversionFunnelReport` page |
| `acquisition` | `AcquisitionReport` page |
| `journeys` | `JourneyReport` page |
| `retention` | `RetentionReport` page |
| `content_performance` | `ContentPerformanceReport` page |
| `live_activity` | `LiveActivityReport` page |
| `goals_report` | `GoalsReport` page |
| `devices_report` | `DevicesReport` page |
| `properties` | `TrackedPropertyResource` |
| `goals` | `SignalGoalResource` |
| `segments` | `SignalSegmentResource` |
| `saved_reports` | `SavedSignalReportResource` |
| `alert_rules` | `SignalAlertRuleResource` |
| `alert_logs` | `SignalAlertLogResource` |
| `interaction_rules` | `SignalInteractionRuleResource` |
| `widgets` | `SignalsStatsWidget` on dashboard |
| `trend_chart` | `EventTrendWidget` on dashboard |
| `pending_alerts_widget` | `PendingSignalAlertsWidget` on dashboard |

### Resource labels

```php
'resources' => [
    'labels' => [
        'outcomes' => 'Outcomes',
        'monetary_value' => 'Monetary Value',
    ],
],
```

`SignalsUiConfig` reads these for column/widget headings.

### Navigation sort

Pages use 10–23; CRUD resources use 30–35.

### Cross-package config dependencies

`config('signals.features.monetary.enabled')` gates currency fields and revenue columns. `config('signals.defaults')` provides timezone/currency defaults.

## 4. Usage

### 4.1 Dashboard

`SignalsDashboard` extends Filament's `Dashboard` with path `/signals`:

- **SignalsStatsWidget**: Counts for tracked properties, active alert rules, unread alerts, identities, sessions, events, outcomes, monetary value.
- **EventTrendWidget**: Line chart of daily events vs outcomes from `SignalsDashboardService::trend()`.
- **PendingSignalAlertsWidget**: Table widget showing unread alert logs with mark-read actions, polled every 15 seconds.

### 4.2 Report pages

All report pages extend `ReportPage` and share:

- **URL-bound state**: `dateFrom`, `dateTo`, `trackedPropertyId`, `signalSegmentId` as `#[Url]` properties.
- **Default date range**: 30 days ending today, set in `mount()`.
- **Filter sanitization**: Inbound IDs validated via `SignalsReportStateSanitizer` → `OwnerWriteGuard::findOrFailForOwner()`.
- **Header actions**: Date range picker, quick-range buttons (7/30/90 days), property/segment selects.

Report types:

| Page | Slug | Data source |
|------|------|-------------|
| `PageViewsReport` | `/signals/page-views` | `PageViewReportService` |
| `ConversionFunnelReport` | `/signals/conversion-funnel` | `ConversionFunnelReportService` |
| `AcquisitionReport` | `/signals/acquisition` | `AcquisitionReportService` |
| `JourneyReport` | `/signals/journeys` | `JourneyReportService` |
| `RetentionReport` | `/signals/retention` | `RetentionReportService` |
| `ContentPerformanceReport` | `/signals/content-performance` | `ContentPerformanceReportService` |
| `LiveActivityReport` | `/signals/live-activity` | `LiveActivityReportService` |
| `GoalsReport` | `/signals/goals` | `GoalsReportService` |
| `DevicesReport` | `/signals/devices` | `DevicesReportService` |

Reports that support saved report definitions use `InteractsWithSavedSignalReportState` and validate saved report IDs through `SignalsReportStateSanitizer`.

### 4.3 Resources

All resources follow the same pattern: `getEloquentQuery()` returns `forOwner()` scoped queries, form/table delegated to dedicated configurator classes.

#### TrackedPropertyResource
- **Model**: `TrackedProperty`
- **Form**: name, slug (auto-generated), domain, type (website/storefront/app), timezone, currency (conditional on monetary feature), write_key (auto-generated), is_active
- **Table**: name, slug, domain, type badge, is_active, write_key (hidden), created_at
- **Mutation**: `CreateTrackedProperty` auto-generates write_key if empty on create

#### SignalAlertRuleResource
- **Model**: `SignalAlertRule`
- **Form**: name, slug, tracked_property_id (owner-scoped), metric_key, operator, threshold, timeframe_minutes, cooldown_minutes, severity, priority, is_active, event_filters, notification channels
- **Table**: name, property, metric_key badge, events, channels, threshold, window, severity badge, is_active, last_triggered_at, alerts count
- **Mutation guards**: `TrackedPropertyMutationGuard` validates tracked_property_id on create/update

#### SignalGoalResource
- **Model**: `SignalGoal`
- **Form**: name, slug, tracked_property_id (owner-scoped), goal_type (conversion/engagement/revenue), event_name, event_category, is_active, description, conditions (field/operator/value repeater)
- **Table**: goal name, type badge, event name, event category badge, property, rule count, is_active, created_at
- **Mutation guards**: `TrackedPropertyMutationGuard` validates tracked_property_id

#### SignalSegmentResource
- **Model**: `SignalSegment`
- **Form**: name, slug, match_type (all/any), is_active, description, conditions (field/operator/value repeater)
- **Table**: segment name, slug, match type badge, rule count, is_active, created_at
- **No mutation guards** (no foreign key validation needed)

#### SignalAlertLogResource
- **Model**: `SignalAlertLog`
- **Form**: Empty schema (read-only resource)
- **Table**: title, severity badge, rule name, property, metric_value, threshold_value, channels_notified, context_metric, delivery results, is_read, created_at
- **Actions**: Mark Read / Mark Unread via `MarkSignalAlertAsRead` / `MarkSignalAlertAsUnread`

#### SignalInteractionRuleResource
- **Model**: `SignalInteractionRule`
- **Form**: name, slug, tracked_property_id (owner-scoped), trigger_type (click/accordion/media/youtube), event_name, event_category, selector, page_pattern, settings (key-value), sort_order, is_active
- **Table**: name, slug, trigger type badge, event name, scanner confidence badge, selector, page pattern, property, is_active, sort_order, updated_at
- **List page extras**: "Scan page" action (live URL or local source), "Create from preview", backed by `InteractionRuleScanner` and cache-based scan preview

#### SavedSignalReportResource
- **Model**: `SavedSignalReport`
- **Form**: name, slug, report_type, tracked_property_id (owner-scoped), signal_segment_id (owner-scoped), is_shared, is_active, description, filters (key/value repeater), plus conditional sections for Funnel, Acquisition, Journey, Content, Retention settings
- **Table**: name, report_type badge, property, segment, is_shared, is_active, created_at
- **Mutation guards**: `SavedSignalReportMutationGuard` validates tracked_property_id, signal_segment_id, and goal_slug references
- **Owner-scoping note**: `getEloquentQuery()` calls `parent::getEloquentQuery()` relying on `HasOwner` global scope on `SavedSignalReport`

### 4.4 Owner scoping

All resources scope their queries with `->forOwner()` (from `HasOwner` trait). The `PendingSignalAlertsWidget` handles the global owner edge case by wrapping its query in `OwnerContext::withOwner(null, ...)`.

ID validation on report filters and mutation paths uses `OwnerWriteGuard::findOrFailForOwner()` with `includeGlobal: false`.

### 4.5 Interaction rule scanning

`InteractionRuleScanner` provides two scan modes:
- **Live URL scan**: Fetches a page via HTTP, parses HTML DOM, extracts interactive elements (links, buttons, inputs, details/summary, audio, video, YouTube iframes).
- **Local source scan**: Scans Blade and Livewire PHP files for HTML patterns matching the trigger type.

Results are cached per-user for 30 minutes. Candidates include a confidence score derived from selector specificity and source type.

## 5. Testing

### Scope

```bash
./vendor/bin/pest --parallel packages/filament-signals/tests/
```

### Test categories

- **Resource tests**: Pages reachable, tables render, forms validate, actions execute.
- **Policy tests**: Each permission ability gates access correctly.
- **Report tests**: Summaries, row data, and filter sanitization.
- **Mutation guard tests**: Cross-tenant IDs rejected, valid IDs pass.
- **Interaction scanner tests**: DOM parsing, selector derivation, local source scanning.

### Cross-tenant regression

Every `HasOwner` resource must have at least one test proving reads are isolated and writes throw when referencing another owner's data.

## 6. Troubleshooting

### Pages/resources not appearing in navigation

Verify the corresponding feature flag is `true` in `config/filament-signals.php`.

### "Selected tracked property is not accessible" on save

`TrackedPropertyMutationGuard` rejected the submitted `tracked_property_id`. Verify the ID belongs to a `TrackedProperty` in the current owner scope. Global (ownerless) properties are excluded by default (`includeGlobal: false`).

### Saved report form rejects goal slugs in funnel steps

`SavedSignalReportMutationGuard` checks each `goal_slug` resolves to an active `SignalGoal` in the current owner scope. Verify the goal exists, is active, and belongs to the selected property scope.

### Filter state resets to empty strings

`SignalsReportStateSanitizer` resets invalid IDs to `''`. If a property or segment is deleted or out of scope, previously valid filter URLs silently reset.

### Interaction scanner returns no candidates

For live URL scans: ensure the URL is reachable from the server and contains elements matching the trigger type. For local source scans: confirm directories contain `.php` or `.blade.php` files with matching HTML patterns.

### Widget polling issues

`PendingSignalAlertsWidget` polls every 15 seconds. If stale, verify `OwnerContext` resolves correctly and `HasOwner` scope on `SignalAlertLog` is active.

### Money/revenue columns missing

Enable `config('signals.features.monetary.enabled', true)` in the `signals` package config.
