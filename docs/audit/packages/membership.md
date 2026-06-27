# Audit: `membership` (AIArmada\Membership)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Polymorphic membership applications, invitations, member pivots, Spatie role sync.

**Surface:** core

---

## Findings

### Low
1. **No exception hierarchy** — 10 actions but zero custom exceptions. Uses `RuntimeException`, `ValueError`, `ModelNotFoundException` directly.
2. **No in-package tests** — 18 test files in monorepo `tests/src/Membership/`, none inside the package.
3. **No in-package routes or facades** — All interactions through injected Actions and Events. Could limit discoverability.
4. **Contracts lack default implementations** — `MembershipHook` and `MembershipApplicationNotifier` are checked via `app()->bound()` but no null/no-op defaults are registered. If unbound, the optional hook/notifier calls silently no-op, which is fine but unannounced.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ None | Both models use `$fillable` exclusively |
| PHP enums | ✅ 3 enums | `ApplicationStatus` (4 cases), `InvitationStatus` (4 cases), `MemberRole` (3 cases) — all with `label()` and `isTerminal()` |
| Owner scoping | ✅ Both models | `HasOwner` + `HasOwnerScopeConfig` on `MembershipApplication` and `MembershipInvitation` |
| Immutable dates | ✅ `CarbonImmutable` | All datetime casts |
| Actions | ✅ 10 (laravel-actions) | Full lifecycle: apply, approve, reject, cancel, invite, accept, revoke, add, remove, change role |
| Events | ✅ 6 | Application lifecycle (submitted, approved, rejected, cancelled) + invitation lifecycle (sent, accepted) |
| Contracts | ✅ 2 interfaces | `MembershipHook` (3 lifecycle hooks), `MembershipApplicationNotifier` (3 notification methods) |
| Tests | ✅ 18 Pest files | Actions, models, enums, traits, commands, role sync, owner isolation |
| Spatie integration | ✅ `authz` package | Team-scoped role sync with `finally`-block context restore |
| Token security | ✅ Configurable hashing | `sha256` hash for invitation tokens in DB, `hash_equals` for comparison |
| Concurrency safety | ✅ `lockForUpdate` | Used in approve, reject, cancel, accept, revoke actions |

---

## Summary

Clean, focused membership lifecycle package: 2 models (Application, Invitation), 10 single-responsibility actions, 6 events, 2 contracts, 3 enums, 1 service. Handles the full membership workflow — self-apply with approval, email invitation with token-based acceptance, direct add/remove/change-role, and Spatie permission role sync.

Strong concurrency safety with `lockForUpdate` on state transitions. Token security via configurable `sha256` hashing with `hash_equals` comparison. Owner scoping on both models. Team-aware Spatie role sync that restores previous team context in `finally` block. `MembershipSubjectGuard` validates subject ownership before mutations.

18 test files cover all actions, models, enums, the `HasMembers` trait, commands, role sync service, and cross-owner isolation. No exception hierarchy or no-op defaults for optional contracts are the main gaps.

**Verdict:** Ready. Clean, secure, well-tested. No `$guarded` issues.
