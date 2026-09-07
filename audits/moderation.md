# moderation Audit

## Packages Reviewed (bullets)

- `packages/moderation` — generic moderation: blocks/bans + moderation-action log (standalone, no Filament adapter): 13 `src/` files (`Traits/HasBlocks.php`, `HasModerationActions.php`, `Contracts/{BlocksEntity,RecordsModerationAction}.php`, `Enums/{BlockStatus,BlockReason,ModerationActionType}.php`, `Models/{Block,ModerationAction}.php`, `Actions/{BlockEntityAction,RecordModerationAction}.php`, `helpers.php`, provider), `config/moderation.php`, 2 migrations
- Root test coverage consulted: `tests/src/Moderation/` (8 files)

## Overall Assessment (quality, health, risks, refactor size)

moderation is a small, well-scoped standalone: both models use `HasOwner`+`HasUuids`+`getTable()`, both Actions validate owner-scoped subjects via `OwnerWriteGuard` and write in `DB::transaction()`. The gaps are completeness and convention: (1) no expiry sweep — `Block.expires_at` is set (default 30 days from `moderation.defaults.block_duration_days`) but no command/job ever transitions expired `Active` blocks, so blocks live forever regardless of `expires_at`; (2) nonstandard `moderation.features.owner` config nesting (same fix as membership M1); (3) `validateOwnerScopedModel()` accepts a legacy `scopeForOwner` duck-type alongside `OwnerScopeConfigurable` — the duck-type is still referenced by cashier/cashier-chip/contacting call sites, so it cannot be deleted yet, but it must be contained, not spread; (4) `moderation_table()`/`moderation_json_type()` helpers are used nowhere except their own definition file (verified via rg — zero call sites); (5) `BlockStatus` lifecycle has no central transition method. No migration required. Refactor size: Small, code-only.

## Migration Impact

**Migration Required: NO**

| Table | Column/Index/Constraint | Data migration | Notes |
|---|---|---|---|
| `moderation_blocks`, `moderation_actions` | none proposed | none | uuid PKs verified; no FK constraints (rg clean); expiry sweep is a status update at runtime, not a schema change |
| none | none | none | Config-key move is code+config only |

## Package Responsibilities

- Blocking: `Contracts/BlocksEntity.php`, `Actions/BlockEntityAction.php`, `Models/Block.php` (`BlockStatus`, `BlockReason`), `Traits/HasBlocks.php` (host-model integration).
- Audit trail: `Contracts/RecordsModerationAction.php`, `Actions/RecordModerationAction.php`, `Models/ModerationAction.php` (`ModerationActionType`), `Traits/HasModerationActions.php`.
- Config: durations, defaults, owner nesting, table names; `helpers.php` table/JSON-type accessors.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### O1 — Expired blocks never expire (no sweep path)
- Severity: High
- Location: `packages/moderation/src/Actions/BlockEntityAction.php:28-30` (`expires_at` defaults to +30d from `moderation.defaults.block_duration_days`), `:44-51` (persists `BlockStatus::Active` + `expires_at`), `Models/Block.php` (`BlockStatus`), `Enums/BlockStatus.php`; no `Console/` directory exists in the package at all (verified)
- Problem: `expires_at` is written but nothing reads it back into a status transition. Every block with default settings stays `Active` forever; `BlockStatus::Expired` (if present) is unreachable, and enforcement code checking `status == Active` enforces dead blocks.
- Why It Matters: Time-bounded sanctions that never lift are both a correctness bug (users blocked past expiry) and a legal/compliance risk (retention beyond stated duration).
- Recommended Fix: Add `ExpireModerationBlocksAction` (query `Active` + `expires_at < now()` scoped per owner via `forOwner` iteration with `OwnerBatchRunner`, transition to expired status in chunks inside transactions) + `Console/Commands/ExpireBlocksCommand.php` scheduled daily (document the schedule line in `docs/04-usage.md`; do not force-register the schedule — host opt-in). Add `Block::expire()` central transition (see Q1) used by both.
- Breaking Change: NO (new classes; behavior change only for already-expired rows, which is the documented intent of `expires_at`)
- Affected Packages: host apps (schedule opt-in), any enforcement readers of `BlockStatus` (now see truthful statuses)
- Required Dependent Changes: add schedule line in host console kernel if desired
- Migration Required: NO

### O2 — Nonstandard owner config path (`moderation.features.owner`)
- Severity: Medium
- Location: `packages/moderation/config/moderation.php:12`, `Models/Block.php:55`, `Models/ModerationAction.php:45` (`ownerScopeConfigKey = 'moderation.features.owner'`), `Actions/*::validateOwnerScopedModel()` reading `moderation.features.owner.enabled`
- Problem: Same convention drift as membership M1: signals/docs/jnt use `{pkg}.owner`.
- Why It Matters: Same silent-unscoped risk class.
- Recommended Fix: Same fix, same pass: top-level `owner` key; update both models + both actions; no fallback shim (per no-legacy-preservation instruction — update all internal consumers now).
- Breaking Change: YES
- Affected Packages: host apps overriding the key; membership (parallel fix, no code coupling)
- Required Dependent Changes: update config overrides to `moderation.owner` (rg `moderation\.features\.owner` before merge)
- Migration Required: NO

### O3 — Legacy `scopeForOwner` duck-type accepted in validators
- Severity: Low
- Location: `packages/moderation/src/Actions/BlockEntityAction.php:53-67`, `RecordModerationAction.php:43-...` (`! $model instanceof OwnerScopeConfigurable && ! method_exists($model::class, 'scopeForOwner')`)
- Problem: The validator accepts either the canonical contract or a legacy `scopeForOwner` method. Verified live: `scopeForOwner` is still referenced by cashier-chip, cashier, contacting sources — so the branch is load-bearing today, but every new caller can choose the legacy path, spreading what should be shrinking.
- Why It Matters: Dual acceptance freezes the migration to `OwnerScopeConfigurable` permanently if left as-is.
- Recommended Fix: Keep the branch but invert the default: emit `trigger_error(E_USER_DEPRECATED)` on the legacy path and document `OwnerScopeConfigurable` as the only supported contract in `docs/04-usage.md`. Do not delete the branch here (external packages still use it — their audits own the migration).
- Breaking Change: NO
- Affected Packages: cashier, cashier-chip, contacting (future migration, not this pass)
- Required Dependent Changes: none this pass
- Migration Required: NO

## Code Quality Findings (same finding format)

### Q1 — `BlockStatus` transitions not centralized
- Severity: Low
- Location: `packages/moderation/src/Models/Block.php`, `Enums/BlockStatus.php`, `Actions/BlockEntityAction.php` (writes `BlockStatus::Active` directly)
- Problem: Status writes happen inline at action sites with no single state→timestamp mapping (contrast docs' `Doc::transitionTo` pattern and growth audit Q1).
- Why It Matters: O1's expiry + future unblock/reblock flows will scatter a third and fourth write site.
- Recommended Fix: Add `Block::transitionTo(BlockStatus $s)` owning status + `expires_at`/`lifted_at`-style timestamp mapping; route `BlockEntityAction` and the new expiry action through it.
- Breaking Change: NO
- Affected Packages: none
- Required Dependent Changes: none
- Migration Required: NO

### Q2 — Dead helpers (`moderation_table`, `moderation_json_type`)
- Severity: Low
- Location: `packages/moderation/src/helpers.php:5,12`
- Problem: Both helpers verified with zero call sites repo-wide (rg `moderation_table|moderation_json_type` returns only the definition file). Migrations presumably inline `config()` + `commerce_json_column_type()` directly.
- Why It Matters: Dead public API invites use of an untested path.
- Recommended Fix: Delete `src/helpers.php` entirely and remove the `files` autoload entry in `composer.json`. (Contrast `references_table()`, which is genuinely consumed incl. tests — keep that one.)
- Breaking Change: YES (nominal — zero verified consumers)
- Affected Packages: none (verified)
- Required Dependent Changes: none
- Migration Required: NO

## Laravel-Specific Findings

- PHP 8.4, no FK constraints/cascades (rg clean), uuid PKs, `getTable()` from config, `json_column_type` in config + both migrations — compliant.
- `spatie/laravel-package-tools` provider usage — compliant.
- No console kernel registration to review (no commands exist — O1 adds the first; register it console-only per `runningInConsole()` convention).
- No soft deletes — compliant.

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction; N/A section for standalones csuite/membership/moderation/references)

- N/A — moderation is standalone with no Filament adapter, correctly so at its size. If a future adapter appears, it must follow the filament-jnt `BaseJntResource` pattern (`OwnerUiScope` + config-driven navigation) and put block/unblock behind `OwnerWriteGuard`-enforcing actions, not table-action closures.

## Database Findings

- Two minimal migrations, idempotent-shaped, no constraints — compliant. Hot lookup is `(blockable_type, blockable_id, status)` active-block checks — verify a composite index exists for the enforcement read; add `index(['blockable_type','blockable_id','status'])` if missing (index-only, no data migration; confirm current index list first — flagged as verify-then-add, not blind).

## Model / Domain Findings

- `HasBlocks`/`HasModerationActions` host traits: same cascade question as membership Q1 — document on-delete policy (moderation rows are audit trail: on subject delete, retain rows with nullable subject? or cascade?). Recommendation: retain `ModerationAction` rows (compliance trail) and cascade only `Block` rows' enforcement relevance by transitioning subject-deleted blocks to expired — implement in trait `deleting` hook + test. No DB cascades.

## Security Findings

- `BlockEntityAction`/`RecordModerationAction` validate both the target and the actor (`$blockedBy`/`$actionedBy`) as owner-scoped before writing — correct dual validation; keep as the pattern.
- O1 is the security-adjacent item (over-enforcement past expiry). No authentication surface in-package (no routes/controllers) — enforcement readers live in host apps; document the canonical enforcement check (`active block exists for (type,id) with expires_at > now() OR status Active post-sweep`) in `docs/04-usage.md` so readers do not reimplement it divergently.

## Performance Findings

- O1 sweep must chunk (`OwnerBatchRunner` or `chunkById`) — blocks table can grow large on UGC apps; per-owner iteration keeps each chunk tenant-bound. No other scale surface.

## Testing Findings

- `tests/src/Moderation/` (8 files) covers the happy paths presumably; gaps: O1 expiry sweep (active+expired → transitioned; active+future → untouched; per-owner isolation during sweep); Q-model policy (subject delete → actions retained, blocks expired); O2 key move; cross-tenant block/read isolation via contract tests. Run: `./vendor/bin/pest --parallel tests/src/Moderation`.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| host apps | config key, helpers, sweep | O2 key move; Q2 helper deletion; O1 schedule opt-in | Update `moderation.owner` overrides; add schedule line; remove any `moderation_table()` uses (none verified) |
| cashier/cashier-chip/contacting | `scopeForOwner` legacy branch | O3 deprecation notice only | None this pass (their audits own migration) |
| commerce-support | `OwnerWriteGuard`, `OwnerBatchRunner` reuse | Additive usage | None |

## Recommended Refactor Plan (ordered steps)

1. O2: move owner key; update models + actions (same pass as membership M1).
2. Q1: centralize `Block::transitionTo()`.
3. O1: expiry action + command + schedule docs.
4. O3: deprecate legacy branch; document canonical contract.
5. Q2: delete dead helpers + autoload entry.
6. Document enforcement check + subject-delete policy; add required tests; run `./vendor/bin/pest --parallel tests/src/Moderation`.

## Files Likely to Change

- `packages/moderation/config/moderation.php`, `composer.json` (autoload), `src/Models/Block.php`, `src/Models/ModerationAction.php`, `src/Actions/BlockEntityAction.php`, `src/Actions/RecordModerationAction.php`, `src/Traits/HasBlocks.php`, `src/Traits/HasModerationActions.php`, new `src/Actions/ExpireModerationBlocksAction.php` + `src/Console/Commands/ExpireBlocksCommand.php`, `docs/04-usage.md`

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/moderation/src/helpers.php` in its entirety (both functions verified dead via repo-wide rg — zero call sites; remove `files` autoload entry alongside)
- `moderation.features.owner` config key (replaced by top-level `owner`; verified internal consumers: 2 models + 2 actions, all updated in the same pass)
- Nothing else: the `scopeForOwner` branch stays (externally load-bearing — verified live references in cashier/cashier-chip/contacting); `BlockStatus`/`BlockReason`/`ModerationActionType` enums stay

## Final Recommended Architecture

moderation stays a tiny standalone with truthful time semantics: central status transitions, a daily expiry sweep, one owner-config convention, guarded dual-validated writes, retained audit trail with explicit subject-delete policy, and zero dead public API.
