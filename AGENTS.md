<laravel-boost-guidelines>
=== .ai/00-overview rules ===

# AI Guidelines Overview (Monorepo Contract)

These files are intentionally split by concern for easier maintenance. Read and apply all of them, and keep each rule in the narrowest subject file that fits it.

## Rule Hierarchy

- Follow the strictest rule when guidance overlaps: security > data isolation > correctness > style.
- If instructions conflict or cannot both be satisfied, say so explicitly, explain the conflict, and choose the safest alternative.
- Never assume UI scoping is security. Server-side enforcement and validation are mandatory.

## Runtime Baseline

- Target PHP 8.4+ only.
- Use Filament v5 APIs.
- Assume long-lived workers. Avoid request-leaking static mutable state, prefer request-scoped or container-scoped state, and keep code safe under Laravel Octane.

## Verification Baseline

- Prefer per-package checks instead of repo-wide runs.
- When a guideline requires verification, run it if feasible. If not, say exactly what the user must run.

## Project References

- Issues are tracked in this repo's GitHub Issues. See `docs/agents/issue-tracker.md`.
- Use the canonical labels `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, and `wontfix`. See `docs/agents/triage-labels.md`.
- Treat this repo as multi-context: read `CONTEXT-MAP.md` first, then the relevant `CONTEXT.md` and ADRs. See `docs/agents/domain.md`.

## Package Contexts

- Every `packages/<pkg>` root must have a `CONTEXT.md`.
- Read the owning package's `CONTEXT.md` before code search or edits.
- Use the package context to route work quickly: identify the package role, search surface, related packages, and follow-up reads.
- `CONTEXT.md` is a routing document, not a full spec or changelog. Keep it short, stable, and easy to scan.
- Required frontmatter: `title`, `package`, `status`, `surface`, `family`.
- Standard section order:
  - `## Snapshot`

  - `## Read next`

  - `## Guardrails`

- `Snapshot` should name the Composer package, the package role, the best starting search paths, and the related packages.
- `Read next` should point to the package docs in this order: `01-overview`, `03-configuration`, `04-usage`, `99-troubleshooting`, then `02-installation` when setup or publishing is involved. Add sibling `CONTEXT.md` files when cross-package changes are likely.
- `Guardrails` should state the package's ownership boundary, the main surfaces it owns, what belongs in sibling packages, and any must-follow review rule such as revalidating IDs or updating docs in the same pass.
- `filament-*` packages are adapters, not domain owners.
- If a task crosses core and Filament boundaries, read both contexts before editing.
- Package docs under `docs/*.md` are canonical. When public behavior or config changes, update the owning package's docs in the same pass.

=== .ai/config rules ===

# Config Guidelines

## Key Discipline

- Keep config keys minimal. If a key is defined but never read, remove it.
- Prefer opinionated defaults over excessive `env()` usage. Use env vars only for secrets or deploy-time values.
- Comments should be section headers only; inline comments are only for non-obvious values.

## Section Order

- Core packages: Database -> Credentials/API -> Defaults -> Features/Behavior -> Integrations -> HTTP -> Webhooks -> Cache -> Logging.
- Filament packages: Navigation -> Tables -> Features -> Resources.

## JSON Columns

- Any package that uses JSON columns in migrations must define and use a `json_column_type` setting so the column type stays configurable.

## Verification

- Find config reads: `rg -n -- "config\('" packages/*/src packages/*/config`
- Find unused keys (typical pattern): `rg -n -- "config\('pkg\." packages/*/config | cat`

=== .ai/database rules ===

# Database Guidelines

## Primary Keys

- Use `uuid('id')->primary()` for primary keys.

## Foreign Key Columns

- Use `foreignUuid('col')` for foreign-key columns only.
- Do not add database foreign-key constraints.

## Integrity Rules

- Never add database-level constraints or cascades: no `->constrained()`, no `->cascadeOnDelete()`, no FK constraints.
- Enforce cascades and integrity in application logic through models, Actions, and services.

## Migrations

- Keep migrations safe and idempotent.
- No `down()` method is required.

## Verification

- Ensure no constraints or cascades slipped in: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`

=== .ai/development rules ===

# Development Guidelines

## Tooling and Scope

- Run tools such as Pint, PHPStan, and Pest only on modified packages.
- If touching `packages/*/src/**`, run Pint only on the changed files or at least only on the changed packages.
- Never run Pint repo-wide "just to be safe"; it creates noisy diffs across unrelated packages.
- Do not open style-only PRs.
- Prefer the standard project-local binaries directly (`./vendor/bin/pest`, `./vendor/bin/phpstan`, `./vendor/bin/rector`, `./vendor/bin/pint`) in a normal local shell.
- Do not add or commit machine-specific launcher files or symlinks such as `php-local`; personal PHP/Herd wrappers belong in local shell config, not the repository.
- Keep tracked agent and MCP config repo-safe. Local development credential files like `auth.json` may exist on your machine, but they must stay ignored and never be committed.
- Do not commit absolute home-directory paths, personal `SITE_PATH` values, or other machine-specific local tool wiring.

## Code Conventions

- Prefer Laravel-native helpers, collections, and the service container when the framework already provides the right abstraction.
- Use modern PHP 8.4 features and explicit typing.
- Use `CarbonImmutable` or other immutable date/time objects wherever possible; avoid mutable `Carbon` unless you have a strong reason.
- Keep business logic out of controllers and models. Put orchestration in Actions.
- Use SOLID principles, repositories for data access, and factories for object creation when those abstractions improve clarity.

## Naming

- Classes: `PascalCase`.
- Methods and variables: `camelCase`.
- Constants: `SCREAMING_SNAKE`.
- Database tables and columns: `snake_case`.
- Boolean names: `is_`, `has_`, `can_`.

## Team Roles

- Auditor: strict auditing/security (`.github/agents/Auditor.agent.md`).
- QC: QA/testing (`.github/agents/QC.agent.md`).
- Visionary: architecture (`.github/agents/Visionary.agent.md`).

## Compatibility Policy

- Breaking changes are allowed when they improve the system. Backward compatibility is not required unless a task explicitly asks for it.

=== .ai/docs rules ===

# Documentation Guidelines

## Location and Structure

- Put package docs in `packages/<pkg>/docs/`.
- Required files: `01-overview.md`, `02-installation.md`, `03-configuration.md`, `04-usage.md`, `99-troubleshooting.md`.
- Use Markdown with YAML frontmatter. Every file must include a `title:` entry.

## Writing Rules

- Use `##` for main sections and `###` for subsections.
- Examples must be copy-paste ready, including imports and namespaces where relevant.
- Cross-reference related docs using relative links.
- Call out breaking changes explicitly and explain the migration path.

## Callouts

- Use the docs callout syntax consistently when a callout improves readability.
- Supported variants: `info`, `warning`, `tip`, `danger`.

=== .ai/filament rules ===

# Filament Guidelines

## Platform Rules

- Use Filament v5 APIs.
- Filament v5 is the target surface. If v5 documentation is thin, the equivalent v4 examples are acceptable because the APIs are compatible.
- Use the official Filament plugins for Tags, Settings, Media, and Fonts when those capabilities are needed.
- Use the built-in `Import` and `Export` actions only.

## Tenancy

- Filament tenancy is not a security boundary. All queries and all action handlers must still obey the owner-scoping contract.

## Navigation

### Config Standard

Every `filament-*` package MUST use a nested `navigation.group` key in its config file:

```php
// config/filament-xxx.php
'navigation' => [
    'group' => 'Default Group Name',
],
```

Settings pages use `navigation.settings_group` instead of `navigation.group`.

### Resource/Page Standard

Every Resource or Page MUST use `getNavigationGroup()` reading from config. Do NOT use the `$navigationGroup` static property:

```php
public static function getNavigationGroup(): string | UnitEnum | null
{
    return config('filament-xxx.navigation.group');
}
```

### Navigation Sort

When navigation sort order is configurable, use `getNavigationSort()` reading from config:

```php
public static function getNavigationSort(): ?int
{
    return config('filament-xxx.navigation.sort');
}
```

### Run-time Overrides

The `CommerceNavigation` engine (from `commerce-support`) supports overriding any navigation setting at runtime via `commerce-support.filament.navigation.items.{FQCN}`. Config-driven navigation is the foundation that makes this work — the engine reads a resource's config default, then merges runtime overrides on top.

### What NOT to do

- Do NOT use `$navigationGroup` static property on a Resource or Page (blocks runtime override)
- Do NOT use flat config keys like `navigation_group` (use nested `navigation.group`)
- Do NOT hardcode a group string in `getNavigationGroup()` — always read from config
- Do NOT delegate through a plugin (avoid `Plugin::get()->getNavigationGroup()` pattern) — resources should read `config()` directly

## Verification

- Double-check method signatures in the installed Filament version before shipping.
- Verify no static `$navigationGroup` remains: `rg "static.*\$navigationGroup" packages/filament-*/src`
- Verify config uses nested key: `rg "'navigation_group'" packages/filament-*/config` (should be empty)

=== .ai/general rules ===

# General Guidelines (Monorepo-Specific)

Use this file for cross-cutting judgment, planning, and change execution.

## Interaction Discipline

- When the user asks a question or raises a concern, answer it directly. Do not jump to editing files unless they explicitly ask for changes.
- If you start editing before the user finishes their thought, stop. Revert the premature edit and let them finish.
- Waiting for direction is better than acting on assumption.

## Plan Before Coding

- For non-trivial work such as multi-step changes, architecture decisions, or risky edits, write a brief plan before coding.
- If new evidence invalidates the plan, stop and re-plan.
- State assumptions explicitly. If there are multiple interpretations, name them and ask instead of guessing.
- Push back when a request is unclear, internally inconsistent, or overcomplicated.

## Choose the Right Shape of Change

- Start codebase-aware by default: inspect sibling files, follow established conventions, and prefer the smallest change that fits the package boundary.
- Switch to architecture-first when copying the existing pattern would spread a known design problem, duplicate shared logic across packages, or create a fix that is locally correct but systemically wrong.
- When you switch, say so explicitly: name the local pattern you are not copying, explain why, propose the smallest shared correction, and list the surfaces that need verification.
- Stay architecture-first in scope, not in blast radius: prefer one well-placed shared primitive or boundary correction over a broad rewrite.
- Preserve extension seams where they help the codebase stay adaptable: hooks, domain events, metadata, contracts, resolvers, and support classes.

## Keep the Change Surgical

- Use the smallest correct change.
- Do not add speculative abstractions, configurability, or error handling for impossible cases.
- If a 50-line fix is enough, do not write 200.
- Match existing style; do not refactor adjacent code, comments, or formatting.
- Clean up only your own mess.
- Mention unrelated dead code instead of deleting it.
- Remove only imports, variables, or functions your change makes unused.
- Never "cleanup" or mass-revert without permission.

## Runtime Extension Safety

- Before removing a method that static analysis reports as undefined, verify runtime extension sources first.
- Check `macro()` / `hasMacro()` registrations, package mixins or traits, and framework or plugin extension points.
- If the method is runtime-provided, preserve behavior and fix analysis with a narrow, targeted change.

## Reusable Orchestration

- Prefer Laravel Actions for reusable orchestration that spans transactions, side effects, normalization, or multiple entry points.
- Keep trivial single-step handlers inline when extraction adds no clarity.
- Reuse existing Actions before creating new ones.

## Behavioral Changes

- When a task changes user behavior such as entry points, forms, actions, or meaningful workflow transitions, evaluate whether product tracking should be updated.
- Prefer high-signal events over noisy click logs.
- Prefer server-confirmed events for backend outcomes.

## Proof

- Verify behavior before declaring done.
- Write a brief success criterion for multi-step work.
- Turn tasks into tests or checks when possible:
  - Add validation -> write failing tests first, then make them pass.
  - Fix a bug -> reproduce it with a test, then fix it.
  - Refactor -> verify behavior before and after.
- Every changed line should trace directly to the request.

=== .ai/model rules ===

# Model Guidelines

## Base Model Contract

- Use `Illuminate\Database\Eloquent\Concerns\HasUuids`.
- Do not set `protected $table`; implement `getTable()` using package config so table names can be prefixed and remapped per package.

## Type Safety

- Type relations and collections with PHPDoc generics.

## Relationship Behavior

- Implement application-level cascades in `booted()` using delete or null-out behavior.
- Never rely on database cascades.

## Lifecycle

- When a model has a status or state machine, keep the enum, transition code, and lifecycle columns in sync.
- Record business-critical terminal transitions in dedicated `timestampTz` columns.
- Use `*_at` for the actual transition time and keep scheduled deadlines such as `expires_at` separate.
- Do not bury lifecycle events in JSON or booleans when the timestamp matters operationally.
- Keep the state-to-timestamp mapping centralised in the transition method or supporting trait.
- Use immutable date casts for lifecycle timestamps when the model supports them.

## Verification

- Search for forbidden DB cascades or constraints in migrations: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`

=== .ai/multitenancy rules ===

# Multitenancy Guidelines

## Boundary and Trust

- Every package that stores tenant-owned data must define an explicit tenant boundary and enforce it on every read and write path.
- Multi-tenancy primitives live in `commerce-support`.
- Filament form options are not security. Always validate on the server.
- `owner_type` / `owner_id` is the default tenant boundary tuple. If a package customizes the column names, it must preserve the same semantics through `HasOwner` and `ownerScopeConfig()`, and it must not reuse the boundary columns for unrelated meaning.

## Runtime Ownership

- Owner enforcement is activated by `commerce-support.owner.enabled`.
- Bind `AIArmada\CommerceSupport\Contracts\OwnerResolverInterface` to resolve the current owner. When owner mode is enabled, using `NullOwnerResolver` is invalid and should fail fast.
- Use `OwnerContext::withOwner($owner, fn () => ...)` for scoped work and `OwnerContext::withOwner(null, fn () => ...)` for explicit global work.
- Use `OwnerContext::setForRequest()` only from middleware or framework integrations. Do not use it as a generic escape hatch.
- Use `AIArmada\CommerceSupport\Middleware\NeedsOwner` on routes or surfaces that must not proceed without a resolved owner.

## Data Model

- Tenant-owned tables use `$table->nullableMorphs('owner')`.
- Tenant-owned models use `HasOwner` from `commerce-support`.
- When scoping is package-configurable, implement `ownerScopeConfig()` via `HasOwnerScopeConfig` and set the package config key.
- Non-tenant ownership concepts such as wallet holder, payee, or actor must use different column names and relationships.

## Ownership Semantics

- Owner-scoped reads require either a resolved owner or explicit global context.
- Persisted owner tuples are immutable after creation. Owned rows must not be promoted, demoted, or reassigned by editing `owner_type` or `owner_id` directly.
- New owned rows may inherit the current owner automatically when `auto_assign_on_create` is enabled.
- Global writes are privileged. Mutating persisted global rows requires explicit global context.
- Helper methods such as `removeOwner()` are only safe on unsaved or new models.
- Default enforcement is a global `OwnerScope` when the model's owner scoping config is enabled.
- Default include-global is `false` unless business rules require otherwise.
- `owner = null` means global-only records, not "all owners".
- Cross-tenant or system operations are allowed only when the call site uses an explicit, greppable opt-out.
- Code that reads or mutates global rows must enter explicit global context instead of relying on a missing owner.

## Query And Write Enforcement

- Tenant-owned `HasOwner` models should rely on `commerce-support`'s `OwnerScope` rather than package-local ad hoc scoping logic.
- Intentionally cross-tenant work must use an explicit opt-out such as `->withoutOwnerScope()` or `withoutGlobalScope(OwnerScope::class)`.
- For inbound IDs on write paths, prefer `OwnerWriteGuard::findOrFailForOwner()` or `ResolveOwnedModelOrFailAction` over hand-rolled checks.
- Reads must be owner-enforced on every surface, UI and non-UI alike. Use `Model::forOwner($owner)` when intentionally selecting an owner context or including global rows, and use `Model::globalOnly()` for global-only records.
- Any inbound foreign IDs such as `location_id`, `order_id`, or `batch_id` must be validated as belonging to the current owner scope before attach or update.
- `DB::table(...)` paths touching tenant-owned data must apply `OwnerQuery::applyToQueryBuilder(...)` because Eloquent global scopes do not apply.
- If a package supports global rows, include-global behavior must be explicit and consistent.
- If you remove the global scope, immediately reapply scoping intentionally (`forOwner`, `globalOnly`, `OwnerQuery`, and so on) unless the operation is truly cross-tenant by design.

## HTTP, Filament, And Background Work

- Filament resources must return owner-safe queries from `getEloquentQuery()`.
- Validate IDs again inside `->action()` handlers for defense in depth, preferably with `OwnerWriteGuard` or `ResolveOwnedModelOrFailAction`.
- Scope relationship option queries and validate submitted IDs.
- Widgets, badges, counts, sums, exists checks, and other aggregates must be owner-scoped.
- Route model binding and download routes must not resolve cross-tenant rows for `HasOwner` models.
- Prefer hardened binding patterns where applicable, such as `OwnerRouteBinding::bind(...)`, an owner-safe query, or `ResolveOwnedModelOrFailAction`.
- Console commands, queued jobs, scheduled tasks, exports, reports, health checks, and webhooks must apply the same owner scoping as HTTP and Filament.
- Prefer `OwnerContextJob` for queued jobs, or implement `OwnerScopedJob` when you need an explicit owner payload contract.
- Commands and batch processing should iterate owners explicitly and wrap each unit of work in `OwnerContext::withOwner(...)`.
- Jobs and commands must not rely on ambient web auth to resolve the owner.

## Shared Infrastructure

- Use `OwnerCache` for tenant-sensitive cache keys. Never share raw cache keys across owners.
- Use `OwnerFilesystem` for tenant-sensitive file storage paths. Never concatenate raw `owner_id` paths manually.
- Use `OwnerScopeKey` when a package needs stable owner-specific cache or file prefixes.

## Verification

- Add at least one cross-tenant regression test proving reads are isolated and writes throw or abort.
- For `HasOwner` models, prefer reusing `commerce-support`'s `OwnerScopingContractTests` where practical.
- Grep for unscoped entry points:
  - `rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/<pkg>/src`
  - `rg -n -- "count\(|sum\(|avg\(|exists\(" packages/<pkg>/src`
  - `rg -n -- "DB::table\(" packages/<pkg>/src`
  - `rg -n -- "Route::.*\{.*\}" packages/<pkg>/routes`
  - `rg -n -- "withoutOwnerScope\(|withoutGlobalScope\(.*Owner" packages/<pkg>/src`
- Grep for non-standard tuple usage and confirm it is intentional and configured:
  - `rg -n -- "owner_type|owner_id|owner_type_column|owner_id_column" packages/<pkg>/src packages/<pkg>/config packages/<pkg>/database`

=== .ai/packages rules ===

# Packages Guidelines

## Package Boundaries

- Packages must work standalone. Prefer `suggest` over hard `require` for optional integrations.
- When related packages are installed together, auto-enable integrations in service providers with `class_exists()` checks.

## Shared Foundations

- Always check `commerce-support` for existing primitives, traits, helpers, and contracts before building custom logic or requiring an external package directly.
- If a capability is useful across packages now or soon, implement it in `commerce-support` so behavior stays consistent and maintainable long term.
- When a capability may grow variants, prefer stable extension seams such as contracts, metadata, hooks, domain events, resolvers, and support classes. Put shared seams in `commerce-support` when multiple packages may benefit.
- Prefer tagged registrars or contributor interfaces for optional integrations instead of hard-coded service-provider branching.
- Keep foundation service providers lean. If they start enumerating downstream packages, split the registration into registrars or support classes.
- When orchestration repeats across HTTP, jobs, listeners, commands, or UI entry points, extract a reusable Action, Service, or Use Case.

## Money And Storage

- Treat money as integer minor units plus an explicit currency code.
- Use `commerce-support` money primitives before rolling your own: `MoneyNormalizer` for normalization, `FormatsMoney` or Akaunting `money(..., ..., false)` for display or value formatting, and package or domain `Money` objects where contracts already expect them.
- Do not hand-roll currency display with raw `number_format()` and string concatenation when a shared formatter is available.
- No soft deletes (`SoftDeletes`).

## Verification

- Verify both standalone install and integrated behavior.

=== .ai/phpstan rules ===

# PHPStan Guidelines

## Baseline

- Level 6.
- Analyse per package, for example `packages/<pkg>/src`, not repo-wide.

## Rules

- Respect `phpstan.neon`.
- Do not add new `ignoreErrors` entries unless root-cause fixes are exhausted.
- Prefer real fixes over suppression.

## Verification

- Example: `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`

=== .ai/spatie rules ===

# Spatie Guidelines

## Preferred Packages

- DTOs: `spatie/laravel-data`
- Logging: `activitylog` for business events, `auditing` for compliance
- Webhooks: `spatie/laravel-webhook-client` for the idempotent job pattern
- Media: `spatie/laravel-medialibrary`
- Settings: `spatie/laravel-settings`
- Tags: `spatie/laravel-tags`
- States: `spatie/laravel-model-states`

## Rule Of Thumb

- If one of these packages solves the problem, use it instead of inventing a custom subsystem.

=== .ai/test rules ===

# Testing Guidelines

## Goal

- Eliminate bugs.

## Parallelism

- Every Pest or PHPUnit invocation must include `--parallel`.
- This applies to single files, directories, package sweeps, and final verification runs.
- If a command does not support `--parallel`, use the closest parallel-capable equivalent instead of omitting it.

## References

- [Overview](https://filamentphp.com/docs/5.x/testing/overview)
- [Resources](https://filamentphp.com/docs/5.x/testing/testing-resources)
- [Tables](https://filamentphp.com/docs/5.x/testing/testing-tables)
- [Schemas](https://filamentphp.com/docs/5.x/testing/testing-schemas)
- [Actions](https://filamentphp.com/docs/5.x/testing/testing-actions)
- [Notifications](https://filamentphp.com/docs/5.x/testing/testing-notifications)

## Execution

- Do not run everything. Run tests per package or scope.
- Single file: `./vendor/bin/pest --parallel path/to/Test.php`
- Directory: `./vendor/bin/pest --parallel path/to/dir`
- Full suite: `./vendor/bin/pest --parallel ...` only for final verification.

## Coverage

- Always include `--parallel` when using `--coverage`.
- Command: `./vendor/bin/pest --coverage --parallel`
- Do not run full coverage if `0% files > 10%`.
- Targets: Core >=80%, Filament >=70%, Support >=80%.
- Always pipe output with `2>&1 | tee /tmp/out.txt`.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4

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
