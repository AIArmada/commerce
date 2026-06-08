# Cashier-Chip friendliness review

This note reviews `packages/cashier-chip` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- top-level entity classes (`Billable`, `Cashier`, `Checkout`, `CheckoutBuilder`, `Coupon`, `Discount`, `Invoice`, `InvoiceLineItem`, `InvoicePayment`, `Payment`, `PaymentMethod`, `PaymentMethodStore`, `PromotionCode`, `StoredPaymentMethod`, `Subscription`, `SubscriptionBuilder`, `SubscriptionItem`)
- `src/Concerns` (10 traits)
- `src/Contracts`
- `src/Events` (5 events)
- `src/Listeners` (4 classes)
- `src/Console` (2 commands)
- `src/Invoices`
- `src/Testing`
- downstream consumers in `checkout`, `customers`, `affiliates`, `chip`

## What is already friendly

### Concerns group gateway-specific behavior

- `Concerns/AllowsCoupons`
- `Concerns/HandlesPaymentFailures`
- `Concerns/InteractsWithChip`
- `Concerns/InteractsWithPaymentBehavior`
- `Concerns/ManagesCustomer`
- `Concerns/ManagesInvoices`
- `Concerns/ManagesPaymentMethods`
- `Concerns/ManagesSubscriptions`
- `Concerns/PerformsCharges`
- `Concerns/Prorates`

This is a strong pattern. Each concern is a focused trait. The `Billable` model composes the right set.

### Listeners react to CHIP events

- `Listeners/HandleBillingCancelled`
- `Listeners/HandlePurchasePaid`
- `Listeners/HandlePurchasePaymentFailure`
- `Listeners/HandlePurchasePreauthorized`
- `Listeners/HandleSubscriptionChargeFailure`

The package reacts to CHIP domain events through listeners, not by reaching into chip models.

### Test infrastructure is in place

- `Testing/FakeChipClient.php`
- `Testing/FakeChipCollectService.php`

Fake clients are the right shape for testing.

## Findings

### 1. There is no `Actions/` directory

**Why this hurts friendliness**

The package owns CHIP-specific billing workflows (charge, refund, create subscription, cancel subscription). These likely live inline in `Concerns/`, in listeners, or in the entity classes themselves. The monorepo rule is "Actions only."

**Recommendation**

Introduce a small `src/Actions` tree:

- `Actions/ChargeChipCustomer`
- `Actions/RefundChipPayment`
- `Actions/CreateChipSubscription`
- `Actions/CancelChipSubscription`
- `Actions/SyncChipPurchaseStatus`

The concerns stay as focused traits. The Actions own the orchestration.

### 2. `Console/Commands` exists but the owner-loop pattern is unclear

**Files**

- `src/Console/RenewSubscriptionsCommand.php`
- `src/Console/WebhookCommand.php`

**Why this hurts friendliness**

These are likely batch operations. If the renewal command iterates per owner, it duplicates the affiliates/signals/chip pattern.

**Recommendation**

Use `commerce-support`'s `OwnerBatchRunner` (when it lands) for `RenewSubscriptionsCommand`. Audit the webhook command for the same pattern.

### 3. Top-level entity classes are 17 sibling files

**Files**

- 17 classes at the package root: `Billable`, `Cashier`, `Checkout`, `CheckoutBuilder`, `Coupon`, `Discount`, `Invoice`, `InvoiceLineItem`, `InvoicePayment`, `Payment`, `PaymentMethod`, `PaymentMethodStore`, `PromotionCode`, `StoredPaymentMethod`, `Subscription`, `SubscriptionBuilder`, `SubscriptionItem`

**Why this hurts friendliness**

The package root is crowded. It's hard to see which class is the public entry point and which are helpers.

**Recommendation**

Group by domain:

- `Billing/` (Billable, Cashier, Checkout, CheckoutBuilder, Coupon, Discount, PromotionCode)
- `Payment/` (Payment, PaymentMethod, PaymentMethodStore, StoredPaymentMethod, InvoicePayment)
- `Subscription/` (Subscription, SubscriptionBuilder, SubscriptionItem)
- `Invoice/` (Invoice, InvoiceLineItem, Invoices/DocsInvoiceRenderer)
- `Concerns/` (move the 10 traits here; this is already correct)

Each group is a focused namespace.

### 4. `Contracts/InvoiceRenderer.php` is a real seam

**Files**

- `Contracts/InvoiceRenderer.php`
- `Invoices/DocsInvoiceRenderer.php`

**Why this is worth noting**

This is the right pattern. The renderer is contracted; `DocsInvoiceRenderer` is one implementation. Adding a new renderer (PDF, email HTML, fiscal printer) is a new class.

### 5. `Testing/FakeChipClient` and `FakeChipCollectService` may overlap

**Files**

- `src/Testing/FakeChipClient.php`
- `src/Testing/FakeChipCollectService.php`

**Why this hurts friendliness**

If both classes fake CHIP behavior, they may duplicate effort. The split between "client" and "collect service" is unclear.

**Recommendation**

Audit both. Pick the canonical faker. If both are needed, document the relationship (e.g., client = HTTP, collect service = business logic).

### 6. `Concerns/InteractsWithChip` likely duplicates `Concerns/InteractsWithPaymentBehavior`

**Files**

- `src/Concerns/InteractsWithChip.php`
- `src/Concerns/InteractsWithPaymentBehavior.php`

**Why this hurts friendliness**

Two traits that may both own CHIP-specific behavior. If both grow, the rules will drift.

**Recommendation**

Audit both. Pick the canonical concern. Collapse the other.

### 7. Events are 5 (out of cashier's 10)

**Files**

- `Events/PaymentSucceeded`, `PaymentFailed`
- `Events/SubscriptionCreated`, `SubscriptionCanceled`, `SubscriptionRenewed`
- `Events/SubscriptionRenewalFailed`

**Why this hurts friendliness**

Cashier emits more events. cashier-chip emits fewer. Listeners that depend on cashier events may not fire when called through cashier-chip.

**Recommendation**

Audit which cashier events are emitted by cashier-chip. Document gaps. Either emit the missing events from cashier-chip, or document that some events are cashier-only.

### 8. `PaymentMethodStoreInterface` is the right contract

**Files**

- `Contracts/PaymentMethodStoreInterface.php`
- `PaymentMethodStore.php` (top-level class)

**Why this is worth noting**

The contract shape is right. The implementation lives at the package root. Move the impl into `Payment/` (per Phase 3 of refactor plan).

## Concrete refactor plan

### Phase 1 — introduce the Actions tree

**Steps**

1. Add `src/Actions/ChargeChipCustomer`, `RefundChipPayment`, `CreateChipSubscription`, `CancelChipSubscription`, `SyncChipPurchaseStatus`.
2. Move orchestration out of concerns, listeners, and entity classes.
3. Add tests for each Action.

### Phase 2 — adopt owner-batch helper

**Steps**

1. Wait for `commerce-support`'s `OwnerBatchRunner`.
2. Migrate `RenewSubscriptionsCommand`.
3. Audit `WebhookCommand` for the same pattern.

### Phase 3 — group top-level entity classes

**Steps**

1. Create `Billing/`, `Payment/`, `Subscription/`, `Invoice/` namespaces.
2. Move entity classes.
3. Update imports.

### Phase 4 — audit potential duplicates

**Steps**

1. Compare `InteractsWithChip` to `InteractsWithPaymentBehavior`.
2. Compare `FakeChipClient` to `FakeChipCollectService`.
3. Pick the canonical owner for each pair.

### Phase 5 — align event coverage with cashier

**Steps**

1. Audit which cashier events are emitted from cashier-chip.
2. Emit missing events or document the gap.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — introduce the Actions tree

- [pending] Add `src/Actions/ChargeChipCustomer`, `RefundChipPayment`, `CreateChipSubscription`, `CancelChipSubscription`, `SyncC...
- [pending] Move orchestration out of concerns, listeners, and entity classes.
- [pending] Add tests for each Action.

### Phase 2 — adopt owner-batch helper

- [pending] Wait for `commerce-support`'s `OwnerBatchRunner`.
- [pending] Migrate `RenewSubscriptionsCommand`.
- [pending] Audit `WebhookCommand` for the same pattern.

### Phase 3 — group top-level entity classes

- [pending] Create `Billing/`, `Payment/`, `Subscription/`, `Invoice/` namespaces.
- [pending] Move entity classes.
- [pending] Update imports.

### Phase 4 — audit potential duplicates

- [pending] Compare `InteractsWithChip` to `InteractsWithPaymentBehavior`.
- [pending] Compare `FakeChipClient` to `FakeChipCollectService`.
- [pending] Pick the canonical owner for each pair.

### Phase 5 — align event coverage with cashier

- [pending] Audit which cashier events are emitted from cashier-chip.
- [pending] Emit missing events or document the gap.



## Suggested verification scope

- per-Action tests
- fake client tests
- listener tests
- cross-package tests for checkout/customers/affiliates

## Recommended first move

Phase 1 — introduce the Actions tree. The package has concerns and listeners but no Actions, and the monorepo rule is consistent. The split is mostly mechanical.
