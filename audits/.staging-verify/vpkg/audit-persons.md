### Prior-audit section
### persons (global identity — no owner scope correct)
Bugs:
- `Models/Person.php:82-87` MEDIUM (was HIGH) — `names/titleAssignments/credentials/affiliations ->get()->each->delete()`, no txn/chunk; partial-delete orphans.
- `Models/Person.php:126-174` MEDIUM — up to 100× `exists()` slug probes + extra `names()` query per save; no 23000 retry → 500 on race.
- `Models/Person.php:255-261` LOW — `transitionStatus(): void` mutate-no-save (style only).
- `Models/Person.php:59-71` LOW (was MEDIUM) — `slug/searchable_name/status/published_at` fillable, but `saving` overwrites slug/searchable + `syncStatusLifecycle` repairs `published_at`.
Security: clean — `TitleAssignment saving` existence checks; `ReorderTitleAction` PDO-quoted `CASE` + lock + txn.
Performance:
- `getFormattedNameAttribute:202-227` MEDIUM N+1 — lazy `titleAssignments()->where(Active)->with(title.category)->get()`; eager-load or `scopeWithFormattedName`.
- `TitleAssignment saving` 1–2 `exists()` per row LOW — bulk import should `whereIn` prefetch.
- `Person:82-87` cascade is also perf: N+1 deletes, no chunk.
