## Second pass — 2026-06-09

### Confirmed

- **Phase 1 — strip non-Filament concerns**: `Http/Controllers/DocDownloadController.php` and `DocPreviewController.php` confirmed deleted from `filament-docs/src/Http/Controllers/`. `Rendering/FilamentRichContentRenderer.php` and `Exports/DocExporter.php` intentionally kept (depend on Filament classes).
- **Phase 2 — adopt commerce-support owner-scope primitives**: `DocsOwnerScope.php` deleted. `HasOwner` + global `OwnerScope` handle scoping automatically.
- **Phase 3 — consolidate getEloquentQuery**: NOOP overrides removed from `DocTemplateResource`, `DocSequenceResource`, `DocEmailTemplateResource`. `DocResource` keeps its override (NOOP but future-proofed).

### Still open

None — all checklists marked [done].

### New findings

1. **`DocResource` uses Filament's native `$tenantOwnershipRelationshipName = 'owner'` instead of `OwnerUiScope::apply()`.** This is inconsistent with `filament-jnt` and `filament-events` which both use `OwnerUiScope::apply()` in `getEloquentQuery()`. `DocResource` delegates to Filament's built-in scoping via the relationship name. Both patterns work but the ecosystem should converge.

2. **`DocResource::getNavigationBadge()` and `getNavigationBadgeColor()` each call `getEloquentQuery()` separately.** Lines 117-132: two separate queries (one for pending/overdue count, one for overdue count) per badge render. The overdue count re-fetches the full query. Could be optimized with a single query.

3. **`Rendering/` and `Exports/` still in the Filament package.** The original finding 1 recommended moving these to the `docs` domain. Phase 1 kept them in `filament-docs` because they depend on Filament classes (`RichContentRenderer`, `Exporter`). This is a pragmatic choice but means the package boundary is blurred — domain rendering concepts live alongside Filament UI.

4. **Cross-domain Actions (`RecordPaymentAction`, `SendEmailAction`) still in `filament-docs`.** The original finding 5 recommended moving these to `chip`/`cashier` and a notifications package. They were not moved.

5. **`RevenueChartWidget` duplication across 3 packages** remains unaddressed (original finding 7). `filament-docs`, `filament-chip`, and `filament-cashier-chip` still have their own `RevenueChartWidget`.

### Updated recommendation

Priority 1: Align owner-scoping pattern with other packages — use `OwnerUiScope::apply()` or document why Filament-native tenancy is preferred here. Priority 2: Optimize nav badge to use a single DB query. Priority 3: Plan a future pass to move `Rendering/`, `Exports/`, and cross-domain Actions to their proper domain packages.

---

# Filament Docs friendliness review

This note reviews `packages/filament-docs` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (4)
- `src/Pages` (2)
- `src/Widgets` (5)
- `src/Actions` (2)
- `src/Exports/DocExporter.php`
- `src/Http/Controllers` (2)
- `src/Rendering` (2)
- `src/Support/DocsOwnerScope.php`
- `FilamentDocsPlugin.php`
- downstream in `docs`, `chip`, `orders`, `customers`

## What is already friendly

### Tables and Schemas subfolders

- `DocResource` and `DocTemplateResource` have `Schemas/` + `Tables/`.

Standard layout.

### Plugin is the entry point

- `FilamentDocsPlugin.php`

Standard shape.

### `DocResource` has 5 RMs

- `Approvals`, `Emails`, `Payments`, `StatusHistories`, `Versions`

RMs are the right place for related-entity editing.

## Findings

### 1. `Http/Controllers/` in a Filament package

**Files**

- `src/Http/Controllers/DocDownloadController.php`
- `src/Http/Controllers/DocPreviewController.php`

**Why this hurts friendliness**

HTTP controllers in a Filament package. They likely serve file streams outside the Filament panel. Filament packages should be panel-only.

**Recommendation**

Move controllers to the `docs` domain package. The Filament package consumes them or registers routes that point to them.

### 2. `Rendering/` in a Filament package

**Files**

- `src/Rendering/FilamentRichContentRenderer.php`
- `src/Rendering/DocsRichContentFileAttachmentProvider.php`

**Why this hurts friendliness**

Custom Filament renderer (rich content rendering) is a domain concern. Belongs in the `docs` domain.

**Recommendation**

Move to `docs/Rendering/` or similar.

### 3. `Support/DocsOwnerScope.php` is a local owner-scope helper

**Files**

- `src/Support/DocsOwnerScope.php`

**Why this hurts friendliness**

`commerce-support` provides owner-scope primitives. The local helper duplicates the pattern.

**Recommendation**

Replace with `commerce-support`'s `OwnerScope` and `OwnerQuery`. Delete the local helper.

### 4. `DocResource` has 4 `getEloquentQuery` references

**Files**

- `DocResource`

**Why this hurts friendliness**

4 refs suggest stacked overrides. Highest density in the audit set.

**Recommendation**

Audit the call chain. Consolidate to one.

### 5. `Actions/RecordPaymentAction.php` and `Actions/SendEmailAction.php` are cross-domain

**Files**

- `src/Actions/RecordPaymentAction.php`
- `src/Actions/SendEmailAction.php`

**Why this hurts friendliness**

Payment and email actions in a docs package are cross-domain. They belong in their respective packages.

**Recommendation**

Move to `chip`/`cashier` and a notifications package, respectively. Filament-docs consumes them.

### 6. `Exports/DocExporter.php` is a Filament export

**Files**

- `src/Exports/DocExporter.php`

**Why this hurts friendliness**

Exporters are domain concerns. Belong in the `docs` package.

**Recommendation**

Move to `docs/Exports/DocExporter.php`.

### 7. `RevenueChartWidget` is duplicated across `filament-chip`, `filament-cashier-chip`, and here

**Files**

- `src/Widgets/RevenueChartWidget.php`

**Why this hurts friendliness**

Same widget name in three packages. Likely 3 different implementations of similar metrics.

**Recommendation**

Audit overlap. Pick canonical per metric. Move to a shared `filament-shared` package if the chart is truly identical.

## Concrete refactor plan

### Phase 1 — strip non-Filament concerns

**Steps**

1. Move `Http/Controllers/`, `Rendering/`, `Exports/`, and cross-domain Actions to the `docs` domain.
2. Re-import in `filament-docs`.

### Phase 2 — adopt `commerce-support` owner-scope primitives

**Steps**

1. Replace `Support/DocsOwnerScope.php` with `commerce-support`'s `OwnerScope`.
2. Update `DocResource` to delegate.

### Phase 3 — consolidate `getEloquentQuery` overrides

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

### Phase 1 — strip non-Filament concerns

- [done] Move `Http/Controllers/` to the `docs` domain. (Rendering/, Exports/, Actions kept in filament-docs — they depend on Filament)
- [done] Re-import in `filament-docs`.

### Phase 2 — adopt `commerce-support` owner-scope primitives

- [done] Replace `Support/DocsOwnerScope.php` with `commerce-support`'s `OwnerScope`. (Deleted local helper; `HasOwner` + global `OwnerScope` already handle scoping)
- [done] Update `DocResource` to delegate. (Removed explicit `DocsOwnerScope::applyToDocs()` — global scope is automatic via `HasOwner`)

### Phase 3 — consolidate `getEloquentQuery` overrides

- [done] Audit the call chain. (4 resources had overrides: DocResource (has `$tenantOwnershipRelationshipName` — parent handles owner scoping), DocTemplateResource (NOOP), DocSequenceResource (NOOP), DocEmailTemplateResource (NOOP). The 3 NOOP overrides were redundant.)
- [done] Consolidate to one. (Removed NOOP `getEloquentQuery` overrides from DocTemplateResource, DocSequenceResource, DocEmailTemplateResource. DocResource keeps its override for future-proofing — it may add query filters.)

### Phase 4 — align owner-scoping pattern with other packages

- [done] Switched `DocResource` from Filament-native `$tenantOwnershipRelationshipName = 'owner'` to `OwnerUiScope::apply()` in `getEloquentQuery()` — consistent with `filament-jnt`/`filament-events`. Removed `$tenantOwnershipRelationshipName` property.

### Phase 5 — optimize navigation badge queries (continued)

- [done] Extracted `cachedBadgeCounts()` that does a single query with `SUM(CASE ...)` for both pending and overdue counts. Cached for 30 seconds. `getNavigationBadge()` and `getNavigationBadgeColor()` both delegate to the shared cached helper.

### Phase 6 — plan future pass for boundary concerns

- [cancelled] Move `Rendering/FilamentRichContentRenderer.php` and `Rendering/DocsRichContentFileAttachmentProvider.php` to the `docs` domain package. Blocked by Filament `RichContentRenderer` class dependency — requires decoupling first. — Cancelled: depends on Filament RichContentRenderer
- [cancelled] Move `Exports/DocExporter.php` to the `docs` domain package. Blocked by Filament `Exporter` base class dependency — requires decoupling first. — Cancelled: depends on Filament Exporter
- [cancelled] Move `Actions/RecordPaymentAction.php` to `chip`/`cashier` domain package. Blocked by cross-domain dependency on `Doc` and `DocService` — requires extracting payment logic first. — Cancelled: blocked on Doc/DocService cross-dependency
- [cancelled] Move `Actions/SendEmailAction.php` to a notifications package. Blocked by dependency on `Doc`, `DocEmailService`, and `DocEmailTemplate` — requires creating a notifications package first. — Cancelled: notifications package doesn't exist

### Phase 7 — address RevenueChartWidget duplication

- [done] Audit overlap between `filament-docs`, `filament-chip`, and `filament-cashier-chip` RevenueChartWidget. Findings: each widget measures a different metric — `filament-docs` tracks Doc payments (30d, single dataset), `filament-chip` tracks Purchase revenue (30d, single dataset), `filament-cashier-chip` tracks subscription MRR + new revenue (12mo, 2 datasets). These are semantically different despite the same widget name.
- [done] Pick canonical implementation per metric. Move to `commerce-support` or a shared location if identical. Conclusion: no consolidation possible — each widget measures a fundamentally different metric from a different domain model. Consolidation would lose domain specificity. The name collision is the only overlap.



## Suggested verification scope

- per-Resource tests
- per-Action tests
- Widget tests
- cross-package tests for docs/chip/orders/customers

## Recommended first move

Phase 1 — strip non-Filament concerns. The HTTP controllers and rendering classes are the most visible boundary violations.
