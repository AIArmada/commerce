# Feedback Audit

## Packages Reviewed (bullets)
- `packages/feedback` (`aiarmada/feedback`) — surveys: forms/sections/questions/options, responses/answers, invitations, analytics (NPS/CSAT/rating/completion), testimonials, templates + seeder
- `packages/filament-feedback` (`aiarmada/filament-feedback`) — Filament v5 admin: form builder resource + analytics page, invitation/response/template/testimonial resources, 3 CSV exports, dashboard page, 9 widgets

## Overall Assessment (quality, health, risks, refactor size)
- Quality: moderate-high. Thoughtful domain bits: `commerce_json_column_type('feedback', 'jsonb')` helper, per-model `HasOwner` (all 9), `OwnerContext::assertResolvedOrExplicitGlobal` in scoring, owner-aware policies on all 4 policy files, `FeedbackModelReferenceGuard`, `AnswerValueNormalizer`, `ValidationRuleBuilder`, `VisibilityRuleEvaluator`, analytics calculators per metric. Config defines `json_column_type`.
- Health: lopsided. Single 9-table migration file (`2000_01_01_000001_create_feedback_tables.php`), 30+ actions (form/question/section CRUD each get their own action class — CRUD-action bloat), 9 Filament widgets for one dashboard (NPS/CSAT/average/rating-distribution/trend/completion/comments/testimonials-pending/overview), zero tests. `QuestionTypeRegistry` uses a static in-memory cache (`private static ?array $disabledTypes`) — Octane-sticky state.
- Risks: (1) registration Q&A in events vs survey Q&A here vs responses in engagement — three question/answer/response vocabularies with no boundary doc; (2) testimonial publishing (`ApproveFeedbackTestimonialAction`, published/hidden/rejected events) is a moderation workflow adjacent to events' moderation — confirm no expectation that they integrate; (3) `CalculateFeedbackResponseScoreAction` raw `DB::table((new FeedbackAnswer)->getTable())` bypasses scopes with manual owner assertion — correct only if the assertion can't be skipped.
- Refactor size: S–M (1–3 days). Split migration file, collapse CRUD actions, fix static cache, trim widgets, tests.

## Migration Impact
**Migration Required: NO**
- Tables/columns: no changes. Single migration creates all 9 tables (`forms`, `sections`, `questions`, `question_options`, `responses`, `answers`, `invitations`, `templates`, `testimonials` — verify exact names from migration during refactor) with `uuid('id')->primary()` throughout.
- Indexes/constraints: no FK constraints/cascades (verified pattern) — compliant. Recommend verifying `(form_id, status)`, `(response_id)`, `(question_id)`, invitation `token` unique, but no DDL in this audit.
- Data migration: none. Migration-file split is for readability of future diffs only (new installs run the same schema; already-run installs are unaffected since class names/order unchanged — explicitly do NOT renumber or split into separately-versioned migrations).

## Package Responsibilities
- Core owns: form lifecycle (`CreateFeedbackFormAction`, `CreateFeedbackFormFromTemplateAction`, `ArchiveFeedbackFormAction`, `CloseFeedbackFormAction`, analytics `CalculateFeedbackFormAnalyticsAction`), question/section/option authoring (`CreateFeedbackQuestionAction`, `CreateFeedbackQuestionOptionAction`, `CreateFeedbackSectionAction`, ...), response submission + scoring (`SubmitFeedbackResponse*`, `CalculateFeedbackResponseScoreAction`, `CalculateFeedbackAnswerScoreAction`, `Support/ScoreCalculator.php`, `AnswerValueNormalizer.php`, `ValidationRuleBuilder.php`), invitations (`ResolveFeedbackInvitationTokenAction`, `InvitationUrlGenerator.php`, token guest access), visibility rules (`VisibilityRuleEvaluator.php`, `QuestionTypeRegistry.php`), analytics (`Analytics/FeedbackAnalyticsService.php`, `NpsCalculator.php`, `CsatCalculator.php`, `RatingDistributionCalculator.php`, `CompletionRateCalculator.php`), testimonials (`ApproveFeedbackTestimonialAction`, publish/hide/reject/extract events), template seeder (`database/seeders/FeedbackTemplateSeeder.php`), polymorphic subject guard (`FeedbackModelReferenceGuard.php`), traits (`GivesFeedback`, `ReceivesFeedback`).
- Filament adapter owns: form builder + analytics pages, 4 sub-resources, dashboard, 9 widgets, 3 exports.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)
1. Severity: Medium. Location: `packages/feedback/database/migrations/2000_01_01_000001_create_feedback_tables.php` (9 `Schema::create` in one file — verified via `$jsonColumnType`/`$addJsonColumn` preamble + 9 `uuid('id')->primary()` hits) vs every other audited package (one migration per table). Problem: single-file schema hides per-table history; future table alters force editing a shared file. Why It Matters: merge conflicts on concurrent table work; `migrate:status` opacity. Recommended Fix: split into 9 one-table migrations with the SAME timestamps/class ordering preserved (new files, delete old file, only for fresh installs — coordinate: if any environment already ran `000001`, keep the original file untouched and put future alters in new migrations; the split is for readability only where safe). Per no-legacy rule the split ships as: 9 new files + delete old, applied to codebases that haven't run it; document the caveat in the file header. Breaking Change: NO (schema-identical). Affected Packages: none. Required Dependent Changes: none. Migration Required: NO (schema-identical split; see caveat).
2. Severity: Medium. Location: `packages/feedback/src/Actions/CreateFeedbackFormAction.php`, `CreateFeedbackQuestionAction.php`, `CreateFeedbackQuestionOptionAction.php`, `CreateFeedbackSectionAction.php`, `ArchiveFeedbackFormAction.php`, `CloseFeedbackFormAction.php` (+ ~24 more single-verb actions). Problem: CRUD-each-gets-an-action bloat — ~30 action classes where ~8 lifecycle actions + Filament forms would do (compare ticketing's 8 actions for equal complexity). Why It Matters: each trivial action is a file to open, a binding to register, a fake to write in tests. Recommended Fix: keep lifecycle/scoring actions (submit, score, analytics, approve-testimonial, archive/close with side effects); collapse pure field-mapping CRUD (create/update question/option/section) into the Filament resource forms + a single `SaveFeedbackFormStructureAction` (or model `update()` calls in the resource — Filament already owns the form). Delete the redundant actions after rewiring resource call sites. Breaking Change: YES (action classes removed). Affected Packages: `filament-feedback` (relation managers call them). Required Dependent Changes: rewire `QuestionsRelationManager`, `SectionsRelationManager`, form-builder pages to the single save action. Migration Required: NO.
3. Severity: Medium. Location: `packages/feedback/src/Support/QuestionTypeRegistry.php:11` (`private static ?array $disabledTypes = null` + lazy fill in `disabledTypes()`). Problem: static mutable cache in a long-lived worker leaks across requests under Octane (repo rule: avoid request-leaking static mutable state). `FeedbackQuestionType::isDisabled()` presumably reads config — first request's config wins for the worker's lifetime. Why It Matters: toggling a question type off requires worker restart; worse, per-tenant type availability (if ever added) would leak across tenants. Recommended Fix: delete the static cache (enum `cases()` iteration is trivial — ~10 cases; the cache saves microseconds) OR key it by config hash with request-scoped reset. Prefer deletion. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.
4. Severity: Low. Location: `packages/feedback/src/Traits/GivesFeedback.php`, `ReceivesFeedback.php` vs engagement's 14 traits vs events' 24 traits. Problem: three packages each ship host-model traits with overlapping names (`ReceivesFeedback` vs `HasResponses` vs `HasEventResponses`). Why It Matters: host models combining feedback + engagement + events traits risk method collisions (`responses()` defined 3×). Recommended Fix: prefix relation methods per package (`feedbackResponses()`, `feedbackForms()`) — verify current method names during refactor; rename collisions only, keep trait names. Document the three-way Q&A boundary (see finding 5). Breaking Change: YES if renames needed (verify first). Affected Packages: `engagement`, `events`. Required Dependent Changes: relation-name updates. Migration Required: NO.
5. Severity: Low (boundary doc). Location: events `EventRegistrationQuestion`/`EventRegistrationAnswer` vs feedback `FeedbackQuestion`/`FeedbackAnswer` vs engagement `Response`. Three question/answer/response subsystems, zero cross-links in docs (verified: no `Feedback` string in `packages/events/src`). Recommended Fix: document — events Q&A = transactional registration data (required, scored rarely); feedback = analytical surveys (NPS/CSAT, invitations, testimonials); engagement responses = social signals (RSVP/interested). No code merge (lifecycles genuinely differ). Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Code Quality Findings (same finding format)
1. Severity: Low. Location: `packages/feedback/src/Actions/CalculateFeedbackResponseScoreAction.php:19-24` (`OwnerContext::resolve()` + `assertResolvedOrExplicitGlobal()` then `DB::table((new FeedbackAnswer)->getTable())` routed through `OwnerQuery::applyToQueryBuilder($query, $owner)`). Problem: re-check 2026-09-07 — the filed claim ("manual assertion + unscoped query instead of `OwnerQuery`") is overstated: the code already applies `OwnerQuery::applyToQueryBuilder()` with the assertion kept as defense in depth. Remaining nit is `DB::table` instead of Eloquent (style/readability, not an isolation gap). Demoted Medium→Low per rubric (pattern nit, not incorrect behavior). Why It Matters: `DB::table` bypasses model casts/scopes by construction; future editors must keep the `OwnerQuery` line adjacent to the query. Recommended Fix: keep assertion + `OwnerQuery` as-is; prefer Eloquent (`FeedbackAnswer::query()`) where model events/casts matter. Audit sibling calculators (`CalculateFeedbackAnswerScoreAction`, `CalculateFeedbackFormAnalyticsAction`, `Analytics/*`) for the same shape — keep `OwnerQuery` wherever `DB::table` is used. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: `packages/feedback/src/Support/VisibilityRuleEvaluator.php`, `ValidationRuleBuilder.php`, `AnswerValueNormalizer.php`, `ScoreCalculator.php`. Four well-named support classes — actually good decomposition (contrast with engagement's god manager). No change; recorded as PASS. Keep as the reference pattern for collapsing feedback's own CRUD-action bloat (logic already lives here, actions are thin — which makes the action collapse safe).

## Laravel-Specific Findings
- PHP 8.4, strict types, UUID PKs, `HasUuids`, `getTable()` from config (spot-checked), `json_column_type` (`feedback.database.json_column_type` verified + `commerce_json_column_type('feedback', 'jsonb')` in migration): PASS.
- No FK constraints/cascades, no soft deletes: PASS. `CarbonImmutable`: spot-check during refactor (not sampled at depth).
- Policies: `FeedbackFormPolicy`, `FeedbackResponsePolicy`, `FeedbackTestimonialPolicy`, `FeedbackTemplatePolicy` all gate on `OwnerContext::resolve() !== null || isExplicitGlobal()` (verified) — correct closed-default shape. PASS.
- Invitation guest access: `ResolveFeedbackInvitationTokenAction:21` uses `withoutOwnerScope()` for token lookup then re-enters owner context (`:47`) — same narrow-window pattern as affiliate-network links; correct if the window covers only the lookup (verified shape). Confirm token entropy + expiry + single-use semantics during refactor.
- Seeder `FeedbackTemplateSeeder` ships with the package — verify the service provider registers it as opt-in (not auto-run). Check during refactor.

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)
- Thin-adapter: MIXED. Builder/CRUD resources correctly delegate to core actions (to be consolidated per A2); re-check 2026-09-07: zero `Analytics/*` calculator references in `filament-feedback/src` (no `NpsCalculator`/`CsatCalculator`/`RatingDistributionCalculator`/`CompletionRateCalculator`/`FeedbackAnalyticsService` hits) — the 9 widgets re-query instead of calling calculators, confirmed duplication. Verify each widget calls its calculator during refactor and delete inline queries (keep widget shells).
- Domain leak (Medium if confirmed): `Exports/FeedbackAnswersExport.php`, `FeedbackResponsesExport.php`, `FeedbackTestimonialsExport.php` — re-check 2026-09-07: all 3 verified using `OwnerUiScope::apply(..., includeGlobal: false)` on their `query()` methods. PASS, no change.
- Duplication: `FeedbackFormAnalytics` page vs `FeedbackOverviewWidget`/`FeedbackResponseTrendWidget` — confirm the page composes widgets rather than re-implementing charts.
- Dependency direction: CORRECT (requires `aiarmada/feedback`). PASS. Navigation: PASS (`getNavigationGroup` from nested config; dashboard + resources).
- Owner scoping: GOOD on resources (`FeedbackFormResource:42-44`, `FeedbackTestimonialResource:41-43` use `OwnerUiScope::apply(..., includeGlobal: false)`).

## Database Findings
- Single-file 9-table schema is the structural outlier of the set — split per A1 (with the already-ran caveat).
- JSON usage via configurable column type: PASS. Invitation tokens: verify unique index + expiry column (check migration during refactor).
- Polymorphic subject columns (`FeedbackModelReferenceGuard` exists — good) need `(subject_type, subject_id)` indexes — verify.

## Model / Domain Findings
- All 9 models `HasOwner` (`feedback.owner` — conventional key): PASS, exemplary alongside engagement/seating.
- Testimonial lifecycle (extracted → pending → published/hidden/rejected events) is a genuine moderation sub-flow — keep in feedback (do NOT merge into events moderation; different content, different moderators).
- `FeedbackTemplate` + seeder vs `CreateFeedbackFormFromTemplateAction` — coherent template story. PASS.
- Analytics value objects (`NpsResultData`, `CsatResultData`, `FeedbackAnalyticsData`) via spatie-data — good typing for dashboard contracts. PASS.

## Security Findings
1. Severity: Medium. Location: `Actions/ResolveFeedbackInvitationTokenAction.php` + `Support/InvitationUrlGenerator.php`. Token-granted guest write access (survey responses) is an intentional auth bypass — confirm: cryptographically random tokens, expiry enforced, response-rate throttling per token, token not logged (check `FeedbackInvitationSent/Opened` event payloads exclude the raw token), `withoutOwnerScope()` window minimal. No defect proven at depth; highest-priority verification item in this package. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: testimonial publishing (public content from private responses) — confirm `ApproveFeedbackTestimonialAction` + policy require explicit approval before any public read path exposes testimonial text; verify no public scope leaks unapproved rows. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Performance Findings
1. Severity: Low. Location: analytics calculators over responses/answers + 9 dashboard widgets. Confirm aggregate queries (not per-response PHP loops) back `NpsCalculator`/`CsatCalculator`/`RatingDistributionCalculator`; cache dashboard per owner-key with short TTL; paginate `FeedbackLatestCommentsWidget`. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: `CalculateFeedbackFormAnalyticsAction` full-form recompute — move to queued recalc on response submission (event-driven) if not already. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Testing Findings
- Severity: High. Zero tests. Minimum Pest suite: submission → scoring matrix per question type (`ValidationRuleBuilder` × `AnswerValueNormalizer` × `ScoreCalculator`), visibility rules, invitation token happy/expired/forged paths, testimonial approval flow, analytics calculators on fixture data (NPS/CSAT known values), cross-tenant isolation for all 9 models, calculator owner-scope test (unscoped answer rows invisible). Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `events` | Q&A conceptual overlap | Low — document boundary | Docs only |
| `engagement` | responses/testimonials overlap | Low — document boundary | Docs only; rename colliding relations if proven |
| `filament-feedback` | ~30 core actions | High — action collapse | Rewire relation managers to consolidated save action |
| none (consumers) | traits `GivesFeedback`/`ReceivesFeedback` | Low — method-collision risk | Prefix relations if collisions proven |

## Recommended Refactor Plan (ordered steps)
1. Delete `QuestionTypeRegistry::$disabledTypes` static cache.
2. Harden `CalculateFeedback*` scorers onto `OwnerQuery::applyToQueryBuilder()`; audit `Analytics/*` for same.
3. Collapse CRUD actions into single structure-save action; rewire Filament relation managers.
4. Split the 9-table migration (with already-ran caveat documented).
5. Verify invitation-token security properties + export scoping + widget→calculator wiring.
6. Prefix colliding trait relations (if proven) + write three-way Q&A boundary doc.
7. Write Pest suite.

## Files Likely to Change
- `packages/feedback/src/Support/QuestionTypeRegistry.php`
- `packages/feedback/src/Actions/CalculateFeedbackResponseScoreAction.php`, `CalculateFeedbackAnswerScoreAction.php`, `CalculateFeedbackFormAnalyticsAction.php`, `Analytics/*.php`
- `packages/feedback/src/Actions/CreateFeedbackQuestion*.php`, `CreateFeedbackSectionAction.php` (collapse), `database/migrations/*` (split)
- `packages/filament-feedback/src/Resources/FeedbackFormResource/RelationManagers/*`, `Widgets/*`, `Exports/*`
- `packages/feedback/docs/01-overview.md` (boundary doc)

## Files / Code That Should Be Removed (explicit list, no legacy preservation)
- `private static ?array $disabledTypes` cache in `QuestionTypeRegistry.php:11` (+ lazy-fill block `:15-24`) — replaced with direct `FeedbackQuestionType::cases()` iteration
- Redundant single-verb CRUD actions (exact deletion list after call-site grep; candidates: `CreateFeedbackQuestionAction.php`, `CreateFeedbackQuestionOptionAction.php`, `CreateFeedbackSectionAction.php` and Update/Delete siblings — verified as pure field-mapping by their names/shapes; confirm each has exactly one Filament call site before deleting)
- Old single-file migration `2000_01_01_000001_create_feedback_tables.php` — after split into 9 files (with already-ran caveat; if any env ran it, keep file and abandon split)
- Any widget that re-queries instead of calling its `Analytics/*` calculator (confirm by wiring check; delete the inline query, keep the widget shell)

## Final Recommended Architecture
- `feedback` = analytical-survey bounded context (forms → invitations → responses → scores/analytics → testimonials), fully owner-scoped, invitation-token guest path narrowly fenced, analytics via dedicated calculators consumed by both core and Filament. CRUD collapses into structure-save; registration-Q&A (events) and social responses (engagement) stay separate contexts linked by docs, not code.
