### Prior-audit section
### growth
Bugs:
- `ResolveExperimentAssignment:248-257` MEDIUM — `variantForSubject` skips `resolveExperimentForCurrentOwner` (verify callers).
- Multi-currency no FX MEDIUM — `MetricsCalculator:217-221` filters to experiment currency, drops rest silently.
Security: clean — signal binding `validateSignalReferences:410-450` + explicit-global gate; no `owner_*` fillable.
Performance:
- `AggregateExperimentMetrics:60-66` unbounded `get()` MEDIUM — route dashboards via bounded/`handleMany` UNION (`:230-327`).
- `pickVariant:259-266` re-queries per assignment LOW — cache per request.
- `handleMany` 3-query UNION + request cache GOOD.
