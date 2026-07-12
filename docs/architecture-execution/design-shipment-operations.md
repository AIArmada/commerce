# Design Record: Durable Shipment Remote Operations

- **Task:** DES-SHP-610
- **Chosen design:** Design B — durable operation records before terminal Shipment state

## Observed facts

1. ShipShipment retries remote creation from its action path (packages/shipping/src/Actions/ShipShipment.php:33-120), so a timeout can leave the carrier outcome unknown before local evidence exists.
2. JntShippingDriver cancel returns false for failures (packages/jnt/src/Shipping/JntShippingDriver.php:178-204), collapsing rejection, timeout, and transport failure.
3. Shipment status has pending and cancellable predicates (packages/shipping/src/States/ShipmentStatus.php:25-43), but those local states cannot prove a carrier result.
4. J&T has retryable/client/server error classification available (packages/jnt/src/Enums/ErrorCode.php:171-213), which the boolean cancellation contract discards.

## Inferences

1. **Inference:** Carrier submission and cancellation are remote operations with success, rejected, and unknown outcomes; they are not synchronous Shipment state transitions.
2. **Inference:** An unknown operation must persist its idempotency key and request evidence before retry, otherwise retry can create duplicate labels.

## Design alternatives

| Dimension | A — enrich booleans | B — durable operations (chosen) | C — carrier-specific persistence |
| --- | --- | --- | --- |
| Depth | Shallow, still loses evidence | One shared operation lifecycle | Duplicated adapters |
| Leverage | Low | All carriers/reconciliation | J&T only |
| Locality | Action-local | Shipping owns operation model | Gateway leaks domain state |
| Caller knowledge | Error flags | Outcome result | Carrier vocabulary |
| Test surface | Retry branches | Operation state machine | Per carrier tables |
| Migration cost | Low | Medium | High |

## Chosen design

Shipping owns ShipmentOperation with immutable kind (create or cancel), idempotency key, request fingerprint, carrier request/response metadata, status (pending, succeeded, rejected, unknown), attempts, and timestamps. Its unique identity is shipment_id plus kind plus active operation key; a new operation is allowed only after a resolved terminal operation when business rules permit it.

ShippingDriverInterface returns CarrierOperationResult, not bool: success contains carrier shipment identity/label data; rejected contains stable provider code/message; unknown contains retry classification and correlation data. A transport timeout after dispatch is unknown, never failure or cancellation. Jnt maps its structured error codes into this result.

ShipShipment first creates/locks the durable create operation in a local transaction, then calls the carrier outside that transaction with the stored idempotency key. It persists the result transactionally: success derives Shipped/label fields, rejected derives a retryable non-terminal local state, unknown leaves Shipment non-terminal and queues reconciliation. CancelShipment follows the same order and changes Shipment to Cancelled only after confirmed carrier success. Exact retries return stored success/rejection; unknown retries use the same key and reconcile before resubmission. No remote call occurs while a database transaction is open.

## Implementation scope manifest

### Create

- packages/shipping/src/Models/ShipmentOperation.php
- packages/shipping/src/Data/CarrierOperationResult.php
- packages/shipping/src/Enums/ShipmentOperationStatus.php
- packages/shipping/src/Actions/ReconcileShipmentOperation.php
- packages/shipping/database/migrations/2026_07_12_000003_create_shipment_operations_table.php
- tests/src/Shipping/Feature/ShipmentOperationTest.php

### Modify

- packages/shipping/src/Actions/ShipShipment.php
- packages/shipping/src/Actions/CancelShipment.php
- packages/shipping/src/Contracts/ShippingDriverInterface.php
- packages/shipping/src/Models/Shipment.php
- packages/shipping/src/Services/ShipmentService.php
- packages/jnt/src/Shipping/JntShippingDriver.php
- tests/src/Shipping/Feature/ShipShipmentTest.php
- tests/src/Shipping/Feature/CancelShipmentTest.php
- tests/src/Jnt/Unit/JntShippingDriverTest.php
- packages/shipping/docs/04-usage.md
- packages/jnt/docs/04-usage.md

## Rejected alternatives

### Rejected: Design A

More boolean values still cannot distinguish remote rejection from an outcome lost after request dispatch.

### Rejected: Design C

Operation durability belongs to carrier-agnostic Shipping and must work before a second driver exists.

## Unknowns

1. Whether every carrier supports idempotency keys or requires reconciliation by merchant reference.
2. Whether a successful create can later be cancelled while tracking has already progressed; that is a carrier capability rule.
