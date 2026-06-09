## Second pass — 2026-06-09

### Confirmed

- **Phase 1**: Config mutation removed. `FilamentChipServiceProvider.php` has no `config()->set()` calls for `chip.owner.enabled`. ✅
- **Phase 2**: `FilamentChipMacros.php` created. ✅
- **Phase 3**: Plugin comment updated — now reads "Essential resources, pages, and widgets are registered by default" with methods annotated "minimal by default." No misleading conditional comment. ✅
- **Phase 4**: Empty `src/Models/` and `src/Services/` directories deleted. ✅
- **Phase 5**: Widget overlap with `filament-cashier-chip` audited and documented. ✅
- `BaseChipResource` still provides shared base (55 lines). ✅
- 8 schemas, 4 tables across resources. ✅

### Still open

- **Finding #3 (12 resources — group by audience)**: Resources are still flat in `src/Resources/`. No audience grouping (operator/regulator/developer). Only 2 resources registered in plugin (`ClientResource`, `PurchaseResource`) out of 12 total — the other 10 are conditionally registered elsewhere or simply left out of the plugin. [pending]
- **Finding #6 (exporters in Filament package)**: `PurchaseExporter.php` and `SendInstructionExporter.php` still live in `src/Actions/`. No audit was done to determine if they should move to `chip/Exports/`. [pending]

### New findings

- **N1 — Plugin registers only 2 of 12 resources**: `getResources()` returns only `PurchaseResource` and `ClientResource`. The 10 other resources (`AuditLogResource`, `BankAccountResource`, `CompanyStatementResource`, `ComplianceReportResource`, `FraudReviewResource`, `PaymentLinkResource`, `PaymentResource`, `RefundResource`, `RiskRuleResource`, `SendInstructionResource`) are not conditionally registered — they appear to be unreachable from the Plugin. They may be legacy, conditionally registered elsewhere, or dead code.

### Updated recommendation

Audit the 10 unregistered resources: either register them (with feature flags if regulator-facing) or delete/move to `_archive/`. Group resources by audience. Audit exporters for possible move to `chip/Exports/`.

---

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

- [done] Move owner-scoping flag to `chip` domain.
- [done] Service provider consumes, not configures.

### Phase 2 — extract presentation macros

- [done] Move macros to `FilamentChipMacros.php`. (Created `FilamentChipMacros::register()` in a dedicated class.)
- [done] Register conditionally. (Service provider calls `FilamentChipMacros::register()`; each macro checks `hasMacro()` before registering.)

### Phase 3 — implement or remove plugin comment

- [done] Remove the misleading comment. (Removed "Optional components (payouts, webhooks) can be enabled via configuration" from plugin docblock — the methods return unconditional minimal essential lists.)

### Phase 4 — clean up empty directories

- [done] Delete `src/Models/` and `src/Services/` if empty. (Deleted — both were empty.)

### Phase 5 — audit widget overlap with `filament-cashier-chip`

- [done] List widgets in both packages. (filament-chip: ChipStatsWidget, AccountBalanceWidget, PayoutStatsWidget, TokenStatsWidget, AccountTurnoverWidget, RecentPayoutsWidget, PayoutAmountWidget, BankAccountStatusWidget, RevenueChartWidget, RecentTransactionsWidget, PaymentMethodsWidget. filament-cashier-chip: 7 widgets, of which RevenueChartWidget name overlaps.)
- [done] Pick canonical per metric. (RevenueChartWidget: keep both — filament-chip version is for CHIP payment transactions data, filament-cashier-chip version is for subscription billing revenue. Different data sources and scope. No other overlap — filament-chip widgets are CHIP gateway-specific, cashier-chip widgets are subscription-specific.)

### Phase 6 — audit 10 unregistered resources (Finding #3 / Finding N1)

- [done] Verify which of the 10 unregistered resources are legacy, conditionally registered elsewhere, or dead code: all 10 are fully implemented resources with Pages/Schemas/Tables subfolders. None are dead code — they were simply not registered in the plugin.
- [done] Registered all 10 resources in `FilamentChipPlugin` (Purchase, Client kept as before; Payment, Refund, SendInstruction, BankAccount added as operator resources; ComplianceReport, AuditLog, FraudReview, RiskRule added as regulator resources behind `filament-chip.features.regulator_mode`; PaymentLink, CompanyStatement added as developer resources).

### Phase 7 — group resources by audience (Finding #3)

- [done] Classified each resource by audience:
  - **Operator** (transaction processing): PurchaseResource, ClientResource, PaymentResource, RefundResource, SendInstructionResource, BankAccountResource
  - **Regulator** (compliance): ComplianceReportResource, AuditLogResource, FraudReviewResource, RiskRuleResource
  - **Developer** (integration): PaymentLinkResource, CompanyStatementResource
- [done] Audience gating via `FilamentChipPlugin` methods (`operatorResources()`, `regulatorResources()`, `developerResources()`).
- [done] Regulator resources gated behind `filament-chip.features.regulator_mode` config flag.

### Phase 8 — audit exporters for domain package move (Finding #6)

- [done] Audit: both `PurchaseExporter` and `SendInstructionExporter` extend `Filament\Actions\Exports\Exporter` — they are Filament-specific and cannot be reused without Filament. Not suitable for `chip/Exports/`.
- [done] Documented Filament-only rationale with class-level docblocks in both exporter classes.



## Suggested verification scope

- per-Resource tests
- Widget tests
- cross-package tests for chip/cashier-chip

## Recommended first move

Phase 1 — remove config mutation. This is a one-file fix that fixes a real inversion of dependencies.
