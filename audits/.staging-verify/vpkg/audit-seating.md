### Prior-audit section
### seating
Bugs:
- DONE (2026-09-13, §8 item 5) — `ConvertHoldsToAllocationsAction` HIGH — no lock, no owner comparison, check-then-create races → double allocation. Fixed: `FOR UPDATE` locks + partial unique `(seat_id)` where active (§8); concurrent converts skip instead of double-allocating.
- `DefaultSeatAllocator:100-136`, `EnsureSeatHoldAction:86-122` MEDIUM-HIGH (was HIGH) — bulk `insert()` bypasses events/validation; owner manually assigned so not cross-tenant, but `seat_id` TOCTOU stands.
- `SeatHold/SeatAllocation` no `booted()` HIGH — any `seat_id/held_by_*/allocated_to_*` accepted outside allocator.
- `Seat/SeatMap/SeatSection` `each(delete)` no txn/chunk MEDIUM.
Security:
- `Livewire/SeatMap:48-93,161-177` MEDIUM (was HIGH) — no `authorize`/rate-limit; `seatable_type` public prop into `where()` with no allowlist. Enumeration, not takeover.
- Filament holds/allocations via managers may bypass `OwnerUiScope` MEDIUM — only `SeatMapResource` scoped.
Performance:
- `SeatMap getStatusProperty:112-154` HIGH — loads entire venue per render; 10k seats OOM. Page sections or cached status query.
- Allocator double query + gap locks MEDIUM — single query + `ORDER BY FIELD` + `SKIP LOCKED`.

### Prior-audit fix-first rows
| 20 | seating | `Actions/ConvertHoldsToAllocationsAction` | No owner validation, no `lockForUpdate` → double allocation | HIGH |
| — | seating | `SeatHold/SeatAllocation` (no `booted()`) | Any `seat_id/held_by_*/allocated_to_*` accepted outside allocator | HIGH |

### Migration-batch rows (§8, code may already be fixed)
| 5 | seating active-allocation partial unique (§2) | Kept additive (`2026_09_12_162834_*`, pgsql/sqlite; row locks elsewhere); locks + 23000-skip | `packages/seating/docs/99-troubleshooting.md` |
