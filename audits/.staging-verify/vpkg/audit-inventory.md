### Prior-audit section
### inventory
Bugs:
- `InventoryReservation:50-59`, `InventoryOperation:46-53` HIGH — `owner_*/order_id/status` fillable, no `booted` guard (unlike Level).
- `InventoryLevel:81-99` HIGH — `quantity_on_hand/reserved/available` fillable; `saving` validates only owner/location, bypasses ledger.
- `InventoryLocation:393-469` MEDIUM (was HIGH) — levels/allocations/children cascaded; movements/batches/serials NOT cascaded.
Security: unvalidated inbound IDs MEDIUM-HIGH — `Reservation/Operation order_id`, `Level preferred_supplier_id`, `Movement user_id` (Level validates location owner only). Reports downgraded to MEDIUM-verify — `StockLevelReport/MovementAnalysisReport/InventoryService` consistently scope; need line-level proof for single `DB::query()` totals, not blanket HIGH.
Performance:
- Allocation loop O(n) writes MEDIUM — per-allocation `decrement` + `Movement::create` in txn.
- Reports `get()` no pagination HIGH — `StockLevelReport`, `MovementAnalysisReport`, `InventoryKpiService:256` load all rows → OOM. Exports are paginated (`StockLevelExport:72`, `BatchExport:60`, `MovementExport:79`, `ValuationExport:57` all `cursor()`) — split from original blanket claim.

### Prior-audit fix-first rows
| 26 | inventory | `Models/InventoryLevel.php:81-99` | `quantity_on_hand/reserved/available` fillable — bypasses ledger | HIGH |
| — | inventory | `InventoryReservation:50-59`, `InventoryOperation:46-53` | `owner_*/order_id/status` fillable, no `booted` guard | HIGH |
