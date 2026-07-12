# Design Record: Reference-Centred Inventory Reservation and Commitment

- **Task:** DES-INV-310
- **Date:** 2026-07-12
- **Status:** Proposed — implementation gated on reviewer approval
- **Chosen design:** Design B — durable Inventory-owned reservation group with reference-only Checkout contract

## Observed facts

1. `CheckoutInventoryServiceInterface` exposes both per-allocation and reference-wide commands: `reserve()` returns an `id`, `releaseReservation()` and `commitReservation()` accept that id, while `releaseAllForReference()` and `commitAllForReference()` act on the whole reference (`packages/inventory/src/Contracts/CheckoutInventoryServiceInterface.php:25-80`). The two ownership levels make callers choose an internal allocation identity even when the intended operation is cart-wide.
2. `CheckoutInventoryService::reserve()` asks `InventoryAllocationService` to allocate by cart/reference, potentially receives several rows, then returns only the first allocation id (or the reference when no first row exists) (`packages/inventory/src/Integrations/CheckoutInventoryService.php:52-91`). That returned id cannot describe a split allocation.
3. `InventoryAdapter::reserve()` fabricates `mock_<uniqid>` when the optional Inventory package is absent (`packages/checkout/src/Integrations/InventoryAdapter.php:28-48`). The fake looks like a durable provider handle even though no provider accepted a reservation.
4. `ReserveInventoryStep` reserves every cart item separately, records `reservation_id` per item in `pricing_data`, and on an error attempts individual releases (`packages/checkout/src/Steps/ReserveInventoryStep.php:117-182`). Its rollback instead releases the complete cart reference (`packages/checkout/src/Steps/ReserveInventoryStep.php:184-201`).
5. The later order step only checks whether the per-item array is non-empty, then commits the whole cart reference once (`packages/checkout/src/Steps/CreateOrderStep.php:313-330`). The stored IDs are not used for the actual commitment.
6. `InventoryAllocationService::allocate()` creates one allocation per selected location and rolls all of its just-created rows back when a requested quantity cannot be fulfilled (`packages/inventory/src/Services/Stock/InventoryAllocationService.php:50-143`). Its `commit()` creates movements and deletes allocations (`packages/inventory/src/Services/Stock/InventoryAllocationService.php:282-337`); `releaseAllForCart()` also deletes allocations (`packages/inventory/src/Services/Stock/InventoryAllocationService.php:231-270`). Deletion leaves no durable proof that a later call is an exact retry rather than a never-reserved reference.
7. Wave 0 added `InventoryOperation`, whose `(order_id, kind)` uniqueness and locked completion record make Order-event deductions/releases retry-safe (`packages/inventory/database/migrations/2026_07_12_000001_create_inventory_operations_table.php:11-26`; `packages/inventory/src/Listeners/DeductInventoryFromOrder.php:38-109`). That identity begins only after an Order exists, so it cannot prove the lifecycle of a pre-payment reservation reference.
8. Inventory is an optional integration: Checkout explicitly checks whether its contract exists before resolving it (`packages/checkout/src/Integrations/InventoryAdapter.php:145-158`), and the reservation step can currently skip when its adapter is absent (`packages/checkout/src/Steps/ReserveInventoryStep.php:56-78`). Optionality is legitimate; pretending a provider allocated stock is not.

## Inferences

1. **Inference:** The canonical checkout-facing reservation identity must be the caller-provided reference, scoped by owner, not any allocation primary key. Allocation IDs and locations are Inventory implementation details.
2. **Inference:** A durable group/header is required because allocation deletion otherwise makes `committed`, `released`, and `never existed` observationally indistinguishable. Wave 0's operation pattern provides the appropriate retry precedent but not the required pre-order identity.
3. **Inference:** Inventory owns reservation state transitions and evidence. Checkout (and, after DES-FIN-710, the finalization module) owns when to request those transitions; it must not interpret allocation rows or coordinate a list of them.
4. **Inference:** The single commitment caller must be the future Checkout finalization module, after it has a durable Order intake result. The Order payment transition must not also issue the same reservation command; it lacks the Checkout reservation reference and would reintroduce duplicate paths.
5. **Inference:** Inventory absence is a capability result, not a successful reservation. Checkout may proceed under its configured unlimited-stock policy only after receiving an explicit `not_managed` result; it must persist no faux provider handle.

## Design alternatives

### Design A — keep allocations public, return all allocation IDs

Change `reserve()` to return every allocation UUID and store that array in Checkout. Commit and release would iterate the UUIDs, optionally with an aggregate helper.

### Design B — durable Inventory reservation group, reference-only contract (chosen)

Add one Inventory-owned reservation-group record keyed by owner plus Checkout reference. The group owns zero or more split allocation rows and retains terminal status after allocations are deleted. Checkout sends an item set once for the reference and receives a domain outcome keyed by the same reference; only group-level release and commitment are public.

### Design C — Checkout-owned reservation ledger

Keep Inventory allocations unchanged, but let Checkout persist a reservation ledger containing allocation UUIDs, state, expiry, and an order link. Checkout would derive retry outcomes and call Inventory once per saved allocation.

| Dimension | A — public allocation collection | B — Inventory reservation group | C — Checkout ledger |
| --- | --- | --- | --- |
| Depth | Shallow API reshaping; internal IDs remain public | Adds one domain aggregate exactly where allocation semantics live | Adds a second lifecycle projection outside Inventory |
| Leverage | Low; each caller still understands split allocation | High; one identity works for reserve, release, commit, expiry, and observability | Medium; could coordinate Checkout only, not other reservation callers |
| Locality | Poor; Checkout must coordinate Inventory rows | High; split allocation remains local to Inventory | Poor; Checkout couples to Inventory storage semantics |
| Caller knowledge | High; callers must retain and retry per-row IDs | Low; callers know reference, requested lines, and outcome only | High; callers know allocation persistence and recovery rules |
| Test surface | Large cart-item × location/retry matrix | Focused group state-machine and adapter contract tests | Large cross-package ledger consistency matrix |
| Migration cost | Medium; public compatibility mapping required | Medium; new header, adapter/step migration, obsolete APIs removed | High; Checkout migration plus reconciliation/repair tooling |

## Chosen design

### Recommendation and ownership

Choose **Design B**. Introduce the Inventory module `CheckoutReservationServiceInterface` and a durable `InventoryReservation` aggregate/header. `InventoryAllocation` remains the private, location-level holding mechanism; it belongs to exactly one active reservation group but is never returned to Checkout.

The dependency is an **optional integration contract**: Inventory supplies an implementation only when installed; Checkout's adapter supplies its own explicit `not_managed` outcome when it is not. The seam is justified by two genuine adapters (Inventory installed / Inventory unavailable), not by a configurable registry.

`FinalizeCheckoutSession` (the module designed by DES-FIN-710) is the sole orchestrator that invokes `commit(reference, orderId)`. It does so after DES-ORD-210's order-intake transaction returns an Order. Inventory exclusively owns the local atomic transition and stock mutation. Payment transitions and Order listeners must not invoke checkout-reservation commitment.

### External interface

```php
interface CheckoutReservationServiceInterface
{
    /** @param list<ReservationLine> $lines */
    public function reserve(string $reference, array $lines, int $ttlSeconds): ReservationOutcome;

    public function release(string $reference): ReservationOutcome;

    public function commit(string $reference, string $orderId): ReservationOutcome;

    public function extend(string $reference, int $ttlSeconds): ReservationOutcome;

    public function find(string $reference): ReservationOutcome;
}
```

`ReservationLine` contains only `product_id`, nullable `variant_id`, and positive `quantity`. `ReservationOutcome` contains `reference`, `state` (`not_managed`, `reserved`, `committed`, `released`, `expired`), `expires_at`, nullable `order_id`, and line-level requested/reserved quantities. It never contains allocation IDs, level IDs, or location IDs. Quantities are integers; no money crosses this contract.

`not_managed` is produced only by Checkout's unavailable-integration adapter. It is not persisted as Inventory state and is explicitly distinguishable from `reserved`.

### Group state and invariants

- Identity is `(owner_type, owner_id, reference)`; references are immutable and unique per owner. Explicit global context is required for global rows, consistent with the owner contract.
- A single `reserved` group represents all location splits for every requested line. Aggregate outcome quantities are calculated from the group/allocations, never by exposing the split.
- A same-reference `reserve()` with identical normalized lines and TTL returns the existing `reserved` outcome. A different line set or a different active reservation intent raises `ReservationReferenceConflict`; it does not append a second set of allocations.
- `commit(reference, orderId)` is legal only from `reserved`. It makes shipment movements, decreases stock, removes allocation rows, records `committed` plus `order_id`, and preserves the outcome summary.
- `release(reference)` is legal only from `reserved` (and is the expiry worker's transition); it restores reserved quantities, removes allocation rows, and records `released` or `expired`.
- An exact terminal retry returns the stored outcome: commit of the same committed reference/order and release of the same released/expired reference are no-ops. Commit with a different order ID or a transition from released/expired; and release after commitment, raise typed transition/conflict errors rather than silently changing history.
- `extend()` changes only an active `reserved` group. Terminal groups return their stored outcome; they are not resurrected.
- The group and all related allocation rows are owner-scoped on every read and write. Allocation location selection remains subject to Inventory's current owner-safe queries.

### Errors, transactions, retries, and observable outcomes

`reserve()` normalizes and validates every line before opening one local Inventory transaction. It locks or creates the group row, locks the selected inventory levels, creates all split allocations, increments reservations, stores aggregate quantities/expiry on the group, and commits atomically. Insufficient stock rolls back the complete group attempt and returns/throws a typed insufficient-inventory error with line-level quantities, not location detail.

`commit()` and `release()` lock the group row first, then all related allocations/levels in a stable order. Each performs stock mutation, movement creation where applicable, allocation deletion, and terminal group update in the **same Inventory database transaction**. Thus a crash rolls back both stock and lifecycle evidence. The terminal header is the durable result used on all retries. This is a local transaction, not a cross-package transaction: finalization must retain a durable progress checkpoint and retry the same reference after the Order intake commit.

If no Inventory package is installed, the adapter returns `not_managed` for reserve/extend/find and `not_managed` no-op outcomes for release/commit. The Checkout step records `inventory_reservation` only for a real `reserved` result; it records capability/step metadata for `not_managed`. No generated ID is acceptable. Unexpected provider errors remain failures and must not be converted to `not_managed`.

### Migration and compatibility

This is a deliberate breaking replacement of the checkout-specific allocation API. Remove `releaseReservation()` and `commitReservation()` and replace item-at-a-time `reserve()` with group `reserve()`. Do not retain UUID-to-group compatibility shims: they preserve the wrong public abstraction and make a split allocation ambiguous again.

Existing live allocation rows have no durable group identity. The implementation migration must create a group for each owner/reference containing active allocations and mark only groups with an unambiguous completed movement history as committed. Ambiguous historical rows remain active until release/expiry; it must not infer commitment from absence. Update Inventory and Checkout usage documentation to state that reference is the only checkout reservation handle.

## Implementation scope manifest

### Files to create

- `packages/inventory/src/Contracts/CheckoutReservationServiceInterface.php` — reference-centred optional integration contract
- `packages/inventory/src/Data/ReservationLine.php` — typed normalized line value
- `packages/inventory/src/Data/ReservationOutcome.php` — stable provider outcome value
- `packages/inventory/src/Models/InventoryReservation.php` — durable reservation-group lifecycle aggregate
- `packages/inventory/src/Exceptions/ReservationReferenceConflict.php` — same-reference incompatible reserve/commit error
- `packages/inventory/src/Exceptions/InvalidReservationTransition.php` — illegal lifecycle transition error
- `packages/inventory/src/Services/Stock/CheckoutReservationService.php` — group-level reserve/release/commit/extend implementation
- `packages/inventory/database/migrations/2026_07_12_000002_create_inventory_reservations_table.php` — group storage and unique owner/reference identity
- `tests/src/Inventory/Feature/CheckoutReservationServiceTest.php` — group lifecycle, split allocation, retry, owner isolation, and insufficient-stock coverage

### Files to modify

- `packages/inventory/src/InventoryServiceProvider.php` — bind the new checkout reservation contract
- `packages/inventory/src/Models/InventoryAllocation.php` — relate allocation to its reservation group
- `packages/inventory/src/Services/Stock/InventoryAllocationService.php` — make group operations the sole checkout-facing allocation path
- `packages/checkout/src/Integrations/InventoryAdapter.php` — expose explicit capability/not-managed outcome and reference-only commands
- `packages/checkout/src/Steps/ReserveInventoryStep.php` — send normalized cart lines once and store a group outcome, not allocation IDs
- `packages/checkout/src/Steps/CreateOrderStep.php` — remove direct commitment; DES-FIN-710 moves that orchestration into finalization
- `packages/checkout/src/Exceptions/InventoryException.php` — consume reference/outcome terminology rather than allocation IDs
- `packages/checkout/docs/04-usage.md` — document optional Inventory capability and reference handling
- `packages/checkout/docs/05-checkout-steps.md` — document one reservation group per checkout reference
- `packages/inventory/docs/04-usage.md` — document reservation group lifecycle, retries, and reference observability

### Files to delete

- `packages/inventory/src/Contracts/CheckoutInventoryServiceInterface.php` — replaced by the reference-centred contract
- `packages/inventory/src/Integrations/CheckoutInventoryService.php` — replaced by the group implementation

### Tests to update or delete

- `tests/src/Inventory/Unit/CheckoutInventoryServiceConfigurationTest.php` — delete; it asserts the removed contract/binding
- `tests/src/Inventory/Unit/InventoryAllocationServiceTest.php` — remove checkout-facing allocation-ID expectations; retain location allocation unit coverage
- `tests/src/Checkout/PaymentFlowTest.php` — replace per-item reservation assertions with group/reference outcome assertions
- `tests/src/Checkout/CreateOrderStepTest.php` — remove direct inventory-commit assertions; DES-FIN-710 adds finalizer coverage

## Rejected alternatives

### Rejected: Design A — public allocation collection

Returning all IDs makes the current first-ID loss less obvious but institutionalizes it. Every consumer would need to retain, authorize, release, commit, and reason about split allocations. It fails the deletion test: remove the collection from Checkout and the desired cart-level behavior remains entirely expressible by the reference.

### Rejected: Design C — Checkout-owned reservation ledger

Checkout would duplicate Inventory's transactional state, create split-brain recovery rules, and still require knowledge of allocation IDs. Inventory is the only module able to atomically mutate levels, allocations, and observable reservation state, so moving the header outward has no additional leverage.

### Rejected: a fake identifier for absent Inventory

It causes a success-shaped result without a success event, cannot be retried or audited, and contaminates persisted checkout state. Explicit optional capability is simpler and truthful.

## Unknowns

1. Whether non-Checkout callers currently depend on `CheckoutInventoryServiceInterface`; the implementation discovery pass must enumerate Composer consumers before deletion and either migrate them to the group contract or give them a domain-specific Inventory API.
2. Whether `cart_id` is guaranteed immutable for a CheckoutSession. If carts can be reused after cancellation, Checkout must derive a distinct reservation reference (for example `checkout:{session UUID}`) rather than reusing the cart UUID.
3. Whether order-cancellation needs to release a pre-commit reservation in any path after DES-FIN-710. The finalizer's durable progress record must define the exact hand-off to the existing order-driven `InventoryOperation` lifecycle.
4. Whether location-level reporting requires a separate internal read model. It must be Inventory-only and must not expand this Checkout contract.
