### Prior-audit section
### tax
Bugs:
- `owner_*` fillable MEDIUM (was CRITICAL) — fact true, but `TaxRate/Zone saving` enforce owner/global-block; defense-in-depth.
- `TaxExemption status` fillable HIGH — bypasses `approve()/transitionStatus()`.
- `TaxZone deleting` bulk skips events LOW — harmless (Rate has no `deleting` hook).
Security: `TaxCalculator:127-160` LOW (was MEDIUM) — `exemptable_type/customer_type` from context, no morph allowlist; lookup owner-scoped so arbitrary string only misses, not leaks.
Performance: DONE (2026-09-13, §8 item 11) — `scopeForAddress:149-175` + `matchesAddress:177-205` MEDIUM — `orWhereJsonContains/Length` + PHP postcode loop per checkout. Fixed by design (no migration): request-scoped owner-aware resolver cache with zone/rate-write invalidation. The `(owner)` index sub-claim was FALSE — `nullableMorphs('owner')` already indexes (see §7).

### Migration-batch rows (§8, code may already be fixed)
| 7 | tax `(owner)` index on rates (§2) | FALSE — already exists via `nullableMorphs`; dropped, see §7 | — |
