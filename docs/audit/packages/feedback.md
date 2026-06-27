# Audit: `feedback` (AIArmada\Feedback)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Feedback, surveys, invitations, scoring, analytics, testimonials.

**Surface:** core

---

## Findings

### Medium
1. **Missing factories directory** — `composer.json` declares `AIArmada\Feedback\Database\Factories\\` autoload mapping pointing to `database/factories/`, but the directory does not exist. Running `composer dump-autoload` will not error but the mapping is dead code.

2. **No exception hierarchy** — 38 actions, 16 events, 8 enums, 5 contracts, but zero custom exception classes. Uses `InvalidArgumentException`, `RuntimeException`, `AuthorizationException` directly. A feedback domain with scoring, analytics, and testimonials should at minimum define a `FeedbackException` base.

### Low
3. **No in-package tests** — 6 Pest test files in monorepo `tests/src/Feedback/` but none inside the package. Won't self-test on standalone install.

4. **No routes in package** — Routes are generated programmatically via `InvitationUrlGenerator` using config prefix. No route files to register in consuming app — URL generation is entirely code-driven, which limits discoverability.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ None | All 9 models use `$fillable` exclusively |
| PHP enums | ✅ 8 enums with `HasLabelOptions` | Form purpose, status, visibility, question type (24 cases), invitation status, response status, template status, testimonial status |
| Owner scoping | ✅ All tenant models | `HasOwner` + `HasOwnerScopeConfig` on all 9 models |
| Immutable dates | ✅ `CarbonImmutable` | All datetime casts use `immutable_datetime` |
| Contracts | ✅ 5 interfaces | `AnswerNormalizer`, `FeedbackAnalyticsCalculator`, `FeedbackRespondent`, `FeedbackSubject`, `InvitationUrlGenerator` |
| Actions | ✅ 38 single-purpose classes | Full lifecycle coverage: CRUD, scoring, analytics, testimonials, invitations |
| Events | ✅ 16 event classes | Form lifecycle, response lifecycle, testimonial lifecycle, invitation lifecycle |
| Policies | ✅ 5 Gate policies | Form, Response, Invitation, Template, Testimonial — with custom gates (publish, close, approve, review, etc.) |
| Tests | ✅ 6 Pest files | Cover cascade deletion, security (mass assignment, cross-owner), invitation tokens, transactions, submission validation |
| DB constraints | ✅ None | No `constrained()` or `cascadeOnDelete()` |
| Exception hierarchy | ❌ None | See Medium finding 2 |
| Factories autoload | ❌ Dead code | See Medium finding 1 |
| Octane safety | ✅ No static mutable state | All services registered via container |

---

## Summary

Clean, well-structured feedback domain package. 9 tenant-owned models with UUID PKs, 8 PHP enums (one with 24 `FeedbackQuestionType` cases), 38 single-responsibility Action classes, 16 lifecycle events, 5 Gate policies with custom authorization, and 5 swappable contract interfaces. Architecturally follows monorepo conventions: no `$guarded`, `HasOwner` on all models, immutable datetime casts, no database constraints, application-level cascades in Action classes.

Notable gaps: no custom exception hierarchy (38 actions sharing domain errors through generic exceptions), dead `database/factories/` autoload mapping, no in-package test directory. 6 monorepo test files cover the critical security, cascade, and submission paths.

**Verdict:** Ready. Clean, complete, tenant-scoped, well-abstracted. Dead autoload mapping should be cleaned up; exception hierarchy would improve DX.
