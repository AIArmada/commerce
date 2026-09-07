# docs Audit

## Packages Reviewed (bullets)

- `packages/docs` — document generation (invoices/receipts/labels): 15 models, DocService, email/share/tracking controllers, numbering, rendering, spatie model-states, reminders (58 `src/` files, `config/docs.php`, 4 migrations, `routes/docs.php`, seeders + factories)
- `packages/filament-docs` — Filament adapter: Doc/Template/Sequence/EmailTemplate resources, aging/approvals pages, 5 widgets, exporter, rich-content renderer (44 `src/` files, `config/filament-docs.php`, `routes/filament-docs.php`)
- Root test coverage consulted: `tests/src/Docs/` (25 files incl. `Unit/Services/DocumentServiceTest.php`, `DocServiceUnitTest.php`), `tests/src/FilamentDocs/` (21 files)

## Overall Assessment (quality, health, risks, refactor size)

docs is the most complete domain package in this set (factories, seeders, 5 doc files + troubleshooting, minor-unit money enforced with major-unit aliases actively rejected in `DocService::calculateTotals()`). It is also the most internally duplicated: (1) two status systems — `Enums/DocStatus.php` plain enum plus `States/DocStatus.php` + 8 spatie state classes (`Draft/Pending/Sent/Paid/PartiallyPaid/Overdue/Cancelled/Refunded`); (2) two numbering registries (`NumberStrategyRegistry` + `ConfiguredNumberStrategyRegistry`, the latter reading config in its constructor); (3) a 651-line `DocService` god service that sets `$doc->owner_type` directly (lines 137, 200, 335, 356, 409, 474), bypassing `OwnerWriteGuard`; (4) `SequenceManager` and `DocEmailService`/`DocRenderService` strip `OwnerScope` and hand-roll owner filters; (5) `DocRenderService::money()` hand-rolls formatting instead of `MoneyFormatter`. Filament adapter scoping is good (`DocsOwnerScope` over `OwnerUiScope`), but `RecordPaymentAction`/`SendEmailAction` duplicate service logic. Tracking/share endpoints use GET with side effects. No schema migration required. Refactor size: Medium-Large, code-only.

## Migration Impact

**Migration Required: YES**

| Table | Column/Index/Constraint | Data migration | Notes |
|---|---|---|---|
| `doc_workflows` | `payload` column type `json` → `jsonb` (standardize with siblings + `jsonb` config default) | none | Type-only change on a config/blob column; Postgres `json` vs `jsonb` changes indexing/operator behavior |
| all other `doc_*` tables (docs, sequences, extended) | none proposed | none | uuid PKs verified; `status` string column serves both enum + spatie-state reads — no column change; state consolidation is code-only |

## Package Responsibilities

- Document lifecycle: `Models/Doc.php` (+ `HasStates`), `Services/DocService.php` (create/update/totals/payments), `Models/{DocVersion,DocStatusHistory,DocApproval,DocPayment,DocSequence,SequenceNumber,DocWorkflow,DocWorkflowStep}.php`, `Services/SequenceManager.php`, numbering registries + `Strategies/DefaultNumberStrategy.php`.
- Rendering/delivery: `Services/DocRenderService.php`, `Rendering/TiptapJsonRenderer.php`, `Contracts/RichContentRendererInterface.php`, `Services/DocEmailService.php`, `Mail/DocMail.php`, `Models/{DocEmail,DocEmailTemplate,DocShareLink}.php`, `Jobs/SendDocReminderJob.php`, controllers (`DocDownload/Preview/Tracking/Share`).
- E-invoicing/tracking extensions: `Models/DocEInvoiceSubmission.php`, `Enums/{DocEInvoiceSubmissionStatus,DocEInvoiceValidationStatus,DocPaymentStatus,EmailStatus,ShareLinkAction,RenderAudience,DocMergeTag,DocTemplateBlockType,ResetFrequency,DocType}.php`, `Support/{DocRichContentStorage,TemplateBlockRegistry}.php`, `Facades/Doc.php`, `Contracts/DocServiceInterface.php`.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### D1 — Two status systems for one lifecycle
- Severity: High
- Location: `packages/docs/src/Enums/DocStatus.php` vs `packages/docs/src/States/DocStatus.php` + `States/{Draft,Pending,Sent,Paid,PartiallyPaid,Overdue,Cancelled,Refunded}.php`; bridged ad-hoc in `Models/Doc.php:232,254,277,303,328` (`DocStatus::labelFor(Paid::class, $this)` etc.)
- Problem: A plain enum and a spatie state machine describe the same lifecycle with a manual label bridge at 4+ call sites. Transitions (`markAsPaid`, overdue detection) must update both representations; the enum and the state classes can disagree about legal transitions.
- Why It Matters: Every status-adjacent bug now has two suspects; the `DocPaymentStatus`/`DocApprovalStatus` enums add two more unconnected lifecycles.
- Recommended Fix: Canonicalize on spatie model-states (`States/DocStatus` + transition classes already exist and `Doc` already uses `HasStates`): delete `Enums/DocStatus.php`, move its `label()`/`labelFor()` into the state base class, route all transitions through `Doc::transitionTo(StateClass)` (which already centralizes status→timestamp mapping — extend, don't duplicate). Update the 4 bridge call sites + any `Enums\DocStatus` imports (rg first).
- Breaking Change: YES
- Affected Packages: filament-docs (status filters/badges/forms referencing the enum), checkout/cashier-chip/chip/orders consumers reading `doc.status` (string value unchanged — only the PHP type changes)
- Required Dependent Changes: replace `Enums\DocStatus` imports with state classes; Filament select options from `DocStatus::options()` equivalent on the state base
- Migration Required: NO (`status` column stores the same string)

### D2 — `DocService` sets `owner_type` directly, bypassing write guards
- Severity: High
- Location: `packages/docs/src/Services/DocService.php:137,200` (`$doc->owner_type = $owner->getMorphClass()`), `:307` (`withoutOwnerScope()`), `:335,356,409,474` (direct owner propagation to payment/history/version)
- Problem: Manual owner-tuple assignment duplicates `HasOwner::assignOwnerOnCreate()` and skips `guardOwnedOwnerWrite`/`guardGlobalOwnerWrite` + `OwnerWriteGuard::findOrFailForOwner()` on inbound IDs. The `:307 withoutOwnerScope()` block then re-implements scoping around the unguarded rows.
- Why It Matters: Owner integrity enforced anywhere except the single chokepoint is owner integrity enforced nowhere; a missed check on one of the 5 propagation sites creates cross-tenant payment/history rows.
- Recommended Fix: Never assign `owner_type`/`owner_id` directly: use `$doc->assignOwner($owner)` (HasOwner API) or rely on auto-assign inside `OwnerContext::withOwner()`; validate every inbound foreign ID (`template_id`, `workflow_id`, payment `doc_id`) with `OwnerWriteGuard::findOrFailForOwner()` before attach; replace the `:307` block with `forOwner($owner, $includeGlobal)` query (the `forOwner` helper already exists at `:649`).
- Breaking Change: NO (behavioral tightening; happy-path rows identical)
- Affected Packages: filament-docs (calls `DocService`; no signature change), checkout/cashier-chip/chip doc-generation listeners
- Required Dependent Changes: none (internal)
- Migration Required: NO

### D3 — Two numbering registries; one reads config in its constructor
- Severity: Medium
- Location: `packages/docs/src/Numbering/NumberStrategyRegistry.php` vs `ConfiguredNumberStrategyRegistry.php` (constructor loops `config('docs.types')`, resolves via `app()`); bound in `DocsServiceProvider.php`; consumed in `DocService.php`
- Problem: Subclass auto-registers strategies at construction time from config, so resolution depends on when the object is built (config-cached vs runtime-mutated `docs.types` disagree), and `app()` inside a constructor breaks under Octane/container rebinding. Two classes for register+resolve is one class too many.
- Why It Matters: Document-number collisions or wrong-format numbers when type config changes at runtime (multi-tenant type overrides) — financial-document correctness.
- Recommended Fix: Single `DocumentNumberRegistry` with lazy resolution (resolve strategy on `generate()`, not construction; memoize per request in a scoped binding, not in the object). Keep the 3-step priority (explicit → convention `App\Numbering\*` → default) as a private method. Update `DocsServiceProvider` binding + `DocService` typehint.
- Breaking Change: YES (one class name changes; constructor behavior changes)
- Affected Packages: host apps with custom strategies under `App\Numbering\*` (convention unchanged — no change needed) or explicit `numbering.strategy` config (unchanged)
- Required Dependent Changes: update `NumberStrategyRegistry`/`ConfiguredNumberStrategyRegistry` imports (rg — verified: only `DocService`, provider, and docs tests)
- Migration Required: NO

### D4 — `SequenceManager` strips scope and hand-rolls owner filtering
- Severity: Medium
- Location: `packages/docs/src/Services/SequenceManager.php:45-57` (`withoutOwnerScope()` + manual `where owner_type/owner_id` or `whereNull`), `:88` (direct `$data['owner_type']` assignment)
- Problem: Same bypass pattern as growth's `ScopeSignalQueryToOwner`: drops `OwnerQuery` semantics (`includeGlobal`, custom tuple columns) for a manual re-implementation. Sequence allocation is also the highest-race surface in the package (concurrent invoice numbering).
- Why It Matters: Mis-scoped sequence reads allocate duplicate document numbers across tenants; lost-update races duplicate numbers within a tenant.
- Recommended Fix: Scope via `DocSequence::query()->forOwner($owner, $includeGlobal)`; allocate inside `DB::transaction()` with `lockForUpdate()` on the sequence row (verify current locking — `SequenceNumber`/`DocSequence::booted()` at `Models/DocSequence.php:159`, `SequenceNumber.php:51` suggest partial handling; centralize in `SequenceManager::next()`).
- Breaking Change: NO
- Affected Packages: filament-docs (`DocSequenceResource` reads)
- Required Dependent Changes: none
- Migration Required: NO

### D5 — Hand-rolled money formatting in the render service
- Severity: Low
- Location: `packages/docs/src/Services/DocRenderService.php:514` (`private function money(...)`), `:242,342-390` (string-interpolated HTML with `{$currency} {$this->money(...)}`)
- Problem: Duplicates `MoneyFormatter::formatMinor()` (including its currency-alias/symbol-override tables) and interpolates amounts into HTML by string concatenation.
- Why It Matters: Currency display diverges from every other package (SGD/AUD/CAD overrides); concatenated HTML around formatted values is an escaping accident waiting for a hostile currency label.
- Recommended Fix: Delete `money()`; call `MoneyFormatter::formatMinor($minor, $currency)`; escape dynamic values at interpolation points (or route through Blade — the package already ships `resources/views/`).
- Breaking Change: NO
- Affected Packages: none (rendered output equivalent for standard currencies)
- Required Dependent Changes: none
- Migration Required: NO

## Code Quality Findings (same finding format)

### Q1 — `DocService` god service (651 lines) + `SendDocReminderJob` (250+ lines of querying)
- Severity: Medium
- Location: `packages/docs/src/Services/DocService.php` (651), `src/Jobs/SendDocReminderJob.php:229-268` (owner-tuple batching queries inside the job)
- Problem: `DocService` owns creation, totals, validation, payments, versions, history, and owner plumbing; the reminder job owns its own due-query batching.
- Why It Matters: Same gravity problem as signals' recorder — the next payment-balance bug requires reading 651 lines.
- Recommended Fix: Extract `DocTotals` (pure totals math — already half-isolated at `:482-548`), `DocPaymentRecorder` (payment + balance + status transition), keep `DocService` as orchestrator. Move the job's batching into a `DueDocReminders` query object the job calls. No new package.
- Breaking Change: NO
- Affected Packages: filament-docs (`RecordPaymentAction` should call `DocPaymentRecorder` — see F1)
- Required Dependent Changes: adapter action rewiring (same pass)
- Migration Required: NO

## Laravel-Specific Findings

- PHP 8.4, no FK constraints/cascades (rg clean), uuid PKs, `getTable()` everywhere, `json_column_type` in config + migrations, factories + seeders present — compliant and the best migration hygiene in the set (4 focused migration files).
- One inconsistency: `000001_create_doc_workflows_table.php` uses `commerce_json_column_type('docs', 'json')` (default `json`) while siblings use `'jsonb'` — Postgres `json` vs `jsonb` changes indexing/operator behavior for workflow payload queries. Fix: standardize on `jsonb` via a new migration (one-word change + config default already `jsonb`).
- `SendDocReminderJob` correctly re-enters owner context per batch (`OwnerContext::withOwner` at :55–57, per-owner iteration at :253–268) — queued-job exemplar alongside jnt's listener.

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)

- Direction correct; navigation compliant (`getNavigationGroup()` from `filament-docs.navigation.group` on all resources/pages); `DocsOwnerScope` thin wrapper over `OwnerUiScope` is correct.
- F1 — Action duplication (Medium): `filament-docs/src/Actions/RecordPaymentAction.php` re-implements payment-balance logic owned by `DocService::recordPayment` (`:318-351` with `lockForUpdate`-adjacent flow); `SendEmailAction` duplicates `DocEmailService` template resolution. Fix: actions become thin (`RecordPaymentAction` → `DocPaymentRecorder::record()` from Q1; `SendEmailAction` → `DocEmailService::send()`), keeping only form + authorization.
- F2 — `DocExporter` uses built-in Export action — compliant with Filament guidance. `FilamentRichContentRenderer` + `DocsRichContentFileAttachmentProvider` correctly implement `RichContentRendererInterface` — adapter-behind-contract exemplar.
- `getEloquentQuery()` overrides add owner scoping via parent query (lines 127–130, 253–256, 170–173, 263–266) — correct pattern (domain global scope + UI `includeGlobal` handling), keep.

## Database Findings

- 15-model schema is coherent; `DocStatusHistory`, `DocVersion`, `DocEmail`, `DocPayment` carry owner tuples (verified) — history/audit rows stay tenant-bound, correct.
- `DocShareLink.token_hash` (sha256 of `Str::random(48)` at `DocRenderService:99-103`) — correct token hygiene (hash at rest, single-exposure plain token). No change.
- `SequenceNumber` vs `DocSequence` split (definition vs counter) is sound; add `lockForUpdate` per D4 if missing.

## Model / Domain Findings

- After D1, one state machine; `DocPaymentStatus`/`DocApprovalStatus` stay as enums (payment/approval are sub-lifecycles, correctly not full state machines — document the distinction in `docs/06-status-management.md`).
- `Doc::booted()` cascade handling + `DocApproval::booted()` + `DocVersion::booted()` implement application-level integrity (no DB cascades — compliant); verify each cascade path has a test (payment delete → balance recompute is the risky one).

## Security Findings

- Share links (`DocShareController@show/pdf`, routes `docs/share/{token}`) are capability URLs — correct design, but verify: (1) token comparison uses hashed lookup (yes — `where token_hash`) with constant-time semantics at the DB layer (acceptable); (2) share responses pass through `OwnerContext::withOwner($owner-from-link)` (`DocRenderService:137-142` — correct, link-scoped not ambient); (3) expiry/revocation enforced (`ShareLinkAction` enum + `expires_at` — verify controller checks expiry before render; flagged must-verify).
- Tracking pixels (`DocTrackingController@open/click`) write on GET —recer prefetch and crawlers will fire them. Document as approximate engagement signal (do not treat as billing-grade events); add `Cache-Control: no-store` + 1px response hardening if missing.
- `DocDownloadController`/`DocPreviewController` must use `OwnerRouteBinding` or guarded lookup (same must-verify as jnt's AwbController); filament `ViewDoc` page already asserts via `DocsOwnerScope`.
- No mass-assignment gaps (explicit fillables + DTO validation in `DocService`).

## Performance Findings

- `DocService::recordPayment` sums `payments()->sum('amount_minor')` per payment inside a locked flow — correct and cheap (indexed `doc_id` assumed; verify index on `doc_payments.doc_id`).
- `AgingReportPage` + `RevenueChartWidget` aggregate over `docs` — acceptable with date-bounded queries; reuse the signals lesson: roll up from `DocStatusHistory` if ranges grow, not live aggregation over line items.
- PDF generation (`spatie/laravel-pdf` + Browsershot) is synchronous in controllers — queue large/batch renders; single-document preview sync is fine.

## Testing Findings

- `tests/src/Docs/` (25) is strong incl. service unit tests; `tests/src/FilamentDocs/` (21) good. Gaps: overpayment rejection + balance invariant (`recordPayment` exceeding `total_minor` — service throws, assert via test); concurrent sequence allocation (two simultaneous `next()` → distinct numbers — fails before D4 lock); share-link expiry enforcement; enum/state parity during D1 migration (old enum value → same string as new state). Run: `./vendor/bin/pest --parallel tests/src/Docs tests/src/FilamentDocs`.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| checkout, cashier-chip, chip, orders | doc generation (`BuildsOrderDocs`, `DocsIntegrationRegistrar`, doc listeners) | D1 type change (`status` string unchanged) | Update `Enums\DocStatus` imports to state classes if referenced (rg `Docs\\Enums\\DocStatus`) |
| filament-docs | service/action calls | D3/Q1/F1 rewiring | Call unified registry/recorder/service methods |
| host apps | `docs.types` numbering config, `App\Numbering\*` | D3 lazy resolution (convention unchanged) | None (verify custom strategies still resolve — covered by test) |

## Recommended Refactor Plan (ordered steps)

1. D2: guard owner writes (`assignOwner` + `OwnerWriteGuard`), replace `withoutOwnerScope` block.
2. D4: scope sequences via `forOwner()` + `lockForUpdate`; `jsonb` standardization migration.
3. D1: canonicalize on spatie states; delete enum; update 4 bridge sites + adapter filters.
4. D3: unify numbering registry (lazy, scoped binding).
5. D5+Q1+F1: formatter delegation; extract totals/payment recorder; thin adapter actions.
6. Verify share-expiry + download/preview binding; add required tests; run `./vendor/bin/pest --parallel tests/src/Docs tests/src/FilamentDocs`.

## Files Likely to Change

- `packages/docs/src/Services/DocService.php`, `src/Services/SequenceManager.php`, `src/Services/DocRenderService.php`, `src/Services/DocEmailService.php`, `src/Models/Doc.php`, `src/Enums/DocStatus.php` (delete), `src/States/DocStatus.php`, `src/Numbering/*.php`, `src/DocsServiceProvider.php`, `src/Jobs/SendDocReminderJob.php`, plus new `src/Services/{DocTotals,DocPaymentRecorder}.php`, `src/Numbering/DocumentNumberRegistry.php`, plus a new migration standardizing `doc_workflows.payload` on `jsonb`
- `packages/filament-docs/src/Actions/{RecordPaymentAction,SendEmailAction}.php`, resources referencing `Enums\DocStatus`, `src/Rendering/*`

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/docs/src/Enums/DocStatus.php` (superseded by `States/DocStatus` hierarchy — verified bridge sites at `Models/Doc.php:232,254,277,303,328`; re-grep `Docs\\Enums\\DocStatus` repo-wide before deleting)
- `packages/docs/src/Numbering/ConfiguredNumberStrategyRegistry.php` (folded into unified lazy registry; `NumberStrategyRegistry.php` replaced by `DocumentNumberRegistry.php` — verified consumers: `DocService`, `DocsServiceProvider`, docs tests only)
- `packages/docs/src/Services/DocRenderService.php::money()` (delegated to `MoneyFormatter::formatMinor()`)
- Direct `$doc->owner_type = ...` assignments in `DocService.php:137,200` (+ propagation sites `:335,356,409,474` → `assignOwner()`/guarded flows)
- Nothing else: `DocPaymentStatus`/`DocApprovalStatus` enums stay (sub-lifecycles by design); `TemplateBlockRegistry` stays

## Final Recommended Architecture

docs runs one state machine with centralized transitions, integer money end-to-end, owner-guarded writes with locked sequence allocation, one lazy numbering registry, hashed capability share links, and a filament adapter that renders, exports, and guards — delegating every mutation and every number to the domain.
