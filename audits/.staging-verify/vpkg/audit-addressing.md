### Prior-audit section
### addressing
Bugs:
- `Models/Address.php:74-78` MEDIUM — `deleting` bulk deletes pivots (skips events) + nulls snapshots, no txn.
- `ImportAddressAreasAction.php:36-214` MEDIUM (was HIGH) — 3–6 queries/row, no txn/chunk; offline import perf + partial on abort.
- `ImportPostalCodesAction.php:25-132` MEDIUM (was HIGH) — per-row `DB::transaction` exists (`:63`), but still per-row queries, no chunk.
Security: clean — `AddressOwnerGuard` + `Addressable:saving` + morph checks; search bound params + clamped limit; seed `DB::table` global-only.
Performance: DONE (2026-09-13, §8 item 9) — `LOWER(name)` full scans MEDIUM — `NormalizeAddressDataAction:83,108,137`, `SearchAddressAreas:84-96`, `HierarchyResolver:40,69`; add functional/lower index; cache `Schema::hasTable` (`Normalize:191-196` hits info-schema per save). Fixed: `LOWER(name)` functional indexes folded into the geography creates. `Addressable` owner + `(type/id)` + `is_primary` indexes present.

### Migration-batch rows (§8, code may already be fixed)
| 9 | addressing `LOWER(name)` indexes (§1) | Folded into the geography creates (normalized-column fallback) | `packages/addressing/docs/99-troubleshooting.md` |
