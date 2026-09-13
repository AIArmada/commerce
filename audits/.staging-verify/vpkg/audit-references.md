### Prior-audit section
### references
Bugs:
- `Models/Reference.php:95-122,240-257` MEDIUM — level-at-a-time `pluck` + per-media `delete`, bulk `delete()` skips child events (in txn, correct).
- No `transitionStatus()` LOW — `Published` without `published_at` persists silently.
Security:
- Migration `slug unique` MEDIUM (was HIGH) — `create_references_table:20 unique(slug)` + `nullableUuidMorphs('owner')` + `HasOwner`. Blocks reuse + enumeration-if-exposed; fix: `(owner_type,owner_id,slug)` composite.
- `$fillable slug/parent_id/is_canonical` MEDIUM — squatting + multiple canonicals; parent guarded, canonical unguarded.
Performance: `collectSubtreeIds` level-at-a-time LOW-MEDIUM — recursive CTE or `withCount` if deep.
