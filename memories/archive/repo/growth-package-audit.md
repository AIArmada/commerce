## Archived repo memory

This note was moved out of the active `memories/repo/` path because it captures a point-in-time package audit.

Prefer package docs, current tests, and active root docs for current behavior.

- Growth/FilamentGrowth: when `growth.features.owner.enabled=false`, `Experiment` must still validate `tracked_property_id`, and `owner_scope` must remain `global` for ownerless records.
- FilamentGrowth `ExperimentResource::ownerScopeKey()` must return `OwnerScopeKey::GLOBAL` when growth owner scoping is disabled, or slug uniqueness logic drifts from persisted records.
- AggregateExperimentMetrics should not infer unique conversions from purchase counts when event contexts lack `assignment_id`; keep conversion attribution conservative.
- Preset module switches must prune stale `settings` keys instead of preserving hidden data across module types.
- GrowthStatsWidget should keep exact counts separate from limited sampled analytics and label sampling clearly.
