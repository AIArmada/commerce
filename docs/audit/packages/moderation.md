# Audit: `moderation` (AIArmada\Moderation)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Generic moderation blocks and moderation-action recording for Laravel models.

**Surface:** domain

---

## Findings

### Medium
1. **No exception hierarchy** — 2 actions but zero custom exception classes. Uses standard exceptions. Other domain packages (events, jnt) demonstrate this pattern well.
2. **No contracts/interfaces** — 2 actions registered as concrete singletons with no abstraction layer. `BlockEntityAction` and `RecordModerationAction` are consumed directly. Testable only by binding mock instances.

### Low
3. **No in-package tests** — 8 test files in monorepo `tests/src/Moderation/`, none inside the package.
4. **No routes or facades** — All interaction through injected actions and trait convenience methods.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ None | Both models use `$fillable`; `owner_type`/`owner_id` not in fillable (confirmed by OwnerIsolationTest) |
| PHP enums | ✅ 3 enums | `BlockReason` (7 cases), `BlockStatus` (3 cases), `ModerationActionType` (7 cases) — all with `label()` |
| Owner scoping | ✅ Both models | `HasOwner` + `HasOwnerScopeConfig` + `OwnerWriteGuard` in actions |
| Immutable dates | ✅ `CarbonImmutable` | `expires_at`, `lifted_at` casts |
| Actions | ✅ 2 singletons | `BlockEntityAction`, `RecordModerationAction` — both use `DB::transaction()` with owner validation |
| Traits | ✅ 2 traits | `HasBlocks` (block + isBlocked + scopeWhereNotBlocked), `HasModerationActions` (record) |
| Tests | ✅ 8 Pest files | Actions, models, enums, traits, owner isolation, installation |
| Contracts | ❌ None | See Medium finding 2 |
| Exceptions | ❌ None | See Medium finding 1 |

---

## Summary

Small, focused moderation package: 2 models (Block, ModerationAction), 3 enums, 2 actions, 2 traits, 4 migrations. Generic enough for any Eloquent model via polymorphic morphs and traits.

All models use `$fillable` (no `$guarded`), `HasOwner`/`HasOwnerScopeConfig` with `OwnerWriteGuard` validation in both actions. 3 PHP enums with `label()`. 2 traits provide convenient `$model->isBlocked()`, `$model->block(...)`, `$model->recordModerationAction(...)` APIs. 8 test files cover actions, models, enums, traits, and cross-owner isolation.

**Verdict:** Ready. Small, clean, owner-scoped, tested. Minor gaps in exception hierarchy and contracts.
