### Prior-audit section
### customers (`aiarmada/customers`)
Bugs:
- `Models/Customer.php:103-104` MEDIUM (was HIGH) — `created_at/updated_at` fillable (audit-noise, not takeover).
- `Models/Segment.php:278-286` MEDIUM — `deactivated_at` never cleared on re-activate.
- `Models/Customer.php:320-328` MEDIUM — `deleting` orphans `contactMethods/socialProfiles/media`.
- `Actions/CreateCustomer.php:67-69` MEDIUM — `LinkCustomerToPerson` runs after txn returns → person-less customer on link failure.
Security: clean — `LinkCustomerToPerson` persons-global by design; customer side guarded.
Performance:
- `Segment:146-175` HIGH — `getMatchingCustomers()->get()` + `rebuildCustomerList sync(pluck)` loads all matches; chunk/paginate.
- `RebuildAllSegments:32-117` MEDIUM — per-segment `sync` + `pluck` + per-ID `event()`; N+1 events.
- DONE (2026-09-13, §8 item 6) — Missing `(owner,status)` composite MEDIUM. Fixed: `(owner_type, owner_id, status)` / `(owner_type, owner_id, is_active)` folded into the customers/segments creates.

### Migration-batch rows (§8, code may already be fixed)
| 6 | customers `(owner, status)` composites (§2) | Folded into the customers/segments creates | `packages/customers/docs/99-troubleshooting.md` |
