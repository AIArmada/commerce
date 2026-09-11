# Feedback Audit — DONE (2026-09-08)

## Verdict

The surveys package (`feedback` + `filament-feedback`) has passed full
review and implementation. Form-structure writes go through a single
owner-guarded action, widgets consume the owner-keyed analytics service,
invitation tokens are random/expiring/single-use/rate-limited with a
narrow lookup window, testimonials gate on approval before any public
read path, and real coverage (58 tests) closes the zero-test High —
with zero rated findings remaining.

## What was done

- **Action collapse:** `SaveFeedbackFormStructureAction` (owner-guarded,
  single save path) replaces six redundant CRUD actions (deleted);
  relation managers and template/duplicate flows rewired; enum-to-string
  visibility handling fixed in duplication — see `code-fixes-record.md`.
- **Static cache deleted:** `QuestionTypeRegistry` iterates `cases()`
  directly — see `code-fixes-record.md`.
- **Trait collision falsified:** feedback already uses
  `feedbackResponses()`/`feedbackTestimonials()`; collisions live only
  in read-only `engagement`/`events` traits (untouched). Three-way Q&A
  boundary documented in `packages/feedback/docs/05-boundaries.md` —
  see `code-fixes-record.md`.
- **Widgets on calculators:** all nine widgets consume the owner-keyed
  `FeedbackAnalyticsService`; inline queries deleted; dashboard
  composition explicit — see `code-fixes-record.md`.
- **Invitation security proven + hardened:** `bin2hex(random_bytes(32))`
  tokens, hashed lookup, expiry + single-use + rate limiting, narrow
  `withoutOwnerScope` window; testimonial `scopePublished` gate
  (approved + published + visible) — see `code-fixes-record.md`.
- **Performance:** aggregate-backed, owner-keyed analytics cached 30s;
  queued recalculation implemented on the analytics aggregate table
  (listener + job + model, tested) — see `code-fixes-record.md`.
- **Database/seeder verified as-is:** token unique index + expiry,
  composite subject indexes, opt-in seeder all present — no migration
  changes.
- Suites: Feedback Area 50 passed (138 assertions) at conversion;
  now 54 passed (152 assertions) with queued-analytics coverage.
  FilamentFeedback
  Area 8 passed (31 assertions); PHPStan level 6 clean on both source
  packages; Pint + `git diff --check` clean.

## Residual notes
- Migration split IMPLEMENTED (2026-09-08, was held then dropped):
  9 one-table files `2000_01_01_000001`–`000009`, schema-identical
  (mechanical per-table verification; only delta is the replicated
  shared preamble). Dev-only delete-and-rerun applies; no backfill.
- Queued analytics recalculation implemented on the persisted
  aggregate table (provider-wired listener dispatches the job
  after commit) — see `code-fixes-record.md`.
- The audit's "zero tests" and raw-`DB::table` claims were stale/
  overstated at implementation time (coverage existed; `OwnerQuery`
  was already applied) — corrected in `code-fixes-record.md`.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
