# growth Audit

## Packages Reviewed (bullets)

- `packages/growth` — revenue experimentation engine: experiments/variants/assignments, assignment resolution, metrics aggregation, signals integration (28 `src/` files, `config/growth.php`, 3 migrations, no routes, `src/helpers.php`)
- `packages/filament-growth` — Filament adapter: `ExperimentResource`, `VariantResource`, dashboard/settings/results pages, widgets, policies (23 `src/` files, `config/filament-growth.php`)
- Root test coverage consulted: `tests/src/Growth/` (20 files), `tests/src/FilamentGrowth/` (4 files — thin)

## Overall Assessment (quality, health, risks, refactor size)

growth has a clean core shape (3 `HasOwner`+`HasUuids` models with `getTable()`, uuid PKs, enum statuses, Actions for orchestration) and correct adapter navigation (`getNavigationGroup()` from nested config everywhere). The problems are consistency and gravity: (1) `Actions/ScopeSignalQueryToOwner.php` strips `OwnerScope` then re-applies via `OwnerQuery::applyToEloquentBuilder()` — redundant, not a bypass as previously claimed (see A2, demoted to Low on re-check: it reads custom tuple columns via `ownerScopeConfig()` and delegates to `OwnerQuery`, verified full file); (2) two god Actions (`ResolveExperimentAssignment` 618 lines, `AggregateExperimentMetrics` 437 lines) plus a parallel projection action (`ProjectExperimentContextIntoSignalProperties` 398 lines); (3) the filament adapter duplicates domain aggregation (`GrowthStatsAggregator` re-queries per experiment — N+1 over the signals tables). Refactor size: Medium.

## Migration Impact

**Migration Required: NO** — migration track completed 2026-09-07, see `migration-record.md#growth`

## Package Responsibilities

- Experiment lifecycle: `Models/Experiment.php` (`ExperimentStatus`), `Models/Variant.php` (`VariantStatus`), `Models/Assignment.php`; `Console/Commands/ArchiveExperimentsCommand.php`, `RecomputeExperimentAssignmentsCommand.php`.
- Assignment: `Actions/ResolveExperimentAssignment.php`, `Actions/RepairExperimentAssignment.php`, `Support/Context/*` (`ExperimentContext`, `ExperimentContextManager`, `ExperimentResolver`), `Http/Middleware/ResolveExperiment.php`, `Livewire/Concerns/InteractsWithExperimentContext.php`, `Support/Request/RequestExperimentSubjects.php`.
- Measurement: `Actions/AggregateExperimentMetrics.php`, `Actions/BuildExperimentSignalProperties.php`, `Actions/ProjectExperimentContextIntoSignalProperties.php`, `Actions/ScopeSignalQueryToOwner.php`.
- Configuration: presets (`Actions/ResolveExperimentPreset.php`), `Settings/GrowthSettings.php`, `Contracts/RequestExperimentSubjectResolver.php` + default resolver.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### A2 — `ScopeSignalQueryToOwner` redundantly strips then re-applies `OwnerScope`
- Severity: Low
- Location: `packages/growth/src/Actions/ScopeSignalQueryToOwner.php:25-36` (`withoutGlobalScope(OwnerScope::class)` then `OwnerQuery::applyToEloquentBuilder()` with `ownerScopeConfig()`-derived columns)
- Problem: Re-check 2026-09-07 (full file read): the prior "bypasses `OwnerQuery`" claim was wrong — the class reads custom tuple columns via `ownerScopeConfig()` and delegates to `OwnerQuery::applyToEloquentBuilder()`, preserving `includeGlobal` semantics. The only real defect is redundancy: stripping the global scope just to re-apply equivalent scoping in the next statement. Used at 8 call sites (models, actions, resolvers — verified via rg).
- Why It Matters: Redundant scope juggling invites future edits that "simplify" away the re-apply half; minor readability/maintainability cost, no active mis-scoping.
- Recommended Fix: Inline the delegation at call sites (`$query->forOwner($owner, $includeGlobal)` — the pattern `AggregateExperimentMetrics` already uses at lines 334–358) and delete the class if call sites stay ≤8 (verify with rg `ScopeSignalQueryToOwner` before deleting); or keep it as a documented thin delegator. If a cross-model scope helper is truly needed, put it in commerce-support next to `OwnerQuery`, not here.
- Breaking Change: NO (call sites unchanged in behavior)
- Affected Packages: signals (read path only)
- Required Dependent Changes: none
- Migration Required: NO

### A3 — God Actions: resolution (618) + aggregation (437) + projection (398)
- Severity: Medium
- Location: `packages/growth/src/Actions/ResolveExperimentAssignment.php` (618), `Actions/AggregateExperimentMetrics.php` (437), `Actions/ProjectExperimentContextIntoSignalProperties.php` (398)
- Problem: Each file mixes orchestration with query building, owner plumbing, and metrics math. `ResolveExperimentAssignment` alone owns subject resolution, owner derivation (`:486-600`), variant querying, and persistence.
- Why It Matters: Untestable seams; the next variant-resolution bug requires reading 618 lines; filament adapter authors re-implement instead of reusing (see F1).
- Recommended Fix: Extract pure query builders (`VariantQuery::activeFor(Experiment)`, `AssignmentQuery::forSubject(...)`) and a `MetricsCalculator` for the aggregation math, leaving Actions as thin orchestrators (transaction + owner context + delegation). No new package, no interfaces — three small final classes in `Actions/` or `Support/`.
- Breaking Change: NO (new classes; keep Action signatures)
- Affected Packages: filament-growth (`ExperimentHelpers`, `GrowthStatsAggregator` should call the extracted calculator — see F1)
- Required Dependent Changes: adapter switches to the extracted calculator (same pass)
- Migration Required: NO

## Code Quality Findings (same finding format)

### Q1 — `ExperimentStatus`/`VariantStatus` transition code scattered
- Severity: Low
- Location: `packages/growth/src/Models/Experiment.php:143`, `Variant.php:115` (`scopeActive`), `Console/Commands/ArchiveExperimentsCommand.php`, `Actions/*` status writes, `started_at/paused_at/concluded_at/archived_at` timestamp columns
- Problem: Lifecycle timestamps exist on the table (good) but no single transition method owns the state→timestamp mapping the way docs' `Doc::markAsPaid()` centralizes it.
- Why It Matters: Partial transitions (status flipped, timestamp missed) corrupt experiment reporting windows.
- Recommended Fix: Add `Experiment::transitionTo(ExperimentStatus $s, ?string $notes = null)` centralizing status + timestamp mapping; route the archive command and Actions through it.
- Breaking Change: NO
- Affected Packages: filament-growth (call sites optionally updated)
- Required Dependent Changes: none required
- Migration Required: NO

## Laravel-Specific Findings

- PHP 8.4, no FK constraints/cascades (rg clean), uuid PKs, `getTable()` from `growth.database.tables.*`, `json_column_type` present in config and all 3 migrations — compliant across the board.
- No `down()`-related action needed. No soft deletes — compliant.
- `GrowthServiceProvider` auto-enables signals integration via `class_exists` (per composer `suggest`/require) — correct standalone/integrated behavior; growth hard-requires `aiarmada/signals` in composer, so the integration flag should default on (it does: `integrations.signals.enabled=true`).
- Owner-config nesting: growth models pin `ownerScopeConfigKey = 'growth.features.owner'` (verified `Experiment.php:66`, `Variant.php:53`, `Assignment.php:57`), sharing the `features.owner` nesting with membership/moderation while signals/docs/jnt use top-level `{pkg}.owner`. Join the membership-audit M1 normalization pass (move to top-level `owner`, same fallback-then-drop mechanics); tracked there, not as a separate growth finding.

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)

- Direction correct (filament-growth → growth + signals; no reverse dependency).
- Navigation compliant (`getNavigationGroup()` from `filament-growth.navigation.group` on all resources/pages).
- F1 — Domain duplication (Medium): `filament-growth/src/Support/GrowthStatsAggregator.php` re-aggregates experiment metrics in the adapter (per-experiment `safeAggregateExperimentMetrics()` loop = N+1 against signals tables) instead of calling `AggregateExperimentMetrics`; `Support/ExperimentHelpers.php::ownerMatchedChildCount()` re-implements owner-matched correlated counts in SQL that the domain should expose. Fix: adapter calls the extracted `MetricsCalculator` (A3) and a domain `Experiment::query()->withOwnerMatchedCounts()` scope; delete the adapter-local math. Hardcoded `protected ?string $currency = 'MYR'` in `GrowthStatsAggregator` must become `config('commerce-support.currency.default')`/metric currency (today multi-currency revenue sums are mislabeled).
- F2 — Defense in depth good: `Policies/ExperimentPolicy.php`, `VariantPolicy.php` exist and mutation paths re-validate; `VariantForm::OwnerScopeKey::forOwner()` usage is correct. Keep, and add `OwnerWriteGuard::findOrFailForOwner()` inside table-action handlers that mutate assignments (currently only form-level scoping verified).

## Database Findings

- Tables use `uuid id`, `nullableUuidMorphs('owner')`, `timestampTz`, configurable JSON type — fully compliant.
- Indexes `['tracked_property_id','status']`, `['tracked_property_id','module_type']` fit the read paths.

## Model / Domain Findings

- Models correctly use `HasOwner` + `HasOwnerScopeConfig` (+ `HasOwnerScopeKey` on Experiment) with `HasUuids` and enum casts — the model layer is the exemplar in this review set alongside docs.

## Security Findings

- Reads are owner-bound. Remaining: `Http/Middleware/ResolveExperiment.php` resolves experiments from request subjects — verify it re-checks `belongsToOwner` before attaching assignment context to the request (subject→experiment confusion lets a caller project another tenant's experiment context into signal properties via `ProjectExperimentContextIntoSignalProperties`). Add an explicit owner-equality assertion at the middleware→projection seam.
- No signed-URL or mass-assignment surface beyond standard Filament forms.

## Performance Findings

- `GrowthStatsAggregator::aggregate()` (adapter) runs 1 count query + 1 fetch (limit 10) + up to 10 full metric aggregations, each fanning into signals tables — dashboard page load scales with experiment count × signal volume. Fix via A3/F1 (single batched aggregation query grouped by experiment) — expected reduction from O(n) aggregations to 1–2 queries.
- `ExperimentHelpers::applyOwnerSafeRelationCounts()` adds 2 correlated subqueries per experiment row — acceptable for admin lists; keep, but move into the domain scope so both adapter and domain share one implementation.

## Testing Findings

- `tests/src/Growth/` (20 files) covers resolution/aggregation reasonably; `tests/src/FilamentGrowth/` (4 files) is thin for 23 adapter files.
- Required: cross-tenant regression (experiment in tenant A invisible to tenant B); `GrowthStatsAggregator` query-count test (assert ≤3 queries for 10 experiments — fails before F1 fix). Run: `./vendor/bin/pest --parallel tests/src/Growth tests/src/FilamentGrowth`.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| filament-growth | `AggregateExperimentMetrics`, models | A3/F1 extraction | Call extracted calculator/scope instead of local math |
| commerce-support | none new | — | None (uses `OwnerQuery`/`OwnerContext` as designed) |

## Recommended Refactor Plan (ordered steps)

1. A2: inline `ScopeSignalQueryToOwner` body as `forOwner()` delegation (or keep as thin delegator); delete only if call sites ≤8.
2. A3+F1: extract calculator/query builders; rewire adapter aggregator; fix hardcoded MYR.
3. Q1: centralize status transitions.
4. Add middleware owner-equality assertion + required tests; run `./vendor/bin/pest --parallel tests/src/Growth tests/src/FilamentGrowth`.

## Files Likely to Change

- `packages/growth/config/growth.php`, `src/Actions/ResolveExperimentAssignment.php`, `src/Actions/ScopeSignalQueryToOwner.php`, `src/Actions/AggregateExperimentMetrics.php`, `src/Actions/ProjectExperimentContextIntoSignalProperties.php`, `src/Models/Experiment.php`, plus new `src/Support/{MetricsCalculator,VariantQuery,AssignmentQuery}.php`
- `packages/filament-growth/src/Support/GrowthStatsAggregator.php`, `src/Support/ExperimentHelpers.php`, `src/Resources/ExperimentResource.php`, `src/Resources/VariantResource.php`, `src/Http/*` (middleware assertion)

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/growth/src/Actions/ScopeSignalQueryToOwner.php` (inlined at its 8 call sites and deleted, or kept as a thin delegator — verify with rg `ScopeSignalQueryToOwner` before deleting)
- Adapter-local metric math in `packages/filament-growth/src/Support/GrowthStatsAggregator.php::safeAggregateExperimentMetrics()` (replaced by domain calculator calls)
- Hand-rolled correlated-count SQL in `packages/filament-growth/src/Support/ExperimentHelpers.php::ownerMatchedChildCount()` (moved to domain scope)
- Hardcoded `'MYR'` default in `GrowthStatsAggregator::$currency` (replaced by config/metric currency)
- Nothing else verified dead: both `ResolveExperimentPreset` and `RepairExperimentAssignment` have live call sites; preset config blocks stay

## Final Recommended Architecture

growth is a thin orchestration layer over owner-bound domain queries: resolution, aggregation, and projection are small Actions delegating to shared query builders/calculator; all signal reads go through `forOwner()` on the tracked property's owner; the filament adapter renders and guards but computes nothing.
