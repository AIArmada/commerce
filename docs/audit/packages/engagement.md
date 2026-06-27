# Audit: `engagement` (AIArmada\Engagement)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Polymorphic engagement interactions — follow, bookmark, respond, react, subscribe, reminders, share, counters.

**Surface:** domain

---

## Findings

### Medium
1. **No exception hierarchy** — Package defines zero custom exceptions. `AuthorizationException` and `InvalidArgumentException` used directly throughout services and the manager. 28 events, 15 contracts, 7 services, but no `EngagementException` base or specific subtypes (`FollowException`, `SubscriptionException`, etc.).

2. **No PHP enums** — All statuses, visibility levels, notification levels, and types are class constants (plain strings). 10 models, 8 distinct status value sets, none backed by PHP enums. Inconsistent with packages like `docs` and `checkout` that use backed enums. Risk of typo bugs in string comparisons across the codebase.

### Low
3. **No `booted()` application-level cascades** — `BookmarkCollection` has `HasMany items()`, but no cascade delete in `booted()`. Soft-deletion pattern used via `STATUS_ARCHIVED`/`removed_at` columns, so orphaned `BookmarkCollectionItem` rows are possible if a collection is hard-deleted.

4. **Tests live outside package** — 12 Pest test files exist in monorepo `tests/src/Engagement/` but none inside the package. Package won't have tests when installed standalone.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ None | All 10 models use `$fillable` exclusively |
| Owner scoping | ✅ All models | `HasOwner`, `HasOwnerScopeConfig` on every model |
| Immutable dates | ✅ `CarbonImmutable` | All datetime casts use `immutable_datetime` |
| Contracts | ✅ 15 interfaces | `EngagementManager`, `SubscriptionManager`, `ReminderManager`, 5 more service contracts + 7 `*able` subject contracts |
| Events | ✅ 28 events | Complete lifecycle coverage per engagement type |
| Traits | ✅ 14 traits | 7 actor-side (`CanFollow`, `CanBookmark`, etc.) + 7 subject-side (`HasFollowers`, `HasBookmarks`, etc.) |
| Services | ✅ 7 default implementations | All bound to interfaces, swappable |
| Tests | ✅ 12 Pest files | Cover follow, bookmark, response, subscription matching, reminders, cross-tenant isolation, policy enforcement, migration lint |
| Octane safety | ✅ No static mutable state | Services are singletons, no request-leaking state |
| Config | ✅ ~30 keys, all env-configurable | 7 config groups: database, defaults, owner, reminder, notifications, models |
| Exceptions | ❌ None custom | See Medium finding 1 |
| PHP enums | ❌ Class constants only | See Medium finding 2 |
| Routes | ✅ None in package | Filament routes in `filament-engagement` |

---

## Summary

Well-architected engagement package with 10 polymorphic models, 15 contracts, 7 swappable service implementations, 28 lifecycle events, and 14 traits for plug-and-play model engagement capabilities. Clean separation of concerns — actor traits (`CanFollow`, `CanBookmark`) are distinct from subject traits (`HasFollowers`, `HasBookmarks`). Service providers use conditional registration for `aiarmada/events` integration.

Architecturally strong: no `$guarded` on any model, `$fillable` exclusively, `HasOwner`/`HasOwnerScopeConfig` on all models, immutable datetime casts, configurable table names and model classes, comprehensive event coverage for every state transition.

12 Pest test files cover the critical paths (follow, bookmark, response, subscription matching, reminders, cross-tenant isolation, policy enforcement). No custom exception hierarchy and class-constant statuses instead of PHP enums are the main quality gaps.

**Verdict:** Ready. Clean, well-abstracted, tested, owner-scoped. No blockers.
