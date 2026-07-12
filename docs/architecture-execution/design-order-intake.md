# Design Record: Order Intake Identity & Transaction Ownership

- **Task:** DES-ORD-210
- **Date:** 2026-07-12
- **Status:** Proposed
- **Chosen design:** Design B — Composite `(intake_source, intake_id)` unique per owner

## Observed facts

1. **No durable intake identity on Order:** The orders table has `order_number` (unique) but no column that links an Order to the caller's intake request. `CreateOrderStep` stores `session.order_id` on the CheckoutSession model as the retry identity (line 76: "Reuse existing order if already created for this session"), then depend on that ambient state. If the session is lost, two concurrent CreateOrderStep calls can both call `createOrder()` before either saves `session.order_id`, producing duplicate Orders.

2. **OrderCreated dispatched inside DB transaction:** `CreateOrder::execute()` (line 61) dispatches `OrderCreated` synchronously inside the `DB::transaction()` block. Listeners that perform external I/O (HTTP calls, queue dispatches) execute inside the uncommitted transaction. If the transaction rolls back, listeners have already emitted side effects.

3. **CreateOrder owns order + items + addresses atomically:** `CreateOrder::execute()` creates the Order, all items, billing address, shipping address, and performs a state transition within one local DB transaction (lines 31-64). This is correct for local atomicity.

4. **Payment registration is separate from intake:** `CreateOrderStep::handle()` creates the order (line 102), then separately calls `confirmPayment()` (lines 119-126) which triggers `PaymentConfirmed` transition. Payment registration is NOT part of the `CreateOrder` transaction — it's a separate action. However, the Checkout step wraps both in the same outer `DB::transaction` from `CheckoutService::processCheckout()`.

5. **CreateOrderStep has side effects scattered in its handle method:** After order creation and payment confirmation, the step marks completion, redeems vouchers, commits inventory reservations, clears the cart, and finalizes free orders (lines 128-156). This is the "scattered finalization" addressed by DES-FIN-710, but is relevant because CreateOrderStep is the only call site that currently depends on `session.order_id` for retry safety.

6. **No concurrent-safe retry:** `CreateOrder::execute()` has no guard against concurrent duplicate calls. Two simultaneous `createOrder()` calls with the same data will produce two Orders. The only protection is the `session.order_id` check in CreateOrderStep, which is an in-memory/in-request check, not a database guarantee.

## Inferences

1. Order intake needs a caller-provided identity that is enforced at the database level with a unique constraint. The `session.order_id` pattern is brittle — it only works within one CheckoutSession's lifecycle and offers no protection against concurrent requests or session loss.

2. `OrderCreated` must be dispatched after the local DB transaction commits. Currently it fires inside the transaction, so listeners that produce side effects (email, webhooks, analytics) could observe uncommitted data or fail without the transaction knowing.

3. Payment registration should remain separate from intake. The intake module materializes the Order. Payment confirmation is a distinct lifecycle transition. Mixing them would blur the intake transaction boundary.

4. The intake identity should be generic enough to support checkout, API, admin, and CSV-import callers — not tied to CheckoutSession.

5. The intake result must distinguish three outcomes: (a) new order created, (b) exact retry — same data, same order, (c) conflicting retry — same identity, different data.

## Design alternatives

### Design A: Single `intake_reference` column

Add an `intake_reference` (varchar, nullable, unique per owner) to the orders table. The caller provides any string (checkout session ID, external reference, UUID). `CreateOrder` accepts an optional `intake_reference` parameter.

```php
// Unique index: (intake_reference, owner_type, owner_id) WHERE intake_reference IS NOT NULL
```

Pros: One column, simple API.
Cons: No semantic distinction between sources. Ambiguous when two different systems generate the same reference format. `intake_reference IS NULL` rows (direct API/admins) offer no retry safety — acceptable but worth noting.

### Design B: Composite `(intake_source, intake_id)` columns (Chosen)

Add two columns: `intake_source` (varchar: 'checkout', 'api', 'admin', 'import') and `intake_id` (varchar). Unique constraint on `(intake_source, intake_id, owner_type, owner_id)`. Both are nullable — null intake means "not idempotent."

```php
// Unique index: (intake_source, intake_id, owner_type, owner_id)
// WHERE intake_source IS NOT NULL AND intake_id IS NOT NULL
```

`CreateOrder` accepts optional `intakeSource` and `intakeId` parameters. If provided, checks for existing order → returns it (exact retry) or throws on conflicting data.

### Design C: Separate `OrderIntake` record

Create an `OrderIntake` model with a unique reference. The intake record is created first (outside the Order transaction). The Order is lazily materialized from the intake. Retries create one intake → one Order.

```php
$intake = OrderIntake::firstOrCreate([...identity...], [...data...]);
if ($intake->order_id) { return $intake->order; }
// Create order and link back
```

Pros: Intake record can be persisted before any order processing. Clean separation of concerns.
Cons: Two models, two tables, more code paths. Over-engineered for what is essentially a unique constraint on the Order itself.

## Comparison

| Dimension | A (Single reference) | B (Composite source+id) | C (Separate intake) |
|-----------|---------------------|------------------------|---------------------|
| Depth | Shallow: one column | Deep: semantic source + id | Deeper: intake before order |
| Leverage | Medium: simple unique constraint | High: covers all caller types | Low: new model for existing concept |
| Locality | High: one migration, one column | High: two columns, one migration | Low: new model, new table, more code |
| Caller knowledge | Low: any string | Low: semantic source + caller ID | Medium: new model to understand |
| Test surface | Small: unique constraint test | Small: unique constraint + source validation | Large: new model CRUD, lifecycle |
| Migration cost | Low | Low | Medium |

## Chosen design

**Design B — Composite `(intake_source, intake_id)` columns, unique per owner.**

### Rationale

1. **Semantic clarity:** `intake_source` distinguishes checkout from API from admin from import. This prevents accidental collisions between systems that use similar ID formats.

2. **Minimal abstraction:** Two columns on the existing Order table. No new models. Mirrors the pattern used for `owner_type`/`owner_id` morphs.

3. **Database-level guarantee:** The unique constraint serializes concurrent attempts. No in-memory check can race.

4. **Retry behavior is explicit:**
   - Exact retry (same source+id, same data) → returns existing Order, success result.
   - Conflicting retry (same source+id, different items/amounts/customer) → throws `OrderIntakeConflictException`.
   - No intake identity → creates new Order every time (backward-compatible for API/admin callers).

5. **Payment registration stays out of intake:** The intake module creates the Order and its items. Payment confirmation is a separate Action/transition that consumes the Order. This matches the existing separation in `CreateOrderStep`.

### External interface (post-design)

`OrderServiceInterface::createOrder()` gains optional `$intakeSource` and `$intakeId` parameters:

```php
public function createOrder(
    array $orderData,
    array $items,
    ?array $billingAddress = null,
    ?array $shippingAddress = null,
    ?string $intakeSource = null,
    ?string $intakeId = null,
): Order;
```

Internal behavior:
1. If `$intakeSource` and `$intakeId` are provided, search for existing Order with matching columns.
2. If found with identical data → return existing Order (idempotent).
3. If found with conflicting data → throw `OrderIntakeConflictException`.
4. If not found → create new Order inside `DB::transaction`.
5. `OrderCreated` event dispatched via `DB::afterCommit`, not inside the transaction.

### Migration

```sql
ALTER TABLE orders ADD COLUMN intake_source VARCHAR(50) NULL;
ALTER TABLE orders ADD COLUMN intake_id VARCHAR(255) NULL;
CREATE UNIQUE INDEX orders_intake_unique ON orders (intake_source, intake_id, owner_type, owner_id)
  WHERE intake_source IS NOT NULL AND intake_id IS NOT NULL;
```

Wait — MySQL partial unique indexes require `WHERE` on indexed columns. SQLite doesn't support partial unique indexes. Let me use a different approach: make the unique index on `(intake_source, intake_id, owner_type, owner_id)` with NULL values permitted. In MySQL, NULL values are considered distinct in unique indexes, so multiple NULL rows won't conflict. In SQLite, this is also true since SQLite 3.8+. Laravel's schema builder can handle this.

### Invariants

- Order identity is immutable after creation. `intake_source` and `intake_id` are never updated.
- Order intake owns the local Order, items, and addresses transaction. IT does not own payment, inventory, or discount commitments.
- `OrderCreated` is emitted exactly once, after the local transaction commits.
- Exact retry: same intake identity with matching data returns the existing Order.
- Conflicting retry: throws. Caller can handle or escalate.

### Errors, transactions, retries

- **New intake:** Creates Order in a local DB transaction. Items and addresses are atomically included.
- **Exact retry:** Short-circuits before any mutation. Returns existing Order.
- **Conflicting retry:** `OrderIntakeConflictException` with details of the conflict.
- **Concurrent new intake:** One caller wins the unique constraint race. The loser retries, finds the existing Order, and returns it (exact retry) or throws (conflict).

## Implementation scope manifest

### Files to modify
- `packages/orders/src/Actions/CreateOrder.php` — add intake identity params, pre-existence check, afterCommit event dispatch
- `packages/orders/src/Actions/CreateOrderFromCart.php` — pass intake identity from cart data
- `packages/orders/src/Contracts/OrderServiceInterface.php` — add intake params to `createOrder()` signature
- `packages/orders/src/Models/Order.php` — add `intake_source`, `intake_id` to fillable/casts
- `packages/orders/database/migrations/» — new migration: add intake columns + unique index
- `packages/checkout/src/Steps/CreateOrderStep.php` — pass `checkout` + `session.id` as intake identity, remove `session.order_id` retry
- `packages/orders/docs/04-usage.md` — document intake identity and retry behavior

### Files to create
- `packages/orders/src/Exceptions/OrderIntakeConflictException.php` (new)

### Tests to create/update
- `tests/src/Orders/OrderIntakeTest.php` (new — exact retry, conflict, concurrent, no-intake backward compat)
- `tests/src/Orders/OrderTransitionsTest.php` — add OrderCreated afterCommit timing assertion
- `tests/src/Checkout/CreateOrderStepTest.php` — verify intake identity passed from checkout

## Rejected alternatives

### Design A (Single reference)
Rejected: insufficient semantic distinction between callers. Two different systems (e.g., API and CSV import) could inadvertently generate the same reference format. The `source` column costs one additional column and prevents this collision class.

### Design C (Separate OrderIntake model)
Rejected: over-engineers a unique constraint. The intake identity is a property of the Order, not a separate entity. A new model adds code paths, test surface, and cognitive overhead for zero functional gain over the composite unique index.

## Unknowns

- Whether `CreateOrderStep` is the only caller that needs concurrent retry safety. API-created orders (via filament, admin, or public API) may also benefit from intake identity but don't currently have it. The design adds the columns as optional — direct callers that don't provide intake identity get the old behavior (new Order every call).
