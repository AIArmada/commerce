---
title: Filament Signals Context
package: filament-signals
status: current
surface: filament
family: analytics-and-events
keywords:
  - filament
  - analytics-ui
  - funnel
  - alerts
---

# Filament Signals Context

## Snapshot
- Composer: `aiarmada/filament-signals`
- Role: Filament analytics UI: dashboards, reports, goals/segments/alerts.
- Triggers: filament, analytics-ui, funnel, alerts
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `signals`, `growth`, `filament-growth`
- Paired: `signals` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../signals/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `signals`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `signals` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Analytics dashboards UI.
- Skip when: Ingestion/rollups — see signals.
- Owner/security: Filament adapter.

## Key surfaces
- Resources: `SavedSignalReportResource`, `SignalAlertLogResource`, `SignalAlertRuleResource`, `SignalGoalResource`, `SignalInteractionRuleResource`, `SignalSegmentResource`, `TrackedPropertyResource`
- Actions/Services: `Support/InteractionRuleScanner`, `Support/SavedSignalReportMutationGuard`, `Support/SignalFormOptionLists`, `Support/SignalsModelReferenceGuard`, `Support/SignalsReportStateSanitizer`, `Support/SignalsUiConfig`, `Support/TrackedPropertyMutationGuard`
- Config `filament-signals.php`: `navigation`, `group`, `features`, `dashboard`, `page_views`, `conversion_funnel`, `acquisition`, `journeys`, `retention`, `content_performance`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-customization.md`
