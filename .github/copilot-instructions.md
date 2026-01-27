# Commerce Custom Guidelines

## Config
```
# Config Guidelines

All configuration options must be actively used or implemented in the codebase.

## Standard Config Order

Config files MUST follow this section order:

### Core Package Configs
1. Database - Tables, prefixes, JSON column types
2. Credentials/API - Keys, secrets, environment
3. Defaults - Currency, tax rates, default values
4. Features/Behavior - Core feature toggles
5. Integrations - Other package integrations
6. HTTP - Timeouts, retries
7. Webhooks - Webhook configuration
8. Cache - Caching settings
9. Logging - Logging configuration

### Filament Package Configs
1. Navigation - Group, sort order
2. Tables - Polling, formats
3. Features - Feature toggles
4. Resources - Resource-specific settings

## Rules
- If a config key is defined but not referenced anywhere, remove it.
- Publish only necessary configs via `php artisan vendor:publish`.
- Keep `config/*.php` files minimal and purposeful.
- Packages with JSON columns in migrations MUST have `json_column_type` config.
- Use compact section headers (single line description only).
- Group related settings under nested arrays.
- Prefer opinionated defaults over excessive configuration.
- Remove redundant env() wrappers for non-sensitive hardcoded values.

## Comment Style
Use compact Laravel-style section headers. Inline comments only for non-obvious values.

## Verification
Search codebase for config key usage:
```bash
grep -r "config('package.key')" src/ packages/*/src/
```
If no matches, remove the config.

## Comment Style
Use compact Laravel-style section headers. Inline comments only for non-obvious values.

## Verification
Search codebase for config key usage:
```bash
grep -r "config('package.key')" src/ packages/*/src/
```
If no matches, remove the config.
```
## Database
```
# Database Guidelines

## Primary Keys
- All tables must use `uuid('id')->primary()` for primary key.

## Foreign Keys
- Use `foreignUuid('relation_id')` for foreign key columns.
- **Do NOT** add `->constrained()`, `->cascadeOnDelete()`, or any DB-level constraints/cascading.
- Application logic must handle referential integrity and cascades.

## Example Migration
```php
Schema::create('orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id');
    $table->foreignUuid('cart_id');
    $table->timestamps();
});
```

## Verification
- Review migrations: no `constrained()` or cascade methods on foreign keys.
- Ensure Eloquent relations handle cascades (e.g., `cascadeOnDelete()` in models).
```
## Models
```
## Model Guidelines

**CRITICAL**: Never use database-level foreign key constraints or cascades (`->constrained()`, `->cascadeOnDelete()`). Handle all referential integrity and cascading **in application code only**.

### Required Model Structure

```php
<?php

declare(strict_types=1);

namespace {{ $namespace }}\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, {{ $childModel }}> ${{ $childPlural }}
 */
class {{ $modelClass }} extends Model
{
    use HasUuids;

    protected $fillable = [
        // List fillable columns matching migration
    ];

    public function getTable(): string
    {
        $tables = config('{{ $configKey }}.database.tables', []);
        $prefix = config('{{ $configKey }}.database.table_prefix', '{{ $tablePrefix}}_');

        return $tables['{{ $tableKey }}'] ?? $prefix.'{{ $tableName }}';
    }

    /**
     * @return HasMany<{{ $childModel }}, $this>
     */
    public function {{ $childPlural }}(): HasMany
    {
        return $this->hasMany({{ $childModel }}::class, '{{ $foreignKey }}');
    }

    /**
     * @return BelongsTo<{{ $parentModel }}, $this>
     */
    public function {{ $parentSnake }}(): BelongsTo
    {
        return $this->belongsTo({{ $parentModel }}::class, '{{ $foreignKey }}');
    }

    /**
     * Application-level cascade delete (NO database constraints!)
     */
    protected static function booted(): void
    {
        static::deleting(function ({{ $modelClass }} ${{ $modelVar }}): void {
            ${{ $modelVar }}->{{ $childPlural }}()->delete();
            // Add other cascades as needed
            // For nullable FKs: ${{ $modelVar }}->{{ $childPlural }}()->update(['{{ $foreignKey }}' => null]);
        });
    }

    protected function casts(): array
    {
        return [
            // Casts for dates, JSON, booleans, enums
            '{{ $jsonField }}' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
```

### Cascade Rules

| Relationship | Delete Action | Example |
|--------------|---------------|---------|
| `hasMany` children | `->delete()` | `$order->items()->delete();` |
| Nullable FK children | `->update(['fk' => null])` | `$order->webhookLogs()->update(['order_id' => null]);` |

### Verification Checklist
- ✅ `HasUuids` trait
- ✅ `getTable()` from config (no hardcoded names)
- ✅ `booted()` with cascade deletes
- ✅ **NO** `protected $table` property
- ✅ PHPDoc `@property` annotations
- ✅ Type-safe relations with generics
- ✅ PHPStan level 6 compliant

**Migration**: Use `foreignUuid('order_id')` **without** `->constrained()` or cascades.
```
## Docs
```
# Documentation Guidelines (Filament-Style)

Docs are stored as markdown in the main repo, with a separate site that builds them.

## File Structure

### Naming Convention
- Use numbered prefixes for ordering: `01-overview.md`, `02-installation.md`
- Use lowercase kebab-case: `03-getting-started.md`
- One topic per file, max ~500 lines

### Required Frontmatter
Every markdown file MUST start with:
```yaml
---
title: Page Title
---
```

### Astro Component Imports (for future docs site)
Add after frontmatter for rich content:
```md
---
title: Configuration
---
import Aside from "@components/Aside.astro"

<Aside variant="warning">
    Breaking change in v2.0...
</Aside>
```

## Package docs/ Structure

Each package must have:
1. `01-overview.md` - Introduction, features
2. `02-installation.md` - Composer, config, migrations
3. `03-configuration.md` - All config options
4. `04-usage.md` - Basic usage patterns
5. Feature-specific docs (numbered)
6. `99-troubleshooting.md` - Common issues

## Content Style
- `##` for main sections, `###` for subsections
- Working code examples with full imports
- Cross-reference related docs with relative links
- Use `<Aside>` components for callouts

## Verification
```bash
# Check frontmatter exists
grep -L "^---" packages/*/docs/*.md

# Find docs without numbered prefix
ls packages/*/docs/*.md | grep -v "/[0-9][0-9]-"
```
```
## Packages
```
# Packages Guidelines

## Independence
- Packages must work fully standalone without requiring other commerce packages.
- Use `suggest` or optional dependencies in `composer.json`, not `require`.

## Tight Integration
- When related packages are installed together, enable seamless integrations:
  - Auto-setup relations, events, middleware via service provider checks.
  - Use `class_exists()` or `config('package.enabled')` for conditional features.

## Example Service Provider
```php
public function boot(): void
{
    if (class_exists(Cashier::class)) {
        // Cart-Cashier integration
    }
    
    if (class_exists(Chip::class)) {
        // Cart-Chip integration
    }
}
```

## Verification
- Test standalone: `composer require package/cart`
- Test integrated: Install multiple, verify auto-features.
```
## Multitenancy
```
# Multitenancy Guidelines (Monorepo Contract)

## Single Source of Truth
- Multi-tenancy primitives live in `commerce-support`.

## Data Model (Required)
- Tenant-owned tables MUST use `$table->nullableMorphs('owner')`.
- Tenant-owned models MUST `use HasOwner`.
- Bind `OwnerResolverInterface` in the container to resolve current owner context.

## Query Rules (Non-negotiable)
- Every read path MUST be owner-scoped: resources, widgets, services, exports/reports, jobs, commands, health checks.
- Never trust Filament option lists: validate submitted IDs (e.g. `location_id`, `order_id`, `batch_id`) belong to the current owner scope.
- If global rows are supported, keep semantics explicit and consistent: `forOwner($owner)` vs `globalOnly()`.

## Verification
- Add at least one cross-tenant regression test per package.
- Grep for unscoped entrypoints:
    - `rg -- "::query\(|->query\(|->getEloquentQuery\(" packages/<pkg>/src`
```
## PHPStan
```
# PHPStan Guidelines

PHPStan must pass at level 6 for all code.

## Verification

Run the following command to verify:

```bash
./vendor/bin/phpstan analyse --level=6
```

## Configuration

The project's `phpstan.neon` configures the baseline. Ensure no errors at level 6 before merging changes.
```
## Test
```
# Testing Guidelines

## Running Tests

**Don't run all tests at once. The test suite is too large and inefficient. Always test by individual package using `tests/src/PackageName`.**

Use `--parallel` flag to speed up test execution:

```bash
./vendor/bin/pest tests/src/PackageName --parallel
```

## Fixing Multiple Test Failures

When fixing tests that have many failures:

1. **Record failures first** - Run tests once and capture all failing test names/locations to a file before making any fixes
2. **Analyze patterns** - Group failures by root cause (e.g., missing field, wrong assertion, invalid test data)
3. **Batch fixes** - Fix all related issues together before re-running tests
4. **Avoid repeated runs** - Test suites are large and slow; minimize full test runs by:
   - Fixing all identified issues in one pass
   - Running only the specific test file during development: `./vendor/bin/pest tests/path/to/TestFile.php`
   - Using `--filter` to run specific test cases when debugging

Example workflow:
```bash
# 1. Run once and capture failures
./vendor/bin/pest tests/src/PackageName --configuration=.xml/package.xml 2>&1 | tee test-failures.txt

# 2. Fix all issues based on the captured output

# 3. Run specific test file to verify fixes
./vendor/bin/pest tests/src/PackageName/Unit/SpecificTest.php --configuration=.xml/package.xml

# 4. Run full suite only after individual files pass
./vendor/bin/pest tests/src/PackageName --parallel --configuration=.xml/package.xml
```

## Coverage

- Scope coverage to specific packages using dedicated PHPUnit XML configs inside .xml folder (e.g., `cart.xml`, `vouchers.xml`).
- Create `package.xml` if it doesn't exist, following the structure of existing ones (bootstrap autoload, testsuite directory, source include, env vars).
- Run coverage:

```bash
./vendor/bin/phpunit .xml/package.xml --coverage
```

- All non filament packages must achieve **minimum 85% coverage**.
- Verify with `./vendor/bin/pest --coverage --min=85` for workspace-wide checks when applicable.

```
## File Safety
```
# File Safety Guidelines

## Git / Working Tree Safety
- **NEVER** do any repo "cleanup" without explicit user instruction/permission.
- This includes (but is not limited to): `git restore`, `git checkout -- <path>`, `git reset`, `git clean`, removing untracked files, mass-reverting changes, or otherwise trying to "get back to a clean state".
- If the working tree is messy or another agent is changing files: stop and ask what to do.

## Backup Before Removal
- **ALWAYS** backup files before removing or replacing them.
- Use `cp file.php file.php.bak` before any destructive operation.
- Remove the backup file after successful completion.
- Never delete files without creating a backup first.

## Verification
- Confirm backup exists before proceeding with removal.
- Run tests after changes to ensure nothing is broken.
- Only delete backup after all tests pass.
```

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

## Verification mindset

- Prefer **small, auditable changes** over broad refactors.
- Use per-package checks (tests/PHPStan) instead of repo-wide runs.
- When a guideline requires verification, either run it (if feasible) or call out what must be run by the user.

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

=== .ai/multitenancy rules ===

# Multitenancy Guidelines

## Monorepo Contract

- **Boundary is mandatory**: Every package that stores tenant-owned data MUST define an explicit tenant boundary and enforce it on **every** read/write path.
- **Single source of truth**: Multi-tenancy primitives live in `commerce-support`.
- **No UI trust**: Filament form options are not security. Always validate on the server.
- **Column semantics**: `owner_type/owner_id` is reserved for the tenant boundary owner (rename any non-tenant uses).

## Design defaults (align ecosystem behavior)

- **Default enforcement**: prefer **default-on** owner enforcement (global scope) when owner mode is enabled.
- **Default include-global**: `false` unless explicitly required by business rules.
- **Meaning of `owner = null`**: treat as **global-only** records.
- **Cross-tenant/system operations**: allowed only when the call site uses an explicit, greppable opt-out.

## Data Model (Required)

- **Migration**: `$table->nullableMorphs('owner')` for tenant-owned tables.
- **Model**: `use HasOwner` (from `commerce-support`).
- **Provider**: Bind `OwnerResolverInterface` to resolve current owner context.
- **Non-tenant "ownership"** (wallet holder, payee, actor, etc.) MUST use different column names/relationships.

## Enforcement (Default-on)

- Tenant-owned `HasOwner` models SHOULD be protected by commerce-support's default-on owner global scope when owner mode is enabled.
- **Escape hatch**: intentionally cross-tenant/system operations MUST use an explicit, greppable opt-out (e.g. `->withoutOwnerScope()` / `withoutGlobalScope(OwnerScope::class)`).
- **Non-request surfaces** (jobs/commands/schedules): MUST NOT rely on ambient web auth. Pass/iterate owner explicitly and apply owner context via the standard override mechanism.

## Query Rules (Non-negotiable)

- **Reads**: Must be owner-enforced on every surface (UI + non-UI). Prefer default-on enforcement; use `Model::forOwner($owner)` only when you are intentionally selecting an owner context and/or explicitly including global rows.
- **Writes**: Any inbound foreign IDs (e.g., `location_id`, `order_id`, `batch_id`) MUST be validated as belonging to the current owner scope before attach/update.
- **Query builder**: `DB::table(...)` paths touching tenant-owned data MUST apply the query-builder owner scoping helper (Eloquent global scopes do not apply).
- **Global rows**: If a package supports global rows, provide clear semantics:
  - `Model::forOwner($owner)` (owner-only)
  - `Model::globalOnly()` (global-only)
  - Optional: include-global behavior must be explicit and consistent.

## Filament Rules

- **Resources**: Ensure `getEloquentQuery()` is owner-safe (default-on scope or explicit scoping). Don’t rely solely on `$tenantOwnershipRelationshipName` or UI filters.
- **Actions**: Validate IDs again inside `->action()` handlers (defense-in-depth).
- **Relationship selects**: Scope option queries and validate submitted IDs.

## Routing & Binding

- Route-model binding/download routes MUST NOT resolve cross-tenant rows for `HasOwner` models.
- Prefer hardened binding patterns where applicable (e.g., bind with an owner-safe query, or resolve via an action that enforces owner).

## Non-UI Surfaces

- Console commands, queued jobs, scheduled tasks, exports, reports, health checks, and webhooks MUST apply the same owner scoping as HTTP/Filament.
- Jobs/commands MUST NOT rely on ambient web auth to resolve owner; pass/iterate owner explicitly and apply owner context via the standard override mechanism.

## Verification (Required)

  - Cross-tenant writes throw/abort.
- Grep for unscoped entrypoints:
  - `rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/<pkg>/src`
  - `rg -n -- "count\(|sum\(|avg\(|exists\(" packages/<pkg>/src`
  - `rg -n -- "DB::table\(" packages/<pkg>/src`
  - `rg -n -- "Route::.*\{.*\}" packages/<pkg>/routes`
  - `rg -n -- "withoutOwnerScope\(|withoutGlobalScope\(.*Owner" packages/<pkg>/src`

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

=== .ai/database rules ===

# Database Guidelines

- **Primary keys**: `uuid('id')->primary()`.
- **Foreign keys**: `foreignUuid('col')` only.
- **Never** add DB-level constraints or cascades: no `->constrained()`, no `->cascadeOnDelete()`, no FK constraints.
- **Cascades/integrity**: enforce in application logic (models/actions/services).
- **Migrations**: keep safe/idempotent; no `down()` required.

## Verification

- Ensure no constraints/cascades slipped in: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`

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

=== .ai/packages rules ===

# Packages Guidelines

- **Independence**: Packages must work standalone. Prefer `suggest` over hard `require` for optional integrations.
- **Foundation**: Always check `commerce-support` for existing primitives, traits, or contracts before building custom logic or requiring external packages directly.
- **Integration**: When related packages are installed together, auto-enable integrations via `class_exists()` checks in service providers.
- **DTOs**: Use `spatie/laravel-data`.
- **Deletes**: No soft deletes (`SoftDeletes`).
- **Testing**: Verify both standalone install and integrated behavior.

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

- php - 8.4.16
- filament/filament (FILAMENT) - v5
- larastan/larastan (LARASTAN) - v3
- laravel/cashier (CASHIER) - v16
- laravel/framework (LARAVEL) - v12
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `livewire-development` — Develops reactive Livewire 4 components. Activates when creating, updating, or modifying Livewire components; working with wire:model, wire:click, wire:loading, or any wire: directives; adding real-time updates, loading states, or reactivity; debugging component behavior; writing Livewire tests; or when the user mentions Livewire, component, counter, or reactive UI.
- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `debugging-output-and-previewing-html-using-ray` — Use when user says &quot;send to Ray,&quot; &quot;show in Ray,&quot; &quot;debug in Ray,&quot; &quot;log to Ray,&quot; &quot;display in Ray,&quot; or wants to visualize data, debug output, or show diagrams in the Ray desktop application.

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

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces using only PHP — no JavaScript required.
- Instead of writing frontend code in JavaScript frameworks, you use Alpine.js to build the UI when client-side interactions are required.
- State lives on the server; the UI reflects it. Validate and authorize in actions (they're like HTTP requests).
- IMPORTANT: Activate `livewire-development` every time you're working with Livewire-related tasks.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== filament/blueprint rules ===

## Filament Blueprint

You are writing Filament v5 implementation plans. Plans must be specific enough
that an implementing agent can write code without making decisions.

**Start here**: Read
`/vendor/filament/blueprint/resources/markdown/planning/overview.md` for plan format,
required sections, and what to clarify with the user before planning.

=== filament/filament rules ===

## Filament

- Filament is used by this application. Follow existing conventions for how and where it's implemented.
- Filament is a Server-Driven UI (SDUI) framework for Laravel that lets you define user interfaces in PHP using structured configuration objects. Built on Livewire, Alpine.js, and Tailwind CSS.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices.

### Artisan

- Use Filament-specific Artisan commands to create files. Find them with `list-artisan-commands` or `php artisan --help`.
- Inspect required options and always pass `--no-interaction`.

### Patterns

Use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field" lang="php">
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

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),
</code-snippet>

Actions encapsulate a button with optional modal form and logic:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

Action::make('updateEmail')
    ->form([
        TextInput::make('email')->email()->required(),
    ])
    ->action(fn (array $data, User $record): void => $record->update($data)),
</code-snippet>

### Testing

Authenticate before testing panel functionality. Filament uses Livewire, so use `livewire()` or `Livewire::test()`:

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Test',
            'email' => 'test@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Test',
        'email' => 'test@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Validation" lang="php">
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

<code-snippet name="Calling Actions" lang="php">
    use Filament\Actions\DeleteAction;
    use Filament\Actions\Testing\TestAction;

    livewire(EditUser::class, ['record' => $user->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    livewire(ListUsers::class)
        ->callAction(TestAction::make('promote')->table($user), [
            'role' => 'admin',
        ])
        ->assertNotified();
</code-snippet>

### Common Mistakes

**Commonly Incorrect Namespaces:**
- Form fields (TextInput, Select, etc.): `Filament\Forms\Components\`
- Infolist entries (for read-only views) (TextEntry, IconEntry, etc.): `Filament\Infolists\Components\`
- Layout components (Grid, Section, Fieldset, Tabs, Wizard, etc.): `Filament\Schemas\Components\`
- Schema utilities (Get, Set, etc.): `Filament\Schemas\Components\Utilities\`
- Actions: `Filament\Actions\` (no `Filament\Tables\Actions\` etc.)
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

**Recent breaking changes to Filament:**
- File visibility is `private` by default. Use `->visibility('public')` for public access.
- `Grid`, `Section`, and `Fieldset` no longer span all columns by default.
</laravel-boost-guidelines>
