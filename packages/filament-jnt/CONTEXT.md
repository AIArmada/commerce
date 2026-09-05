---
title: Filament Jnt Context
package: filament-jnt
status: current
surface: filament
family: checkout-flow
keywords:
  - filament
  - jnt-ui
  - waybill
  - tracking
---

# Filament Jnt Context

## Snapshot
- Composer: `aiarmada/filament-jnt`
- Role: Filament admin for J&T orders, tracking events, webhook logs, sync/print actions.
- Triggers: filament, jnt-ui, waybill, tracking
- Search first: `src/Resources, config, docs`
- Related: `jnt`, `shipping`, `filament-shipping`
- Paired: `jnt` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../jnt/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `jnt`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `jnt` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: J&T operations UI.
- Skip when: Carrier API — see jnt.
- Owner/security: Filament adapter.

## Key surfaces
- Resources: `BaseJntResource`, `JntOrderResource`, `JntTrackingEventResource`, `JntWebhookLogResource`
- Actions/Services: `Actions/CancelOrderAction`, `Actions/PrintAwbTableAction`, `Actions/SyncTrackingAction`, `Support/JntStatsAggregator`, `Support/NavigationBadgeHelper`
- Config `filament-jnt.php`: `navigation`, `group`, `badge_color`, `polling_interval`, `tables`, `datetime_format`, `features`, `orders`, `tracking_events`, `webhook_logs`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
