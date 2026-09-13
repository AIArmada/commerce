End-to-end review of packages/feedback (surveys/responses/invitations/analytics/testimonials). No tests dir, no routes dir, and empty src/Http in this package (verified via ls).

FINDINGS (severity | category | file:line | title — description | evidence | recommendation | confidence)

1. HIGH | bug | src/Actions/DuplicateFeedbackFormAction.php:38-77 | Duplicate drops section-less questions — only questions nested under sections are copied; questions with feedback_section_id=null are silently lost in the copy.
Evidence: `foreach ($source->sections as $section) { ... foreach ($section->questions ...` with no handling of unsectioned questions.
Recommendation: after the section loop, copy `$source->questions()->whereNull('feedback_section_id')` with their options. | high

2. HIGH | bug | src/Traits/ReceivesFeedback.php:41-57 | createFeedbackFormFromTemplate creates an EMPTY form — ignores the template definition (no sections/questions/options copied), bypasses CreateFeedbackFormFromTemplateAction (no reference guard, no FeedbackFormCreated event).
Evidence: `FeedbackForm::create([... 'name' => $template->name, 'purpose' => ...])` — definition never read.
Recommendation: delegate to CreateFeedbackFormFromTemplateAction with subject overrides, or replicate section/question/option creation. | high

3. HIGH | bug | src/Analytics/NpsCalculator.php:61-81, src/Analytics/CsatCalculator.php:63-83 | Per-question NPS/CSAT aggregates the wrong column — when $questionKey is given, whereHas filters responses but the CASE/COUNT/AVG still runs on feedback_responses.score (whole-response total), not the answer score for that question. Wrong whenever a form has >1 scored question.
Evidence: `->whereHas('answers', ... question key ...)` then `COUNT(CASE WHEN score >= 9 ...)` on the response query.
Recommendation: aggregate over FeedbackAnswer (join question, filter key) in the questionKey path. | high

4. HIGH | security | src/Actions/MarkFeedbackResponseAsSpamAction.php:11-21, ReviewFeedbackResponseAction.php:11-21, RejectFeedbackResponseAction.php:11-21, PublishFeedbackFormAction.php:13-27, CloseFeedbackFormAction.php:13-27, ArchiveFeedbackFormAction.php:13-27, MarkFeedbackInvitationOpenedAction.php:11-25, RejectFeedbackTestimonialAction.php:13-27, HideFeedbackTestimonialAction.php:13-24, ExtractFeedbackTestimonialAction.php:15-52 | Lifecycle mutations skip OwnerWriteGuard — these actions forceFill+save whatever model instance is passed, with no owner-context check, so a caller holding a cross-owner instance can mutate it. Inconsistent: Approve/Publish testimonial, delete, reorder, and submit-path actions DO guard.
Evidence: e.g. `$response->forceFill(['status' => 'spam', ...])->save();` with no guard call.
Recommendation: start each execute() with `OwnerWriteGuard::findOrFailForOwner(Model::class, $model->id)` and operate on the returned instance. | high

5. HIGH | bug | src/Actions/SubmitFeedbackResponseAction.php:139-149 | One-response-per-respondent bypassed after review + raceable — the exists() check only matches status='submitted', so after an admin reviews (status=reviewed) the respondent can submit again; there is also no unique DB constraint, so concurrent submits both pass the check.
Evidence: `->where('status', 'submitted')->exists()` then throw; no unique index in responses migration.
Recommendation: check `whereIn('status', ['submitted','reviewed'])` (decide rejected/spam policy) and add a unique index on (feedback_form_id, respondent_type, respondent_id) for opted-in forms, or lock on a respondent key. | high (bypass) / med (race)

6. MEDIUM | bug | src/Actions/CalculateFeedbackResponseScoreAction.php:26-30 | max_score computed as MAX(answer score) — `COALESCE(MAX(score),0) as max_score_val` takes the single largest answer score instead of the sum of per-question maxima (ScoreCalculator::calculateMaxScore exists but is unused here).
Recommendation: sum per-question maxima for the answered questions. | high

7. MEDIUM | bug | src/Analytics/FeedbackAnalyticsService.php:43-57 | pending_review duplicates completed_responses — both count `status='submitted'`; the "pending review" metric is meaningless.
Recommendation: define completed as submitted+reviewed and pending as submitted-only (or vice versa per product decision). | high

8. MEDIUM | bug | src/Support/ValidationRuleBuilder.php:73-87 | Choice answers never validated against defined options + Matrix/Likert misclassified — choiceRules only adds string/array/boolean, so arbitrary values pass and are stored (ScoreCalculator silently scores 0); Matrix/Likert are choice types but get the 'string' branch while real payloads are arrays, so legitimate matrix answers fail validation; FileUpload/Signature (disabled types) fall through with no type rule at all.
Recommendation: add `in:`/allowlist validation from question options for single/dropdown, per-element allowlist for multi/ranking; give Matrix/Likert array rules; reject disabled types. | high (allowlist) / med (matrix shape)

9. MEDIUM | bug | src/Actions/SendFeedbackInvitationAction.php:40-59 | Invitation never marked sent — creates status=Pending with sent_at=null, dispatches FeedbackInvitationCreated; the FeedbackInvitationSent event is never dispatched anywhere (grep confirmed) and no action transitions Pending→Sent.
Recommendation: set status=Sent + sent_at on send and dispatch FeedbackInvitationSent (or document Pending as terminal and remove the dead event). | high

10. MEDIUM | security | src/Actions/StartFeedbackResponseAction.php:24-33, src/Support/FeedbackModelReferenceGuard.php:14-44 | Respondent identity is caller-asserted, never auth-bound — any respondent_type (any Model subclass, no allowlist) + id that merely exists passes; nothing verifies the respondent is the authenticated user, enabling impersonation via the public submit path.
Evidence: `$this->referenceGuard->resolve($respondentType, $respondentId)` only checks existence/owner.
Recommendation: document that HTTP callers must bind respondent to auth()->user(); add an optional verified-respondent assertion; consider an allowlist of respondent classes. | med

11. MEDIUM | bug | src/Actions/SaveFeedbackFormStructureAction.php:48-93 | No question-key uniqueness or type validation — duplicate keys per form corrupt Submit (submittedValues keyed by key; validator rules keyed `answers.{key}` collide) and unknown/disabled types bypass QuestionTypeRegistry::isTypeAvailable.
Recommendation: enforce unique key per form (validation + unique index on feedback_questions(feedback_form_id,key)) and reject disabled/unknown types. | high (uniqueness gap) / med (impact)

12. MEDIUM | performance | src/Models/FeedbackForm.php:64-73, FeedbackResponse.php:63-69, FeedbackQuestion.php:59-65, FeedbackSection.php:45-50, FeedbackAnswer.php:50-55; src/Actions/DeleteFeedbackFormAction.php:20-50 | Cascades load everything into memory with N+1 deletes — model `deleting` hooks use `->each(fn => ->delete())` (one query per row, full collections hydrated); DeleteFeedbackFormAction plucks ALL question/response/answer ids and `->get()->each->delete()` testimonials unbounded (also risks whereIn parameter limits on huge forms).
Recommendation: chunk deletes (chunkById + query deletes where events aren't needed) or queue form deletion. | high

13. MEDIUM | performance | src/Support/ScoreCalculator.php:67-87 with SubmitFeedbackResponseAction.php:72-89; src/Analytics/FeedbackAnalyticsService.php:41-58 | Per-option queries in the submit loop + 7 round-trips per analytics calc — calculateChoiceScore issues one query per submitted option value inside the per-answer loop; calculateLive runs 7 separate count/avg queries.
Recommendation: preload options once per question (map value→score); collapse live aggregates into one selectRaw. | high

14. MEDIUM | bug | src/Actions/StartFeedbackResponseAction.php:24-64 | Start bypasses all form/invitation status checks — creates Draft responses on draft/closed/archived forms and with expired/cancelled/submitted invitations (Submit's guards don't apply to direct calls).
Recommendation: replicate assertFormAcceptingSubmissions/assertInvitationValid (minus one-response check) or route creation through a shared guard. | high

15. MEDIUM | bug+security | src/Actions/ExtractFeedbackTestimonialAction.php:15-52 | Testimonial extracted from first arbitrary text answer, unsanitized — picks the first non-empty text_value regardless of question type (could be an email/phone field), stores raw HTML/JS into a publicly publishable quote; also no OwnerWriteGuard and `$response->form` can fatal if the form is missing.
Evidence: `FeedbackAnswer::where(...)->whereNotNull('text_value')->first()` then `'quote' => $textAnswer->text_value`.
Recommendation: only extract from long_text/short_text questions flagged testimonial-eligible, strip tags + length-cap, guard owner, null-check form; document output-escaping for consumers. | med

16. MEDIUM | bug | database/migrations/*.php vs src/Models/*/getTable() | table_prefix config is ignored by ALL migrations — models prepend `feedback.database.table_prefix` but migrations (including via commerce_schema_create_if_missing, verified not to apply it) create unprefixed tables, so any non-empty prefix breaks the package. Migrations also lack down() methods.
Recommendation: apply the prefix in migrations (or remove the prefix feature); add down() or document non-rollback. | high (prefix) / low (down)

17. LOW | bug | src/Data/SubmitFeedbackResponseData.php:9-24 vs Submit/StartFeedbackResponseAction | Response metadata silently dropped — `$data->metadata` is accepted but never persisted (Start doesn't take it; forceFill only sets status/timestamps/ip/ua).
Recommendation: persist metadata on the response or remove the field. | high

18. LOW | security | src/Actions/ResolveFeedbackInvitationTokenAction.php:21-26 | Invitation rate limit keyed per-token-hash — each guessed token gets a fresh 60-attempt bucket, so the limiter throttles legitimate re-resolution, not enumeration. Impact is low (256-bit tokens: `bin2hex(random_bytes(32))`, sha256-hashed at rest, hash_equals-checked — good).
Recommendation: add an IP-keyed limiter alongside the token-keyed one. | high (mechanism) / low (impact)

19. LOW | bug | src/Analytics/FeedbackAnalyticsService.php:116-122 | Non-portable trend query — `DATE(submitted_at)` + `groupBy('date')` (reserved word) is MySQL-specific; breaks on pgsql/sqlite.
Recommendation: use a grammar-aware date cast or compute buckets in PHP. | med

20. LOW | bug | src/Analytics/FeedbackAnalyticsService.php:80-88 | nps()/csat() ignore their parameters — both take ($form, $questionKey) but return the bare calculator instances, a misleading API.
Recommendation: return `->calculate($form, $questionKey)` results or drop the params. | high

21. LOW | performance | database/migrations/2000_01_01_000005_create_feedback_responses_table.php | Missing index for the one-response check — no index on (feedback_form_id, respondent_type, respondent_id); forms.slug is unindexed/non-unique.
Recommendation: add the composite index (consider unique per finding 5) and index slug. | med

22. LOW | bug | src/Support/AnswerValueNormalizer.php:28-57 | Unsafe coercions on direct calls — `CarbonImmutable::parse($value)` throws on invalid dates (safe only via Submit because validation runs first); `(float)$value` silently coerces garbage ('abc'→0.0).
Recommendation: try/catch parse → null; validate numerics before cast. | med

23. LOW | bug | src/Actions/SubmitFeedbackResponseAction.php:91-96 | ip_address stored untruncated into a string column — an overlong X-Forwarded-For value causes a SQL error (500).
Recommendation: validate/truncate to 255 chars. | med

24. LOW | bug | src/Support/InvitationUrlGenerator.php:12-17 | Generated invitation URLs have no matching route — neither this package (no routes dir) nor filament-feedback (verified: no routes dir) defines `/{prefix}/invitations/{token}`; consuming apps must build it. Docs don't say so.
Recommendation: document the required app-side route (token → ResolveFeedbackInvitationTokenAction → form) or ship a route file. | high

POSITIVES (brief): owner-scoping is consistently applied (HasOwner+HasOwnerScopeConfig on all 10 models, OwnerWriteGuard on the submit/template/duplicate/delete/reorder paths, OwnerQuery on raw builders, owner-scoped analytics job + OwnerCache dashboard); invitation tokens are 256-bit, hashed at rest, constant-time compared; invitation expiry/cancel/reuse checks exist in both submit and resolve paths with lockForUpdate on the invitation; duplicate/template creation runs in transactions; publish-testimonial correctly requires approval + permission; migrations follow repo rules (uuid PKs, no FK constraints, sensible composite indexes); no Octane-unsafe mutable static state (QuestionTypeRegistry is pure); mass assignment is tight (owner_* excluded from $fillable).
