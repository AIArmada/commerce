### Prior-audit section
### shipping
Bugs:
- `CreateShipment:77` HIGH — `event(new ShipmentCreated)` inside txn, no `afterCommit` (orders uses `afterCommit:143-145`); ghost waybill on rollback.
- `ShippingRate:186-194` MEDIUM — fail-open `default => true`; should fail-closed.
- `ShippingRate:294` MEDIUM — `(int)(total*rate/10000)` truncation vs cart half-up (`CartMoney:51-61`).
Security:
- `OrderFulfillmentHandler:224` HIGH — `Shipment::where(tracking_number)->first()` no `forOwner`; enumerable tracking → disclosure. Scope or signed URL (copy jnt `AwbController`).
- `OrderFulfillmentHandler:288,345` HIGH — `location_id` via bare `InventoryLocation::find()`; ships from + leaks foreign warehouse.
- `Shipment:72-74 owner_*` fillable note — `CreateShipment:29-37` rejects mismatch (keep guard; remove from fillable). Label route is token+auth+owner-match (not `signed` — jnt AWB is the signed reference).
Performance:
- `RecalculateShipmentWeight:17-19` hydrates MEDIUM — use SQL `sum(weight*quantity)` (already used `CreateShipment:74`).
- Triple item scan MEDIUM — eager-load `items` once (copy `GenerateCheckoutDocumentsJob:72`).
- Driver fan-out MEDIUM (narrowed) — default parallel via `Concurrency`; serial only in fallback; no timeout/circuit in either. Add timeout/circuit + short-TTL cache.

### Prior-audit fix-first rows
| — | shipping | `CreateShipment:77` | Event before commit → ghost waybill on rollback | HIGH |
| 37 | shipping | `Integrations/OrderFulfillmentHandler.php:224,288,345` | Unscoped tracking lookup + unvalidated `location_id` | HIGH |
