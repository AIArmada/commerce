# Growth friendliness review

This note reviews `packages/growth` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Actions` (10 classes)
- `src/Support`
- `src/Http/Middleware`
- `src/Models` (3 classes)
- `src/Settings`
- `src/Livewire/Concerns`
- `src/Contracts`
- downstream consumers in `signals`, `affiliates`, `cart`, `checkout`, `events`

## What is already friendly

### Actions are organized and well-named

- `Actions/AggregateExperimentMetrics`
- `Actions/BuildExperimentSignalProperties`
- `Actions/ProjectExperimentContextIntoSignalProperties`
- `Actions/ResolveAccessibleExperiment`
- `Actions/ResolveAccessibleExperimentBySlug`
- `Actions/ResolveExperimentAssignment`
- `Actions/ResolveExperimentPreset`
- `Actions/ResolveReadableExperiment`
- `Actions/ResolveReadableExperimentBySlug`
- `Actions/ScopeSignalQueryToOwner`

The Action tree is focused and follows the monorepo's "Actions only" rule. Each Action is a single workflow step.

### Request-scoped experiment context is a real seam

- `Support/ExperimentContextManager.php`
- `Support/ExperimentContext.php`
- `Support/RequestExperimentSubjects.php`
- `Http/Middleware/ResolveExperiment.php`

The middleware binds the experiment context on the request, and the manager resolves it. This is the right shape for a request-scoped state seam.

### Livewire concern integrates the package with Filament

- `Livewire/Concerns/InteractsWithExperimentContext.php`

The package has a clear entry point for Livewire components to consume the context.

### Settings is one class

- `Settings/GrowthSettings.php`

Single settings class keeps the config surface tight.

## Findings

### 1. `ResolveAccessibleExperiment`, `ResolveReadableExperiment`, and their slug variants are likely near-duplicates

**Files**

- `Actions/ResolveAccessibleExperiment.php`
- `Actions/ResolveAccessibleExperimentBySlug.php`
- `Actions/ResolveReadableExperiment.php`
- `Actions/ResolveReadableExperimentBySlug.php`

**Why this hurts friendliness**

Four Resolve Actions that differ only by (accessibility vs readability) and (ID vs slug). This is the canonical "two-by-two duplication" smell.

**Recommendation**

Add a small `Support/ExperimentResolver` that takes a strategy parameter (accessible/readable) and a key (id/slug). The four Actions become thin adapters or are replaced by direct calls to the resolver.

### 2. No `Services/` directory — context lives in `Support/`

**Files**

- `Support/ExperimentContextManager.php`
- `Support/ExperimentContext.php`
- `Support/RequestExperimentSubjects.php`

**Why this hurts friendliness**

The `Support/` folder mixes context management, request subjects, and helpers. The naming convention is fine, but a clear separation between "state" and "support" would help.

**Recommendation**

Consider grouping:

- `Support/Context/` (ExperimentContext, ExperimentContextManager)
- `Support/Request/` (RequestExperimentSubjects)

Or leave as-is and document.

### 3. The `RequestExperimentSubjectResolver` contract is the right shape

**Files**

- `Contracts/RequestExperimentSubjectResolver.php`

**Why this is worth noting**

This is a real contract. The default implementation is in `Support/`. New subject resolvers (cookie-based, header-based, user-property-based) can implement the contract.

### 4. `AggregateExperimentMetrics` and `ScopeSignalQueryToOwner` are unique

**Files**

- `Actions/AggregateExperimentMetrics.php`
- `Actions/ScopeSignalQueryToOwner.php`

**Why this is worth noting**

These two Actions are not duplicated and they show the package's two real workflows:

- aggregate metrics (analytics)
- scope signals (signals integration)

This is the right shape — analytics and signals integration are separate concerns.

### 5. Models are minimal (3)

**Files**

- `Models/Experiment.php`
- `Models/Variant.php`
- `Models/Assignment.php`

**Why this is worth noting**

The data model is small. This is a focused package. Keep this discipline.

### 6. `BuildExperimentSignalProperties` and `ProjectExperimentContextIntoSignalProperties` are likely related

**Files**

- `Actions/BuildExperimentSignalProperties.php`
- `Actions/ProjectExperimentContextIntoSignalProperties.php`

**Why this hurts friendliness**

Two Actions that both produce signal properties. One may be the lower-level builder and the other the projector. If both are needed, document the relationship.

**Recommendation**

Audit both. If `Project` wraps `Build`, document the layering. If they overlap, collapse.

### 7. No `Console/Commands` directory

**Why this hurts friendliness**

Bulk operations (assignment recomputation, experiment archival) have no clear owner.

**Recommendation**

Add a `src/Console/Commands` directory when the first batch operation is needed.

## Concrete refactor plan

### Phase 1 — collapse the four Resolve Actions

**Steps**

1. Add `Support/ExperimentResolver` with strategy and key parameters.
2. Replace the four Actions with thin adapters or remove them.
3. Update callers.

### Phase 2 — audit signal properties Actions

**Steps**

1. Compare `BuildExperimentSignalProperties` to `ProjectExperimentContextIntoSignalProperties`.
2. Pick the canonical owner.
3. Document the relationship.

### Phase 3 — organize `Support/`

**Steps**

1. Consider grouping by domain.
2. Update imports.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — collapse the four Resolve Actions

- [pending] Add `Support/ExperimentResolver` with strategy and key parameters.
- [pending] Replace the four Actions with thin adapters or remove them.
- [pending] Update callers.

### Phase 2 — audit signal properties Actions

- [pending] Compare `BuildExperimentSignalProperties` to `ProjectExperimentContextIntoSignalProperties`.
- [pending] Pick the canonical owner.
- [pending] Document the relationship.

### Phase 3 — organize `Support/`

- [pending] Consider grouping by domain.
- [pending] Update imports.



## Suggested verification scope

- per-Action tests
- context manager tests
- middleware tests
- cross-package tests for signals/affiliates

## Recommended first move

Phase 1 — collapse the four Resolve Actions. The two-by-two duplication is the most visible smell and the cleanup is mostly mechanical.
