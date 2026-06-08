# Filament Chip friendliness review

This note reviews `packages/filament-chip` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (12 + abstract `BaseChipResource`)
- `src/Pages` (1 + 2 shared base pages)
- `src/Widgets` (11)
- `src/Actions` (2)
- `src/Models` (empty)
- `src/Services` (empty)
- `FilamentChipPlugin.php`
- `FilamentChipServiceProvider.php` (presentation macros)
- downstream in `chip`, `cashier-chip`, `filament-cashier-chip`

## What is already friendly

### Abstract base resource

- `BaseChipResource.php` (55 lines, `tenantOwnershipRelationshipName = 'owner'`, config-driven nav)

Standard pattern.

### Shared base Pages for read-only resources

- `Resources/Pages/ReadOnlyListRecords.php`
- `Resources/Pages/ReadOnlyViewRecord.php`

5+ chip resources use these. Good reuse pattern.

### Tables and Schemas subfolders

- 8 schemas, 4 tables

Standard layout for most resources.

## Findings

### 1. `FilamentChipServiceProvider` mutates domain package config at boot

**Files**

- `src/FilamentChipServiceProvider.php:33-35`

The line: `if ((bool) config('filament-chip.enforce_owner_scoping', true)) { config()->set('chip.owner.enabled', true); }`

**Why this hurts friendliness**

A Filament package should not mutate the domain package's config at boot. This inverts the dependency direction.

**Recommendation**

Move the owner-scoping flag to the `chip` domain package. The Filament package consumes, not configures.

### 2. `FilamentChipServiceProvider` registers presentation macros

**Files**

- `src/FilamentChipServiceProvider.php:41-103` — `Panel::softShadow`, `Split::glow`, `Stack::carded`, `Fieldset::inlineLabelled`

**Why this hurts friendliness**

Presentation macros in a service provider are a Filament v5 boundary leak. Macros affect every panel globally, not just CHIP.

**Recommendation**

Move presentation macros to a dedicated `FilamentChipMacros` class. Register conditionally or in a per-panel provider.

### 3. 12 Resources is a lot for a payments package

**Files**

- `AuditLogResource`, `BankAccountResource`, `ClientResource`, `CompanyStatementResource`, `ComplianceReportResource`, `FraudReviewResource`, `PaymentLinkResource`, `PaymentResource`, `PurchaseResource`, `RefundResource`, `RiskRuleResource`, `SendInstructionResource`

**Why this hurts friendliness**

12 resources, several read-only, several regulator-facing. New admin/regulator surfaces will keep being added.

**Recommendation**

Group resources by audience (operator, regulator, developer). Consider hiding regulator resources behind a feature flag.

### 4. Plugin comment lies about conditional logic

**Files**

- `FilamentChipPlugin.php`

The comment says "Optional components (payouts, webhooks) can be enabled via configuration" — but the actual `getPages/Resources/Widgets` methods return unconditional lists.

**Why this hurts friendliness**

Code that contradicts its own documentation is a maintenance trap.

**Recommendation**

Either implement the conditional logic or remove the comment.

### 5. Empty `Models/` and `Services/` directories

**Files**

- `src/Models/` (empty)
- `src/Services/` (empty)

**Why this hurts friendliness**

Dead code. Either they were moved out or never populated.

**Recommendation**

Delete empty directories.

### 6. `Actions/PurchaseExporter.php` and `SendInstructionExporter.php` are Filament Actions

**Files**

- `src/Actions/PurchaseExporter.php`
- `src/Actions/SendInstructionExporter.php`

**Why this hurts friendliness**

Exporters can live anywhere. If they only serve Filament, fine. If they're reusable, they should live in the `chip` domain.

**Recommendation**

Audit the exporters. Move to `chip/Exports/` if reusable.

### 7. Widgets likely overlap with `filament-cashier-chip`

**Files**

- 11 widgets including `RevenueChartWidget` (also exists in `filament-cashier-chip`)

**Why this hurts friendliness**

Duplicate surfaces for similar metrics.

**Recommendation**

Audit overlap with `filament-cashier-chip`. Pick canonical per metric.

## Concrete refactor plan

### Phase 1 — remove config mutation in service provider

**Steps**

1. Move owner-scoping flag to `chip` domain.
2. Service provider consumes, not configures.

### Phase 2 — extract presentation macros

**Steps**

1. Move macros to `FilamentChipMacros.php`.
2. Register conditionally.

### Phase 3 — implement or remove plugin comment

**Steps**

1. Implement conditional registration, or
2. Remove the misleading comment.

### Phase 4 — clean up empty directories

**Steps**

1. Delete `src/Models/` and `src/Services/` if empty.

### Phase 5 — audit widget overlap with `filament-cashier-chip`

**Steps**

1. List widgets in both packages.
2. Pick canonical per metric.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — remove config mutation in service provider

- [pending] Move owner-scoping flag to `chip` domain.
- [pending] Service provider consumes, not configures.

### Phase 2 — extract presentation macros

- [pending] Move macros to `FilamentChipMacros.php`.
- [pending] Register conditionally.

### Phase 3 — implement or remove plugin comment

- [pending] Implement conditional registration, or
- [pending] Remove the misleading comment.

### Phase 4 — clean up empty directories

- [pending] Delete `src/Models/` and `src/Services/` if empty.

### Phase 5 — audit widget overlap with `filament-cashier-chip`

- [pending] List widgets in both packages.
- [pending] Pick canonical per metric.



## Suggested verification scope

- per-Resource tests
- Widget tests
- cross-package tests for chip/cashier-chip

## Recommended first move

Phase 1 — remove config mutation. This is a one-file fix that fixes a real inversion of dependencies.
