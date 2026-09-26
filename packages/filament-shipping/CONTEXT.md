---
title: Filament Shipping Context
package: filament-shipping
status: current
surface: filament
family: checkout-flow
keywords:
  - filament
  - shipments-ui
  - fulfilment
  - rma
---

# Filament Shipping Context

## Snapshot
- Composer: `aiarmada/filament-shipping`
- Role: Filament admin for shipments, zones, RMAs, fulfilment queue, manifests.
- Triggers: filament, shipments-ui, fulfilment, rma
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `shipping`, `jnt`, `filament-jnt`
- Paired: `shipping` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../shipping/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `shipping`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `shipping` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Shipping operations UI.
- Skip when: Rate/carrier math — see shipping.
- Owner/security: Mirrors shipping scope.

## Key surfaces
- Resources: `ShipmentResource`, `ShippingZoneResource`, `ShippingRateResource`, `ReturnAuthorizationResource`
- Relation managers: `ShipmentResource/RelationManagers/{EventsRelationManager,ItemsRelationManager}`, `ReturnAuthorizationResource/RelationManagers/ItemsRelationManager`, `ShippingZoneResource/RelationManagers/RatesRelationManager`
- Pages: `Pages/ShippingDashboard`, `Pages/FulfillmentQueue`, `Pages/ManifestPage`
- Widgets: `Widgets/ShippingDashboardWidget`, `Widgets/PendingShipmentsWidget`, `Widgets/CarrierPerformanceWidget`, `Widgets/PendingActionsWidget`
- Actions/Support: `Actions/{ShipAction,CancelShipmentAction,PrintLabelAction,SyncTrackingAction,ApproveReturnAction,RejectReturnAction}` (all `Filament\Actions\Action` subclasses — no bulk variants), `Support/MoneyInput`, `Support/ShippingStatsAggregator`
- Config `filament-shipping.php` keys: `shipping_methods`, `carriers` (shipped but unread), `features.enable_fulfillment_queue`, `fulfillment.urgent_threshold_hours`, `fulfillment.old_threshold_hours`, `navigation.group`, `navigation.sort`, `pages.navigation_sort.{dashboard,fulfillment_queue,manifest}`, `resources.navigation_sort.{shipments,zones,rates,returns}`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
