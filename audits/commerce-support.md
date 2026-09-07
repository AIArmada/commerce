# commerce-support Audit

## Packages Reviewed (bullets)

- `packages/commerce-support` — foundation package: owner-tenancy primitives, money, navigation engine, payment contracts, targeting engine, health, webhooks, testing contracts (175 `src/` files, `config/commerce-support.php`, `database/migrations/*.stub`, no routes, no per-package `tests/`)
- `packages/filament-commerce-support` — Filament adapter: `ManageCommerceNavigation` settings page, `NavigationConfigurator`, `Language/Currency/Timezone` resources (14 `src/` files, `config/filament-commerce-support.php`)
- Root test coverage consulted: `tests/src/CommerceSupport/` (45 files), `tests/src/FilamentCommerceSupport/` (1 file)

## Overall Assessment (quality, health, risks, refactor size)

commerce-support is a broadly well-designed foundation: owner-tenancy (`HasOwner`, `OwnerScope`, `OwnerContext`, `OwnerQuery`, `OwnerWriteGuard`, `OwnerRouteBinding`, `OwnerCache`, `OwnerFilesystem`, `OwnerScopeKey`, `OwnerBatchRunner`), money (`MoneyFormatter`, `MoneyNormalizer`, `FormatsMoney`), navigation engine (`Support/Filament/CommerceNavigation.php`, 706 lines), payment contracts, a 24-evaluator targeting engine, and reusable test contracts (`Testing/OwnerScopingContractTests.php`). As the standard other packages are judged against, it mostly practices what it preaches (config-driven tables via `getTable()`, `json_column_type` discipline, no FK constraints/cascades, no soft deletes).

Health risks: Octane safety is uneven — `OwnerContext` keeps a `private static array $fallback` and several registries/caches hold in-memory state with no verified Octane flush coverage; `FilamentCommerceSupport` has 1 root test for 14 source files. Settled 2026-09-08 (see code-fixes-record.md): authz models moved out, money APIs int-only, single navigation engine. No schema migration is required by any recommendation below. Refactor size: Medium (code-only, breaking in 2 spots with internal consumers updated in the same pass).

## Migration Impact

**Migration Required: NO**

No finding in this audit requires a schema change. Detail:

| Table | Column/Index/Constraint | Data migration | Notes |
|---|---|---|---|
| `webhook_calls` (`bigIncrements id`) | none proposed | none | Type inconsistency vs uuid rule documented as accepted; changing PK type would be destructive — explicitly NOT recommended |
| `audits` (`bigIncrements id`, stub) | none proposed | none | Same as above |
| all `commerce-support` stub tables | none | none | `json_column_type` already configurable; no constraint/cascade to drop (verified clean via rg) |

## Package Responsibilities

- Owner-tenancy contract owner: `HasOwner`, `HasOwnerScopeConfig`, `HasOwnerScopeKey`, `OwnerScope`, `OwnerScopeConfig`, `OwnerContext`, `OwnerQuery`, `OwnerWriteGuard`, `OwnerRouteBinding`, `OwnerCache`, `OwnerFilesystem`, `OwnerSignedDownload`, `OwnerTuple/*`, `OwnerBatchRunner`, `OwnerContextJob`/`OwnerScopedJob`, `OwnerContextTeamResolver`, `NullOwnerResolver`, `OwnerResolverInterface`, middleware (`NeedsOwner`, `OwnerIdentificationMiddleware`, `SetExplicitGlobalOwnerContext`), testing contracts.
- Money standard owner: `MoneyFormatter`, `MoneyNormalizer`, `FormatsMoney`; default currency config `commerce-support.currency.default`.
- Navigation standard owner: `CommerceNavigation`, `CommerceNavigationPlugin`, `NavigationConfigurator`, `Filament/OwnerUiScope`, `Filament/OwnerScopedIds`, `FilamentPermission`.
- Payment subject/gateway contracts (`Contracts/Payment/*`), event interfaces (`Contracts/Events/*`), targeting engine (`Targeting/*`), health (`Health/*`), webhook pipeline (`Webhooks/*`, `Actions/ProcessWebhookCallAction.php`), audit/activity traits, install/publish commands.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### A4 — In-memory caches/registries with unverified Octane lifecycle
- Severity: Medium
- Location: `packages/commerce-support/src/Support/OwnerContext.php:33` (`private static array $fallback`), `packages/commerce-support/src/Support/AuditableModelRegistry.php`, `packages/commerce-support/src/Support/LoggableModelRegistry.php`, `packages/filament-authz/src/Authz.php` (`$discoveryCache`, `$permissionCache` on a singleton)
- Problem: Static/in-singleton mutable state survives across Octane requests unless explicitly flushed. `OwnerContext` mitigates via request binding (`readState`/`writeState`/`httpRequest`), but the registries and the filament-authz discovery caches have no Octane flush wiring visible in this package (authz registers Octane listeners for its own state; commerce-support's registries are not covered).
- Why It Matters: Stale owner context or stale permission discovery across requests is a cross-tenant data-leak vector under Octane — the exact failure mode the tenancy system exists to prevent.
- Recommended Fix: Register Octane `RequestReceived`/`RequestTerminated` flush for `AuditableModelRegistry`, `LoggableModelRegistry`, and expose `OwnerContext::flushState()` called from the same listener; convert filament-authz `Authz::$discoveryCache` to request-scoped binding (`$app->scoped`) instead of singleton property (see authz audit A3). Add an Octane-leak regression test per registry.
- Breaking Change: NO
- Affected Packages: filament-authz, all Octane deployments
- Required Dependent Changes: none (additive listeners)
- Migration Required: NO

### A5 — `currency_symbol()` global helper duplicates `MoneyFormatter`
- Severity: Low
- Location: `packages/commerce-support/src/helpers.php:79` (`currency_symbol()`), `packages/commerce-support/src/Support/MoneyFormatter.php` (`symbol()`, `prefixSymbol()`)
- Problem: Two currency-symbol code paths (global function vs formatter). Verified live consumers: `filament-shipping` rate/zone form files use `currency_symbol()`.
- Why It Matters: Symbol overrides (`SYMBOL_OVERRIDES` for SGD/AUD/CAD) applied in one path but not the other produce inconsistent display.
- Recommended Fix: Make `currency_symbol()` delegate to `MoneyFormatter::symbol()` (one line), keep the helper name for compat within this review cycle, then remove the helper and update the two filament-shipping call sites.
- Breaking Change: NO (first step); YES for final removal — do both now, update consumers in same pass
- Affected Packages: filament-shipping
- Required Dependent Changes: replace `currency_symbol($x)` with `MoneyFormatter::symbol($x)` at the two call sites
- Migration Required: NO

## Code Quality Findings (same finding format)

### Q1 — `Helpers` file mixes unrelated globals
- Severity: Low
- Location: `packages/commerce-support/src/helpers.php` (`commerce_json_column_type`, `commerce_schema_create_if_missing`, `commerce_csrf_middleware`, `currency_symbol`)
- Problem: One file owns JSON-type resolution, idempotent schema creation, CSRF middleware naming, and currency symbols — unrelated concerns with different change cadences.
- Why It Matters: Every helper change forces review of the whole file; new contributors extend the junk drawer instead of placing logic with its owner.
- Recommended Fix: Keep function names (they are a de-facto public API used by every package's migrations) but move implementations next to owners (`MoneyFormatter::symbol()` for currency; `Support/ConditionalMigrationLoader.php` already exists for schema helpers — delegate to it). No renames in this pass.
- Breaking Change: NO
- Affected Packages: all (call sites unchanged)
- Required Dependent Changes: none
- Migration Required: NO

### Q2 — Migration stubs use inconsistent morph-key handling
- Severity: Low
- Location: `packages/commerce-support/database/migrations/1970_01_01_000002_create_audits_table.php.stub`, `1970_01_01_000003_fix_audits_user_actor_column_type.php.stub`
- Problem: Branching on `uuid`/`ulid`/default morph types inside stubs adds complexity every downstream publisher inherits.
- Why It Matters: Stub complexity becomes every app's migration complexity.
- Recommended Fix: Resolve morph-key type once in `SupportServiceProvider` and expose a single `commerce_morph_key($table, $prefix)` helper used by stubs; shrink the two stubs to straight-line calls.
- Breaking Change: NO
- Affected Packages: none (stub output equivalent)
- Required Dependent Changes: none
- Migration Required: NO

## Laravel-Specific Findings

- PHP 8.4: `composer.json` requires `php: ^8.4` — compliant.
- No DB FK constraints/cascades: rg over `src/`, `config/`, `database/` clean — compliant.
- PK types: stub tables use `uuid('id')->primary()` except `webhook_calls` (`bigIncrements`) and `audits` (`bigIncrements`) stubs — documented inconsistency, intentionally not migrated (shared Spatie webhook-client table shape; changing PK type is destructive). `JntWebhookLog` correctly omits `HasUuids` because it maps to `webhook_calls` — do not "fix".
- `down()` methods: stubs correctly omit `down()` — compliant.
- `json_column_type`: present in config and consumed by all stubs — compliant, and this is the pattern all downstream packages correctly copy.
- Octane: see A4. `OwnerContext::withOwner()`/`setForRequest()` discipline is the documented standard; the gap is registry flush wiring, not the API.

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)

- `filament-commerce-support` is 14 files: plugin, provider, 3 resources (Language/Currency/Timezone — all correctly use `getNavigationGroup()` from nested `navigation.group` config, no static `$navigationGroup`), `ManageCommerceNavigation` page, `NavigationConfigurator`. Direction is correct (adapter depends on core, core does not depend on adapter).
- Thin-adapter violation: `ManageCommerceNavigation` (1055 lines) re-implements engine logic instead of calling `CommerceNavigation` — see A3. The 3 resources are appropriately thin (correct `getEloquentQuery()` pass-through for global reference data).
- No domain leak beyond A3; no tenancy violation (language/currency/timezone are global reference tables, correctly unscoped).

## Database Findings

- No FK constraints, no cascades, no soft deletes anywhere in the package (verified via rg) — compliant.
- Stub migrations are idempotent via `commerce_schema_create_if_missing()` — compliant and the pattern downstream packages must keep copying.
- Index coverage on stub tables is minimal but acceptable for reference/audit tables; no change recommended (do not add speculative indexes without query evidence).

## Model / Domain Findings

- `HasOwner`/`OwnerScope`/`OwnerQuery` design is sound: immutable owner tuples, explicit global opt-out, `auto_assign_on_create`, `includeGlobal=false` default. This is the standard; downstream deviations (signals' parallel trait, growth's hand-rolled scoping, docs' direct `owner_type` assignment) are downstream bugs, not foundation bugs — filed in their audits.
- `Role`/`Permission`/`AuthzScope` location is the one domain error — see A1.

## Security Findings

- `OwnerContext::assertResolvedOrExplicitGlobal()` fail-closed default is correct and is the single most valuable security property in the monorepo.
- Gaps: (1) Octane registry flush (A4) — stale-state cross-tenant risk; (2) `OwnerSignedDownload` and `PublicHttpUrlGuard`/`ValidatedHttpTarget` exist but no audit verified their call-site coverage — recommend a follow-up coverage grep, not a code change here; (3) `FilamentPermission` centralizes Filament ability mapping — confirm all filament-* resources use it rather than ad-hoc `can()` strings (spot-check showed ad-hoc `Filament::auth()?->check()` closures in filament-jnt — filed there).

## Performance Findings

- `CommerceNavigation` resolves navigation by reflecting over resource/page classes per render; under a panel with many resources this is per-request reflection. Acceptable after A3 collapse; do not add caching layers speculatively — the A3 reduction itself removes the double-scan (engine + settings page each scanning).
- `TargetingEngine` with 24 evaluators is evaluation-order sensitive; no memoization per request. No change without profiling evidence.

## Testing Findings

- Root coverage `tests/src/CommerceSupport/` (45 files) is the strongest in the review set, but `tests/src/FilamentCommerceSupport/` has 1 file for a 1055-line page — the highest-risk untested surface in the foundation.
- Required: feature test for `ManageCommerceNavigation` (override merge + group resolution), Octane-leak regression tests for registries (A4), money boundary tests locking int-only signatures after A2.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| authz | `CommerceSupport\Models\Role/Permission/AuthzScope` | Models move to authz (A1) | Re-point imports to `AIArmada\Authz\Models\*`; own table config |
| filament-authz | `FilamentPermission`, `Authz` models | Same as above + scoped-cache fix (A4) | Update imports; `$app->scoped(Authz::class)` |
| docs, jnt, growth, signals | `MoneyFormatter`/`MoneyNormalizer` | Signatures narrow to int (A2) | Pass minor-unit ints; user input via new parser |
| filament-shipping | `currency_symbol()` | Helper delegates then removed (A5) | Use `MoneyFormatter::symbol()` |
| all filament-* | `CommerceNavigation` engine | Engine collapse (A3) | None (internal) |
| all Octane deployments | registry flush listeners | Additive | None |

## Recommended Refactor Plan (ordered steps)

1. A4: add Octane flush listeners + scoped filament-authz binding; add leak regression tests.
2. A5 + Q1/Q2: helper delegation and stub simplification.
3. Add missing tests: Octane leaks (money boundary + `ManageCommerceNavigation` feature suites now exist — keep green). Run per-package: `./vendor/bin/pest --parallel tests/src/CommerceSupport tests/src/FilamentCommerceSupport`.

## Files Likely to Change

- `packages/commerce-support/src/Support/FilamentPermission.php`, `src/helpers.php` (A5), `src/Support/OwnerContext.php` (A4), `src/SupportServiceProvider.php`
- `packages/commerce-support/src/Support/AuditableModelRegistry.php`, `src/Support/LoggableModelRegistry.php` (A4)
- `packages/commerce-support/database/migrations/*.stub` (Q2)
- `packages/authz/src/AuthzServiceProvider.php`, `packages/filament-authz/src/Authz.php`, `packages/filament-authz/src/FilamentAuthzServiceProvider.php` (A4 scoped binding)

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `currency_symbol()` in `packages/commerce-support/src/helpers.php` (after the two filament-shipping call sites move to `MoneyFormatter::symbol()` — verified only consumers via rg)
- Nothing else: `webhook_calls`/`audits` `bigIncrements` PKs stay (shared-table compat); `scopeForOwner` compat references stay until the cashier/cashier-chip/contacting call sites are migrated in their own audits

## Final Recommended Architecture

commerce-support remains the foundation but stops being a domain owner: tenancy + money + navigation + payment/targeting contracts + testing contracts only. Authz models live in authz. Money APIs are int-only with explicit call-site conversion. One navigation engine with a thin settings page. All shared mutable state is request-scoped or Octane-flushed. Every primitive ships with a contract test that downstream packages reuse rather than re-implement.
