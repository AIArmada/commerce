# references Audit

## Packages Reviewed (bullets)

- `packages/references` — generic scholarly reference/source management with hierarchical parts (standalone, no Filament adapter): 7 `src/` files (`Traits/HasReferenceParts.php`, `Enums/{ReferenceType,ReferenceStatus,ReferencePartType}.php`, `Models/Reference.php`, provider, `helpers.php`), `config/references.php`, 1 migration
- Root test coverage consulted: `tests/src/References/` (4 files — thinnest alongside FilamentCommerceSupport)

## Overall Assessment (quality, health, risks, refactor size)

references is a clean micro-package with one serious structural defect: it calls commerce-support helpers (`commerce_json_column_type()`, `commerce_schema_create_if_missing()`) in its migration while **not requiring** `aiarmada/commerce-support` in composer (requires only medialibrary, package-tools, sluggable; verified `packages/references/composer.json` vs migration lines 12,14). Installed standalone — the configuration its own composer advertises — the migration fatals on undefined functions. Secondary issues: (1) no tenancy story at all — no `HasOwner`, no owner columns, global slug uniqueness — while every sibling package is owner-scoped (Medium: undecided convention, no active consumers, fix is docs+config); (2) `booted()` cascade (`children()->get()->each->delete()`) is N+1, untransactioned, recursive-model-event-firing, and orphans media-library attachments (`InteractsWithMedia` files are never cleaned); (3) `HasReferenceParts` trait vs `reference_parts` JSON column vs `part_type/part_number/part_label` columns — three overlapping representations of "parts"; (4) global unique `slug` with no scope. The correct fix given its scholarly-reference domain is: declare the commerce-support dependency (it already uses it), keep references global-by-design with that decision documented and enforced (no tenant columns, no migration), and fix the delete path. One composer-line change; no schema migration required. Refactor size: Small, code-only. Highest severity: High (undeclared dependency = broken standalone install).

## Migration Impact

**Migration Required: NO**

| Table | Column/Index/Constraint | Data migration | Notes |
|---|---|---|---|
| `ref_references` | none proposed | none | uuid PK verified; no FK constraints (rg clean — `parent_id` is `foreignUuid()->nullable()->index()` with no constraint, compliant); global slug uniqueness kept intentionally (global-by-design decision, documented) |
| none | no owner columns added | none | Deliberate: references are global scholarly data, not tenant data — decision recorded in config/CONTEXT/docs instead of schema |

## Package Responsibilities

- Canonical reference records: `Models/Reference.php` (`ReferenceType`, `ReferenceStatus`, slug via `HasSlug`, media via `InteractsWithMedia`), `Traits/HasReferenceParts.php` (`ReferencePartType`, `reference_parts` JSON + `part_*` columns), `Enums/*`, `helpers.php` (`references_table()` — genuinely used, incl. tests), provider, single migration, `config/references.php` (incl. `json_column_type`, `slug.*`).

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### R1 — Undeclared commerce-support dependency (broken standalone install)
- Severity: High
- Location: `packages/references/composer.json` (no `aiarmada/commerce-support` in require) vs `packages/references/database/migrations/2000_01_01_000001_create_references_table.php:12,14` (`commerce_json_column_type('references', 'jsonb')`, `commerce_schema_create_if_missing(...)`)
- Problem: The migration calls two commerce-support globals that do not exist unless commerce-support happens to be installed alongside. Composer advertises standalone installability; standalone migration fatals with undefined function.
- Why It Matters: The package's own install path is broken in exactly the configuration its metadata promises.
- Recommended Fix: Add `"aiarmada/commerce-support": "self.version"` to require (it is already the de-facto standard: config follows the `json_column_type` discipline, table naming follows `getTable()`-from-config). No code change needed beyond the composer line.
- Breaking Change: NO (additive dependency)
- Affected Packages: commerce-support (no change), csuite if references ever joins the bundle (not currently bundled — no change)
- Required Dependent Changes: none
- Migration Required: NO

### R2 — No tenancy story while siblings are all owner-scoped
- Severity: Medium
- Location: `packages/references/src/Models/Reference.php` (no `HasOwner`, no `ownerScopeConfigKey`; fillable/casts have no owner tuple), migration (no `owner` morphs), `config/references.php` (no `owner` section)
- Problem: A host app using references alongside any owner-scoped package gets globally-visible references with no flag indicating the choice. Re-check 2026-09-07 (demoted High→Medium per rubric): no cross-package consumers verified in `src`, no routes/controllers, single-model scholarly data — this is an undecided-convention maintainability gap, not an active vulnerability or data-loss risk. If references are ever tenant-specific (per-organization bibliographies), there is no path without a schema change; if they are global, nothing says so and a future contributor will "fix" it inconsistently.
- Why It Matters: Undecided tenancy defaults to cross-tenant visibility — the failure mode the entire monorepo contract exists to prevent.
- Recommended Fix: Decide global-by-design (correct for scholarly/canonical references): add explicit `'tenancy' => ['mode' => 'global']` to `config/references.php`, state it in `CONTEXT.md` + `docs/01-overview.md`, and add a `Reference::isGlobalRecord()`-style assertion or comment block so the absence of `HasOwner` reads as intentional. If any consumer needs tenant references, that is a new scoped model then — not a silent column addition now.
- Breaking Change: NO (documents status quo)
- Affected Packages: future consumers (now warned), none today (no verified cross-package consumers in src)
- Required Dependent Changes: none
- Migration Required: NO

### R3 — Recursive N+1 cascade orphans media and skips transactions
- Severity: Medium
- Location: `packages/references/src/Models/Reference.php:72-76` (`static::deleting(... children()->get()->each->delete())`)
- Problem: `each->delete()` fires one query per child plus one event cascade per level (N+1, unbounded recursion depth on deep hierarchies), runs outside any transaction (partial deletes on failure), and never clears `InteractsWithMedia` attachments — detached media rows/files accumulate per deleted subtree.
- Why It Matters: Data loss (partial tree) + storage leak (orphan media) on the package's only destructive path.
- Recommended Fix: Wrap in `DB::transaction()`; collect descendant IDs iteratively (single recursive CTE or chunked BFS, not per-model events); delete media collections explicitly (`$ref->clearMediaCollection()` per collection or a single `media` cleanup query scoped to the subtree) before deleting rows; use one `whereIn()->delete()` for the subtree rows (or keep model events only if per-model cleanup is required — it is, for media — but batch the media step first, then batch-delete rows).
- Breaking Change: NO (observable: complete deletes + no orphan media)
- Affected Packages: none (no external delete-path consumers verified)
- Required Dependent Changes: none
- Migration Required: NO

### R4 — Three overlapping "parts" representations
- Severity: Low
- Location: `packages/references/src/Traits/HasReferenceParts.php` vs `reference_parts` JSON column vs `part_type/part_number/part_label` string columns (migration) vs `ReferencePartType` enum
- Problem: A reference can express "part-ness" via the trait API, the JSON blob, or the flat columns, with no documented precedence. Readers/writers pick different representations for the same fact.
- Why It Matters: Query code (`where part_type`) and display code (`reference_parts` JSON) disagree about the same reference.
- Recommended Fix: Canonicalize: flat `part_*` columns are the queryable source of truth for single-part references; `reference_parts` JSON is the multi-part payload; the trait owns the sync between them (trait mutators keep both consistent) and documents the rule in its docblock. No column change.
- Breaking Change: NO
- Affected Packages: none
- Required Dependent Changes: none
- Migration Required: NO

## Code Quality Findings (same finding format)

### Q1 — `getSlugOptions()` reads config with silent fallbacks
- Severity: Low
- Location: `packages/references/src/Models/Reference.php:84-95` (`config('references.slug.source', 'title')`, `max_length` 200)
- Problem: Misconfigured `slug.source` (nonexistent attribute) produces empty slugs → unique-constraint failures far from the cause.
- Why It Matters: Same silent-misconfiguration class as authz's permission separator.
- Recommended Fix: Assert at boot (`ValidatesConfiguration`-style, commerce-support already ships the trait — use it): `slug.source` must name a fillable string attribute; fail fast in provider boot when invalid.
- Breaking Change: NO
- Affected Packages: none
- Required Dependent Changes: none
- Migration Required: NO

## Laravel-Specific Findings

- PHP 8.4 — compliant. No FK constraints/cascades (rg clean; `foreignUuid('parent_id')` without `->constrained()` is correctly just a UUID column) — compliant. Application-level cascade exists (R3 fixes its implementation, not its placement) — compliant in intent.
- uuid PK, `getTable()` from `references.database.tables.references`, `json_column_type` in config — compliant (after R1 makes the helper source a real dependency).
- `HasSlug` (`doNotGenerateSlugsOnUpdate`, length cap) + `InteractsWithMedia` + `HasFactory` composition is correct; keep.
- Carbon: model already uses `CarbonImmutable` (`published_at` cast `immutable_datetime`) — compliant with immutable-dates guidance.

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction; N/A section for standalones csuite/membership/moderation/references)

- N/A — references is standalone with no Filament adapter, appropriate at 1 model / 7 files. If an adapter appears later, slug uniqueness must remain global (match the R2 decision) and media collections need explicit Filament media handling — noted here so the future adapter does not reintroduce R3's orphan bug through UI deletes.

## Database Findings

- Single focused migration, idempotent via helpers (post-R1), uuid PK, `timestampTz`, configurable JSON — compliant.
- Indexes `['type','status']`, `['parent_id','part_type','part_number']`, plus single-column `type/part_type/is_canonical` fit the filter paths; global unique `slug` matches the global-by-design decision. No change.

## Model / Domain Findings

- Keep the model global (R2) and fix deletes (R3) and parts precedence (R4); the enum trio (`ReferenceType/Status/PartType`) is appropriately enums-not-state-machines (no lifecycles with timestamps — correct call, contrast docs where timestamps demanded states).

## Security Findings

- No auth surface in-package (no routes/controllers); no mass-assignment gap (`$fillable` explicit, `slug` included intentionally for import flows — verify imports validate `slug` uniqueness errors into domain exceptions, not 500s).
- `url` field is stored text rendered by consumers — document that rendering hosts must escape it (no auto-linking without escaping); package-level fix not possible without a renderer, so docs note in `docs/04-usage.md`.

## Performance Findings

- R3's cascade is the only scale-sensitive path (deep trees). No reporting surface. No change beyond R3.

## Testing Findings

- `tests/src/References/` (4 files incl. `InstallationTest.php`, which consumes `references_table()` — the helper is load-bearing, keep) is thin for even a micro-package.
- Required: R1 install test (migrate with only references+commerce-support installed — fails before fix); R3 subtree-delete test (3-level tree + attached media → all rows gone, media gone, single transaction); parts-precedence test (trait write → both representations consistent); global-visibility test (two tenants, one reference — visible to both, asserting the documented decision). Run: `./vendor/bin/pest --parallel tests/src/References`.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| commerce-support | new require from references (R1) | References installs helpers legitimately | None (no code change) |
| (none) | no code consumers verified | R2–R4 internal | None |
| future Filament adapter | parts/delete semantics | Inherits R3/R4 contracts | Build on trait + transactional delete |

## Recommended Refactor Plan (ordered steps)

1. R1: add commerce-support require (one line; unbreaks standalone install).
2. R2: document global-by-design in config + CONTEXT + overview docs.
3. R3: transactional subtree delete with media cleanup.
4. R4: trait-owned parts sync + documented precedence.
5. Q1: slug-source boot assertion; URL-escaping docs note.
6. Add required tests; run `./vendor/bin/pest --parallel tests/src/References`.

## Files Likely to Change

- `packages/references/composer.json`, `config/references.php`, `src/Models/Reference.php`, `src/Traits/HasReferenceParts.php`, `src/ReferencesServiceProvider.php` (assertion), `CONTEXT.md`, `docs/01-overview.md`, `docs/04-usage.md`

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- Nothing verified dead: `references_table()` stays (live consumers incl. `tests/src/References/InstallationTest.php`); the trait, enums, provider, and single migration all stay. This package's removals are behaviors (non-transactional cascade, ambiguous precedence), not files.

## Final Recommended Architecture

references stays a 7-file global micro-package with an honest dependency, documented global tenancy, one transactional media-aware delete path, one parts-precedence rule owned by its trait, and install tests proving standalone setup works.
