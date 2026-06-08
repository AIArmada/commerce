# Filament Growth friendliness review

This note reviews `packages/filament-growth` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (2)
- `src/Pages` (3)
- `src/Widgets` (2)
- `src/Support/AccessibleGrowthRecords.php` (15 query hits, uses `withoutOwnerScope`)
- `src/Policies` (2)
- `FilamentGrowthPlugin.php`
- downstream in `growth`, `signals`, `affiliates`, `cart`

## What is already friendly

### Policies cover all resources

- `Policies/ExperimentPolicy.php`
- `Policies/VariantPolicy.php`

This is the only package in the audit set with policies for every resource.

### Plugin is the entry point

- `FilamentGrowthPlugin.php`

Standard shape.

## Findings

### 1. `Support/AccessibleGrowthRecords.php` is the classic ad-hoc owner-scope replacement

**Files**

- `src/Support/AccessibleGrowthRecords.php` (15 query calls, uses `withoutOwnerScope`)

**Why this hurts friendliness**

This is the exact smell the multitenancy guideline warns against: an ad-hoc "accessible" filter that replaces the standard `OwnerScope`. The class is also imported and used heavily in `ExperimentResource`.

**Recommendation**

Replace with `commerce-support`'s `OwnerQuery` and `OwnerScope`. The "accessible" semantics should map to owner scope, not a custom class.

### 2. `ExperimentResource` has 3 `getEloquentQuery` refs

**Files**

- `ExperimentResource`

**Why this hurts friendliness**

3 refs to the same method suggest stacked overrides (superclass + trait + class). Each may add its own filter.

**Recommendation**

Audit the call chain. Consolidate to one.

### 3. No `Schemas/` or `Tables/` subfolders in any resource

**Files**

- `ExperimentResource` and `VariantResource` are bare `Pages` only.

**Why this hurts friendliness**

The standard layout is missing.

**Recommendation**

Split into subfolders following the standard pattern.

### 4. `GrowthStatsWidget` has 4 query calls

**Files**

- `Widgets/GrowthStatsWidget.php`

**Why this hurts friendliness**

4 raw queries in a single widget.

**Recommendation**

Move to a `Support/GrowthStatsAggregator.php` service in the `growth` domain.

### 5. `ManageGrowthSettings` page is settings-as-Page

**Files**

- `src/Pages/ManageGrowthSettings.php`

**Why this hurts friendliness**

Settings UIs typically warrant a dedicated settings pattern. If it's just a CRUD on `GrowthSettings`, use a settings page pattern.

**Recommendation**

Audit the page. If it's a settings UI, document the pattern. If it's a CRUD, consider replacing with a Resource.

## Concrete refactor plan

### Phase 1 — replace ad-hoc owner-scope helper

**Steps**

1. Replace `Support/AccessibleGrowthRecords.php` with `commerce-support`'s `OwnerQuery`.
2. Delete the local helper.
3. Update `ExperimentResource` to use the new pattern.

### Phase 2 — split resources into subfolders

**Steps**

1. Add `Schemas/` and `Tables/` to both resources.

### Phase 3 — consolidate `getEloquentQuery` overrides

**Steps**

1. Audit the call chain.
2. Consolidate.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — replace ad-hoc owner-scope helper

- [pending] Replace `Support/AccessibleGrowthRecords.php` with `commerce-support`'s `OwnerQuery`.
- [pending] Delete the local helper.
- [pending] Update `ExperimentResource` to use the new pattern.

### Phase 2 — split resources into subfolders

- [pending] Add `Schemas/` and `Tables/` to both resources.

### Phase 3 — consolidate `getEloquentQuery` overrides

- [pending] Audit the call chain.
- [pending] Consolidate.



## Suggested verification scope

- per-Resource tests
- per-Page tests
- Widget tests
- cross-package tests for growth/signals/affiliates

## Recommended first move

Phase 1 — replace the ad-hoc owner-scope helper. This is the most visible architectural smell and the fix aligns the package with the multitenancy guideline.
