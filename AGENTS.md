<laravel-boost-guidelines>
=== .ai/00-overview rules ===

# AI Guidelines Overview (Monorepo Contract)

These files are intentionally split by concern for easier maintenance. Read and apply all of them, and keep each rule in the narrowest subject file that fits it.

## Guideline Map

- `00-overview` — contract, runtime baseline, verification baseline, repo navigation.
- `general` — agent interaction, planning, change shape, proof, git safety.
- `development` — tooling scope, code conventions, compatibility policy.
- `database` — schema, columns, lifecycle timestamps, migrations, persistence policy.
- `model` — Eloquent base contract, relations, state-machine wiring.
- `config` — config keys, section order, JSON column type.
- `packages` — package boundaries, shared foundations, money, reusable orchestration.
- `docs` — package documentation structure and style.
- `filament` — Filament adapter and navigation rules.
- `multitenancy` — owner boundary, scoping, and enforcement.
- `phpstan` — static analysis and runtime-extension safety.
- `spatie` — preferred Spatie packages.
- `test` — test execution, parallelism, coverage.

## Rule Hierarchy

- Follow the strictest rule when guidance overlaps: security > data isolation > correctness > style.
- If instructions conflict or cannot both be satisfied, say so explicitly, explain the conflict, and choose the safest alternative.

## Runtime Baseline

- Target PHP 8.5+ only.
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
- Config comments should be section headers only; inline comments are only for non-obvious values.

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
- No soft deletes: never use the `SoftDeletes` trait and never add `deleted_at` columns.

## Lifecycle Columns

- Lifecycle columns are always named `status`. The `state` column name is reserved for geographic address regions (province/state next to `city`/`postcode`) or for mirroring an external API field name.
- Record business-critical terminal transitions in dedicated `timestampTz` columns.
- Use `*_at` for the actual transition time and keep scheduled deadlines such as `expires_at` separate.
- Non-terminal states use toggle timestamps (set on entry, cleared on exit, e.g. `held_at`); terminal states record once and never clear.
- Binary active/inactive lifecycles may use a single toggle timestamp (`deactivated_at`/`hidden_at`) with a scope instead of a `status` column.
- Do not bury lifecycle events in JSON or booleans when the timestamp matters operationally.

## Migrations

- Keep migrations safe and idempotent.
- No `down()` method is required.
- Evolve schema by editing the owning migration in place; do not add a new migration file per schema change and do not drop and recreate tables to alter them.
- Do not write data backfills or migrations that reinterpret legacy semantics; new columns start clean with no legacy null meaning.

## JSON Columns

- JSON columns use the package's configured `json_column_type`; never hardcode `json` or `jsonb` in a migration.

## Verification

- Ensure no constraints or cascades slipped in: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`
- Ensure no soft deletes slipped in: `rg -n -- "softDeletes\(|SoftDeletes" packages/*/database packages/*/src`
- Ensure JSON columns use package config: `rg -n -- "->json\(|->jsonb\(" packages/*/database`

=== .ai/development rules ===

# Development Guidelines

## Tooling and Scope

- Run tools such as Pint, PHPStan, and Pest only on modified packages.
- If touching `packages/*/src/**`, run Pint only on the changed files or at least only on the changed packages.
- Never run Pint repo-wide "just to be safe"; it creates noisy diffs across unrelated packages.
- Prefer the standard project-local binaries directly (`./vendor/bin/pest`, `./vendor/bin/phpstan`, `./vendor/bin/rector`, `./vendor/bin/pint`) in a normal local shell.
- Do not add or commit machine-specific launcher files or symlinks such as `php-local`; personal PHP/Herd wrappers belong in local shell config, not the repository.
- Keep tracked agent and MCP config repo-safe. Local development credential files like `auth.json` may exist on your machine, but they must stay ignored and never be committed.
- Do not commit absolute home-directory paths, personal `SITE_PATH` values, or other machine-specific local tool wiring.

## Code Conventions

- Use `CarbonImmutable` or other immutable date/time objects wherever possible; avoid mutable `Carbon` unless you have a strong reason.

## Compatibility Policy

- Breaking changes are allowed when they improve the system. Backward compatibility is not required unless a task explicitly asks for it.
- Do not add backward-compatibility shims, legacy aliases, or deprecated-code paths; remove the old path instead of keeping both.

=== .ai/docs rules ===

# Documentation Guidelines

## Location and Structure

- Put package docs in `packages/<pkg>/docs/`.
- Required files: `01-overview.md`, `02-installation.md`, `03-configuration.md`, `04-usage.md`, `99-troubleshooting.md`.
- Use Markdown with YAML frontmatter. Every file must include a `title:` entry.

## Writing Rules

- Examples must be copy-paste ready, including imports and namespaces where relevant.
- Cross-reference related docs using relative links.
- Call out breaking changes explicitly and explain the migration path.

## Callouts

- Use the docs callout syntax consistently when a callout improves readability.
- Supported variants: `info`, `warning`, `tip`, `danger`.

=== .ai/filament rules ===

# Filament Guidelines

## Platform Rules

- Filament v5 is the target surface. If v5 documentation is thin, the equivalent v4 examples are acceptable because the APIs are compatible.
- Use the official Filament plugins for Tags, Settings, Media, and Fonts when those capabilities are needed.
- Use the built-in `Import` and `Export` actions only.

## Tenancy

- All queries and all action handlers must still obey the owner-scoping contract.

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

## Verification

- Verify no static `$navigationGroup` remains: `rg "static.*\$navigationGroup" packages/filament-*/src`
- Verify config uses nested key: `rg "'navigation_group'" packages/filament-*/config` (should be empty)

=== .ai/general rules ===

# General Guidelines (Monorepo-Specific)

Use this file for cross-cutting judgment, planning, and change execution.

## Interaction Discipline

- When the user asks a question or raises a concern, answer it directly. Do not jump to editing files unless they explicitly ask for changes.
- If you start editing before the user finishes their thought, stop. Revert the premature edit and let them finish.

## Plan Before Coding

- For non-trivial work such as multi-step changes, architecture decisions, or risky edits, write a brief plan before coding.
- State assumptions explicitly. If there are multiple interpretations, name them and ask instead of guessing.
- Push back when a request is unclear, internally inconsistent, or overcomplicated.

## Choose the Right Shape of Change

- Switch to architecture-first when copying the existing pattern would spread a known design problem, duplicate shared logic across packages, or create a fix that is locally correct but systemically wrong.
- When you switch, say so explicitly: name the local pattern you are not copying, explain why, propose the smallest shared correction, and list the surfaces that need verification.
- Stay architecture-first in scope, not in blast radius: prefer one well-placed shared primitive or boundary correction over a broad rewrite.

## Keep the Change Surgical

- Do not add speculative abstractions, configurability, or error handling for impossible cases.
- If a 50-line fix is enough, do not write 200.
- Match existing style; do not refactor adjacent code, comments, or formatting.
- Clean up only your own mess.
- Mention unrelated dead code instead of deleting it.
- Remove only imports, variables, or functions your change makes unused.

## Proof

- Verify behavior before declaring done.
- Write a brief success criterion for multi-step work.
- Turn tasks into tests or checks when possible:
  - Add validation -> write failing tests first, then make them pass.
  - Fix a bug -> reproduce it with a test, then fix it.
  - Refactor -> verify behavior before and after.
- Every changed line should trace directly to the request.

## Git Safety

- Never use `git` to mass-delete, mass-revert, or bulk-reset work. No `git clean -fdx`, no `git reset --hard` across branches, no `git checkout -- .`, no `git push --force`, no `git push --delete` without explicit per-branch approval.
- Never run destructive git commands without explicit, per-command user approval.
- If a git operation would affect more than one commit, stop and ask first.
- `git stash` and `git stash pop` are safe. Avoid `git stash drop` and `git stash clear` — they permanently delete stashed work.

=== .ai/laravel-dune rules ===

## Laravel Dune

This application follows **Laravel Dune**, a deterministic, feature-owned architecture built on Laravel's native container, Eloquent, transactions, queues, and Boost MCP tooling.

Core rule: **Make the locally obvious change the globally correct change. Unsafe shortcuts fail mechanically with errors naming the supported owner.**

### The Five Architectural Laws

1. **Conventional path requires fewer decisions:** Inject concrete feature `Actions` directly via Laravel's container. Add `Api/` interfaces only for real cross-feature or external boundaries.
2. **Forbidden dependencies fail mechanically:** `php tools/architecture-check.php` (AST) and Larastan `OwnedWriteRule` enforce boundaries and single-writer ownership in CI. Never bypass them with suppressions.
3. **Every durable value has one obvious owner:** Each `table:*` and `store:*` belongs to exactly one feature in `app/Features/<Name>/feature.json`. Only listed `Actions/` or `Persistence/` classes in that feature may mutate it.
4. **New product work adds isolated files, not branches in shared roots:** Place routes, views, migrations, and domain `Extensions/` inside the owning feature folder so `PlatformServiceProvider` discovers them automatically.
5. **Exceptions are narrow, explicit, and expiry-dated:** Architecture exceptions live exclusively in `tools/architecture.json` with exact file, exact target, reason, owner, and expiry date (never wildcards).

### Five Public Nouns & Directory Boundaries

- **Framework Core (`app/Platform/`):** Generic discovery, `FeatureModule`, `ExtensionRegistry`, strict runtime defaults. **Never imports `app/Features/` or `app/Host/`.**
- **Feature (`app/Features/<Name>/`):** Owned capability folder (`feature.json`, `FeatureServiceProvider.php`, `Actions/`, `Models/`, `EntryPoints/`, `Routes/`, optional `Api/`, `Queries/`, `Extensions/`, `Persistence/`, `Views/`). Sibling features may import only another feature's `Api/` namespace.
- **Entrypoints (`Routes/{web,api,console}.php` & `EntryPoints/`):** Thin HTTP controllers, FormRequests, jobs, console commands, Livewire/Filament adapters. Validate and authorize at the boundary, then delegate immediately to an Action.
- **Extensions (`Extensions/`):** Pluggable domain units (widgets, tools, renderers, handlers) discovered via `ExtensionRegistry` instead of central `match`/`switch` blocks.
- **Host (`app/Host/`):** External SDKs, payment/LLM/mail gateways, OS/process exec, and raw infrastructure behind pure contracts in `app/Shared/Contracts/`. **Never imports concrete features.**

### Shortest Supported Write Path

<code-snippet name="Thin Entrypoint calling a Concrete Owning Action" lang="php">
declare(strict_types=1);

namespace App\Features\Orders\EntryPoints\Http;

use App\Features\Orders\Actions\CancelOrder;
use Illuminate\Http\JsonResponse;

final readonly class CancelOrderController
{
    public function __invoke(CancelOrderRequest $request, int $orderId, CancelOrder $cancelOrder): JsonResponse
    {
        $receipt = $cancelOrder->handle(
            orderId: $orderId,
            actorId: $request->user()->id,
            reason: $request->validated('reason'),
        );

        return new JsonResponse(['order_id' => $receipt->orderId, 'status' => $receipt->status]);
    }
}
</code-snippet>

### Banned Anti-Slop Patterns in Product Code

- **No hidden Observer/model-hook mutations:** Keep write side effects in named `Actions`; observers/listeners may only delegate to an Action.
- **No cross-feature mutable Eloquent leaks:** Never return an Eloquent `Model`, `Builder`, or `Illuminate\*` runtime type from `Api/` or `app/Shared/Contracts/`. Use `final readonly` DTOs, IDs, or backed enums.
- **No explicit `$table` omissions:** Every concrete feature Eloquent model MUST declare a literal `protected $table = '...';`.
- **No imperative timing hacks:** Never call `sleep()`, `usleep()`, `time_nanosleep()`, or `Sleep::sleep()` as synchronization workarounds.
- **No `env()` outside `config/`:** Always read via `config()`.
- **No `Model::unguard()` or mass-assignment bypasses** and **no inline `@phpstan-ignore` / `phpcs:ignore` suppressions**.
- **No backward-compatibility shims, legacy wrappers, or leftover dead files:** Update callers directly and delete superseded classes, aliases, routes, observers, and unused config keys in the same change.
- **No narrative comments (~99% ban):** Keep only legal headers, verified external vendor quirks, pure formatter ignores, public API contract docs, and RFC/issue links. Encode `DO NOT REMOVE` invariants as types, lints, or tests.

### Boost MCP & Skill Workflow

- Consult `.ai/rules/index.md` before planning or editing any file.
- Use Boost `search-docs` for version-accurate Laravel/ecosystem APIs and `database-schema` / `last-error` / `browser-logs` for evidence-backed verification.
- Record durable judgment-only rules via Boost's `record-rule` MCP tool (never edit `.ai/rules/index.md` inconsistently by hand).
- Activate on-demand skills when relevant: `laravel-dune` (app feature development & bootstrap), `laravel-dune-convert` (legacy app migration), `laravel-dune-package` (Laravel Composer packages under `src/`, both focused single-capability and modular multi-feature, fresh or convert), `laravel-dune-verify` (runtime verification & Feature Map), `laravel-dune-correct` (turning repeated corrections into mechanical checks).
- Before declaring done, run `composer arch:check` plus affected Pest/PHPUnit tests—**"it compiles" is not evidence.**

### Monorepo Root Usage

- This repository is the `aiarmada/commerce` Composer monorepo: packages live under `packages/<pkg>/` (shared foundation in `packages/commerce-support/`), tests under `tests/src/<Namespace>/`, and the local Laravel host app under `demo/`.
- For `packages/<pkg>/` work, use `laravel-dune-package` and follow the monorepo contracts in `.ai/guidelines/` (`getTable()` instead of literal `$table`, UUIDs, application-level cascades in `booted()`, and owner scoping from `commerce-support`).
- Refresh Boost guidelines and rules at the monorepo root with `php artisan commerce:boost-update` and `php artisan commerce:boost-index-rules`, and verify changes with scoped `./vendor/bin/pint`, `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`, and `./vendor/bin/pest --parallel <affected-test-path>`.

=== .ai/model rules ===

# Model Guidelines

## Base Model Contract

- Use `Illuminate\Database\Eloquent\Concerns\HasUuids`.
- Do not declare `protected $table`; implement `getTable()` so the default table name resolves from package config (prefixable and remappable per package).
- When a model has self-referencing relations, `getTable()` must also honor per-instance overrides (`return $this->table ?? <config resolution>;`): Laravel aliases the inner table via `setTable()` for self-join existence queries, and ignoring the alias silently misbinds the join to the outer table.

## Type Safety

- Type relations and collections with PHPDoc generics.

## Relationship Behavior

- Implement application-level cascades in `booted()` using delete or null-out behavior.
- Never rely on database cascades.

## Lifecycle

- When a model has a status or state machine, keep the enum, transition code, and lifecycle columns in sync.
- Keep the state-to-timestamp mapping centralised in the transition method or supporting trait.
- Use immutable date casts for lifecycle timestamps when the model supports them.
- Lifecycle column naming and timestamp shape are defined in the database guidelines.

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

## Reusable Orchestration

- Prefer Laravel Actions for reusable orchestration that spans transactions, side effects, normalization, or multiple entry points.
- When orchestration repeats across HTTP, jobs, listeners, commands, or UI entry points, extract a reusable Action, Service, or Use Case.
- Keep trivial single-step handlers inline when extraction adds no clarity.
- Reuse existing Actions before creating new ones.

## Money

- Treat money as integer minor units plus an explicit currency code.
- Use `commerce-support` money primitives before rolling your own: `MoneyNormalizer` for normalization, `FormatsMoney` or Akaunting `money(..., ..., false)` for display or value formatting, and package or domain `Money` objects where contracts already expect them.

=== .ai/phpstan rules ===

# PHPStan Guidelines

## Baseline

- Level 6.
- Analyse per package, for example `packages/<pkg>/src`, not repo-wide.

## Rules

- Do not add new `ignoreErrors` entries unless root-cause fixes are exhausted.

## Runtime Extensions

- Before removing a method that static analysis reports as undefined, verify runtime extension sources first.
- Check `macro()` / `hasMacro()` registrations, package mixins or traits, and framework or plugin extension points.
- If the method is runtime-provided, preserve behavior and fix analysis with a narrow, targeted change.

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

=== .ai/test rules ===

# Testing Guidelines

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
- Targets: Core >=80%, Filament >=70%, Support >=80%.
- Always pipe output with `2>&1 | tee /tmp/out.txt`.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

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

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

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

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.
</laravel-boost-guidelines>
