# Shipping friendliness review

This note reviews `packages/shipping` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services` (8 classes)
- `src/Actions` (5 classes)
- `src/Drivers` (4 classes)
- `src/Strategies` (4 classes)
- `src/Integrations`
- `src/Cart`
- `src/Http/Controllers`
- `routes/web.php`
- downstream consumers in `cart`, `checkout`, `orders`, `jnt`

## What is already friendly

### Real driver adapter seam

- `Drivers/FlatRateShippingDriver.php`
- `Drivers/ManualShippingDriver.php`
- `Drivers/NullShippingDriver.php`
- `Drivers/ZoneBasedShippingDriver.php` (all impl `Contracts/ShippingDriverInterface.php`)

This is the right shape. Adding a new carrier (or a new shipping model like JNT) is a new driver behind the contract.

### Real rate selection strategies

- `Strategies/CheapestRateStrategy.php`
- `Strategies/FastestRateStrategy.php`
- `Strategies/BalancedRateStrategy.php`
- `Strategies/PreferredCarrierStrategy.php` (all impl `Contracts/RateSelectionStrategyInterface.php`)

Each strategy is a separate class. Adding a new selection rule is additive.

### Orders integration is a real adapter

- `Integrations/OrderFulfillmentHandler.php` (impl `orders/Contracts/FulfillmentHandlerInterface.php`)

The package exposes an `OrderFulfillmentHandler` rather than reaching into `Order` directly.

### Cart integration is isolated

- `Cart/ShippingCondition.php`
- `Cart/ShippingConditionProvider.php`

Cart composes shipping through a provider, not by reaching into the shipping model.

### Money is a typed DTO

- `Data/RateQuoteData.php`
- `Data/ShipmentData.php` (and 8 more)

DTOs are typed via spatie/laravel-data. Callers can rely on the shape.

## Findings

### 1. `ShippingManager` is a top-level orchestrator

**Files**

- `src/ShippingManager.php`
- `src/Services/ShipmentService.php`
- `src/Services/RateShoppingEngine.php`

**Why this hurts friendliness**

`ShippingManager` likely owns the public entry point for all shipping operations. `ShipmentService` and `RateShoppingEngine` are siblings. The boundaries between "manager," "service," and "engine" are unclear.

**Recommendation**

Pick a single canonical orchestration surface. Either:

- keep `ShippingManager` as a thin facade and move mutations to Actions, or
- promote `ShipmentService` to the public surface and remove `ShippingManager`

Today the manager + service + engine triad is three names for overlapping concerns.

### 2. Service count is high (8) for a package with only 5 Actions

**Files in `src/Services/`**

- `ShipmentService`
- `RateShoppingEngine`
- `BatchRateLimiter`
- `FreeShippingEvaluator`
- `ShippingZoneResolver`
- `TrackingAggregator`
- `RetryService`

**Why this hurts friendliness**

Mutations are split between services and Actions, but most mutations likely live in services. This is inconsistent with the monorepo's "Actions only" rule.

**Recommendation**

Move all shipment mutations to Actions:

- `Actions/CreateShipment` (exists)
- `Actions/UpdateShipmentStatus` (exists)
- `Actions/CancelShipment`
- `Actions/ApplyReturnAuthorization` (exists)
- `Actions/RecordTrackingEvent`
- `Actions/ResolveReturnAuthorization` (exists)

Services become read-side (queries, tracking, zone resolution, rate shopping).

### 3. Free shipping is its own service but the policy is config-driven

**Files**

- `src/Services/FreeShippingEvaluator.php`
- `src/Services/FreeShippingResult.php`

**Why this hurts friendliness**

Free-shipping evaluation is a real business rule. As the package supports more promotional shipping (first-class free, threshold-based, member-only), the evaluator will grow.

**Recommendation**

Move free-shipping evaluation behind a `FreeShippingPolicyInterface` and a registry. The promotion package can register its own policies.

### 4. Zone resolution is a service but not a clear strategy

**Files**

- `src/Services/ShippingZoneResolver.php`
- `src/Models/ShippingZone.php`

**Why this hurts friendliness**

Zone resolution (which zone applies for a given address, customer, or cart) is a variant-heavy area. New resolution rules (B2B zones, international zones, product-class zones) will edit the same class.

**Recommendation**

Extract a `ZoneResolutionStrategyInterface` and one strategy per rule. The resolver coordinates them. The strategy is configurable per merchant.

### 5. Retry logic is a service but not a shared seam

**Files**

- `src/Services/RetryService.php`

**Why this hurts friendliness**

`RetryService` is shipping-specific today. If chip, cashier-chip, or jnt also need retry, the logic will be copied.

**Recommendation**

If a second package demonstrates the same need, move the retry helper to `commerce-support` and make shipping use it.

### 6. Routes file is a single signed URL

**Files**

- `routes/web.php`
- `src/Http/Controllers/LabelController.php`

**Why this hurts friendliness**

This is fine for the current state. Note that adding more routes (tracking lookup, return portal) will edit the same file. Consider grouping controller routes under a prefix to make the boundary clear.

### 7. Status mapping contract exists but the event is the only consumer

**Files**

- `Contracts/StatusMapperInterface.php`
- `Events/ShipmentStatusChanged.php`
- `Events/TrackingUpdated.php`

**Why this hurts friendliness**

The status mapper contract exists, but its consumers are unclear. New carriers need to map their status to the package's status, and that mapping should be in one place.

**Recommendation**

Each driver (or a shared adapter for the driver family) should implement the status mapper. The mapping is registered per driver, not hard-coded in the central service.

## Concrete refactor plan

### Phase 1 — clarify the manager/service/engine boundary

**Steps**

1. Decide whether `ShippingManager` is the facade or the implementation.
2. Move the other's logic into the chosen one.
3. Make the unused one a thin compat adapter.

### Phase 2 — extract mutations to Actions

**Steps**

1. Move all shipment mutations from services to Actions.
2. Update callers (controllers, listeners, filament, jnt).
3. Add tests for each Action.

### Phase 3 — extract free-shipping and zone strategies

**Steps**

1. Add `Contracts/FreeShippingPolicyInterface` and a registry.
2. Add `Contracts/ZoneResolutionStrategyInterface` and a registry.
3. Update `FreeShippingEvaluator` and `ShippingZoneResolver` to use the registries.

### Phase 4 — move retry helper to foundation if needed

**Steps**

1. Wait for evidence that another package needs the same retry behavior.
2. If yes, extract `commerce-support/Support/RetryPolicy` and migrate.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — clarify the manager/service/engine boundary

- [pending] Decide whether `ShippingManager` is the facade or the implementation.
- [pending] Move the other's logic into the chosen one.
- [pending] Make the unused one a thin compat adapter.

### Phase 2 — extract mutations to Actions

- [pending] Move all shipment mutations from services to Actions.
- [pending] Update callers (controllers, listeners, filament, jnt).
- [pending] Add tests for each Action.

### Phase 3 — extract free-shipping and zone strategies

- [pending] Add `Contracts/FreeShippingPolicyInterface` and a registry.
- [pending] Add `Contracts/ZoneResolutionStrategyInterface` and a registry.
- [pending] Update `FreeShippingEvaluator` and `ShippingZoneResolver` to use the registries.

### Phase 4 — move retry helper to foundation if needed

- [pending] Wait for evidence that another package needs the same retry behavior.
- [pending] If yes, extract `commerce-support/Support/RetryPolicy` and migrate.



## Suggested verification scope

- per-Action tests for new mutation Actions
- driver and strategy tests
- zone resolution tests after extraction
- cross-package tests for cart/checkout/orders/jnt after refactor

## Recommended first move

Phase 1 — clarify the manager/service/engine boundary. The triad is the most visible architectural smell in the package, and resolving it is mostly mechanical.
