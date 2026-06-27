# Audit: `orders` (AIArmada\Orders)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Order records, payments, refunds, notes, invoices, and order state transitions.

**Surface:** domain

---

## Findings

### Low
1. **No exception hierarchy** — 10 actions, 14 events, 12 states, but zero custom exceptions. Uses `InvalidArgumentException` and `RuntimeException` directly. An orders package handling payments and refunds should have at least `OrderException` base.
2. **No in-package tests** — 27 test files in monorepo `tests/src/Orders/`, none inside the package.
3. **No routes or facades** — All interaction through injected Actions and `OrderServiceInterface`.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ None | All 6 models use `$fillable` exclusively |
| Money storage | ✅ Integer minor units | `unsignedBigInteger` for all monetary values (subtotal, grand_total, etc.) |
| State machine | ✅ Spatie `HasStates` | 12 states with 7 transition classes — full lifecycle (Created → PendingPayment → Processing → Shipped → Delivered → Completed) |
| Enums | ✅ 3 enums | `PaymentStatus`, `OrderItemStatus`, `RefundStatus` — all with `label()`, `color()`, `isFinal()` |
| Contracts | ✅ 4 interfaces | `OrderServiceInterface`, `PaymentHandler`, `FulfillmentHandler`, `InventoryHandler` |
| Events | ✅ 14 events | Full lifecycle coverage — created, paid, shipped, delivered, completed, canceled, refunded, payment failed, inventory, commission |
| Actions | ✅ 10 classes | Create, cancel, complete, payment, refund, invoice, receipt, document determination |
| Owner scoping | ✅ All models | `HasOwner` + `HasOwnerScopeConfig`; child records infer owner from parent Order via `OwnerWriteGuard` |
| Immutable dates | ✅ `CarbonImmutable` | All lifecycle timestamp casts |
| `booted()` cascades | ✅ Order model | `deleting` cascades to items, addresses, payments, refunds, notes |
| PDF invoices | ✅ spatie/laravel-pdf + Browsershot | Invoice generation with dedicated GenerateInvoice action |
| Tests | ✅ 27 Pest files | Model, action, transition, state machine, owner scoping, policies, health check, notifications, docs integration |
| Translations | ✅ en + ms | State labels translated (6 files) |
| Docs | ✅ 7 files | Including dedicated state-machine.md and api-reference.md |

---

## Summary

Well-engineered order management package: 6 models (Order, OrderItem, OrderAddress, OrderPayment, OrderRefund, OrderNote), 12-state Spatie state machine with 7 transition classes, 14 events, 10 actions, 4 handler contracts for extensible integrations (payment, fulfillment, inventory), 3 enums, PDF invoice generation.

Money stored as integer minor units (`unsignedBigInteger`) throughout. Owner scoping on all models with child records inheriting owner from parent Order via `OwnerWriteGuard`. `booted()` cascade on Order (`deleting` → children). Lifetime transitions are fully defined at the state level (`canCancel()`, `canRefund()`, `canModify()`, `isFinal()`).

Integration surface is well-designed — `PaymentHandler`, `FulfillmentHandler`, `InventoryHandler` contracts registered via `OrderHandlerRegistrar` for one-time binding. Integrations with inventory (deduct on payment, release on cancel), docs (auto-create invoice/receipt docs), and affiliates (commission attribution) are gated on config.

27 test files cover models, actions, state machine, transitions, owner scoping, policies, health check, notifications, and docs integration.

**Verdict:** Ready. Strong state machine, money as integers, well-abstracted integrations, comprehensive tests. No `$guarded` issues.
