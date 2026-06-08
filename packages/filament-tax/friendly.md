# Filament Tax friendliness review

This note reviews `packages/filament-tax` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (4)
- `src/Pages` (1)
- `src/Widgets` (3)
- `src/Actions` (1)
- `src/Support/FilamentTaxAuthz.php`
- `FilamentTaxPlugin.php`
- composer.json (missing `commerce-support`)

## What is already friendly

### Tables and Schemas subfolders

- All 4 resources have `Schemas/` + `Tables/`.

Standard layout.

### Resource hierarchy with RMs

- `TaxZoneResource` with RM: Rates (with its own `Schemas/Tables` subdirs)

RMs are supported.

## Findings

### 1. `composer.json` is missing `commerce-support`

**Files**

- `composer.json` (require: only `aiarmada/tax`)

**Why this hurts friendliness**

Every other Filament package except `filament-customers` requires `commerce-support` explicitly. The tax package's `getEloquentQuery` overrides (in all 4 resources) and the `TaxZoneResource/RelationManagers/RatesRelationManager.php:10` import of `AIArmada\Tax\Support\TaxOwnerScope` from the `tax` domain suggest an implicit dependency chain.

**Recommendation**

Add `aiarmada/commerce-support: self.version` to the require list. This makes the dependency explicit and matches the monorepo convention.

### 2. `Support/FilamentTaxAuthz.php` is a custom authz class

**Files**

- `src/Support/FilamentTaxAuthz.php`

**Why this hurts friendliness**

`filament-authz` exists. Re-implementing authz in `filament-tax` duplicates the seam.

**Recommendation**

Use `filament-authz` for tax-zone permissions. Delete `FilamentTaxAuthz.php`.

### 3. `TaxZoneResource/RelationManagers/RatesRelationManager` has its own `Schemas/` and `Tables/` subdirs

**Files**

- `TaxZoneResource/RelationManagers/{Schemas, Tables, RatesRelationManager}.php`

**Why this hurts friendliness**

The same pattern exists in `filament-pricing` (`PriceListResource/RelationManagers/{Schemas, Tables, ...}`). Schemas and Tables are siblings to RMs rather than children.

**Recommendation**

Standardize the RM subfolder layout. Either put `Schemas/` and `Tables/` inside each RM (the standard pattern) or accept the sibling layout and document it.

### 4. `Actions/DownloadTaxExemptionCertificateAction.php` is a Filament Action for file download

**Files**

- `src/Actions/DownloadTaxExemptionCertificateAction.php`

**Why this hurts friendliness**

File download is typically a route download, not a Filament Action. The Action wraps what should be a `Route::get(...)` handler.

**Recommendation**

Either:
- keep the Action if it composes with other Filament Actions, or
- move to a route download if it's purely a file stream

### 5. All 4 resources have `getEloquentQuery` overrides (2 each)

**Files**

- 4 resources × 2 refs each = 8 refs

**Why this hurts friendliness**

Consistent owner scoping, but the count suggests stacked overrides.

**Recommendation**

Audit the call chain. Consolidate to one per resource.

## Concrete refactor plan

### Phase 1 — add `commerce-support` to composer

**Steps**

1. Add `aiarmada/commerce-support: self.version` to `require`.
2. Run `composer update`.

### Phase 2 — adopt `filament-authz`

**Steps**

1. Replace `Support/FilamentTaxAuthz.php` with `filament-authz` calls.
2. Delete the local class.

### Phase 3 — standardize RM subfolder layout

**Steps**

1. Pick the standard pattern (Schemas/Tables inside each RM).
2. Refactor `TaxZoneResource/RelationManagers/`.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — add `commerce-support` to composer

- [pending] Add `aiarmada/commerce-support: self.version` to `require`.
- [pending] Run `composer update`.

### Phase 2 — adopt `filament-authz`

- [pending] Replace `Support/FilamentTaxAuthz.php` with `filament-authz` calls.
- [pending] Delete the local class.

### Phase 3 — standardize RM subfolder layout

- [pending] Pick the standard pattern (Schemas/Tables inside each RM).
- [pending] Refactor `TaxZoneResource/RelationManagers/`.



## Suggested verification scope

- per-Resource tests
- Widget tests
- cross-package tests for tax/authz

## Recommended first move

Phase 1 — add `commerce-support` to composer. This is a one-line fix that aligns the package with the monorepo convention.
