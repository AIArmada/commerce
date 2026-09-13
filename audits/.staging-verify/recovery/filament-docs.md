End-to-end review of packages/filament-docs (UI adapter over packages/docs). All findings verified by reading bodies in this package plus cross-checks in docs/commerce-support/filament-chip. No tests exist in this package (no tests/ dir) — findings are from code inspection, not test runs.

## CRITICAL

**C1 — security — any viewer can create/edit/delete everything**
Files: src/Resources/DocResource.php:60-73, DocTemplateResource.php:52-65, DocSequenceResource.php:59-72, DocEmailTemplateResource.php:64-77
`canCreate/canEdit/canDelete` use `hasAnyAbility(['purchase.create','purchase.viewAny'])` (etc.), so holding read-only `purchase.viewAny` grants full write on documents, templates, sequences, and email templates. Every other package reviewed uses the exact ability per action (e.g. filament-* `canCreate → 'x.create'` only). Evidence: `return FilamentPermission::hasAnyAbility(['purchase.create', 'purchase.viewAny']);`. Recommendation: gate each action on its own ability only. Confidence: high.

## HIGH

**H1 — security — docs UI shares CHIP `purchase.*` ability namespace**
Files: same four resources + src/Pages/AgingReportPage.php:43, PendingApprovalsPage.php:68; cf. packages/filament-chip/src/Resources/PurchaseResource.php:34,39 (different domain: CHIP payment purchases, read-only).
Granting purchase-view for payments silently grants document viewing (and via C1, document write). Recommendation: introduce dedicated `document.*`/`docs.*` abilities and migrate. Confidence: high (shared string verified); medium on exploitability (depends on role design).

**H2 — bug — arbitrary status jumps bypass the state machine**
Files: src/Resources/DocResource/Schemas/DocForm.php:93-97; packages/docs/src/Services/DocService.php:208-246; packages/docs/src/Models/Doc.php:87-111 (status fillable).
The form exposes a free status Select; `DocService::update()` does `$doc->update($data)` with no transition validation, so draft→paid skips `transitionTo()` guards, transition timestamps, and status-history audit rows (`transitionStatusTo` is only used by the dedicated status actions). Recommendation: remove direct status editing from the form (or route through `updateStatus()`), and add a transition guard in `DocService::update`. Confidence: high.

**H3 — bug — payments created/edited via RelationManager bypass DocPaymentRecorder**
File: src/Resources/DocResource/RelationManagers/PaymentsRelationManager.php:100-135,142-160; cf. packages/docs/src/Services/DocPaymentRecorder.php:25-89 (lockForUpdate, owner guard, currency match, remaining-balance cap, Paid/PartiallyPaid transitions) and DocPayment.php:68-91 (no created/saved recalc hook).
Default relationship create/edit skips the recorder: overpayment allowed (no remaining-balance check, unlike RecordPaymentAction's maxValue), no row lock, and doc status never transitions to Paid/PartiallyPaid (stale stats/aging). Delete-payment also never reverses status. Recommendation: route RM create/edit/delete through `DocPaymentRecorder`/domain methods or replicate its guards. Confidence: high.

**H4 — bug/security — doc_number and template slug uniqueness not owner-scoped**
Files: DocForm.php:59 (`->unique(ignoreRecord: true)`), DocTemplateForm.php:48; contrast DocEmailTemplateResource.php:271-303 (`scopeUniqueRuleToOwner`).
Causes cross-owner uniqueness collisions and lets a user probe existence of another owner's numbers/slugs via validation errors. Recommendation: apply the same owner-scoped unique rule. Confidence: high on the code gap; medium on enumeration impact.

## MEDIUM

**M1 — bug — RecordPaymentAction leaks domain exceptions as 500s**
File: src/Actions/RecordPaymentAction.php:104-122. No try/catch (unlike SendEmailAction which catches Throwable and notifies), so the recorder's InvalidArgumentException on overpayment/race/currency mismatch bubbles to a Livewire error instead of a validation message. Amount/paid_at constraints exist only as form rules. Recommendation: catch and surface as notification/ValidationException. Confidence: high.

**M2 — bug — SendEmailAction offers templates that always fail**
File: src/Actions/SendEmailAction.php:45-60 vs packages/docs/src/Services/DocEmailService.php:102-121 (`resolveTemplate` enforces `trigger='send'`, owner scope, doc_type, active). The dropdown filters only by doc_type+active, so picking a reminder/paid-trigger template always throws → generic "Email Failed". (Cross-owner template submission itself is safe — domain re-scopes.) Recommendation: filter options by `trigger='send'`. Confidence: high.

**M3 — performance — aging summary loads all docs into memory**
File: src/Pages/AgingReportPage.php:199-238. Unbounded `->get()` of all payable docs per page render plus `CarbonImmutable::now()` per row/bucket. Recommendation: aggregate buckets in SQL (CASE/pivot) or cache. Confidence: high.

**M4 — performance — bulk PDF generation runs synchronously in-request**
File: src/Resources/DocResource/Tables/DocsTable.php:198-208. Loops `generatePdf(save:true)` per selected record; large selections risk timeouts/memory. Recommendation: chunk and dispatch to queue with progress notification. Confidence: medium.

**M5 — security — unassigned approvals approvable by any user with page access**
File: src/Resources/DocResource/RelationManagers/ApprovalsRelationManager.php:240-253 (`assigned_to === null → return true`, only 403 otherwise; no permission check beyond resource view + C1). May be intended "any approver" flow, but combined with C1 the approver set is effectively all viewers. Recommendation: require an explicit approval ability for unassigned items. Confidence: medium.

**M6 — bug — bulk mark-as-sent skips per-record validity, no transaction**
File: DocsTable.php:210-219 vs single-action `canMarkAsSent` (DocsTable.php:237-240). Invalid transitions throw mid-loop → partial completion. Recommendation: filter to eligible records and wrap in a transaction. Confidence: medium.

## LOW

**L1 — perf — dashboard widgets fan out ~15 uncached count queries**
DocStatsWidget.php:26-36 (7 queries), StatusBreakdownWidget.php:46-47 (up to 8), DocTemplateResource.php:104 (uncached badge), PendingApprovalsPage badge+blade double-count (:54-59 + blade :15). Recommendation: single GROUP BY / reuse DocResource's OwnerCache pattern. Confidence: high.
**L2 — bug/maint — DocsOwnerScope is dead code** (src/Support/DocsOwnerScope.php; zero usages) though CONTEXT.md names it the owner/security surface; owner checks rely on OwnerUiScope + domain guards instead. Either use it in Actions/RMs or remove. Confidence: high.
**L3 — bug — email-template duplicate slug can collide** (DocEmailTemplateResource.php:210-217, timestamp suffix; same-second dupes collide) and gives no feedback notification. Confidence: high.
**L4 — bug — PendingApprovalsPage duplicates approve/reject bodies** (:156-195) instead of calling `DocApproval::approve()/reject()` (thin wrappers today — same behavior, single-source-of-truth issue only, verified in docs/Models/DocApproval.php:137-153). Confidence: high.
**L5 — correctness — minor**: temporaryUrl expiry `addMinutes(...)->endOfHour()` extends past configured minutes (DocsRichContentFileAttachmentProvider.php:44-47); RevenueChartWidget `DATE(paid_at)` + GROUP BY alias is MySQL/SQLite-leaning (RevenueChartWidget.php:35-42); form validation gaps (currency free text maxLength 3, tax_rate unbounded numeric, repeater name/qty/price without maxLength/max, DocForm.php:111-124,191-228). Confidence: medium.

## Positives (brief)
- Consistent `OwnerUiScope::apply(..., includeGlobal:false)` on all resource queries, pages, widgets, badges; navigation via `config('filament-docs.navigation.group')` + `getNavigationGroup()` per repo rules; money consistently int minor units with MoneyFormatter.
- Domain layer correctly re-guards what the UI passes through: template resolution owner/doc_type/trigger-scoped, payment recorder locked + balance-capped, DocDownload/DocPreview controllers accept `Doc|string` and re-check via OwnerWriteGuard (route-binding string fallback is safe).
- Rich-content file provider validates IDs via `DocRichContentStorage::isAllowedFileId`; blades escape output (`{{ }}`); SendEmailAction logs failures without leaking internals; Approvals RM revalidates assignee server-side; DocPayment inherits owner from doc on create.
- No migrations/FKs/SoftDeletes in package (adapter-only, compliant); no Octane-unsafe static state; no injection/SSRF/path-traversal/deserialization sinks found (no raw SQL with input, no unserialize, no file-path input).

## Not verified / out of scope
- Whether spatie state config would reject direct status assignment (code path shows no enforcement; not runtime-tested). No package tests to run (Pest suite absent here).
- Filament exporter base-query scoping for DocExporter (modifyQuery only adds withSum; presumed scoped via table query — not traced into vendor).