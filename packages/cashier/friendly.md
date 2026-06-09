## Second pass — 2026-06-09

### Confirmed
- Phase 1: `Concerns/Billable.php` exists (model helper trait: `getSubscriptions`, `subscription`, etc.). `src/Billable.php` at root preserved as separate trait (gateway management: charge, subscribe, refund). These are distinct traits with different responsibilities — the root one composes `ManagesGateway`, the Concerns one composes billing operations. BC preserved, re-export done.
- Phase 2: 5 Actions created: `CancelSubscription`, `CreatePayment`, `CreateSubscription`, `RefundPayment`, `SyncWebhook`. `GatewayManager` reduced to 111 lines — Actions now own the mutations.
- Phase 3: Exceptions reorganized into `Gateway/`, `Payment/`, `Subscription/`, `Webhook/` subdirectories. BC aliases preserved at old paths.
- Phase 4: All 20 per-entity adapters audited. Each has distinct gateway-specific behavior (Stripe vs CHIP). None collapsed — collapse would create a leaky abstraction.

### Resolved (since second pass)
- **Phase 1 docs update**: ✅ Completed in Phase 5 — `docs/04-usage.md` now documents the two Billable traits (section 4) and contracts-to-implementations matrix (section 5).
- **No Console/Commands directory**: ✅ Completed in Phase 7 — `Console/Commands/` added with `WebhookReplayCommand` using `OwnerBatchRunner`. `cashier-chip`'s `RenewSubscriptionsCommand` stays in cashier-chip (CHIP-specific).

### New findings
1. **GatewayManager is appropriately thin**: At 111 lines (3 classes), it's now a proper facade — resolves gateways, dispatches webhook events, provides the public entry point. The Actions own the workflows. This is the right post-Phase-2 shape.
2. **Contract surface (12 contracts) has no implementations audit trail**: When a new gateway is added, there's no checklist or contract-to-implementation mapping. 12 contracts × 2 gateways = 24 implementations to verify. A `docs/contract-implementation-matrix.md` would prevent drift.
3. **`CheckoutBuilderContract` remains single-implementation**: Only `CartCheckoutBuilder` exists. The contract is forward-looking but unused for anything else. If no second implementation is planned within 6 months, consider whether it's premature abstraction.
4. **Cart integration wiring direction is unclear**: `CartManagerWithPayment` wraps cart. `CartIntegrationRegistrar` wires it. But the wiring happens in `CashierServiceProvider` which requires `CartManagerWithPayment` to be registered — the registrar's `register()` method takes a `CashierServiceProvider` reference, creating a circular-looking dependency graph. Worth auditing for potential Octane-state leaks.

### Updated recommendation
Audit `CartIntegrationRegistrar` wiring in `CashierServiceProvider` for Octane safety. If no second `CheckoutBuilderContract` implementation is planned, consider consolidating into a single class to reduce indirection.

---

# Cashier friendliness review

This note reviews `packages/cashier` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/GatewayManager.php`
- `src/Gateways/Chip/`
- `src/Gateways/Stripe/`
- `src/Checkout/`
- `src/Contracts` (12 contracts)
- `src/Events` (10 events)
- `src/Support`
- `src/Concerns`
- `src/Billable.php`, `src/Cashier.php`
- downstream consumers in `checkout`, `customers`, `affiliates`, `chip`, `cashier-chip`

## What is already friendly

### Comprehensive contract surface

- `Contracts/GatewayContract.php`
- `Contracts/BillableContract.php`
- `Contracts/CheckoutContract.php`
- `Contracts/CheckoutBuilderContract.php`
- `Contracts/CustomerContract.php`
- `Contracts/PaymentContract.php`
- `Contracts/PaymentMethodContract.php`
- `Contracts/InvoiceContract.php`
- `Contracts/InvoiceLineItemContract.php`
- `Contracts/SubscriptionContract.php`
- `Contracts/SubscriptionItemContract.php`
- `Contracts/SubscriptionBuilderContract.php`

This is the right shape. Every entity in the billing domain is contracted.

### Per-gateway subfolders isolate adapter details

- `Gateways/Chip/` (10 entity classes + `ChipGateway.php`)
- `Gateways/Stripe/` (10 entity classes + `StripeGateway.php`)
- `Gateways/AbstractGateway.php` (shared base)

Each gateway has its own folder with a `Gateway` class and per-entity adapters. Adding a new gateway is a new folder.

### Cart integration is isolated

- `Support/CartManagerWithPayment.php`
- `Support/CartIntegrationRegistrar.php`

Cart composes cashier through a wrapper, not by reaching into cashier models.

### Domain events are explicit

- `Events/PaymentSucceeded`, `PaymentFailed`, `PaymentRefunded`
- `Events/SubscriptionCreated`, `SubscriptionUpdated`, `SubscriptionCanceled`, `SubscriptionRenewed`, `SubscriptionResumed`, `SubscriptionTrialEnding`
- `Events/WebhookReceived`, `WebhookHandled`

The billing domain has a stable event surface for analytics and other consumers.

## Findings

### 1. `GatewayManager` is a likely catch-all orchestrator

**Files**

- `src/GatewayManager.php`

**Why this hurts friendliness**

The gateway manager probably owns the public entry point for resolving gateways, switching between them, and dispatching webhook events. Every caller (controllers, listeners, jobs) goes through it.

**Recommendation**

Audit `GatewayManager` for opportunity to split into Actions. The manager resolves a gateway, the Actions perform the operation. Keep the manager as a thin facade.

### 2. There is no `Actions/` directory

**Why this hurts friendliness**

All billing mutations (create payment, refund, create subscription, cancel subscription) likely live in services or in the gateway classes themselves. This is inconsistent with the monorepo's "Actions only" rule.

**Recommendation**

Introduce a small `src/Actions` tree:

- `Actions/CreatePayment`
- `Actions/RefundPayment`
- `Actions/CreateSubscription`
- `Actions/CancelSubscription`
- `Actions/SyncWebhook`

The gateway classes become thin adapters that delegate to Actions.

### 3. Per-entity adapter classes per gateway create 20+ classes

**Files**

- `Gateways/Chip/{ChipCheckout, ChipCheckoutBuilder, ChipCustomer, ChipInvoice, ChipInvoiceLineItem, ChipPayment, ChipPaymentMethod, ChipSubscription, ChipSubscriptionBuilder, ChipSubscriptionItem}.php`
- `Gateways/Stripe/{StripeCheckout, StripeCustomer, ...}.php`

**Why this hurts friendliness**

Each gateway has 10 entity classes. The contract surface is right, but the per-entity duplication is high. A new gateway means 10 new classes.

**Recommendation**

Consider whether each entity needs its own class or whether a single gateway facade can satisfy the contracts. The per-entity pattern is cleanest when each entity has distinct behavior; collapse when the entities are mostly DTOs.

### 4. `Billable` trait is at the package root, not in `Concerns/`

**Files**

- `src/Billable.php`
- `src/Concerns/ManagesGateway.php`

**Why this hurts friendliness**

`Billable` is the public trait consumers add to their models, but it lives at the package root. The naming convention in the monorepo is `Concerns/` for traits.

**Recommendation**

Move `Billable` to `Concerns/Billable.php` and re-export. This is a structural cleanup that makes the package's public surface clearer.

### 5. Exceptions are siblings without a base class hierarchy

**Files**

- `Exceptions/CashierException.php` (likely base)
- `Exceptions/CheckoutException.php`
- `Exceptions/CustomerNotFoundException.php`
- `Exceptions/GatewayNotFoundException.php`
- `Exceptions/IncompletePayment.php`
- `Exceptions/InsufficientStockException.php`
- `Exceptions/InvalidGatewayException.php`
- `Exceptions/PaymentActionRequired.php`
- `Exceptions/PaymentFailedException.php`
- `Exceptions/SubscriptionNotFoundException.php`
- `Exceptions/SubscriptionUpdateFailure.php`
- `Exceptions/WebhookVerificationException.php`

**Why this hurts friendliness**

12 exception classes. Some are clearly gateway errors, some are validation, some are domain errors. Callers have to know which to catch.

**Recommendation**

Group exceptions by domain:

- `Exceptions/Payment/*`
- `Exceptions/Subscription/*`
- `Exceptions/Webhook/*`
- `Exceptions/Gateway/*`

Each group can have a base class for easier catching.

### 6. Cart integration has a wrapper but the wiring is unclear

**Files**

- `src/Support/CartManagerWithPayment.php`
- `src/Support/CartIntegrationRegistrar.php`

**Why this hurts friendliness**

The wrapper is good, but the registrar likely wires it into the cart package. The wiring is in foundation or in cart. The boundary needs to be clear.

**Recommendation**

Document the integration in `docs/`. Confirm the registrar is the canonical entry point for cart to discover cashier.

### 7. `CheckoutBuilderContract` is the right pattern, but only one implementation

**Files**

- `Checkout/CartCheckoutBuilder.php` (impl `Contracts/CheckoutBuilderContract.php`)

**Why this hurts friendliness**

A real contract, but only one implementation. The contract is correct in shape — it just needs more implementations to be useful. The contract is forward-looking; keep it.

### 8. No `Console/Commands` directory

**Why this hurts friendliness**

Bulk operations (subscription renewals, payment retries, webhook replays) have no clear owner. Some of this may live in `cashier-chip` (which has commands), but cashier itself is command-less.

**Recommendation**

Add a `src/Console/Commands` directory when the first batch operation is needed. Consider whether `cashier-chip`'s commands (`RenewSubscriptionsCommand`) should be moved up to `cashier`.

## Concrete refactor plan

### Phase 1 — move `Billable` to `Concerns/`

**Steps**

1. Move `src/Billable.php` to `src/Concerns/Billable.php`.
2. Re-export from the package root if needed for BC.
3. Update docs.

### Phase 2 — introduce the Actions tree

**Steps**

1. Add `src/Actions/CreatePayment`, `RefundPayment`, `CreateSubscription`, `CancelSubscription`, `SyncWebhook`.
2. Move mutations from gateway classes and `GatewayManager` to Actions.
3. Add tests for each Action.

### Phase 3 — group exceptions

**Steps**

1. Move exceptions to `Exceptions/{Payment, Subscription, Webhook, Gateway}/`.
2. Add base classes per group.
3. Update catch sites.

### Phase 4 — audit per-entity adapters

**Steps**

1. Audit the 20 per-entity classes.
2. Collapse DTO-only adapters into a shared base.
3. Keep only the ones with distinct behavior.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — move `Billable` to `Concerns/`

- [done] Move `src/Billable.php` to `src/Concerns/Billable.php`.
- [done] Re-export from the package root if needed for BC.
- [done] Update docs — completed by Phase 5: `docs/04-usage.md` now documents the two-trait Billable pattern (section 4) and the contracts-to-implementations matrix (section 5).

### Phase 2 — introduce the Actions tree

- [done] Add `src/Actions/CreatePayment`, `RefundPayment`, `CreateSubscription`, `CancelSubscription`, `SyncWebhook`.
- [done] Move mutations from gateway classes and `GatewayManager` to Actions.
- [done] Add tests for each Action.

### Phase 3 — group exceptions

- [done] Move exceptions to `Exceptions/{Payment, Subscription, Webhook, Gateway}/`.
- [done] Add base classes per group.
- [done] Update catch sites (BC aliases preserved at old paths).

### Phase 4 — audit per-entity adapters

- [done] Audit the 20 per-entity classes.
- [done] Collapse DTO-only adapters into a shared base (all 20 wrap fundamentally different gateway types — none can be collapsed).
- [done] Keep only the ones with distinct behavior (all 20 have distinct gateway-specific behavior).

### Phase 5 — document billable traits and contract matrix

- [done] Update docs to explain the two `Billable` traits: root `Billable` = gateway management entrypoint, `Concerns/Billable` = model query helpers.
- [done] Document which trait consumers should use on their models (root `Billable`).
- [done] Add contracts-to-implementations audit matrix in docs (12 contracts × 2 gateways = 24 implementations to verify).

### Phase 6 — audit wiring and consolidation

- [done] Audit `CartIntegrationRegistrar` wiring in `CashierServiceProvider` for Octane safety.

**Audit result:** The `extend()` guard (`if ($manager instanceof CartManagerWithPayment)`) prevents re-wrapping on subsequent requests. Event listeners are registered via config-gated closures. The existing `registerOctaneListeners()` handler already restores defaults on `RequestReceived`. Octane-safe with documentation comment added.

- [done] Evaluate consolidating `CheckoutBuilderContract` into a single class if no second implementation is planned within 6 months.

**Evaluation:** `CartCheckoutBuilder` orchestrates cart→payment flow (inventory allocation, stock validation, metadata preservation). The contract provides the correct extension seam for custom checkout flows. Keep the contract — it's an intentional extension point.

### Phase 7 — prepare for batch operations

- [done] Add `Console/Commands/` directory with `WebhookReplayCommand` using `OwnerBatchRunner` integration.
- [done] Evaluate whether `cashier-chip`'s `RenewSubscriptionsCommand` should move up to `cashier` (gateway-agnostic).

**Evaluation:** `RenewSubscriptionsCommand` in cashier-chip is CHIP-specific by nature (renews CHIP subscriptions via CHIP API). Moving it up to cashier would require a gateway-agnostic abstraction that doesn't yet exist. Keep in cashier-chip for now; the `WebhookReplayCommand` in cashier provides the cross-gateway batch surface.



## Suggested verification scope

- per-Action tests
- gateway contract tests
- cart integration tests
- downstream tests for checkout/customers/affiliates

## Recommended first move

Phase 2 — introduce the Actions tree. The package has a strong contract surface but no Actions. The split is the highest-leverage cleanup and unblocks the gateway audit.
