## Second pass — 2026-06-09

### Confirmed

- **Phase 1 — split resources into subfolders**: All 6 resources now have `Schemas/` subfolders (12 schema files across EventResource, RegistrationResource, OccurrenceResource, EventSubLocationResource, VenueResource, EventSeriesResource).
- **Phase 2 — audit EventResource RMs**: 10 RMs cataloged and grouped into 4 domains (Scheduling, People, Submissions & Reviews, Metadata). Decision: kept as single Event entity — these are all aspects of one event.
- **Phase 3 — consolidate getEloquentQuery**: All 6 resources have single, non-stacked overrides using `OwnerUiScope::apply()`.

### Still open

None — all checklists marked [done].

### New findings

1. **4 moderation Actions defined as static methods on `EventResource` itself.** `submitForReviewAction()`, `approveAction()`, `requestChangesAction()`, `rejectEventAction()` (lines 123-238) are all inline on the Resource class. EventResource is 239 lines — these actions plus the 10 RMs make it the heaviest Resource in the audit set. They should be extracted to `Actions/` classes.

2. **`includeGlobal: false` used consistently across all resources.** Global rows are excluded everywhere (e.g. EventResource:55, EventSeriesResource:45). This is a design choice. Worth documenting in the CONTEXT.md that global events are not visible in the Filament panel.

3. **Navigation badge queries the DB twice per render.** `EventResource::getNavigationBadge()` calls `getEloquentQuery()->where(...)->count()` and `getNavigationBadgeColor()` also hits DB. This pattern is fine for admin but could be optimized via caching (as `filament-jnt` does).

4. **`FilamentEventQueryAdapter` is a solid pattern but underused.** It bridges Filament filter state to domain `EventSearchCriteria` DTOs, but is only used for counts/snapshot reads. The main Resource queries still run through Eloquent directly. Opportunity to unify querying.

5. **No `$tenantOwnershipRelationshipName` on EventResource.** Unlike `filament-jnt` and `filament-docs` which set this for Filament's built-in tenancy, EventResource relies solely on `OwnerUiScope::apply()` in `getEloquentQuery()`. This is consistent with the commerce-support pattern — just noting the difference.

### Updated recommendation

Priority 1: Extract the 4 moderation actions from EventResource to `Actions/` classes. Priority 2: Document the `includeGlobal: false` policy in CONTEXT.md. Priority 3: Consider caching nav badge counts.

---

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

- [done] Move Forms/Tables/Infolists into `Schemas/` and `Tables/`.

### Phase 2 — audit `EventResource` RMs

- [done] List the 10 RMs:
  1. OccurrencesRelationManager (scheduling)
  2. EventPeopleRelationManager (people)
  3. EventSubmissionsRelationManager (submissions)
  4. EventReviewsRelationManager (reviews)
  5. EventChangeNoticesRelationManager (changes)
  6. EventAssetsRelationManager (assets)
  7. EventClassificationsRelationManager (classification)
  8. EventEngagementsRelationManager (engagement metrics)
  9. EventAttendanceRelationManager (attendance)
  10. EventAgendaItemsRelationManager (agenda)
- [done] Group by domain or split the entity. (Grouped into 4 domains: Scheduling — Occurrences, AgendaItems; People — People, Attendance; Submissions & Reviews — Submissions, Reviews, ChangeNotices; Metadata — Assets, Classifications, Engagements. Keeping as a single Event entity is intentional — these are all aspects of one event.)

### Phase 3 — consolidate `getEloquentQuery`

- [done] Audit the call chain. (5 resources override: EventResource, EventSeriesResource, OccurrenceResource, RegistrationResource, EventSubLocationResource, VenueResource. Each has a single override calling `parent::getEloquentQuery()` then `OwnerUiScope::apply()` + `with()`.)
- [done] Consolidate to one. (Each resource already has a single, non-stacked override. The friendly.md's "3 refs" counts are 1 definition + 1 `parent::getEloquentQuery()` + 1+ calls in badge methods — normal usage, not stacked overrides.)

### Phase 4 — extract moderation actions from EventResource

- [done] Extract `submitForReviewAction()` from `EventResource.php` to `Actions/SubmitForReviewAction.php`.
- [done] Extract `approveAction()` from `EventResource.php` to `Actions/ApproveEventAction.php`.
- [done] Extract `requestChangesAction()` from `EventResource.php` to `Actions/RequestChangesAction.php`.
- [done] Extract `rejectEventAction()` from `EventResource.php` to `Actions/RejectEventAction.php`.
- [done] Update `EventResource` to import and use the extracted action classes.

### Phase 5 — document includeGlobal policy and optimize nav badge

- [done] Document the `includeGlobal: false` design choice in `CONTEXT.md` (global events are not visible in the Filament panel).
- [done] Optimize navigation badge to cache counts (30-second Cache::remember, following `filament-jnt`'s `NavigationBadgeHelper` pattern).

### Phase 6 — unify querying via FilamentEventQueryAdapter

- [done] Audit main Resource queries to identify where direct Eloquent queries can be replaced with `FilamentEventQueryAdapter`. Finding: the adapter is intentionally scoped to counts/snapshot reads (`search()` method returns `EventSearchResultData`). Main Resource table queries in `EventTable` use Filament's native table query builder which requires Eloquent `Builder` for routing, actions, and bulk operations. Replacing these with the adapter would lose Filament Table functionality. The current architecture (direct Eloquent for tables, adapter for badges/counts) is correct.
- [done] Extend `FilamentEventQueryAdapter` usage beyond counts/snapshot reads to main Resource table queries. Conclusion: extending to main table queries is not feasible without losing Filament Table features (action URLs, bulk actions, record routing). The adapter's current scope (counts, badges, snapshot reads) is the correct boundary.



## Suggested verification scope

- per-Resource tests
- RM tests
- cross-package tests for events/orders/customers/products/checkout

## Recommended first move

Phase 1 — split resources into subfolders. The current shape is the most visible structural smell and the cleanup is mechanical.
