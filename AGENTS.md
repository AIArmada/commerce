<laravel-boost-guidelines>
=== .ai/00-overview rules ===

# AI Guidelines Overview (Monorepo Contract)

These files are intentionally split by concern for easier maintenance. Read and apply **all** of them.

## How to apply

- **Follow the strictest rule when in doubt** (security > data isolation > correctness > style).
- **If instructions conflict or are impossible**, say so explicitly, explain why, and propose the safest alternative.
- **Never assume UI scoping is security**. Server-side enforcement and validation are mandatory.

## Runtime assumptions

- **PHP**: Target **PHP 8.4+** only.
- **Filament**: Use Filament v5 APIs. Filament v5 is API-compatible with Filament v4; the primary difference is Livewire (v5 uses Livewire v4, v4 uses Livewire v3). When official v5 docs are missing, Filament v4 docs/examples are acceptable.
- **Octane compatibility**: Assume long-lived workers. Avoid request-leaking static mutable state, prefer request-scoped/container-scoped state, and ensure code is safe under Laravel Octane.

## Verification mindset

- Prefer **small, auditable changes** over broad refactors.
- Use per-package checks (tests/PHPStan) instead of repo-wide runs.
- When a guideline requires verification, either run it (if feasible) or call out what must be run by the user.

=== .ai/config rules ===

# Config Guidelines

- **Keys**: Keep minimal. If a key is defined but never read, remove it.
- **Section order** (keep consistent across packages):
  - Core: Database -> Credentials/API -> Defaults -> Features/Behavior -> Integrations -> HTTP -> Webhooks -> Cache -> Logging.
  - Filament: Navigation -> Tables -> Features -> Resources.
- **Rules**:
  - Any package that uses JSON columns in migrations MUST define and use a `json_column_type` setting.
  - Prefer opinionated defaults over excessive `env()` usage (only use env vars for secrets or deploy-time values).
  - Comments: section headers only; inline comments only for non-obvious values.

## Verification

- Find config reads: `rg -n -- "config\('" packages/*/src packages/*/config`
- Find unused keys (typical pattern): `rg -n -- "config\('pkg\." packages/*/config | cat`

=== .ai/database rules ===

# Database Guidelines

- **Primary keys**: `uuid('id')->primary()`.
- **Foreign keys**: `foreignUuid('col')` only.
- **Never** add DB-level constraints or cascades: no `->constrained()`, no `->cascadeOnDelete()`, no FK constraints.
- **Cascades/integrity**: enforce in application logic (models/actions/services).
- **Migrations**: keep safe/idempotent; no `down()` required.

## Verification

- Ensure no constraints/cascades slipped in: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`

=== .ai/development rules ===

# Development Guidelines

- **Safety**: NEVER "cleanup" or mass-revert without permission.
- **Scope**: Run tools (Pint/PHPStan) ONLY on modified packages.

## Monorepo Formatting

- **Golden rule**: No style-only PRs.
- If touching `packages/*/src/**`, run Pint only on changed files (or at least only the changed packages).
- Never run Pint repo-wide “just to be safe” — it creates noisy diffs across unrelated packages.

## Best Practices

- **Strict Laravel**: `Arr::get()`, `Collections`, `Service Container`.
- **Modern PHP**: 8.4+ (readonly, match, modern typing).
- **Time**: Use `CarbonImmutable` (or immutable date/time objects) wherever possible; avoid mutable `Carbon` unless you have a strong reason.
- **Octane-safe by default**: Avoid process-wide mutable statics/singletons for request data; use request attributes, scoped container bindings, or explicit context wrappers that always restore state.
- **Logic**: Action Classes only. No logic in Controllers/Models.
- **Structure**: SOLID, Repository for access, Factory for creation.

## Naming

- **Classes**: `PascalCase`.
- **Methods/Vars**: `camelCase`.
- **Consts**: `SCREAMING_SNAKE`.
- **DB**: `snake_case` (tables/cols).
- **Bool**: `is_`, `has_`, `can_`.

## Agents

- **Auditor**: Strict auditing/security (`.github/agents/Auditor.agent.md`).
- **QC**: QA/Testing (`.github/agents/QC.agent.md`).
- **Visionary**: Architecture (`.github/agents/Visionary.agent.md`).

## Beta Status

- **Break Changes**: Allowed for improvement. No backward compatibility required.

=== .ai/docs rules ===

# Documentation Guidelines

- **Location**: `packages/<pkg>/docs/`
- **Required files**: `01-overview`, `02-install`, `03-config`, `04-usage`, `99-trouble`
- **Format**: Markdown with YAML frontmatter (`title:`) at the top of every file.

## Content rules

- Use `##` for main sections, `###` for subsections.
- Examples must be copy-paste ready (include imports/namespaces where relevant).
- Cross-reference related docs using relative links.
- Call out breaking changes explicitly and explain the migration path.

## Callouts

- Import: `import Aside from "@components/Aside.astro"`
- Variants: `info`, `warning`, `tip`, `danger`

=== .ai/filament rules ===

# Filament Guidelines

- **Version**: Filament v5.
  - Filament v5 is API-compatible with Filament v4; the main difference is Livewire (v5 uses Livewire v4, v4 uses Livewire v3).
  - When v5 docs are incomplete, v4 docs/examples are acceptable.
- **Spatie**: MUST use official Filament plugins (Tags, Settings, Media, Fonts).
- **Actions**: Use built-in `Import`/`Export` actions only.
- **Multitenancy**: Filament tenancy is NOT sufficient; all queries and action handlers must still obey the owner-scoping contract.

## Verification

- Double-check method signatures in the installed Filament version before shipping.

=== .ai/general rules ===

# General Guidelines (Monorepo-Specific)

Use this file for cross-cutting guidance that is **not already covered** in other guideline files.

## 1) Workflow Quality

- For non-trivial work (multi-step changes, architecture decisions, or risk of regressions), write a brief plan before coding.
- If new evidence invalidates the plan, stop and re-plan.
- Verify behavior before declaring done.

## 2) Runtime-Extension Safety

Before removing a method that static analysis reports as undefined, verify runtime extension sources first:
- `macro()` / `hasMacro()` registrations
- package mixins / traits
- framework/plugin runtime extension points

If runtime-provided, preserve behavior and fix analysis with a narrow, targeted approach rather than deleting feature calls.

## 3) Action-Oriented Orchestration

- Prefer Laravel Actions for reusable orchestration that spans transactions, side effects, normalization, or multiple entrypoints.
- Keep trivial single-step handlers inline when extraction adds no clarity.
- Reuse existing actions before creating new ones.

## 4) Tracking Review for Behavioral UI Changes

When a task changes user behavior (entry points, forms, actions, or meaningful workflow transitions), evaluate whether product tracking should be updated.
- Prefer high-signal events over noisy click logs.
- Prefer server-confirmed events for backend outcomes.

=== .ai/model rules ===

# Model Guidelines

- **Base**:
  - Use `Illuminate\Database\Eloquent\Concerns\HasUuids`.
  - Do NOT set `protected $table`; implement `getTable()` using package config (tables map + prefix).
- **Relations**: type relations and collections with PHPDoc generics.
- **Cascades**: implement application-level cascades in `booted()` (delete or null-out). Never rely on DB cascades.
- **Migrations**: use `foreignUuid()` only (no `constrained()` / FK constraints).

## Verification

- Search for forbidden DB cascades/constraints in migrations: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`

=== .ai/multitenancy rules ===

# Multitenancy Guidelines

## Monorepo Contract

- **Boundary is mandatory**: Every package that stores tenant-owned data MUST define an explicit tenant boundary and enforce it on **every** read/write path.
- **Single source of truth**: Multi-tenancy primitives live in `commerce-support`.
- **No UI trust**: Filament form options are not security. Always validate on the server.
- **Column semantics**: `owner_type/owner_id` is the default tenant boundary tuple. If a package customizes the column names, it MUST still preserve the same semantics through `HasOwner` / `ownerScopeConfig()` and MUST NOT reuse the boundary columns for unrelated meaning.

## Runtime Ground Truth (`commerce-support`)

- **Owner mode switch**: owner enforcement is activated by `commerce-support.owner.enabled`.
- **Resolver contract**: bind `AIArmada\CommerceSupport\Contracts\OwnerResolverInterface` to resolve the current owner. When owner mode is enabled, using `NullOwnerResolver` is invalid and should fail fast.
- **Owner context API**: use `OwnerContext::withOwner($owner, fn () => ...)` for scoped work and `OwnerContext::withOwner(null, fn () => ...)` for explicit global work.
- **HTTP-only override**: use `OwnerContext::setForRequest()` only from middleware/framework integrations. Do **not** use it as a generic escape hatch.
- **Request protection**: use `AIArmada\CommerceSupport\Middleware\NeedsOwner` on routes/surfaces that must not proceed without a resolved owner.

## Design defaults (align ecosystem behavior)

- **Default enforcement**: models using `HasOwner` get a global `OwnerScope` when their owner scoping config is enabled.
- **Default include-global**: `false` unless explicitly required by business rules.
- **Meaning of `owner = null`**: treat as **global-only** records, not “all owners”.
- **Cross-tenant/system operations**: allowed only when the call site uses an explicit, greppable opt-out.
- **Explicit global is first-class**: code that reads or mutates global rows must enter explicit global context instead of relying on a missing owner.

## Data Model (Required)

- **Migration**: `$table->nullableMorphs('owner')` for tenant-owned tables.
- **Model**: `use HasOwner` (from `commerce-support`).
- **Config-backed models**: when scoping is package-configurable, implement `ownerScopeConfig()` via `HasOwnerScopeConfig` and set the package config key.
- **Provider**: Bind `OwnerResolverInterface` to resolve current owner context.
- **Non-tenant "ownership"** (wallet holder, payee, actor, etc.) MUST use different column names/relationships.

## Model Semantics (Non-negotiable)

- **Reads require context**: owner-scoped reads require either a resolved owner or explicit global context.
- **Persisted owner tuple is immutable**: after creation, owned rows MUST NOT be promoted, demoted, or reassigned by editing `owner_type` / `owner_id` directly.
- **Auto-assignment**: new owned rows may inherit the current owner automatically when `auto_assign_on_create` is enabled.
- **Global writes are privileged**: mutating persisted global rows requires explicit global context.
- **Unsaved exceptions only**: helper methods like `removeOwner()` are only safe on unsaved/new models.

## Enforcement (Default-on)

- Tenant-owned `HasOwner` models SHOULD rely on commerce-support's `OwnerScope` rather than package-local ad hoc scoping logic.
- **Escape hatch**: intentionally cross-tenant/system operations MUST use an explicit, greppable opt-out (e.g. `->withoutOwnerScope()` / `withoutGlobalScope(OwnerScope::class)`).
- **Scoped lookup helper**: for inbound IDs on write paths, prefer `OwnerWriteGuard::findOrFailForOwner()` or `ResolveOwnedModelOrFailAction` over hand-rolled checks.
- **Non-request surfaces** (jobs/commands/schedules): MUST NOT rely on ambient web auth. Pass/iterate owner explicitly and apply owner context via `OwnerContext::withOwner(...)` or the job helpers below.

## Query Rules (Non-negotiable)

- **Reads**: Must be owner-enforced on every surface (UI + non-UI). Use the default `HasOwner` global scope whenever possible; use `Model::forOwner($owner)` when intentionally selecting an owner context and/or explicitly including global rows.
- **Writes**: Any inbound foreign IDs (e.g., `location_id`, `order_id`, `batch_id`) MUST be validated as belonging to the current owner scope before attach/update.
- **Query builder**: `DB::table(...)` paths touching tenant-owned data MUST apply `OwnerQuery::applyToQueryBuilder(...)` (Eloquent global scopes do not apply).
- **Global rows**: If a package supports global rows, provide clear semantics:
  - `Model::forOwner($owner)` (owner-only)
  - `Model::globalOnly()` (global-only)
  - Optional: include-global behavior must be explicit and consistent.
- **Unscoped Eloquent is opt-out only**: if you remove the global scope, immediately reapply scoping intentionally (`forOwner`, `globalOnly`, `OwnerQuery`, etc.) unless the operation is truly cross-tenant by design.

## Filament Rules

- **Resources**: Ensure `getEloquentQuery()` is owner-safe (default-on scope or explicit scoping). Don’t rely solely on `$tenantOwnershipRelationshipName` or UI filters.
- **Actions**: Validate IDs again inside `->action()` handlers (defense-in-depth), preferably with `OwnerWriteGuard` / `ResolveOwnedModelOrFailAction`.
- **Relationship selects**: Scope option queries and validate submitted IDs.
- **Widgets / badges / aggregates**: any `count()`, `sum()`, `exists()`, or stats query must be owner-scoped too.

## Routing & Binding

- Route-model binding/download routes MUST NOT resolve cross-tenant rows for `HasOwner` models.
- Prefer hardened binding patterns where applicable (e.g., `OwnerRouteBinding::bind(...)`, an owner-safe query, or `ResolveOwnedModelOrFailAction`).

## Non-UI Surfaces

- Console commands, queued jobs, scheduled tasks, exports, reports, health checks, and webhooks MUST apply the same owner scoping as HTTP/Filament.
- **Jobs**: prefer `OwnerContextJob` for queued jobs, or implement `OwnerScopedJob` when you need an explicit owner payload contract.
- **Commands / batch processing**: iterate owners explicitly and wrap each unit of work in `OwnerContext::withOwner(...)`.
- Jobs/commands MUST NOT rely on ambient web auth to resolve owner; pass/iterate owner explicitly and apply owner context via the standard override mechanism.

## Shared Infrastructure Rules

- **Cache**: use `OwnerCache` for tenant-sensitive cache keys; never share raw cache keys across owners.
- **Filesystem**: use `OwnerFilesystem` for tenant-sensitive file storage paths; never concatenate raw `owner_id` paths manually.
- **Owner scope keys**: when a package needs stable owner-specific cache/file prefixes, use `OwnerScopeKey` rather than inventing a package-local format.

## Verification (Required)

- Add at least one cross-tenant regression test proving reads are isolated and writes throw/abort.
- For `HasOwner` models, prefer reusing `commerce-support`'s `OwnerScopingContractTests` where practical.
- Grep for unscoped entrypoints:
  - `rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/<pkg>/src`
  - `rg -n -- "count\(|sum\(|avg\(|exists\(" packages/<pkg>/src`
  - `rg -n -- "DB::table\(" packages/<pkg>/src`
  - `rg -n -- "Route::.*\{.*\}" packages/<pkg>/routes`
  - `rg -n -- "withoutOwnerScope\(|withoutGlobalScope\(.*Owner" packages/<pkg>/src`
- Grep for non-standard tuple usage and confirm it is intentional/configured:
  - `rg -n -- "owner_type|owner_id|owner_type_column|owner_id_column" packages/<pkg>/src packages/<pkg>/config packages/<pkg>/database`

=== .ai/packages rules ===

# Packages Guidelines

- **Independence**: Packages must work standalone. Prefer `suggest` over hard `require` for optional integrations.
- **Foundation-first**: Always check `commerce-support` for existing primitives, traits, helpers, and contracts before building custom logic or requiring external packages directly.
- **Standardize shared capabilities**: If functionality is useful across packages (now or soon), implement it in `commerce-support` so behavior stays consistent and maintainable long term.
- **Integration**: When related packages are installed together, auto-enable integrations via `class_exists()` checks in service providers.
- **DTOs**: Use `spatie/laravel-data`.
- **Deletes**: No soft deletes (`SoftDeletes`).
- **Testing**: Verify both standalone install and integrated behavior.

=== .ai/phpstan rules ===

# PHPStan Guidelines

- **Level**: 6
- **Scope**: per package (e.g. `packages/<pkg>/src`), not repo-wide.
- **Rules**:
  - Respect `phpstan.neon` / `phpstan-baseline.neon`.
  - Do not add new `ignoreErrors` unless root-cause fixes are exhausted.
  - Prefer real fixes over suppression.

## Verification

- Example: `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`

=== .ai/spatie rules ===

# Spatie Guidelines

- **DTOs**: `spatie/laravel-data`
- **Logging**: `activitylog` (business events), `auditing` (compliance)
- **Webhooks**: `spatie/laravel-webhook-client` (idempotent job pattern)
- **Media**: `spatie/laravel-medialibrary`
- **Settings**: `spatie/laravel-settings`
- **Tags**: `spatie/laravel-tags`
- **States**: `spatie/laravel-model-states`

## Rule of thumb

- If one of the above solves the problem, prefer it over inventing a custom subsystem.

=== .ai/test rules ===

# Testing Guidelines

- **Goal**: ELIMINATE BUGS.
- **Refs** (Filament v4 docs are acceptable for v5 testing APIs):
  - [Overview](https://filamentphp.com/docs/4.x/testing/overview)
  - [Resources](https://filamentphp.com/docs/4.x/testing/testing-resources)
  - [Tables](https://filamentphp.com/docs/4.x/testing/testing-tables)
  - [Schemas](https://filamentphp.com/docs/4.x/testing/testing-schemas)
  - [Actions](https://filamentphp.com/docs/4.x/testing/testing-actions)
  - [Notifications](https://filamentphp.com/docs/4.x/testing/testing-notifications)

## Execution

- **Do not run everything**. Run tests per package/scope.
- **Single**: `./vendor/bin/pest --parallel path/to/Test.php`
- **Dir**: `./vendor/bin/pest --parallel path/to/dir`
- **Full**: `./vendor/bin/pest --parallel ...` (final only)

## Coverage

- Always include `--parallel` when using `--coverage`.
- Command: `./vendor/bin/pest --coverage --parallel`
- Don’t run full coverage if `0% files > 10%`.
- Targets: Core ≥80%, Filament ≥70%, Support ≥80%.
- **Output**: ALWAYS pipe: `2>&1 | tee /tmp/out.txt`.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.
</laravel-boost-guidelines>
