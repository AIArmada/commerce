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

## Agent skills

### Issue tracker

Issues are tracked in this repo's GitHub Issues. See `docs/agents/issue-tracker.md`.

### Triage labels

Use the canonical labels `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, and `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Treat this repo as multi-context: read `CONTEXT-MAP.md` first, then the relevant context `CONTEXT.md` and ADRs. See `docs/agents/domain.md`.

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

- **Tooling commands**: Prefer standard project-local binaries directly (`./vendor/bin/pest`, `./vendor/bin/phpstan`, `./vendor/bin/rector`, `./vendor/bin/pint`) in a normal local shell. Do **not** add or commit machine-specific launcher files/symlinks such as `php-local`; personal PHP/Herd wrappers belong in local shell config, not the repo.
- **Repo-safe local tooling**: Keep tracked agent/MCP config repo-safe. Local development credential files like `auth.json` may exist on your machine, but they must stay ignored and never be committed. Do **not** commit absolute home-directory paths, personal `SITE_PATH` values, or other machine-specific local tool wiring.
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

### Codebase-aware vs architecture-first

- Start **codebase-aware** by default: inspect sibling files, follow established conventions, and prefer the smallest change that fits the current package and boundary.
- Steer **architecture-first** when copying the existing pattern would spread a known design problem, duplicate shared logic across packages, or force a fix that is locally correct but systemically wrong.
- Common escalation signals:
  - the root cause lives in a shared primitive, package boundary, owner-scoping rule, or cross-cutting contract;
  - the same workaround would need to be repeated in multiple files or packages;
  - the local pattern conflicts with hard rules such as security, multitenancy, Octane safety, or package independence;
  - the right fix likely belongs in `commerce-support` or another shared foundation, not in one package-specific patch.
- When you switch, say so explicitly: name the local pattern you are not copying, explain why, propose the smallest architecture change that fixes the root cause, and list the surfaces that need verification.
- Stay architecture-first in scope, not in blast radius: prefer one well-placed shared primitive or boundary correction over a broad rewrite.

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

## 5) Thoughtfulness Before Changes

### Before coding

- State assumptions explicitly.
- If there are multiple interpretations, name them and ask instead of guessing.
- Surface tradeoffs and push back when a request is unclear or overcomplicated.

### Keep it simple

- Use the smallest correct change.
- Do not add speculative abstractions, configurability, error handling for impossible cases, or features beyond the request.
- If a 50-line fix is enough, do not write 200.
- Prefer the simplest solution a senior engineer would not call overengineered.

### Be surgical

- Touch only what the request requires.
- Match existing style; do not refactor adjacent code, comments, or formatting.
- Clean up only your own mess.
- Mention unrelated dead code instead of deleting it.
- Remove only imports, variables, or functions your change makes unused.

### Work toward proof

- Write a brief plan for multi-step work.
- Define success criteria up front.
- Verify each step until the result is done.
- Turn tasks into tests or checks when possible:
  - Add validation → write failing tests first, then make them pass.
  - Fix a bug → reproduce it with a test, then fix it.
  - Refactor X → verify behavior before and after.
- Every changed line should trace directly to the request.

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
- **Money & currency**: Treat money as integer minor units plus an explicit currency code. Use `commerce-support` money primitives before rolling your own: `MoneyNormalizer` for normalization, `FormatsMoney` or Akaunting `money(..., ..., false)` for display/value formatting, and package/domain `Money` objects where contracts already expect them. Do **not** hand-roll currency display with raw `number_format()` and string concatenation when a shared formatter is available.
- **Integration**: When related packages are installed together, auto-enable integrations via `class_exists()` checks in service providers.
- **DTOs**: Use `spatie/laravel-data`.
- **Deletes**: No soft deletes (`SoftDeletes`).
- **Testing**: Verify both standalone install and integrated behavior.

=== .ai/phpstan rules ===

# PHPStan Guidelines

- **Level**: 6
- **Scope**: per package (e.g. `packages/<pkg>/src`), not repo-wide.
- **Rules**:
  - Respect `phpstan.neon`.
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
- **Parallelism is mandatory**: Every Pest or PHPUnit test invocation must include `--parallel`.
  This applies to single files, directories, package sweeps, and final verification runs.
  If a command does not support `--parallel`, use the closest parallel-capable equivalent instead of omitting it.
- **Refs** (Filament v4 docs are acceptable for v5 testing APIs):
  - [Overview](https://filamentphp.com/docs/4.x/testing/overview)
  - [Resources](https://filamentphp.com/docs/4.x/testing/testing-resources)
  - [Tables](https://filamentphp.com/docs/4.x/testing/testing-tables)
  - [Schemas](https://filamentphp.com/docs/4.x/testing/testing-schemas)
  - [Actions](https://filamentphp.com/docs/4.x/testing/testing-actions)
  - [Notifications](https://filamentphp.com/docs/4.x/testing/testing-notifications)

## Execution

- **Do not run everything**. Run tests per package/scope.
- **Always use `--parallel`**. No exceptions.
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
- filament/filament (FILAMENT) - v5
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/cashier (CASHIER) - v16
- laravel/framework (LARAVEL) - v13
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

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

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

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

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== filament/blueprint rules ===

## Filament Blueprint

You are writing Filament v5 implementation plans. Plans must be specific enough
that an implementing agent can write code without making decisions.

**Start here**: Read
`/vendor/filament/blueprint/resources/markdown/planning/overview.md` for plan format,
required sections, and what to clarify with the user before planning.

=== filament/filament rules ===

## Filament

- Filament is a Laravel UI framework built on Livewire, Alpine.js, and Tailwind CSS. UIs are defined in PHP via fluent, chainable components. Follow existing conventions in this app.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

### Artisan

- Always use Filament-specific Artisan commands to create files. Find available commands with the `list-artisan-commands` tool, or run `php artisan --help`.
- Inspect required options before running, and always pass `--no-interaction`.

### Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field visibility" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>

Use `Set $set` inside `->afterStateUpdated()` on a `->live()` field to mutate another field reactively. Prefer `->live(onBlur: true)` on text inputs to avoid per-keystroke updates:

<code-snippet name="Reactive field update" lang="php">
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('title')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
        'slug',
        Str::slug($state ?? ''),
    )),

TextInput::make('slug')
    ->required(),

</code-snippet>

Compose layout by nesting `Section` and `Grid`. Children need explicit `->columnSpan()` or `->columnSpanFull()`:

<code-snippet name="Section and Grid layout" lang="php">
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make('Details')
    ->schema([
        Grid::make(2)->schema([
            TextInput::make('first_name')
                ->columnSpan(1),
            TextInput::make('last_name')
                ->columnSpan(1),
            TextInput::make('bio')
                ->columnSpanFull(),
        ]),
    ]),

</code-snippet>

Use `Repeater` for inline `HasMany` management. `->relationship()` with no args binds to the relationship matching the field name:

<code-snippet name="Repeater for HasMany" lang="php">
use Filament\Forms\Components\Repeater;

Repeater::make('qualifications')
    ->relationship()
    ->schema([
        TextInput::make('institution')
            ->required(),
        TextInput::make('qualification')
            ->required(),
    ])
    ->columns(2),

</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column value" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>

Use `SelectFilter` for enum or relationship filters, and `Filter` with a `->query()` closure for custom logic:

<code-snippet name="Table filters" lang="php">
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

SelectFilter::make('status')
    ->options(UserStatus::class),

SelectFilter::make('author')
    ->relationship('author', 'name'),

Filter::make('verified')
    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),

</code-snippet>

Actions are buttons that encapsulate optional modal forms and behavior:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data)),

</code-snippet>

### Testing

Testing setup (requires `pestphp/pest-plugin-livewire` in `composer.json`):

- Always call `$this->actingAs(User::factory()->create())` before testing panel functionality.
- For edit pages, pass `['record' => $user->id]`, use `->call('save')` (not `->call('create')`), and do not assert `->assertRedirect()` (edit pages do not redirect after save).

<code-snippet name="Table test" lang="php">
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Create resource test" lang="php">
use function Pest\Laravel\assertDatabaseHas;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertHasNoFormErrors()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);

</code-snippet>

<code-snippet name="Edit resource test" lang="php">
livewire(EditUser::class, ['record' => $user->id])
    ->fillForm(['name' => 'Updated'])
    ->call('save')
    ->assertNotified()
    ->assertHasNoFormErrors();

assertDatabaseHas(User::class, [
    'id' => $user->id,
    'name' => 'Updated',
]);

</code-snippet>

<code-snippet name="Testing validation" lang="php">
livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();

</code-snippet>

Use `->callAction(DeleteAction::class)` for page actions, or `->callAction(TestAction::make('name')->table($record))` for table actions:

<code-snippet name="Calling actions" lang="php">
use Filament\Actions\Testing\TestAction;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();

</code-snippet>

### Correct Namespaces

- Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
- Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

### Common Mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, `Fieldset`, and `Repeater` do not span all columns by default.
- **Use `Select::make('author_id')->relationship('author', 'name')` for BelongsTo fields.** `BelongsToSelect` does not exist in v4.
- **`Repeater` uses `->schema()`, not `->fields()`.**
- **Never add `->dehydrated(false)` to fields that need to be saved.** It strips the value from form state before `->action()` or the save handler runs. Only use it for helper/UI-only fields.
- **Use correct property types when overriding `Page`, `Resource`, and `Widget` properties.** These properties have union types or changed modifiers that must be preserved:
  - `$navigationIcon`: `protected static string | BackedEnum | null` (not `?string`)
  - `$navigationGroup`: `protected static string | UnitEnum | null` (not `?string`)
  - `$view`: `protected string` (not `protected static string`) on `Page` and `Widget` classes

=== spatie/guidelines-skills rules ===

# Project Coding Guidelines

- This codebase follows Spatie's coding guidelines.
- Always activate the `spatie-laravel-php` skill when writing, editing, reviewing, or formatting Laravel or PHP code.
- Always activate the `spatie-javascript` skill when writing, editing, reviewing, or formatting JavaScript or TypeScript code.
- Always activate the `spatie-version-control` skill when creating commits, branches, or managing Git operations.
- Always activate the `spatie-security` skill when configuring security, reviewing authentication, or setting up servers and databases.

=== spatie/laravel-activitylog rules ===

# spatie/laravel-activitylog

Activity logging package for Laravel. Logs model events and manual activities to a database table.

## Key Concepts

- **Activity**: An Eloquent model (`Spatie\Activitylog\Models\Activity`) storing log entries with subject, causer, event, attribute_changes, and properties.
- **Subject**: The model being acted upon (polymorphic `subject_type`/`subject_id`).
- **Causer**: The model that caused the action, typically the authenticated user (polymorphic `causer_type`/`causer_id`).
- **LogOptions**: Fluent configuration object returned by `getActivitylogOptions()` on models using the `LogsActivity` trait.
- **ActivityEvent**: Enum with cases `Created`, `Updated`, `Deleted`, `Restored`.
- **`attribute_changes`** column: stores `{"attributes": {...}, "old": {...}}` for tracked model changes.
- **`properties`** column: stores custom user data set via `withProperties()`.

## Traits

### `LogsActivity`

Add to models to automatically log create/update/delete events. Optionally implement `getActivitylogOptions()` to configure which attributes to track (defaults to logging events without attribute changes).

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Article extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
```

### `CausesActivity`

Add to user/causer models. Provides `activitiesAsCauser()` relationship.

### `HasActivity`

Combines `LogsActivity` and `CausesActivity`. Provides `activities()`, `activitiesAsSubject()`, and `activitiesAsCauser()`.

## Manual Logging

```php
activity()
    ->performedOn($article)
    ->causedBy($user)
    ->event(ActivityEvent::Updated)
    ->withProperties(['key' => 'value'])
    ->log('Article was updated');
```

## LogOptions Methods

| Method | Description |
|--------|-------------|
| `logFillable()` | Log all fillable attributes |
| `logAll()` | Log all attributes |
| `logOnly(array)` | Log specific attributes |
| `logExcept(array)` | Exclude attributes |
| `logOnlyDirty()` | Only log changed attributes |
| `dontLogEmptyChanges()` | Skip logging when no tracked attributes changed |
| `dontLogIfAttributesChangedOnly(array)` | Ignore updates that only change these attributes |
| `useLogName(string)` | Set custom log name |
| `setDescriptionForEvent(Closure)` | Custom description per event |
| `useAttributeRawValues(array)` | Store raw (uncast) values |

## Querying Activities

```php
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Enums\ActivityEvent;

Activity::forEvent(ActivityEvent::Created)->get();
Activity::causedBy($user)->get();
Activity::forSubject($article)->get();
Activity::inLog('orders')->get();
```

## Setting the causer

Override the causer for a block of code:

```php
use Spatie\Activitylog\Facades\Activity;

Activity::defaultCauser($admin, function () {
    // all activities here are caused by $admin
});

// or set globally for the rest of the request
Activity::defaultCauser($admin);
```

## Disabling Logging

```php
activity()->withoutLogging(function () {
    // no activities logged here
});
```

## Accessing Changes and Properties

```php
$activity = Activity::latest()->first();

// Tracked model changes (set automatically by LogsActivity)
$activity->attribute_changes; // Collection: {"attributes": {...}, "old": {...}}

// Custom user data (set via withProperties)
$activity->properties; // Collection
$activity->getProperty('key'); // single value
```

## Custom Activity Model

Set `activity_model` in `config/activitylog.php` to a class that extends `Model` and implements `Spatie\Activitylog\Contracts\Activity`. Use a custom model for custom table names or database connections.

## Customizing Actions

The package uses action classes (`LogActivityAction`, `CleanActivityLogAction`) that can be extended and swapped via config:

```php
// config/activitylog.php
'actions' => [
    'log_activity' => \App\Actions\CustomLogActivityAction::class,
    'clean_log' => \App\Actions\CustomCleanAction::class,
],
```

Custom action classes must extend the originals. Override protected methods (`save()`, `beforeActivityLogged()`, `resolveDescription()`, etc.) to customize behavior.

## Configuration

Key config options in `config/activitylog.php`:
- `enabled`: Master on/off switch (env: `ACTIVITYLOG_ENABLED`)
- `clean_after_days`: Days to keep records for `activitylog:clean` command
- `default_log_name`: Default log name (string)
- `default_auth_driver`: Auth driver for causer resolution
- `include_soft_deleted_subjects`: Include soft-deleted subjects
- `activity_model`: Custom Activity model class
- `default_except_attributes`: Globally excluded attributes
- `actions.log_activity`: Action class for logging activities
- `actions.clean_log`: Action class for cleaning old activities

=== spatie/laravel-medialibrary rules ===

## Media Library

- `spatie/laravel-medialibrary` associates files with Eloquent models, with support for collections, conversions, and responsive images.
- Always activate the `medialibrary-development` skill when working with media uploads, conversions, collections, responsive images, or any code that uses the `HasMedia` interface or `InteractsWithMedia` trait.

</laravel-boost-guidelines>
