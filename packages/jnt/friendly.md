# JNT friendliness review

This note reviews `packages/jnt` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services` (4 classes)
- `src/Actions` (3 subfolders: Orders, Tracking, Waybills)
- `src/Console/Commands` (7 commands)
- `src/Http/Controllers` (2 controllers)
- `src/Webhooks`
- `src/Shipping/JntShippingDriver.php`
- `src/Cart` (cart integration)
- `src/Builders`
- `src/Listeners`
- `src/Models` (5 classes)
- `src/Events` (10 events)
- `src/Notifications` (3 classes)
- `src/Health`
- `src/Rules` (6 validation rules)
- `routes/web.php`, `routes/webhooks.php`
- downstream consumers in `cart`, `checkout`, `orders`, `shipping`

## What is already friendly

### Actions are organized by domain

- `Actions/Orders/`
- `Actions/Tracking/`
- `Actions/Waybills/`

This is the right shape. Each domain folder is a focused Action surface.

### Shipping driver plugs into the shipping package

- `src/Shipping/JntShippingDriver.php` (impl `shipping/Contracts/ShippingDriverInterface.php`)

The package extends shipping through a contract, not by editing shipping.

### Cart integration is isolated

- `src/Cart/CartManagerWithJntShipping.php`
- `src/Cart/JntShippingCalculator.php`
- `src/Cart/JntShippingConditionProvider.php`

Cart composes JNT shipping through a provider, not by reaching into JNT models.

### Webhook seam uses spatie/laravel-webhook-client

- `Webhooks/JntSpatieSignatureValidator.php`
- `Webhooks/JntWebhookProfile.php`
- `Webhooks/JntWebhookResponse.php`
- `Webhooks/ProcessJntWebhook.php`

This is the right pattern. The package plugs into the monorepo's webhook foundation.

### Validation rules are isolated

- `Rules/DimensionInCentimeters`, `MalaysianPostalCode`, `MonetaryValue`, `PhoneNumber`, `WeightInGrams`, `WeightInKilograms`

Custom rules are first-class classes.

### Notifications are isolated

- `Notifications/OrderDeliveredNotification`, `OrderProblemNotification`, `OrderShippedNotification`

Each notification is a focused class.

### Health check is in place

- `src/Health/JntHealthCheck.php`

## Findings

### 1. Console command count is very high (7) and most likely orchestrate the same JNT API

**Files**

- `Console/Commands/ConfigCheckCommand.php`
- `Console/Commands/WebhookTestCommand.php`
- `Console/Commands/OrderPrintCommand.php`
- `Console/Commands/OrderCancelCommand.php`
- `Console/Commands/OrderCreateCommand.php`
- `Console/Commands/OrderTrackCommand.php`
- `Console/Commands/HealthCheckCommand.php`

**Why this hurts friendliness**

7 commands is a lot. They likely share common JNT API call patterns, error handling, and CLI output formatting.

**Recommendation**

Extract a shared `Console/JntCommand` base class or a `JntApiClient` service that the commands use. Currently each command may construct its own API client.

### 2. `JntExpressService` is a likely catch-all orchestrator

**Files**

- `src/Services/JntExpressService.php`

**Why this hurts friendliness**

The main service likely owns many operations (order create, cancel, track, print). The split between service and `Actions/` subfolders is unclear.

**Recommendation**

Audit `JntExpressService` for opportunity to delegate to the Action subfolders. Keep it as a thin facade.

### 3. Service count (4) is small but unclear

**Files in `src/Services/`**

- `JntExpressService` (orchestrator)
- `JntTrackingService` (tracking)
- `JntStatusMapper` (status mapping)
- `WebhookService`

**Why this hurts friendliness**

The split between `JntTrackingService` and `JntStatusMapper` is unclear. Tracking needs the mapper, so they may overlap.

**Recommendation**

Audit both. If `JntStatusMapper` is a pure mapping helper, it should live in `Support/` and be a stateless class.

### 4. `WebhookService` and `Webhooks/ProcessJntWebhook` likely overlap

**Files**

- `src/Services/WebhookService.php`
- `src/Webhooks/ProcessJntWebhook.php`

**Why this hurts friendliness**

Two classes that may both own webhook processing. The Spatie webhook-client expects a job class (`ProcessJntWebhook`), so `WebhookService` may be the listener that gets invoked.

**Recommendation**

Audit both. Make the relationship clear: `WebhookService` is the orchestrator that the job calls, and `ProcessJntWebhook` is the Spatie job entry point. Or merge if one is redundant.

### 5. Routes are simple and clear

**Files**

- `routes/web.php` — `GET /jnt/awb/{orderId}` (signed)
- `routes/webhooks.php` — `POST webhooks/jnt/status` (signature-validated)

**Why this is worth noting**

Routes are minimal and well-scoped. Keep this discipline.

### 6. No `Contracts/` directory

**Why this hurts friendliness**

The package has real adapter seams (shipping driver, webhook validator, webhook profile) but no `Contracts/` namespace. The contracts live in shipping or in spatie/laravel-webhook-client, which is fine. Just note the convention.

### 7. Listeners and events are well-organized

**Files**

- 1 listener: `Listeners/SendShipmentNotifications.php`
- 10 events: `JntOrderStatusChanged`, `OrderCreatedEvent`, `OrderCancelledEvent`, `ParcelPickedUp`, `ParcelInTransit`, `ParcelOutForDelivery`, `ParcelDelivered`, `TrackingUpdated`, `TrackingUpdatedEvent`, `TrackingStatusReceived`, `WaybillPrintedEvent`

**Why this is worth noting**

The event surface is rich. Note that some event names suggest duplication (`TrackingUpdated` and `TrackingUpdatedEvent`, `OrderCreatedEvent` — though this is consistent with other packages). The listener is a thin adapter to notifications.

### 8. Status mapping is a real concern

**Files**

- `Services/JntStatusMapper.php`
- `Enums/TrackingStatus.php`
- `Enums/ScanTypeCode.php`

**Why this is worth noting**

JNT carrier statuses need to be mapped to the package's `TrackingStatus`. As the carrier adds new statuses, the mapper must be updated. Consider promoting the mapper to a contract + strategy.

## Concrete refactor plan

### Phase 1 — audit and reduce command duplication

**Steps**

1. Compare the 7 commands for shared patterns.
2. Extract a `JntCommand` base class or `JntApiClient` service.
3. Reduce per-command boilerplate.

### Phase 2 — clarify the service/actions split

**Steps**

1. Audit `JntExpressService` for delegation to `Actions/`.
2. Audit `JntTrackingService` and `JntStatusMapper` for overlap.
3. Audit `WebhookService` and `ProcessJntWebhook` for overlap.

### Phase 3 — promote status mapping to a strategy

**Steps**

1. Add `Contracts/StatusMappingStrategyInterface`.
2. Move `JntStatusMapper` to a strategy implementation.
3. Allow other carriers to register their own strategies.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — audit and reduce command duplication

- [pending] Compare the 7 commands for shared patterns.
- [pending] Extract a `JntCommand` base class or `JntApiClient` service.
- [pending] Reduce per-command boilerplate.

### Phase 2 — clarify the service/actions split

- [pending] Audit `JntExpressService` for delegation to `Actions/`.
- [pending] Audit `JntTrackingService` and `JntStatusMapper` for overlap.
- [pending] Audit `WebhookService` and `ProcessJntWebhook` for overlap.

### Phase 3 — promote status mapping to a strategy

- [pending] Add `Contracts/StatusMappingStrategyInterface`.
- [pending] Move `JntStatusMapper` to a strategy implementation.
- [pending] Allow other carriers to register their own strategies.



## Suggested verification scope

- per-Action tests
- console command tests
- webhook job tests
- shipping driver tests
- cross-package tests for cart/orders/shipping

## Recommended first move

Phase 2 — clarify the service/actions split. This is the highest-leverage cleanup because the boundary between `JntExpressService` and the three `Actions/` subfolders is the most unclear, and clarifying it unblocks the command and webhook audits.
