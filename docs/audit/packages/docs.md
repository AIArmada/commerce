# Audit: `docs` (AIArmada\Docs)

**Status:** Conditionally Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Business document generation, numbering, PDFs, emails, approvals, e-invoice tracking.

**Surface:** output

---

## Findings

### Critical
1. **No tests** — 14 migrations, 13 models, 11 enums, 4 services, state machine with 8 states, numbering system, PDF generation, email tracking, share links, e-invoice submission pipeline. Zero test files exist. Every status transition, numbering strategy, share-link security path, and e-invoice pipeline is untested.

### High
2. **No exception hierarchy** — 2 standard exceptions used (`InvalidArgumentException`, `ValidationException`); package defines zero custom exceptions. Controllers throw `Symfony\Component\HttpKernel\Exception\NotFoundHttpException`. A document-generation package with payments and multi-step workflows should at minimum define a `DocsException` base and `DocumentNotFoundException`, `NumberGenerationException`, `PdfGenerationException`.

### Medium
3. **No static analysis config** — No `phpstan.neon` or `pint.json` in the package. Relies entirely on project-level tooling analysis, which won't catch package-level issues during standalone install.

### Low
4. **`DocPayment` uses raw `string` status** — Unlike other models that use backed enums for status (`DocApprovalStatus`, `EmailStatus`, etc.), `DocPayment::$casts['status']` is `'string'`. No enum, no documented valid values, no transition logic. Risk of invalid states in payment records.
5. **`DocShareLink` has `$plainToken` as public property** — `public string $plainToken` is set after creation. This is a plaintext token exposed on the model instance. If a collection of share links is serialized to JSON or logged, the plain token leaks. Should be `protected $hidden` or never stored on the instance after creation.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ None found | All models use `$fillable` |
| Owner scoping | ✅ All models | `HasOwner`, `HasOwnerScopeConfig` on all 13 models |
| Immutable dates | ✅ `CarbonImmutable` | All datetime casts use `immutable_datetime` |
| State machine | ✅ Spatie `HasStates` | Doc status: 8 states, defined transitions |
| Concurrency safety | ✅ `lockForUpdate` | Sequence number generation uses row lock |
| Cascade deletes | ✅ Application-level | `booted()` methods for owned children |
| Octane safety | ✅ No static mutable state | Singletons in service provider, no request-leaking state |
| Contracts | ✅ `DocServiceInterface`, `RichContentRendererInterface` | Core service abstracted behind interface |
| Views | ✅ 3 Blade files | `show`, `email`, template default |
| Routes | ✅ 4 routes + shared-link public routes | Tracking pixel, click tracking, share view/PDF |
| Email tracking | ✅ Open/click pixels, security hashes | Uses Crypt for token, sanitizes redirect URLs |
| PDF pipeline | ✅ Via spatie/laravel-pdf + Browsershot | Render, store, download |
| Docs | ✅ 9 documentation files | Configuration, usage, troubleshooting all covered |
| Config (env keys) | ✅ All configurable via env | 20+ env vars |
| Validation | ✅ Input validation in DTOs and services | DocData::from(), ShareLinkData, render service |
| Tests | ❌ NONE | See Critical finding 1 |
| Exception hierarchy | ❌ None | See High finding 2 |
| PHPStan/Pint config | ❌ None in package | See Medium finding 3 |

---

## Summary

`docs` is the most functionally rich package in the monorepo: a full document lifecycle engine with numbering (thread-safe via `lockForUpdate`), state machine (8 states with defined transitions), PDF generation (via spatie/laravel-pdf + Browsershot), email tracking (open/click pixels with Crypt-secured tokens), share links (token-based access with expiry/revocation), e-invoice submission pipeline, versions, approvals, workflows, and template system (declarative JSON layouts with Tiptap rendering).

Architecture is solid — all models use `$fillable` (no `$guarded`), all implement `HasOwner`/`HasOwnerScopeConfig`, immutable dates everywhere, application-level cascade deletes, Octane-safe singletons, no static mutable state. The `SequenceManager` uses `lockForUpdate` for concurrency-safe number generation. `DocShareLink` hashes tokens in DB and uses Crypt for tracking tokens.

The one blocker is zero tests. For a package handling financial document generation, numbering sequences, PDF output, email tracking, and payment recording, this is a significant risk. Every state transition in the 8-state machine, every format in the numbering system, every security check in share links, and every e-invoice submission path is untested.

**Verdict:** Conditionally Ready. Clean code, solid architecture, excellent docs, no `$guarded` issues. But no tests on a financial document engine is a deployment risk.
