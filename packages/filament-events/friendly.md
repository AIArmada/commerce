# Filament Events friendliness review

This note reviews `packages/filament-events` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (6)
- `src/Support/FilamentEventQueryAdapter.php`
- `FilamentEventsPlugin.php`
- downstream in `events`, `orders`, `customers`, `products`, `checkout`

## What is already friendly

### Plugin is the entry point

- `FilamentEventsPlugin.php`

Standard shape.

## Findings

### 1. `EventResource` has 10 RelationManagers

**Files**

- `EventResource/RelationManagers/{EventAgendaItems, EventAssets, EventAttendance, EventChangeNotices, EventClassifications, EventEngagements, EventPeople, EventReviews, EventSubmissions, Occurrences}.php`

**Why this hurts friendliness**

10 RMs is the highest in the audit set. Heavy owner-scoping responsibility, heavy inline logic, and 10 separate files to maintain.

**Recommendation**

Consider splitting `Event` into related entities. A 10-RM resource is a sign that the entity has too many concerns. At minimum, group RMs by domain (agenda vs people vs reviews).

### 2. No `Schemas/` or `Tables/` subfolders in any resource

**Files**

- All 6 resources inline Forms/Tables/Infolists.

**Why this hurts friendliness**

`filament-events` is the only Filament package with this many resources (6) and no `Schemas/Tables` subfolders.

**Recommendation**

Split into subfolders following the standard pattern (compare `filament-inventory` which is the most consistent in the audit set).

### 3. No custom Pages, Widgets, Actions, or Policies

**Files**

- (no `src/Pages/`, `src/Widgets/`, `src/Actions/`, or `src/Policies/`)

**Why this hurts friendliness**

Heavy resource surface but no support pages or widgets. Some RM-heavy operations (e.g., event submission review) typically warrant custom pages.

**Recommendation**

Audit operator workflows. Add pages/widgets for high-value operator surfaces.

### 4. `Support/FilamentEventQueryAdapter.php` adapts domain queries to Filament

**Files**

- `src/Support/FilamentEventQueryAdapter.php`

**Why this is worth noting**

This is a good pattern: a query adapter between domain and Filament. Keep it as a real seam. If the same pattern is needed for other packages, promote to `commerce-support`.

### 5. `getEloquentQuery` is overridden in all 6 resources (3 refs each on heavy resources)

**Files**

- `EventResource:3`, `EventSeriesResource:3`, `OccurrenceResource:3`, `RegistrationResource:3`

**Why this hurts friendliness**

3 refs to the same method suggest stacked overrides (superclass + trait + class). Each may add its own filter, making owner scoping unclear.

**Recommendation**

Audit the call chain. Consolidate to a single `getEloquentQuery` that delegates to `commerce-support`'s `OwnerQuery`.

## Concrete refactor plan

### Phase 1 — split resources into subfolders

**Steps**

1. Move Forms/Tables/Infolists into `Schemas/` and `Tables/`.

### Phase 2 — audit `EventResource` RMs

**Steps**

1. List the 10 RMs.
2. Group by domain or split the entity.

### Phase 3 — consolidate `getEloquentQuery`

**Steps**

1. Audit the call chain.
2. Consolidate to one.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — split resources into subfolders

- [pending] Move Forms/Tables/Infolists into `Schemas/` and `Tables/`.

### Phase 2 — audit `EventResource` RMs

- [pending] List the 10 RMs.
- [pending] Group by domain or split the entity.

### Phase 3 — consolidate `getEloquentQuery`

- [pending] Audit the call chain.
- [pending] Consolidate to one.



## Suggested verification scope

- per-Resource tests
- RM tests
- cross-package tests for events/orders/customers/products/checkout

## Recommended first move

Phase 1 — split resources into subfolders. The current shape is the most visible structural smell and the cleanup is mechanical.
