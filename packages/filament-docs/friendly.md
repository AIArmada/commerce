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

- [pending] Move `Http/Controllers/`, `Rendering/`, `Exports/`, and cross-domain Actions to the `docs` domain.
- [pending] Re-import in `filament-docs`.

### Phase 2 — adopt `commerce-support` owner-scope primitives

- [pending] Replace `Support/DocsOwnerScope.php` with `commerce-support`'s `OwnerScope`.
- [pending] Update `DocResource` to delegate.

### Phase 3 — consolidate `getEloquentQuery` overrides

- [pending] Audit the call chain.
- [pending] Consolidate to one.



## Suggested verification scope

- per-Resource tests
- per-Action tests
- Widget tests
- cross-package tests for docs/chip/orders/customers

## Recommended first move

Phase 1 — strip non-Filament concerns. The HTTP controllers and rendering classes are the most visible boundary violations.
