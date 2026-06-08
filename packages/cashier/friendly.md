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

- [pending] Move `src/Billable.php` to `src/Concerns/Billable.php`.
- [pending] Re-export from the package root if needed for BC.
- [pending] Update docs.

### Phase 2 — introduce the Actions tree

- [pending] Add `src/Actions/CreatePayment`, `RefundPayment`, `CreateSubscription`, `CancelSubscription`, `SyncWebhook`.
- [pending] Move mutations from gateway classes and `GatewayManager` to Actions.
- [pending] Add tests for each Action.

### Phase 3 — group exceptions

- [pending] Move exceptions to `Exceptions/{Payment, Subscription, Webhook, Gateway}/`.
- [pending] Add base classes per group.
- [pending] Update catch sites.

### Phase 4 — audit per-entity adapters

- [pending] Audit the 20 per-entity classes.
- [pending] Collapse DTO-only adapters into a shared base.
- [pending] Keep only the ones with distinct behavior.



## Suggested verification scope

- per-Action tests
- gateway contract tests
- cart integration tests
- downstream tests for checkout/customers/affiliates

## Recommended first move

Phase 2 — introduce the Actions tree. The package has a strong contract surface but no Actions. The split is the highest-leverage cleanup and unblocks the gateway audit.
