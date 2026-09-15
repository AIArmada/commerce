# Verified Review Repair Progress

Source: audits/verified-review-2026-09-13.md

## Overall

- Total actionable: 1255 (1248 assigned across the 39 units + 7 cross-cutting appendix lines; the
  seating row counts 7 already-fixed-verified lines inside its 60 — composition noted under that unit)
- Completed: 1255
- Remaining: 0
- Blocked: 0
- Newly discovered: 62
- Packages completed: 39 / 39 work units + appendix + final verification batch
- Current package: ALL COMPLETE — handed off (full suite deferred to sharded CI)
- Current repair unit: HANDOFF — all 39 units intaked + supervisor-verified; per-unit suites all green;
  appendix G2 + events AUD:B8 + commerce-support PHPStan fixed in the final batch (see appendix section)

## HANDOFF (2026-09-14)

- Final full-suite run attempted twice, both interrupted before completion: first fataled on a duplicate
  global `regressionUser()` helper (Chip + Feedback RegressionTest files) — supervisor fixed by renaming
  Feedback's copy to `feedbackRegressionUser()` and re-verified Feedback green (88 passed); all other
  repeated helpers confirmed `function_exists`-guarded. Second run terminated by user decision: full suite
  takes hours locally, will run sharded in the GitHub workflow instead.
- Handoff document: `/tmp/repair-handoff-2026-09-14.md` (next steps, outstanding decisions, env notes).
- Tree left uncommitted by design; commit strategy is the user's call.

Note: unit denominator corrected 37 → 39 (the status table holds 39 rows: 29 R1 + 10 R2; earlier
sessions miscounted the denominator — per-row COMPLETE markings were always correct).

## Package Status

| Package / work unit | Actionable | Completed | Remaining | Status |
|---|---:|---:|---:|---|
| commerce-support | 28 | 28 | 0 | COMPLETE |
| addressing | 19 | 19 | 0 | COMPLETE |
| affiliate-network | 21 | 21 | 0 | COMPLETE |
| affiliates | 38 | 38 | 0 | COMPLETE |
| authz | 26 | 26 | 0 | COMPLETE |
| cart | 33 | 33 | 0 | COMPLETE |
| cashier | 34 | 34 | 0 | COMPLETE |
| cashier-chip | 28 | 28 | 0 | COMPLETE |
| checkout | 34 | 34 | 0 | COMPLETE |
| chip | 22 | 22 | 0 | COMPLETE |
| communications | 35 | 35 | 0 | COMPLETE |
| contacting | 13 | 13 | 0 | COMPLETE |
| csuite | 8 | 8 | 0 | COMPLETE |
| customers | 19 | 19 | 0 | COMPLETE |
| docs | 29 | 29 | 0 | COMPLETE |
| engagement | 24 | 24 | 0 | COMPLETE |
| events | 30 | 30 | 0 | COMPLETE |
| feedback | 30 | 30 | 0 | COMPLETE |
| filament-addressing | 17 | 17 | 0 | COMPLETE |
| filament-affiliate-network | 21 | 21 | 0 | COMPLETE |
| filament-affiliates | 22 | 22 | 0 | COMPLETE |
| filament-authz | 22 | 22 | 0 | COMPLETE |
| filament-cart | 24 | 24 | 0 | COMPLETE |
| filament-cashier-chip | 30 | 30 | 0 | COMPLETE |
| filament-commerce-support | 16 | 16 | 0 | COMPLETE |
| filament-communications | 16 | 16 | 0 | COMPLETE |
| filament-contacting | 22 | 22 | 0 | COMPLETE |
| filament-customers | 24 | 24 | 0 | COMPLETE |
| filament-docs | 23 | 23 | 0 | COMPLETE |
| R2: filament-cashier+ticketing+pricing+filament-chip | 59 | 59 | 0 | COMPLETE |
| R2: filament-feedback+signals+filament-shipping+tax | 52 | 52 | 0 | COMPLETE |
| R2: filament-jnt+persons+jnt+filament-orders | 64 | 64 | 0 | COMPLETE |
| R2: filament-organizations+products+filament-pricing+references | 62 | 62 | 0 | COMPLETE |
| R2: filament-products+organizations+filament-seating+filament-persons | 31 | 31 | 0 | COMPLETE |
| R2: filament-promotions+inventory+vouchers+filament-engagement | 61 | 61 | 0 | COMPLETE |
| R2: filament-signals+promotions+filament-vouchers+filament-ticketing | 70 | 70 | 0 | COMPLETE |
| R2: filament-tax+shipping+growth+filament-growth | 55 | 55 | 0 | COMPLETE |
| R2: membership+moderation | 33 | 33 | 0 | COMPLETE |
| R2: seating+orders+filament-events+filament-inventory | 60 | 60 | 0 | COMPLETE |

Note: verdict lines attributed to the `##` section containing each
`### Verification verdicts` block. DUP-counted-once lines are included in
counts; each repair log records every covered finding ID so shared-root-cause
repairs close multiple IDs at once. FALSE/FIXED/UNVERIFIED lines are excluded
from actionable counts.

Order of work: commerce-support first (foundation), then Round-1 domain
packages in review order, then Round-1 filament adapters, then Round-2 groups.

---

# REPAIR LOG

### [DONE] commerce-support — R1:#1 + AUD:B6 (mass assignment)

Root cause:
Report/SavedSearch/NotificationPreference listed polymorphic identities and
workflow state as $fillable, so any ::create($input) path could forge
reporter/reviewer identity and terminal workflow state.

Change:
Narrowed $fillable to caller-supplied content only (Report: report_type,
title, message, metadata; SavedSearch: name, query, filters, meta;
NotificationPreference: preference fields only). Report gained model-default
status=open/severity=medium, enum casts, creating hook stamping reported_at,
and explicit transitions startReview/resolve/reject/archive that associate
the reviewer server-side.

Files:
- packages/commerce-support/src/Models/Report.php
- packages/commerce-support/src/Models/SavedSearch.php
- packages/commerce-support/src/Models/NotificationPreference.php
- tests/src/CommerceSupport/ReportWorkflowTest.php (new)

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/ReportWorkflowTest.php — PASS (4 passed)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#1
- commerce-support AUD:B6 (DUP of R1:#1)

Notes:
No in-repo writers of these models exist (verified by grep); consumers must
set identities via relationships/forceFill and workflow via transitions.

### [DONE] commerce-support — R1:#2 + R1:#7 + R1:#3 + AUD:B3 (webhook claim/dedup/indexes/exception)

Root cause:
Cross-delivery dedup was a plain exists() inside the same row-locked txn
that ran side effects: concurrent same-event deliveries both passed the
check (double processing), the lock was held during arbitrary side effects,
webhook_calls had zero indexes, and (string)$e persisted traces to the DB.

Change:
Claim-then-process: short lockForUpdate txn claims the row
(status=processing, stamps event_id/event_type); duplicate check + side
effects run outside the lock; UNIQUE(name, event_id, event_type) makes the
claim race-safe (loser marks itself processed without side effects); fresh
`processing` claims are skipped while stale ones (>30 min) are reclaimable.
Base isDuplicateProcessedEvent now queries indexed event_id/event_type
columns instead of JSON paths. Failures report() server-side and store only
{class, truncated message} matching the model's array cast. Canonical
migration gained event_id/event_type columns, (name,status) + processed_at
indexes, and the unique constraint.

Files:
- packages/commerce-support/src/Actions/ProcessWebhookCallAction.php
- packages/commerce-support/src/Webhooks/CommerceWebhookProcessor.php
- packages/commerce-support/database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub
- tests/src/CommerceSupport/WebhooksTest.php

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/WebhooksTest.php — PASS (12 passed, incl. 2 new)

Migration/schema:
- canonical migration edited: packages/commerce-support/database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub

Covered findings:
- commerce-support R1:#2
- commerce-support R1:#7
- commerce-support R1:#3
- commerce-support AUD:B3 (DUP of R1:#3)

Notes:
Unique key includes event_type to preserve the tested contract that the
same provider id with different event types processes both. chip/checkout/
jnt subclasses override protected hooks only (handle() is final) so the new
extractEventId seam is compatible; chip's JSON-path isDuplicate override
still works and can adopt columns in the chip repair pass.

### [DONE] commerce-support — R1:#4 + R1:#10 + R1:#15 (targeting trust/N+1/recursion)

Root cause:
TargetingContext duplicated the Context/* value-object extraction inline
and re-ran it per getter call (orders()->count() twice at construction
plus once per isFirstPurchase read; per-line categories lazy-loads per
read). Channel/country/geo resolution trusted client-settable headers
unconditionally. Custom and/or/not expressions recursed without depth or
node caps in both validate() and the public evaluateExpression().

Change:
Getters now delegate to the constructor-computed Cart/User/Environment
contexts (single order-count query per context, derived isFirstPurchase);
CartContext preloads associated-model categories with one query per model
class and reads only loaded relations (relation + category_id branches
moved in from TargetingContext for parity). Proxy/CDN headers are ignored
unless commerce-support.targeting.trust_proxy_headers is explicitly enabled
(default false); metadata > user > headers > defaults precedence kept;
Referer/UTM documented as untrusted hints. Custom expressions capped at
depth 10 / 200 nodes in validate() and defensively in evaluateExpression()
(truncation fails closed to false even under `not` parity). Docs updated
(06-targeting-engine.md, 03-configuration.md).

Files:
- packages/commerce-support/src/Targeting/TargetingContext.php
- packages/commerce-support/src/Targeting/Context/UserContext.php
- packages/commerce-support/src/Targeting/Context/CartContext.php
- packages/commerce-support/src/Targeting/Context/EnvironmentContext.php
- packages/commerce-support/src/Targeting/TargetingEngine.php
- packages/commerce-support/config/commerce-support.php
- packages/commerce-support/docs/06-targeting-engine.md
- packages/commerce-support/docs/03-configuration.md
- tests/src/CommerceSupport/TargetingContextTest.php (new)

Verification:
- ./vendor/bin/pest --parallel --filter="Targeting" tests/src/CommerceSupport/ — PASS (16 passed)
- ./vendor/bin/pest --parallel tests/src/Promotions/PromotionServiceBehaviorTest.php — PASS (7 passed)
- ./vendor/bin/pest --parallel tests/src/Pricing/PriceCalculatorTest.php — PASS (34 passed)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#4
- commerce-support R1:#10
- commerce-support R1:#15

Notes:
Downstream constructors (checkout/promotions adapter, pricing, vouchers)
pass server-side metadata or null requests, so default-off header trust
does not break them. getTimezone/getCurrency intentionally not delegated
(divergent fallback semantics, no query cost).

### [DONE] commerce-support — R1:#5 (OwnerFilesystem traversal)

Root cause:
Traversal guard was a substring block on `..` plus a leading-slash check,
bypassable via URL-encoding (single/double), backslash separators,
drive-letter/absolute Windows paths; storage used the unqualified default
disk.

Change:
path() now normalizes through a strict validator: rejects empty/control
chars, rawurldecodes repeatedly, unifies separators, rejects absolute and
drive-letter paths, and rejects any empty/`.`/`..` segment. All operations
route through a disk() helper honoring the new
commerce-support.filesystem.disk config (null = app default) so operators
can pin a private disk. Docs updated (11-isolation-primitives.md,
03-configuration.md).

Files:
- packages/commerce-support/src/Support/OwnerFilesystem.php
- packages/commerce-support/config/commerce-support.php
- packages/commerce-support/docs/11-isolation-primitives.md
- packages/commerce-support/docs/03-configuration.md
- tests/src/CommerceSupport/OwnerFilesystemTest.php

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/OwnerFilesystemTest.php — PASS (24 passed, incl. 11-case evasion dataset)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#5

Notes:
Single in-repo caller (docs DocRichContentStorage) passes a trimmed config
directory, unaffected by stricter validation.

### [DONE] addressing — R1:#1 (coordinates)

Root cause: floatOrNull() cast any non-empty value, so "abc" became 0.0.
Change: return null for non-numeric input (mirrors seed guards).
Files: packages/addressing/src/Data/AddressData.php + Data tests.
Verification: ./vendor/bin/pest --parallel tests/src/Addressing/Data — PASS.
Covered: addressing R1:#1.

### [DONE] addressing — R1:#2 + R1:#3 + R1:#7 + AUD:B2 (area import lifecycle)

Root cause: per-row uncached queries, no txn; synced_at stamped before
dirty check; dry-run skipped validation; unconditional is_active=true.
Change: single txn, country pluck, memoized lookups, validation before
dry-run branch, synced_at only on real change, is_active on create only +
explicit reactivate param/--reactivate flag (SeedCountryGeographiesAction
passes reactivate: true). Docs updated.
Files: Actions/ImportAddressAreasAction.php,
Actions/SeedCountryGeographiesAction.php,
Commands/ImportAddressAreasCommand.php,
Commands/ImportAddressAreasCsvCommand.php, docs/04-usage.md.
Verification: ImportAddressAreasActionTest — PASS (12, incl. 3 new).
Covered: addressing R1:#2, R1:#3, R1:#7, AUD:B2.

### [DONE] addressing — R1:#4 (navigation URL sanitization)

Root cause: NormalizeNavigationUrl had zero call sites; javascript: URLs
persisted verbatim.
Change: AddressData::from() normalizes googleMapsUrl/wazeUrl and
navigationLinks.*.url (covers model saving + BuildAddressLinks read path).
Files: Data/AddressData.php, docs/04-usage.md.
Verification: Data suite + Events/VenueAddressAdoptionTest — PASS.
Covered: addressing R1:#4.

### [DONE] addressing — R1:#5 + R1:#12 (save-path cost + formatted drift)

Root cause: saving hook always ran full normalization; formatted fields
preserved verbatim and drifted.
Change: NORMALIZED_ATTRIBUTES dirty check skips normalization; new
request-memoized SchemaTableCache; formatted fields regenerated via
AddressFormatter when inputs change unless caller explicitly set them.
Files: Models/Address.php, Support/SchemaTableCache.php (new),
Actions/NormalizeAddressDataAction.php, Support/AddressCountryResolver.php.
Verification: AddressLifecycleTest (new, 4) + Orders + Customers suites — PASS.
Covered: addressing R1:#5, R1:#12.

### [DONE] addressing — R1:#8 (save-area atomicity)

Change: save + relationship rewrite wrapped in DB::transaction.
Files: Actions/SaveAddressAreaAction.php.
Verification: SaveAddressAreaActionTest — PASS (5).
Covered: addressing R1:#8.

### [DONE] addressing — R1:#9 (CSV failure handling)

Change: jsonArray() wraps JsonException into InvalidArgumentException;
trailing empty columns tolerated; command try/catch wraps execute().
Files: Support/CsvAddressAreaSource.php,
Commands/ImportAddressAreasCsvCommand.php.
Verification: import + package suites — PASS.
Covered: addressing R1:#9.

### [DONE] addressing — R1:#10 (re-attach label)

Change: existing-pivot path updates label when provided and different.
Files: Traits/HasAddresses.php.
Verification: HasAddressesTest — PASS (13).
Covered: addressing R1:#10.

### [DONE] addressing — R1:#11 (cast normalization)

Change: array set-path routed through AddressData::from()->toArray().
Files: Casts/AddressDataCast.php.
Verification: AddressDataCastTest — PASS (4).
Covered: addressing R1:#11.

### [DONE] addressing — R1:#13 (trust-sensitive fillable, option 2)

Root cause: validation_*/provider_* fillable.
Change: documented trusted-write-only on the model (finding's explicit
second option); verified the Filament form exposes none of these fields
(no user-input path). Narrowing $fillable was rejected because it would
silently break three trusted cross-package writers (filament-customers
validation page, customers sync, orders CreateOrder).
Files: Models/Address.php (docblock).
Verification: AddressFormSchema field grep + suites — PASS.
Covered: addressing R1:#13.

### [DONE] addressing — R1:#14 (morph-type oracle)

Change: single inaccessibleMessage() for all probing branches.
Files: Support/AddressOwnerGuard.php.
Verification: AddressOwnerGuardTest — PASS (4, incl. new test).
Covered: addressing R1:#14.

### [DONE] addressing — R1:#15 (seed N+1 + parent options)

Change: chunked city preload + batched inserts; single state preload;
parentOptions() gained $search/$limit (default 5000).
Files: Actions/SeedAddressCitiesAction.php,
Actions/SeedAddressStatesAction.php, Support/AddressAreaHierarchy.php.
Verification: seed suites — PASS (5 + 4).
Covered: addressing R1:#15.

### [DONE] addressing — AUD:B1 (delete cascade)

Change: model delete() per dependent inside DB::transaction; snapshot
null-out in same txn.
Files: Models/Address.php.
Verification: new lifecycle test (events fire, pivots gone) — PASS.
Covered: addressing AUD:B1.

### [DONE] addressing — AUD:B3 (postal-code import)

Change: one country preload, memoized areas, single outer txn.
Files: Actions/ImportPostalCodesAction.php.
Verification: ImportPostalCodesActionTest — PASS (5).
Covered: addressing AUD:B3.

### [ALREADY FIXED] addressing — R1:#6, AUD:B4, AUD:Q#9

Evidence: name-lower indexes present in canonical creates
(000001:35, 000002:25, 000003:26); LowerNameIndexesMigrationTest passes.
No migration touched.

## PACKAGE COMPLETE — addressing

Findings completed: 19 / 19 (16 repaired, 3 verified already-fixed)

Package verification:
- ./vendor/bin/pest --parallel tests/src/Addressing — PASS (200 passed, 485 assertions; worker + supervisor re-run)
- ./vendor/bin/pint --test on touched files — PASS (1 concat_space fixed)
- ./vendor/bin/phpstan analyse packages/addressing/src --level=6 — PASS (no errors)

Outstanding:
- none (non-actionable follow-up: Filament parent picker could adopt the new parentOptions search params in the filament-addressing pass)

### [DONE] commerce-support — R1:#6 + R1:#8 + AUD:B7 + AUD:B8 (owner resolution trust)

Root cause:
fromTypeAndId() instantiated owners without querying, so orphaned/forged
tuples yielded working scopes on trust boundaries (jobs collapsed every
payload — even restored models — back to tuples; batch runner resolved
per-iteration). The batch tuple scan loaded all distinct tuples via get()
and threw on malformed tuples mid-run after partial callbacks.
include_global was suppressed via global config mutation, which is
Octane-fragile and misses boot-snapshotted scopes.

Change:
New OwnerContext::fromTypeAndIdOrFail() (half-tuple ->
InvalidArgumentException, missing row -> ModelNotFoundException, returns
fresh model) plus ParsedOwnerTuple::toOwnerModelOrFail() and
OwnerJobContext::toOwnerModelOrFail(); OwnerContextJob trait and
OwnerBatchRunner discovery use the OrFail variants. Runner discovery
streams distinct tuples via cursor with deterministic ordering and
validates every tuple (structural + existence) before any callback.
include_global suppression moved to the new request-scoped
OwnerScopeOverride (consulted per query in OwnerScope::apply, flushed on
Octane boundaries); global config is never mutated. Docs updated
(04-multi-tenancy.md).

Files:
- packages/commerce-support/src/Support/OwnerContext.php
- packages/commerce-support/src/Support/OwnerTuple/ParsedOwnerTuple.php
- packages/commerce-support/src/Support/OwnerJobContext.php
- packages/commerce-support/src/Traits/OwnerContextJob.php
- packages/commerce-support/src/Support/OwnerScopeOverride.php (new)
- packages/commerce-support/src/Support/OwnerScope.php
- packages/commerce-support/src/Support/OwnerBatchRunner.php
- packages/commerce-support/src/SupportServiceProvider.php
- packages/commerce-support/docs/04-multi-tenancy.md
- tests/src/CommerceSupport/OwnerBatchRunnerTest.php
- tests/src/CommerceSupport/OwnerContextJobTest.php
- tests/src/CommerceSupport/OwnerContextTest.php

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/OwnerBatchRunnerTest.php — PASS (12)
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/OwnerContextTest.php — PASS (15)
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/OwnerContextJobTest.php — PASS (9)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#6
- commerce-support R1:#8
- commerce-support AUD:B7 (DUP of R1:#8)
- commerce-support AUD:B8

Notes:
OwnerRouteBinding already queries (findOrFailForOwner) — no change.
OwnerUiScope resolves record tuples for comparison only — lenient variant
kept deliberately. Cross-package toOwnerModel() callers keep lenient
semantics; each package pass decides its own boundaries. Running package
workers were notified of the OrFail/batch-runner contract changes.

### [DONE] commerce-support — R1:#9 (OwnerCache invalidation + stampede)

Root cause:
forgetOwner() relied solely on tag flush, silently no-opping on
file/database/array drivers and leaving stale owner keys; remember() had
no rebuild lock (thundering herd).

Change:
Keys now embed a per-owner cache version
(owner:{scopeKey}:v{N}:{logical}); forgetOwner() always bumps the version
(portable invalidation on every driver) and keeps tag flush as
best-effort memory hygiene. remember() uses double-checked locking
(atomic lock, 5s block, uncached-compute fallback on timeout). Docs
updated (11-isolation-primitives.md).

Files:
- packages/commerce-support/src/Support/OwnerCache.php
- packages/commerce-support/docs/11-isolation-primitives.md
- tests/src/CommerceSupport/OwnerCacheTest.php

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/OwnerCacheTest.php — PASS (14, incl. version + file-store tests)
- ./vendor/bin/pest --parallel tests/src/Cart/Unit/LoginMigrationCacheKeyTest.php — PASS (1)
- ./vendor/bin/pest --parallel tests/src/Cart/Unit/Listeners/HandleUserLoginAttemptTest.php — PASS (7)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#9

Notes:
All in-repo key() consumers build and read symmetrically through
OwnerCache, so the format change is transparent.

### [DONE] commerce-support — R1:#11 (log/audit PII capture)

Root cause:
LogsCommerceActivity defaulted loggable attributes to raw $fillable
(capturing credentials/PII); HasCommerceAudit's sensitive list covered
only 10 credential fields, omitting email/phone/address/name/dob.

Change:
New shared SensitiveAttributes list (exact PII/credential names +
unambiguous credential fragments). Activity-logging default is now
fillable minus sensitive; explicit per-model allowlists untouched. Audit
redaction/exclusion uses the shared list. Fixed the trait docblock's
broken example fence. Docs updated (07-auditing-logging.md).

Files:
- packages/commerce-support/src/Support/SensitiveAttributes.php (new)
- packages/commerce-support/src/Concerns/LogsCommerceActivity.php
- packages/commerce-support/src/Concerns/HasCommerceAudit.php
- packages/commerce-support/docs/07-auditing-logging.md
- tests/src/CommerceSupport/Concerns/LogsCommerceActivityTest.php
- tests/src/CommerceSupport/Concerns/HasCommerceAuditTest.php

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/Concerns/ — PASS (16)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#11

Notes:
Two obsolete assertions (name/email logged/audited by default) updated to
the fail-closed design. No model overrides getSensitiveFields, so the
shared list applies repo-wide.

### [DONE] commerce-support — R1:#12 + AUD:B1 + AUD:B2 (money display math)

Root cause:
Display formatting divided minor units in float (drift on large values);
formatCurrency() failure returned false into a string return (TypeError);
toDollars() was not labelled display-only.

Change:
MoneyFormatter decimal paths now build grouped decimals with exact
integer math (intdiv/str_pad, half-away-from-zero rounding parity with
number_format when display precision is lower). MoneyNormalizer::format()
computes the decimal exactly, casts only at the intl boundary, and throws
a descriptive RuntimeException instead of returning false; toDollars()
labelled display-only with never-persist guidance.

Files:
- packages/commerce-support/src/Support/MoneyFormatter.php
- packages/commerce-support/src/Support/MoneyNormalizer.php
- tests/src/CommerceSupport/MoneyFormatterTest.php

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/MoneyFormatterTest.php — PASS (6, incl. large-value + rounding tests)
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/MoneyNormalizerTest.php — PASS (4)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#12
- commerce-support AUD:B1 (DUP of R1:#12)
- commerce-support AUD:B2

### [DONE] commerce-support — R1:#13 (health widget)

Root cause:
Blade getters re-ran latestResults() three times per render;
preg_replace/preg_split results were unguarded (null/false -> TypeError).

Change:
Per-instance memo of health results (one store read per render);
null/false guards on name formatting. Also fixed the result mapping to
the real spatie/health 1.40 contract (see NEW-001 — inseparable).

Files:
- packages/commerce-support/src/Filament/Widgets/CommerceHealthWidget.php
- tests/src/CommerceSupport/CommerceHealthWidgetTest.php

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/CommerceHealthWidgetTest.php — PASS (2)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#13

### [DONE] commerce-support — R1:#14 + AUD:B9 (reference-data seeding)

Root cause:
Per-row select + insert/update (~2 queries x ~150+ rows), no
transaction — slow and partially applied on failure.

Change:
All three seed actions rewritten to single-transaction bulk sync: one
keyed preload, PHP diff preserving created/updated/skipped + quiesce
semantics, chunked inserts (500) and keyed upsert for changed rows.
Update rows carry the existing id (SQLite checks NOT NULL before
conflict resolution).

Files:
- packages/commerce-support/src/Actions/SeedCurrenciesAction.php
- packages/commerce-support/src/Actions/SeedLanguagesAction.php
- packages/commerce-support/src/Actions/SeedTimezonesAction.php
- tests/src/CommerceSupport/SeedReferenceDataTest.php (new)

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/SeedReferenceDataTest.php — PASS (4, incl. quiesce + query-count + update-only tests)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#14
- commerce-support AUD:B9 (DUP of R1:#14)

### [DONE] commerce-support — R1:#16 (pinned HTTP transport)

Root cause:
CURLOPT_RESOLVE pinning was attached as a curl option with no check
that the effective transport was curl-backed; a custom Guzzle handler
stack would silently ignore the DNS pin.

Change:
When a resolve entry is required, the client now requires the curl
extension AND refuses to send if a custom Guzzle handler is configured
in the effective request options (fail closed before any byte is sent).
Http::fake() flows are unaffected (no transport involved).

Files:
- packages/commerce-support/src/Http/PinnedHttpClient.php
- tests/src/CommerceSupport/PinnedHttpClientTest.php (new)

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/PinnedHttpClientTest.php — PASS (3)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#16

### [DONE] commerce-support — R1:#17 (env upsert atomicity)

Root cause:
Plain read-modify-write on .env (concurrent installer runs interleave)
plus prefix key matching that duplicated `KEY =value` lines.

Change:
Exclusive flock around the whole read-modify-write; atomic same-directory
temp+rename write; exact key extraction (blank/comment/non-assignment
lines ignored, spaces around `=` tolerated); early return when every key
is skipped; explicit failure when .env is missing.

Files:
- packages/commerce-support/src/Actions/UpsertEnvVariablesAction.php
- tests/src/CommerceSupport/UpsertEnvVariablesTest.php (new)

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/UpsertEnvVariablesTest.php — PASS (4)

Migration/schema:
- none

Covered findings:
- commerce-support R1:#17

### [ALREADY FIXED] commerce-support — R1:#18

Evidence: ResolveOwnerDisplayName, OwnerAvatar, and
OwnerUiScope::extractDisplayName do not exist anywhere in current source
(repo-wide grep, case-insensitive). The surviving display-name code
(HasOwner::getOwnerDisplayNameAttribute + Voucher override) reads the
instance-cached `$this->owner` morphTo relation plus loaded attributes —
no fromTypeAndId, no fresh(), no per-attribute queries. No code change.

Follow-up for filament-vouchers pass: VouchersTable renders
owner_display_name per row without eager-loading `owner` (standard
table-level N+1, owned by that adapter).

### [DONE] commerce-support — R1:#19 + AUD:B4 (tag tables)

Root cause:
taggables hardcoded foreignUuid('taggable_id'), breaking tagging of
non-UUID models; Tag model + pivot table ignored commerce-support table
config.

Change:
taggable columns now use morphs('taggable') so the key type follows
Schema::defaultMorphKeyType (commerce-support.database.morph_key_type).
Tag::getTable() reads commerce-support.database.tables.tags; provider
syncs spatie tags.taggable.table_name from
commerce-support.database.tables.taggables; tagTablesExist() and the
canonical stub use the configured names.

Files:
- packages/commerce-support/database/migrations/1970_01_01_000001_create_tag_tables.php.stub
- packages/commerce-support/src/Models/Tag.php
- packages/commerce-support/src/SupportServiceProvider.php
- packages/commerce-support/config/commerce-support.php
- tests/src/CommerceSupport/TagTablesTest.php (new)

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/TagTablesTest.php — PASS (3)

Migration/schema:
- canonical migration edited: packages/commerce-support/database/migrations/1970_01_01_000001_create_tag_tables.php.stub

Covered findings:
- commerce-support R1:#19
- commerce-support AUD:B4

### [DONE] commerce-support — AUD:B5 (signature validator)

Root cause:
Base validator compared the raw header against the bare hex digest, so
providers sending `sha256=<hex>` failed; no timestamp freshness concept,
leaving accepted signatures replayable outside event dedup.

Change:
validateSignature() strips a single case-insensitive `{algo}=` scheme
prefix before hash_equals. Added opt-in replay-window enforcement:
subclasses override getTimestampHeader() (+ optional tolerance, default
300s); missing/unparseable/stale/future-dated timestamps fail closed.
Default behavior unchanged for existing subclasses (J&T verified).

Files:
- packages/commerce-support/src/Webhooks/CommerceSignatureValidator.php
- tests/src/CommerceSupport/SignatureValidatorTest.php (new)

Verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport/SignatureValidatorTest.php — PASS (2)
- ./vendor/bin/pest --parallel tests/src/Jnt/Unit/Webhooks/JntSignatureValidatorTest.php — PASS (4)

Migration/schema:
- none

Covered findings:
- commerce-support AUD:B5

## PACKAGE COMPLETE — commerce-support

Findings completed: 28 / 28 (27 repaired, 1 already-fixed R1:#18)

Package verification:
- ./vendor/bin/pest --parallel tests/src/CommerceSupport — PASS (318 passed, 960 assertions; re-run after pint)
- ./vendor/bin/pint on all touched files — PASS (13 auto-fixed, suite re-verified)
- Cross-package fallout checks: Promotions (7), Pricing (34), Jnt signature (4), cart cache-key (1+7) — all PASS

Outstanding:
- none
- PHPStan level 6: environmentally blocked in this sandbox (EPERM/ENOSPC) — rerun when disk clears
- Follow-ups routed: chip JSON-path isDuplicate (columns available), filament-vouchers owner eager-load, filament-affiliate-network link/category guards, orders attribution snapshot

### [DONE] affiliate-network — all 21 (worker repair, 19 units)

- R1:#1 site-delete cascade: deleting hook chunkById(200) + per-offer delete (grandchildren no longer orphaned).
- R1:#2 archive owner crash: batch over owner-bearing AffiliateSite (offers table lacks owner cols).
- R1:#3 unguarded update: UpdateOffer updatableFields() allowlist + guardRelocation(); sync internals via forceFill.
- R1:#4 bad enum default: canonical offers migration status default 'pending' -> 'draft'.
- R1:#5 conversion idempotency: listener early-returns when order metadata attribution set (off-request attribution availability -> orders follow-up F1).
- R1:#6 + AUD:B1 re-apply race/cooldown: catch 23000/23505 -> return existing; cooldown from rejected_at ?? updated_at.
- R1:#7 + AUD:B2 createLink guards: require isActive() + approval + http(s) target_url (kills stored open redirect).
- R1:#8 + AUD:B5 sync N+1: existingBySubject preload, per-subject try/catch + txn, sync.max_programs cap (config), non-http catalog URLs -> null.
- R1:#9 archive unbounded: chunkById(500), archived_at set, --older-than clamped.
- R1:#10 missing indexes: ends_at, (site,program,subject), (status,visibility) added to canonical offers create.
- R1:#11 global slug unique: canonical categories migration unique(owner_type,owner_id,slug).
- R1:#12 token fail-open: throw OfferNotFoundException on decrypt failure; rawurlencode programId.
- R1:#13 forgeable attribution: cookie encrypted; parseCookie rejects undecryptable/malformed.
- R1:#14 null fresh(): `??` fallback at all 5 sites (grep-verified).
- R1:#15 create validation: Validator rules in CreateOffer; sync internals via forceFill.
- R1:#16 approved list: getApprovedOffers limit (default 500); middleware half already conditional (no change).
- AUD:B3 click/conversion gaming: bot-UA filter (redirect still served); route signed + throttled.
- AUD:B4 fillable counters: clicks/conversions/revenue, source_checksum/last_synced_at removed from fillables; forceFill in actions; factory withStats() post-create.
- Docs updated: 03-configuration (sync section), 04-usage, 06-services, 08-api-reference.

Verification:
- ./vendor/bin/pest --parallel tests/src/AffiliateNetwork — PASS (238 passed, 494 assertions; worker + supervisor re-run)
- ./vendor/bin/pint --test on touched files — PASS

Migration/schema:
- canonical offers create edited (default + indexes)
- canonical offer-categories create edited (scoped unique)

Covered findings: affiliate-network R1:#1..#16, AUD:B1..B5 (21/21).

Notes:
Residual accepted: global (null-owner) categories can share slugs at DB level (NULL tuples in unique index) — tenants isolated, per finding scope. Follow-ups routed: F1 orders (attribution snapshot on event), F2/F3 filament-affiliate-network (isActive guard; scoped slug unique rule). PHPStan environmentally blocked (sandbox EPERM + ENOSPC) — rerun when disk clears.

## PACKAGE COMPLETE — affiliate-network

Findings completed: 21 / 21

Package verification:
- ./vendor/bin/pest --parallel tests/src/AffiliateNetwork — PASS (238 passed)
- pint --test — PASS

Outstanding:
- none (F1/F2/F3 follow-ups recorded for owning-package passes)

### [DONE] affiliates — all 38 (worker repair, 17 units)

- U1 R1:#1 + AUD:B7 commission caps: new CommissionCaps central clamp; closed upline fixed-level + performance-bonus gaps.
- U2 R1:#2 + AUD:B1 + AUD:Q#2 + AUD:B8 payout creation: approved-only + lockForUpdate + conditional claim + balance reserve + owner-match + terminal-initial-status rejection.
- U3 R1:#3 + AUD:B2 + AUD:Q#15 payout lifecycle: transitionTo() map, complete->paid, fail/cancel->release + unlink + sync.
- U4 R1:#4 + AUD:B4 + AUD:Q#14 + AUD:B6 + R1:#19 conversion accounting: void branch, clamped balance moves, balance locks, memoized sync flag.
- U5 R1:#10 + R1:#20 + AUD:B3 + AUD:Q#13 maturity: chunkById, txn + lock + recheck, null occurred_at guard.
- U6 R1:#7 + AUD:B10 + R1:#17 fraud/attribution: FraudDetectionService wired, fingerprint + rotation, rate-limit add/increment.
- U7 R1:#6 + R1:#13 + AUD:B11 API/link security: validation, http(s) + allowed-host + full-query signature, `none` auth removed.
- U8 R1:#12 owner fillable: owner keys removed from fillables (4 models), forceFill internals.
- U9 R1:#5 + R1:#18 cascades/prune: chunked per-model deletes (events fire), bounded nulls-first prune.
- U10 R1:#8 + R1:#9 + AUD:B14 reports: SQL aggregates, whereBetween, upsert, grouped breakdown, tier/promotion caches.
- U11 R1:#11 + AUD:B13 engine staleness: scoped binding + saved/deleted bust on rule/tier/promotion.
- U12 AUD:B5 idempotency: txn + always-on key + unique-violation -> return-existing (incl. RecordAffiliateOutcome).
- U13 AUD:B9 webhooks: empty secret -> no queue/send.
- U14 AUD:B12 IP PII: IpHasher (SHA-256) at write points; touchpoints.ip_address widened 45->64 (canonical).
- U15 R1:#14 export traversal: basename() on {payout}; --path kept as explicit operator path.
- U16 R1:#15 CreateAffiliate validation: name/rate-cap/OwnerWriteGuard/approval-mode status.
- U17 R1:#16 cookie secure default true.
- Docs/config hygiene: stale api.auth key removed, cookie/fingerprint/allowed_hosts documented.

Verification:
- ./vendor/bin/pest --parallel tests/src/Affiliates — PASS (1157 passed, 5 skipped, 2696 assertions; worker + supervisor re-run)
- ./vendor/bin/pint --test 65 touched files — PASS
- ./vendor/bin/phpstan analyse packages/affiliates/src --level=6 --debug — PASS (no errors; --debug needed for sandbox TCP block)

Migration/schema:
- canonical 000002 edited (comment), canonical 000006 edited (ip 45->64)

Covered findings: affiliates R1:#1..#20, AUD:B1..B14, AUD:Q#2/#13/#14/#15 (38/38).

Notes:
Advisory for filament-affiliates: payout status options offering illegal transitions now throw TransitionNotFound; manual creation must select approved conversions. Accepted residuals: PerformanceBonusService firstOrCreate race (low, command-path), reconcilePayout provider-override bypass (by design), GeoAnomalyRule touchpoint IPs unpopulated in-package, per-affiliate daily aggregation chunk design.

## PACKAGE COMPLETE — affiliates

Findings completed: 38 / 38

Package verification:
- ./vendor/bin/pest --parallel tests/src/Affiliates — PASS (1157 passed, 5 skipped)
- pint --test — PASS; phpstan level 6 — PASS

Outstanding:
- none

### [DONE] authz — all 26 (worker repair, 21 units)

- R1:#1 team_resolver inert: setTeamResolverConfig() fills permission.team_resolver default (host choice wins); docs.
- R1:#2 super-admin Gate memo: rememberSuperAdmin() per user+role (team-null check, team excluded from key).
- R1:#3 global flush: flushPermissionCache -> resetScopeState() (request-scoped clear + relation unset); clearCache() keeps explicit global flush.
- R1:#4 + AUD:B2 + AUD:Q#1 take() authz: private isAuthorized() (self-refusal, canImpersonate/super-admin, canBeImpersonated, scope guard) + $authorize escape hatch (default true).
- R1:#5 + R1:#7 + AUD:B1 + AUD:B3 Role: create() mirrors parent (enum_value + RoleAlreadyExists); permissions() via Config/registrar; scoped team overwrites caller teams key; findByParam allowlist.
- R1:#6 + R1:#20-part Permission: enum_value() in create()/findOrCreate(); deleted getPermissions() delegation.
- R1:#8 quietLogin: direct user/loggedOut assignment (no Authenticated event).
- R1:#9 open redirect: shared BackToUrlSanitizer (rejects backslash + controls), used by both call sites.
- R1:#10 Blade split: expression passthrough + empty guard + callAfterResolving registration fix (N1).
- R1:#11 dead controller: routes/web.php POST leave-impersonation (web+auth) + loadRoutesFrom.
- R1:#12 resolver race: read-first fast path + 23000 catch -> re-read.
- R1:#13 sync validation: validateSyncConfig() + DB::transaction.
- R1:#14 cascade timing: deleting -> deleted hook.
- R1:#15 self-guard: string-cast comparison (helpers + isAuthorized).
- R1:#16 SuperAdmin: --panel honored (resolveGuard), users.name_column config, LIKE escaping, insert-race retry.
- R1:#17 prohibition statics: double loop removed + reset() helper keeping boot-time registrations (N2).
- R1:#18 redundant index dropped (canonical migration edited).
- R1:#19 UUID-only keys documented (02-installation).
- R1:#20 unknown case throws (SUPPORTED_CASES + boot assertion).
- R1:#21 session-driver clobber documented.
- R1:#22 coverage: AuthzRepairRegressionTest (5) + ImpersonationSecurityTest (7).

Verification:
- ./vendor/bin/pest --parallel tests/src/Authz — PASS (25 passed, 65 assertions; worker + supervisor re-run)
- ./vendor/bin/pint --test on touched files — PASS
- PHPStan environmentally blocked (sandbox TCP + opcache/ENOSPC) — rerun outside sandbox

Migration/schema:
- canonical 000001 edited (redundant index dropped)

Covered findings: authz R1:#1..#22, AUD:B1..B3, AUD:Q#1 (26/26).

Notes:
Follow-ups for filament-authz pass: F1 align ImpersonateAction/TableAction OR-semantics with take() contract; F2 adopt BackToUrlSanitizer in ImpersonateController + audit sanitizeRedirectPath copies.

## PACKAGE COMPLETE — authz

Findings completed: 26 / 26

Package verification:
- ./vendor/bin/pest --parallel tests/src/Authz — PASS (25 passed)
- pint --test — PASS

Outstanding:
- none (F1/F2 follow-ups recorded for filament-authz pass)

---

### [DONE] contacting — all 13 (inline repair, 9 units)

- R1:#1 + R1:#4 + R1:#5 tri-state updates: isPrimary/isVerified/metadata/purpose/label-like DTO fields are Optional-kept on update (explicit values still apply); create/update actions honor displayValue, verifiedAt, validFrom/validUntil, sortOrder; countryCode Optional-guarded; dead normalizedValue/normalizedUrl DTO fields removed; snake_case array input mapping added so the documented array shapes work (NEW-004).
- R1:#2 validation + resolvers: static DTO rules() (required type/platform+value, column max lengths, purpose allowlist, strict-gated type/platform allowlists, date/integer/boolean shapes) enforced via explicit validate() in all four actions; email-type values rejected when the normalizer yields null (trim-tolerant, matching normalizer semantics); resolveContact/resolveContacts/primaryContactMethod skip null-normalized rows.
- R1:#3 cascade: deleting hooks use withoutOwnerScope()->chunkById() per-model deletes, each wrapped in the child's own owner context (explicit global for global rows) so events and HasOwner guards run and no cross-owner orphans remain.
- R1:#6 + R1:#7 links/URLs: mailto requires valid addr-spec with no whitespace, tel rejects control chars and validates RFC3966 charset, wa.me is digits-only, telegram URLs pass NormalizesUrl plus a t.me/telegram.me/telegram.dog allow-list with rawurlencoded handles, SocialProfileConfig::buildUrl encodes handles, extractHandle strips query/fragment, NormalizesUrl accepts schemes case-insensitively and lowercases scheme+host.
- R1:#8 + AUD:B1 verification sync: saving hook stamps verified_at when is_verified flips true (explicit values win) and clears it when flipped false, on both ContactMethod and SocialProfile.
- R1:#9 primary backstop (partial): MySQL functional index uses CAST(JSON_ARRAY(...) AS CHAR(512)) instead of delimiter-ambiguous CONCAT_WS in both canonical migrations. Owner-column inclusion is BLOCKED-test-conflict: MigrationIndexesTest requires null-owner direct writes to violate against owned rows, which any owner partitioning would break; owner scoping stays app-level (tested).
- R1:#10 snapshot/link perf: fromBundle loadMissing('owner') + single bulk insert (manual uuids/timestamps, one snapshotable guard, per-source owner match) instead of per-row saves; (source_type, source_id) index added to contact_snapshots; forContactable() gained an optional limit. The save() preflight + saving-hook guard pair is kept intentionally (non-destructive preflight is covered behavior).
- R1:#11 snapshots/contracts: ContactSnapshot updating/deleting throw LogicException (append-only); source_id/source_type removed from fillable (action assigns directly); NormalizeContactMethodAction/NormalizeSocialProfileAction implement the normalizer contracts and are bound in ContactingServiceProvider; dead ContactSnapshotData deleted with its two constructor-only tests.
- AUD:B2 null-parent primaries: documented as by design in 04-usage.md (no parent scope to partition by) with a regression test pinning coexistence.

Verification:
- ./vendor/bin/pest --parallel tests/src/Contacting — PASS (373 passed, 598 assertions)
- tests/src/Contacting/VerifiedReviewRegressionTest.php — PASS (19 passed, incl. padded-email acceptance)
- ./vendor/bin/pest --parallel tests/src/Customers — PASS (242 passed; caught + fixed over-strict email check)
- ./vendor/bin/pest --parallel tests/src/Affiliates — PASS (1157 passed, 5 pre-existing skips)
- ./vendor/bin/pest --parallel tests/src/Events — 247 passed, 1 failed in AssignmentRequestActionsTest (proven pre-existing via scoped stash rerun: fails identically without contacting changes; no contacting code in path; belongs to events pass)
- ./vendor/bin/pint --test packages/contacting tests/src/Contacting — PASS (49 files)
- ./vendor/bin/phpstan analyse packages/contacting/src --level=6 --debug — PASS, no errors (plain run is sandbox TCP-blocked)

Migration/schema:
- canonical 000001 + 000003 edited (MySQL JSON_ARRAY backstop expression; sqlite/pgsql branches unchanged)
- canonical 000002 edited (source_type+source_id index added)

Covered findings: contacting R1:#1..#11, AUD:B1 (merged into R1:#8), AUD:B2 (13/13; R1:#9 partial with documented test-conflict sub-clause).

Notes:
- Behavior deltas for consumers: action calls now validate (ValidationException on empty/invalid input); email-type values must normalize non-null; resolvers exclude null-normalized rows; snapshots are immutable; ContactSnapshotData is deleted; documented snake_case arrays now actually map (previously silently dropped multi-word flags).
- Follow-ups for filament-contacting pass: none new; form-level handle/url presence rules stay with the filament findings.

## PACKAGE COMPLETE — contacting

Findings completed: 13 / 13

Package verification:
- ./vendor/bin/pest --parallel tests/src/Contacting — PASS (373 passed)
- pint --test — PASS
- phpstan level 6 — PASS

Outstanding:
- R1:#9 owner-column backstop variant: BLOCKED-test-conflict (MigrationIndexesTest pins cross-owner violation), documented above
- events AssignmentRequestActionsTest failure: pre-existing, out of scope, flagged for events pass

---

### [DONE] cart — all 33 (worker repair, 26 units, supervisor-verified)

- U1 R1:#1 login-migration cache removed: pre-login session id stashed in the guest's own session; deleted LoginMigrationIdentifierResolver/LoginMigrationCacheKey + binding + stale test; MigrateCartOnLoginAction drops cache fallback. LoginMigrationSecurityTest (happy path + planted entry ignored).
- U2 R1:#2 swap() uses storage as-is (no withOwner(null)). U16 R1:#16 DatabaseStorage::flush() deletes via owner predicate instead of truncate().
- U3 R1:#3+AUD:B6 swapIdentifier refuses occupied targets (false, both carts untouched); checks inside txn with lockForUpdate; same-id no-op; mirrored in Testing/InMemoryStorage (incl. same-key self-delete fix); SwapTest #1 updated (had locked data-loss behavior); StorageInterface + docs/08-storage.md.
- U4 R1:#4+AUD:B1+AUD:Q#1+AUD:B10 CAS retry once w/ backoff on 23000/23505 + CartConflictException; syncFromCart retries once in fresh txn; stale-overwrite proven impossible (noted in code).
- U5 R1:#5+AUD:B2+AUD:Q#2 guest→user migration in one txn, events after commit; retry-after-failure clean, retry-after-success no-op.
- U6 R1:#6 mergeItems coerces/clamps qty to [1,max], throws on corrupt rows + max_items overflow (event-enrichment declined: no per-item channel on CartMerged).
- U14 R1:#14 merged_into_id marked after target write. U15 R1:#15 sumItemQuantities guards non-array/non-numeric rows.
- U7 R1:#7+AUD:B5 applyCustom validates name/type/target/value/order up front (domain Exception); no type allowlist (custom types via handler registry; Filament form verified compatible).
- U23 AUD:B4 fixed-value minor/major syntax documented as intentional contract + locking test, no behavior change.
- U8 R1:#8 abandonment marking chunkById(500), dry-run count without hydration. U9 R1:#9 deletion via id-batched loop, dry-run single count().
- U10 R1:#10 SyncNormalizedCartJob ShouldBeUnique (owner+identifier+instance, 60s). U25 AUD:B9 child syncs only when dirty/new. U24 AUD:B7 assertSnapshotScope fails loudly on cross-owner parent resolution.
- U11 R1:#11 associated-model restore batched per class, OwnerQuery-scoped, request instance cache; no class allowlist ("consider"-level, would break buyables).
- U12 R1:#12 addMultiple validates all rows first (all-or-nothing), single load/save; refreshBuyablePrices in-memory + ItemUpdated per change.
- U13 R1:#13 resolveIntQuantity (ints pass, validated int-strings cast, else InvalidCartItemException); missing ['value'] still means 0→removal (locked test contract kept).
- U17 R1:#17 associated persistence class+id only (data snapshot dropped); docs example updated.
- U18 R1:#18 depth cap 32 in validateSerializable/normalizeContextValue; comma-splitting kept (test-locked + Filament UI documented), now code-documented.
- U19 R1:#19 thousand separators always imply major units in both price normalizers; comma tests green; documented.
- U20a R1:#20a unscoped CartSnapshot::user() removed (zero callers). U20b login listener guards session + caps 25 instances/login.
- U22 AUD:B3 markAsConverted idempotent w/ version-checked atomic update; loser returns or throws CartConflictException; unsaved/null-version handled.
- No-change: AUD:B8 verified safe; AUD:B11+AUD:Q#4 FIXED (composite index in canonical migration, no 2026_* file).

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Cart — PASS (1070 passed, 2 skipped pre-existing RUN_STRESS_TESTS gates, 2771 assertions)
- ./vendor/bin/pint --test cart scope — PASS (part of 481-file worker-scope run)
- ./vendor/bin/phpstan analyse packages/cart/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- none (no schema change required)

Covered findings: cart 33/33 (R1:#1..#20 + AUD:B1..B11 + AUD:Q#1/Q#2/Q#4 per audit count; DUPs counted once).

Notes:
- 6 new regression files, 24 tests, all green.
- NEW-005/006/007 recorded below (worker-found, left open).
- Follow-up: tests/Support/Cart/InMemoryStorage.php swapIdentifier still overwrites on conflict (shared harness file, out of worker scope) — diverges from hardened StorageInterface contract.

## PACKAGE COMPLETE — cart

Findings completed: 33 / 33

Package verification:
- ./vendor/bin/pest --parallel tests/src/Cart — PASS (1070 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding:
- none (NEW-005/006/007 open; harness InMemoryStorage follow-up noted)

---

### [DONE] cashier — all 34 (worker repair, 23 units, supervisor-verified)

- U1 R1:#1+AUD:B1/B2/B6/Q#1/Q#2/Q#3 webhook signatures: handleWebhook() takes ?rawPayload on contract/abstract/both gateways, verified before handling (WebhookVerificationException); Stripe forwards raw body; SyncWebhook verifies before dispatch; null raw = documented trusted-internal-replay path. 6 WebhookSecurityTest tests.
- U2 R1:#7 replay command: dead replay-all removed; event-ID-only with gateway validation, StripeGateway::fetchWebhookEvent (typed EventService), explicit CHIP failure, dry-run kept. 5 command tests.
- U3 R1:#2 CHIP zero-amount checkout via product(name, unitMinor, qty); ChipCheckoutBuilder::price() parses "name:amountMinor" else throws.
- U4 R1:#3 recurring_token out of ChipPayment/ChipSubscription toArray (accessor kept). U5 R1:#4 CreatePayment rethrows rate-limit unwrapped, unexpected errors generic payment_failed + logged.
- U6 R1:#5+AUD:B3 refunds: Stripe omits amount for full refunds + positive-amount guards both gateways (HTTP-fake asserted).
- U7 R1:#6+AUD:B4 retrieval owner checks on Stripe/CHIP retrieve paths; denials security-logged, null contract kept per existing tests.
- U8 R1:#8 CHIP charge/refund/subscribe-create rate-limited. U9 R1:#9 CartCheckoutBuilder validates/allocates in-txn, gateway outside, releaseAllInventory compensation.
- U10 R1:#10 Support/ActionGuard (gateway/amount/string validation + belongsToOwner fail-closed) wired into all 4 actions; validation battery + 2 cross-owner tests.
- U11 R1:#11 refund $options parameter removed (zero callers; forwarding would break GatewayContract mocks); reflection arity test.
- U12 R1:#12 StripePayment N+1: shared client, charge memoization, expanded Charge honored; missing-secret now loud.
- U13 R1:#13+AUD:B8 listing caps (DEFAULT_LIST_LIMIT=100, eager items, CHIP invoice cap); AUD:B7 N+1 premise disproven on installed vendor (single list call) but mapping simplified anyway.
- U14 R1:#14 UnifiedInvoice fromStripe invoice_pdf / fromChip contract currency + config fallback, both tested.
- U15 R1:#15 setPreferredGateway via ActionGuard + LogicException when column absent. U16 R1:#16 queue-safe SnapshotPayment/SnapshotSubscription (constructors preserved, round-trips tested).
- U17 R1:#17 locale via Money::setLocale; formatLocale() NOT adopted (would break pinned 'RM10.00' contract), both asserted.
- U18 R1:#18 trial cast CarbonInterface fast path + parse guard, false on garbage. U19 R1:#19 Billable catch narrowed to GatewayException; all else logged + rethrown (both paths tested).
- U20 R1:#20 portal URL: cashier.portal.allowed_panels (default ['billing']) + relative/same-host returnUrl validation, url('/') fallback; config + docs.
- U21 R1:#21+AUD:B5 schema cache 60s TTL + flushColumnCache + Octane hook; stale-then-flush test.
- U22 R1:#22 cashierOwnerScopeConfig() single source on AbstractGateway; CHIP billable-vs-owner validation; existing + adversary tests green.
- U23 R1:#23 38 new tests (WebhookSecurity/ActionValidation/MoneyLifecycle).

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Cashier — PASS (294 passed, 617 assertions)
- ./vendor/bin/pint --test cashier scope — PASS (part of 481-file worker-scope run)
- ./vendor/bin/phpstan analyse packages/cashier/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- none (package has no migrations by design)

Covered findings: cashier 34/34 (all verdict lines actionable and repaired).

Notes:
- Contract deltas: RefundPayment signature changed ($options removed, zero callers verified); handleWebhook gained optional rawPayload (safe: no external implementers/callers per worker grep incl. filament-cashier).
- U20's hardening intentionally changed customerPortalUrl behavior; two stale CashierChip tests pinning the open redirect were updated at intake (see cashier-chip entry).

## PACKAGE COMPLETE — cashier

Findings completed: 34 / 34

Package verification:
- ./vendor/bin/pest --parallel tests/src/Cashier — PASS (294 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding:
- none

---

### [DONE] cashier-chip — all 28 (worker repair, 18 units + supervisor intake fix)

- U1 R1:#1/#10/#16 invoice creation: addProductCents + assertAmountWithinBounds on tab totals; Checkout::create bounds/currency/redirect validation; sort by Invoice::date().
- U2 R1:#2 swap carries unit_amount (per-price override or unit_amounts[]/unit_amount options; negatives rejected).
- U3 R1:#3 webhook: purchase_id dedup (second delivery no-op, tested), complete claimed/unknown attempt or record completed, never touches canceled subs, enum statuses.
- U4 R1:#4 renewal idempotency_key renewal-{attempt_id}; latest unknown reconciled each run (paid→completed, pending→wait, failed→past-due, no-purchase→retry same attempt id); record* accept unknown.
- U5 R1:#5 SubscriptionBuilder::assertCouponValidForSubscription enforced before discounting (Paused voucher throws InvalidCoupon, tested).
- U6 R1:#6/#23 tokens encrypted casts both models; store matches on decrypted values (hash_equals, empty-guarded); recurring_token out of fillable; canonical migrations string→text, token unique/index dropped (uniqueness in store).
- U7 R1:#7 single renewalAmount() = items − applicableCouponDiscount (once/repeating/forever windows; repeating-window failure fails open to stored discount) used by claim/charge/invoice/format; integer minor units.
- U8 R1:#8 findPayment mirrors findInvoice client-id verification (stranger → null, tested). U9 R1:#9 safeFilename allowlist for Content-Disposition.
- U10 R1:#11 chip options allowlist + URL validation (client_id/brand_id/purchase no longer overridable).
- U11 R1:#12 charge() throws InvalidCustomer::missingBillable when billable absent; default amount = renewalAmount().
- U12 R1:#13 BILLING_INTERVALS + assertValidBillingInterval + advanceBillingDate enforced at builder/model/creation/webhook/renewal/period-start.
- U13 R1:#14/#15 invoices(?limit=25) + purchase memo + capped merge; subscription(type) constrained-first (≤2 queries asserted).
- U14 R1:#18 RedirectUrlValidator (absolute http/https + optional allowed_hosts) in charge/payment/checkout/setup; new config key documented.
- U15 R1:#19 PaymentMethodMetadata minimization at all three save sites.
- U16 R1:#20 save/setDefault transactional with lockForUpdate; newest-as-default fallback removed (no portable partial unique on MySQL).
- U17 R1:#22 isDefault reads stored flag; deletePaymentMethods one CHIP loop + one bulk local delete.
- U18 AUD:B2 display-only webhooks.verify_signature removed; WebhookCommand reports real chip.webhooks.verify_signature; config/docs/README/TestCase updated.
- No-change with evidence: R1:#17 FALSE (no duplication in current source); R1:#21 + AUD:B1/Q#1 + AUD:B5/Q#2 FIXED (verified present; no-reclaim residual pinned by existing test = kept contract); AUD:B3 verified; AUD:B4 verified-no-change (kill-switch pinned by CrossTenantIsolationTest both states); AUD:B6 ALREADY-FIXED (eager loads present).
- R1:#24 coverage: Feature/RepairRegressionTest.php, 23 regression tests covering every unit.
- Intake fix (supervisor): worker-reported 2 ChipGatewayTest failures adjudicated — tests pinned cashier R1:#20's open redirect (external returnUrl echo + arbitrary panel), which the cashier worker fixed per audit; tests updated to the hardened contract (external→url('/') fallback, relative/same-host passthrough, panel allow-list reject + allow-listed custom panel). Suite now fully green.

Verification (supervisor re-ran + intake fix):
- ./vendor/bin/pest --parallel tests/src/CashierChip — PASS (578 passed, 992 assertions)
- ./vendor/bin/pint --test cashier-chip scope — PASS (part of 481-file worker-scope run)
- ./vendor/bin/phpstan analyse packages/cashier-chip/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- canonical migrations edited: token columns string→text, token unique/index dropped, purchase_id + lease_expires_at indexes added, subscription_id aligned to foreignUuid. No FK constraints, no SoftDeletes.

Covered findings: cashier-chip 28/28.

Notes:
- NEW-008/009 recorded below (worker-found, left open: $0-renewal product decision; second-purchase reconciliation).
- Cross-package candidate: promote RedirectUrlValidator to commerce-support (worker verified PublicHttpUrlGuard is the wrong shape for browser redirects).

## PACKAGE COMPLETE — cashier-chip

Findings completed: 28 / 28

Package verification:
- ./vendor/bin/pest --parallel tests/src/CashierChip — PASS (578 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding:
- none (NEW-008/009 open with product/host decisions pending)

---

### [DONE] communications — all 35 (worker repair, 27 units, supervisor-verified)

- U1 R1:#1/#2+AUD:B1/B2-part/Q#16 tracking tokens: handle() returns new CreatedTrackingTokenData (trackingToken + plaintextToken); getToken() removed (zero callers repo-wide, supervisor re-grepped); target URL must be absolute http(s), encrypted via DestinationProtector, honors tracking.allowed_hosts; expiry parse failure → InvalidArgumentException; OwnerWriteGuard on delivery lookup.
- U2 R1:#3+AUD:B6 DestinationProtector delegates to Laravel Crypt (authenticated; tamper/malformed → DecryptException fail-closed); hash()/hint() unchanged; old ciphertexts unreadable (documented breaking).
- U3 R1:#4 recorder compares DeliveryStatus ->value (was strict string compare → always Processing).
- U4 R1:#5 provider-event state machine: TransitionDeliveryAction gained force flag (stamps + events); applyProviderStatus allows explicit forward jumps (keeps tested pending→delivered/unsubscribed) but refuses terminal regression; replay --force bypasses terminal guard; EVENT_TIMESTAMP_MAP removed.
- U5 R1:#6 webhook owner fallback: default null resolver kept (tested); action derives delivery owner via include-global read and re-enters scope; unknown delivery → RuntimeException; owner-disabled finder fixed (NEW-011).
- U6 R1:#7 idempotency: single atomic acquire decides duplicates; lock released on dispatcher failure.
- U7 R1:#8 plan deliveries: planning.max_deliveries cap (500); recipients/contents preloaded in 2 queries with validation outside txn (same ModelNotFound contract); date/attempt validation; owner guard.
- U8 R1:#9 dispatch job chunkById(200) with for-update-skip-locked; recalc only when rows transitioned. U10 R1:#11 due-dispatch bulk update restricted to legal set; --batch clamped ≥1; one job per communication retained (job contract).
- U9 R1:#10+AUD:B3 cascades: delivery cascade chunked; Communication::deleting in txn, children keep parent_id nulled, hook-free leaf relations bulk-deleted, per-model deletes only where hooks required; thread/batch chunked.
- U11 R1:#12 webhook payload limits: 413 over max_payload_bytes (256KB) or max_payload_depth (32, iterative).
- U12 R1:#13 tracking interaction: expired/revoked → RuntimeException; TrackingInteractionType enum (accepts enum or string, keeps tested 'click'); scoped findOrFail first (ModelNotFound preserved).
- U13 R1:#14 managed notification: notifiable must be object with getKey(); getMorphClass for Models (::class otherwise, keeps non-Model contract); channels validated non-empty strings (no closed allowlist — custom channel classes supported); string via() normalized; attempts from config.
- U14 R1:#15 inbound validation + redaction: sender morphs must resolve, ids non-empty, pairs both-or-neither; PayloadRedactor::redactText masks PEM/Bearer/Basic, applied to inbound subject/body and all outbound content-text persistence; fixed inbound rendered_at crash (NEW-010).
- U15 R1:#16 webhook agility (partial): per-provider algorithm (validated vs hash_hmac_algos, fail-closed) + signature_header config; uniform-401 BLOCKED-test-conflict (WebhookTest pins 404, supervisor confirmed).
- U16 R1:#17 fingerprint prefers payload.id/event_id + sha256, JSON_THROW_ON_ERROR fallback. U17 R1:#18 expiry preserves deadline, dispatches new CommunicationExpired, force-expires non-engaged/non-terminal deliveries in chunked txns. U18 R1:#19 prune commands catch bad --before → error + FAILURE.
- U19 R1:#20 thread race: canonical unique(channel, external_thread_id); 23000 → return concurrent winner. U20 R1:#21 attempt race: txn + lockForUpdate + owner guard; canonical unique(delivery_id, attempt_number).
- U21 R1:#22 recalc pluck + enum/string normalization; inbox prune chunked (counts + cross-owner kept). U22 R1:#23 inbox guard structural skip only for non-owner-aware/disabled; AuthorizationException propagates (cross-owner test).
- U23 R1:#24 duplicate provider event 23000 → domain RuntimeException in both record paths. U24 R1:#25 policy update()/delete() → false (read-only; filament-communications ViewAction-only verified unaffected).
- U25 R1:#26 SplObjectStorage auto-capture state + per-channel pruning (mail+sms both reach sent; 7 existing tests green).
- U26 AUD:B2 (+B4/Q#17 DUPs) OwnerWriteGuard on 3 named + 2 restructured actions; scoped findOrFail first (no oracle); skipped when scoping disabled.
- U27 AUD:B5 attachment saving-hook rules (traversal-free relative paths, type/subtype mimes, max_size_bytes 10MB default, non-empty filename; allowed_disks/allowed_mimes opt-in).
- DUPs/info: AUD:B1/B3/B4/B6/Q#16/Q#17 merged into primaries; AUD:B7 GOOD note, no action. No FALSE/pre-FIXED lines.

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Communications — PASS (300 passed, 1321 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentCommunications — PASS (41 passed, consumer intact)
- ./vendor/bin/pint --test communications scope — PASS (207 files)
- ./vendor/bin/phpstan analyse packages/communications/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- canonical 000002 (thread unique channel+external_thread_id), 000007 (attempt unique delivery+number) edited directly; no corrective migrations

Covered findings: communications 35/35 (27 done incl. R1:#16 partial with documented test-conflict sub-clause; DUPs counted once).

Notes:
- Contract deltas (all zero-BC): CreateTrackingTokenAction::handle() return type + getToken() removal (no callers); DestinationProtector ciphertext format; PayloadRedactor::redactText + ProviderWebhookRegistrar::getAlgorithm()/getSignatureHeader() contract additions (additive; no external implementers per grep).
- NEW-010/011 fixed inline by worker; NEW-012 open (same-pattern unguarded IDs in 9 unnamed actions — follow-up unit recommended).

## PACKAGE COMPLETE — communications

Findings completed: 35 / 35

Package verification:
- ./vendor/bin/pest --parallel tests/src/Communications — PASS (300 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding:
- R1:#16 uniform-401 sub-clause: BLOCKED-test-conflict (WebhookTest pins 404), documented above
- NEW-012 open (follow-up unit recommended)

---

### [DONE] chip — all 22 (worker repair, 18 units, supervisor-verified)

- U1 R1:#1 mutation cache: postMutation caches only cancel/charge/release/mark_as_paid/resend_invoice/delete_recurring_token; refund()/capture() always POST live (charge stays cached per AdversaryRecurringChargeIdempotencyTest pin; R1:#1 names only refunds/captures).
- U2 R1:#2 webhook brand oracle: signature verified first (401, no owner touch), then single owner resolution; 500-on-owner-failure kept per existing tests (distinction now only for authenticated callers).
- U3 R1:#3 idempotency TTL: reserved_at + chip.cache.ttl.purchase_idempotency; expired stubs auto-released in find()/reserve(); pruneExpiredReservations()/countExpiredReservations() + chip:prune-idempotency-stubs (owner-batched, --limit, --dry-run); Purchase::scopeWithoutIdempotencyStubs on all 6 analytics queries.
- U4 R1:#4 sync fatal: handle() takes ?owner with owner/explicit-global wrap; exists() inside per-ID try; command --owner-type/--owner-id with fail-closed validation.
- U5 R1:#5+AUD:B5 webhook dedup: claimWebhookRecord with UNIQUE(idempotency_key) arbiter, 23000 backoff, failed-holder release + single re-claim; disabled paths still dispatch.
- U6 R1:#6 analytics: SQL aggregates for getHealth; chunked streaming for revenue/hourly trends; per-retry_count SQL cutoffs for retryables (portable).
- U7 R1:#7+AUD:B4 refund-state txn + lockForUpdate re-read; identical accumulation semantics.
- U8 R1:#8 Send webhooks: chip.owner.send_webhook_owner tuple (boot-validated); SendWebhookReceived dispatched inside it post-signature; fail-closed 500 when enabled-but-unresolvable; payload shape untouched (exact-equality test kept).
- U9 R1:#9 chip.verify-webhook route-middleware alias registered for hosts; package route keeps Spatie validator (existing test kept).
- U10 R1:#10 default middleware ['api','throttle:120,1']; public-key Cache::remember serialized behind lock when store is lock-capable.
- U11 R1:#11 explicit $fillable on all 7 models (owner tuple excluded; id/state kept where filament-chip create flows mass-assign API fields).
- U12 R1:#12 unix timestamps → bigInteger, money → unsignedBigInteger in 000001/000002/000003/000005/000009 (edited in place); Send-table int PKs + small ids left (API-mirrored, out of scope).
- U13 R1:#13 public-key fetch via extracted BaseHttpClient::performRequest/requestRaw (rate limits, retries, logging); fixed logResponse TypeError on non-array bodies (NEW-014).
- U14 R1:#14 rateLimitKey appends OwnerScopeKey when owner mode on. U15 R1:#15 trio: malformed dates → null; assertSafePathSegment in all 21 id-taking Collect methods; validateBankAccount mirroring instruction validation.
- U16 AUD:B1+AUD:Q#32 minor-unit math: ProductData::multiplyMinorUnits (half-up + numeric guard), getSubtotalInCents/getDiscountTotalInCents, single net rounding in getTotalPrice.
- U17 AUD:B2 paid handler persists total_minor/payment_method/updated_on with keep-current fallbacks; failed_at/refunded_at immutable casts; stale paid_on docblock removed (no such column).
- U18 AUD:B6 directory null-owner branch asserts context + applies forOwner/globalOnly explicitly.
- No FALSE/pre-FIXED lines; every premise verified by reading before fixing.
- Intake note: N1 commerce-support UNIQUE(name,event_id,event_type) lacks owner dimension (broke ChipWebhookOwnerResolutionTest:261 on clean tree too); chip works around via extractEventId() owner-qualified key. Root fix recorded as NEW-013 (open, supervisor-owned).

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Chip — PASS (1081 passed, 4 skipped, 2813 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentChip — PASS (17 passed, consumer intact)
- ./vendor/bin/pint --test chip scope — PASS (260 files)
- ./vendor/bin/phpstan analyse packages/chip/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- canonical 000001/000002/000003/000005/000009 edited in place (bigInteger/unsignedBigInteger); no corrective migrations; no FK/SoftDeletes

Covered findings: chip 22/22 (21 done + AUD:B3 BLOCKED-test-conflict; supervisor confirmed PurchaseStatusTest pins no non-API statuses).

Notes:
- cashier-chip consumer: findByChipCustomerId() null-branch now fails fast with clear message (behavior otherwise identical; explicit owners unaffected).

## PACKAGE COMPLETE — chip

Findings completed: 22 / 22

Package verification:
- ./vendor/bin/pest --parallel tests/src/Chip — PASS (1081 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding:
- AUD:B3: BLOCKED-test-conflict (both candidate fixes contradict pinned tests; field-level partial distinction preserved), documented above
- NEW-013 open (commerce-support webhook unique needs owner dimension or documented override contract)

---

### [DONE] csuite — all 8 (worker repair, 7 done + 1 blocked, supervisor-verified)

- R1:#1 orphaned bundle test: moved packages/csuite/tests/BundleTest.php → tests/src/Csuite/BundleTest.php (sole in-package tests dir; now collected by default suite with zero harness edits); in-package copy deleted.
- R1:#8 manifest reads: toBeFile guards before both file_get_contents calls (csuite's own + per-package).
- R1:#3 missing cashier plugins: added aiarmada/filament-cashier + aiarmada/filament-cashier-chip as self.version requires (both exist, both plugins implement Filament\Contracts\Plugin — supervisor confirmed), test plugin list, README/docs/usage/installation entries; composer validate passes.
- R1:#2 license link → ../../LICENSE (matches filament-affiliates convention; target exists).
- R1:#4 cart snippet regenerated from real config (snapshots/snapshot_items/snapshot_conditions, money/owner/limits; stale alert_rules/recovery_*/table_prefix gone; defaults verified half_up/1000/cart_snapshots).
- R1:#5 nav example uses bundled filament-cart/filament-vouchers/filament-docs keys + real CartResource; non-bundled references removed.
- R1:#6 "Everything"/"Full Suite"/"Install all Commerce" → curated-bundle wording (zero remnants by grep).
- R1:#7 BLOCKED-incorrect-premise: minimum-stability in a non-root package is inert at resolution (worker proved empirically with path-repo experiment + control); all 60+ sibling manifests carry the identical key, so a csuite-only edit would diverge with zero gain. No file change is the correct action.

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Csuite — PASS (1 passed, 115 assertions)
- ./vendor/bin/pint --test tests/src/Csuite — PASS (1 file)
- phpstan N/A (metapackage, no PHP src by design)
- composer validate packages/csuite/composer.json — valid (supervisor re-ran)

Migration/schema:
- none (metapackage; none demanded, none created)

Covered findings: csuite R1:#1..#8 (8/8; 7 done + R1:#7 blocked-incorrect-premise).

Notes:
- NEW-015 recorded below (worker-found doc-snippet drift cluster, incl. commerce-support wizard ownership for NEW-6).

## PACKAGE COMPLETE — csuite

Findings completed: 8 / 8

Package verification:
- ./vendor/bin/pest --parallel tests/src/Csuite — PASS (1 passed)
- pint --test — PASS

Outstanding:
- R1:#7: BLOCKED-incorrect-premise (documented above)
- NEW-015 open (doc-snippet drift cluster)

---

### [DONE] checkout — all 34 (two workers: predecessor 28 + retry 3, supervisor-verified)

Predecessor (killed by API quota; work verified by retry worker via diff review + green suite):
- R1:#1 Stripe evt id: prefer data.object.id, fall back to top-level (2 tests).
- R1:#2+AUD:B4/B9/Q#4 customer IDOR: assertCustomerInScope (exists + owner-match) + customerNotFound(); host-MUST for cart ownership documented (3 tests).
- R1:#3+AUD:B8/Q#2 mass assignment: fillable cut to 6 host-input fields; new persistState() (forceFill) for internals; 11 steps + services converted (2 tests).
- R1:#4 actor allowlist: checkout_actor.allowed_types + auth/customer always allowed + owner-consistency (4 tests; docs gap closed by retry worker).
- R1:#5 callback URL reads checkout.defaults.session_query_param. R1:#6 NormalizesCallbackAmounts trait (minorAmount/currency/callbackString) in both CHIP processors.
- R1:#7 repeat failure/cancel idempotent (early return on PaymentFailed; compensation de-duped per payment id via log check).
- R1:#8+AUD:B13 preloadPriceables: one whereIn per class + always-on owner validation.
- R1:#9 primary: retries append :attempt:{n} to idempotency key (log-noise clause blocked, see below).
- R1:#10 gateway checked against map for actual callback type from route segments. R1:#11 UUID gate pre-query, failed validations consume budget, key adds IP.
- R1:#12+AUD:B11 transitionStatus single path (Spatie transitionTo only + updating-hook timestamps); raw DB::table write deleted (rg zero hits).
- R1:#13 recordVerificationFailure persists status + error + structured log, stays retryable.
- R1:#14+AUD:B12 beginStep coalesces step-state + current_step into one write; no-op setStepState skips write.
- R1:#15 redirect informational; server-sourced redirect pinned by test.
- AUD:B2 finalizer uses transitionStatus() for both transitions.
- AUD:B3+AUD:Q#3 lockAndRefreshSession on all pipeline/callback/retry/cancel paths; callback txn flattened (1 txn test).
- AUD:B5 pricing join by item_id with positional fallback. AUD:B6 'unknown' gateway/txn fails closed (recordIncompletePaymentReference + abort confirm).
- AUD:B14 gateway I/O split out of txn (pre-payment txn → lock-free payment → re-lock + interference check with quiet result + orphan void; StepExecutor::untilStep; 3 tests).
- 15 existing suites updated in place for conversions; all green.

Retry worker (this turn):
- AUD:B1+AUD:Q#1 residual: UUID-shape guard (Str::isUuid) throws sessionNotFound before querying (0 queries proven via DB::listen); core "unscoped find" premise contradicted (OwnerScope fails closed; cross-owner resume already proven blocked by CheckoutOwnerScopingTest; owner-disabled is designed single-tenant + unguessable UUID PKs).
- R1:#4 docs gap: checkout_actor.allowed_types documented in 03-configuration.md.
- PHPStan gate: $fillable PHPDoc array<int,string> → list<string> (Eloquent covariance).

No-change with evidence:
- AUD:B11 MOOT-by-fix (raw update R1:#12 deleted; nothing left to document; contract in transitionStatus docblock).

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Checkout — PASS (306 passed, 1105 assertions)
- ./vendor/bin/pint --test checkout scope — PASS (151 files)
- ./vendor/bin/phpstan analyse packages/checkout/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- none touched (none needed)

Covered findings: checkout 34/34 (31 fixed + AUD:B11 moot + R1:#9-partial/R1:#16 blocked-test-conflict).

Notes:
- Follow-ups: cart-side owner-aware lookup for startCheckout($cartId) (host-MUST documented meanwhile); orders owns CheckoutDerivedKeyWarningTest if log-noise reduction is ever desired; product decision on max(price,compare) (R1:#16).

## PACKAGE COMPLETE — checkout

Findings completed: 34 / 34

Package verification:
- ./vendor/bin/pest --parallel tests/src/Checkout — PASS (306 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding:
- R1:#16: RESOLVED in Q7 revisit — user chose SALE PRICE WINS.
  basePriceForProduct (max) deleted; product price is now the offer
  priceAmount literally (compare stays compare). Pin rewritten
  (9700/12700). Checkout suite green (306).
- R1:#9 log-noise clause: BLOCKED-test-conflict (orders-side test pins per-build warning; out of checkout scope), supervisor-confirmed in test file

---

### [DONE] customers — all 19 (worker repair, supervisor-verified)

- R1:#1 session-customer IDOR: ResolvesCustomerIdentity gained scopedSessionCustomer()/customerIsInCurrentOwnerScope(); both resolver entries revalidate session records vs resolved owner (global-only when none; include_global honored); mismatched/unpersisted treated as absent (2 tests).
- R1:#3 relation OwnerScope bypass: relation results verified in-scope; mismatches fall through to scope-filtered user_id query.
- R1:#4 duplicate profiles: canonical unique(owner_type,owner_id,user_id); getOrCreateCustomerProfile catches UniqueConstraintViolationException, refreshes, returns winner (or ValidationException if unreadable); NULL-user guests unaffected (index test + race tests).
- R1:#5 email TOCTOU: UniqueConstraintViolationException rethrown as ValidationException('email taken') (simulated-race test).
- R1:#6 segment semantics unified: missing/empty field, null value, unknown field, empty sets match nothing in BOTH paths; value_status supported both; in-memory requires active like query path; enum statuses handled (mechanism partly differed from writeup; verified by reading).
- R1:#7+AUD:B5/B6 segment perf: 3 SQL aggregates for stats; matchingCustomersQuery()/countMatchingCustomers()/matchingCustomerIds() (keyset-lazy); rebuilds chunk IDs by 1000 with 1000-chunked event targets (per-customer event semantics kept, tested); dry-run counts.
- R1:#8 sargable email lookups: exact normalized_value = ? match (canonically lowercased by contacting) with legacy fallback only for null/empty normalized rows; SQL-shape asserted in tests.
- R1:#9 fillable (partial): lifecycle timestamps + metadata removed; optIn/optOut via forceFill; user_id/status/is_guest/accepts_marketing/created_at/updated_at remainder BLOCKED-test-conflict (mass-assignment pinned across suite; supervisor confirmed HasCustomerProfileTest user_id pins).
- R1:#10 documents: mime whitelist (pdf/doc/docx/xls/xlsx/csv/txt/jpeg/png/webp) + 10MB acceptsFile cap (SVG/HTML out; config-tested).
- R1:#11 merge/address: same-owner-tuple check on SetDefaultCustomerAddress (InvalidArgumentException); merge verifies every source note's tuple pre-move; group merge preserves role/joined_at via keyed syncWithoutDetaching.
- R1:#12 checkout bloat: phone/mobile/whatsapp dedupe by contacting-normalized value per customer (returns existing); address match case-insensitive on line/city/postcode/state. Repeat identical checkouts create zero new rows. Destructive per-type cap / update-in-place explicitly declined (would silently delete legitimate changed-address history).
- R1:#13 slug race: Segment::save converts unique-violation to ValidationException when app check finds the duplicate (simulated-race test); collision premise incorrect (owner_scope is per-tuple sha256; same truth both layers); no migration change (would break tested cross-owner same-slug).
- R1:#14 (partial): stale no-orders-delete comment removed (finding's allowed alternative); accepts_marketing default true→false in $attributes + canonical migration; customers_created_at_index added (user_id covered by R1#4 composite; owner/status present). Policy restriction BLOCKED-test-conflict (PoliciesTest + 3 isolation/policy suites pin allow-for-authenticated; supervisor confirmed).
- AUD:B2 deactivated_at cleared on re-activate + stamped on deactivate, Segment + CustomerGroup twin. AUD:B3 Customer::deleting deletes contact methods/social profiles (model deletes, events fire) + media rows (files removed). AUD:B4 person link moved inside txn in CreateCustomer + UpdateCustomerProfile (link failure rolls back; bogus-ID tests).
- No-change with evidence: R1:#2 FALSE per audit verdict (persons-global by design; customer side guarded); AUD:B7+AUD:Q#6 FIXED pre-existing (composites present + MigrationIndexesTest-asserted).

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Customers — PASS (272 passed, 518 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentCustomers — PASS (28 passed, consumer intact despite marketing/segment/documents deltas)
- ./vendor/bin/pint --test customers scope — PASS (80 files)
- ./vendor/bin/phpstan analyse packages/customers/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- canonical 000001 edited directly (owner/user unique, accepts_marketing default false, created_at index); no follow-up migrations; no FK; uuid PKs preserved

Covered findings: customers 19/19 (17 done + R1:#9-partial/R1:#14-partial blocked-test-conflict remainders).

Notes:
- Contract deltas for filament-customers pass: marketing opt-in default false; empty/unknown segment conditions match nothing; documents restricted; user_id unique per owner (UniqueConstraintViolationException on dupes). Filament suite still green (28 passed).
- checkout consumer note: CustomerResolver now ignores foreign/unpersisted session customers — checkout must pass session owner explicitly or resolution returns null.
- NEW-016/017 fixed inline by worker (recorded below).

## PACKAGE COMPLETE — customers

Findings completed: 19 / 19

Package verification:
- ./vendor/bin/pest --parallel tests/src/Customers — PASS (272 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding:
- R1:#9 remainder: BLOCKED-test-conflict (fillable pins across suite), documented above
- R1:#14 policy restriction: BLOCKED-test-conflict (allow-for-authenticated pinned), documented above

---

### [DONE] docs — all 29 (worker repair, 25 units, supervisor-verified)

- U1 R1:#1 per-owner numbers: unique(owner_type,owner_id,doc_number) in canonical migration; create()/createFromType() scope-checked pre-check + 23000→InvalidArgumentException.
- U2 R1:#2 pdf_path out of fillable; storePdf forceFill; update allowlist excludes it (2 existing unit setups adjusted to forceFill, same downloadPdf behavior).
- U3 R1:#3 DocTypeKey::sanitize (enum or [A-Za-z0-9_-], else global defaults) in DocService/DocRenderService/SequenceManager/DefaultNumberStrategy/DocumentNumberRegistry.
- U4 R1:#4+AUD:B4 embeds: absolute URLs must pass PublicHttpUrlGuard (public IPs only); relative unchanged.
- U5 R1:#5 SendDocEmailJob (marks Sent/Failed + failed_at, idempotent, 3 tries) on configured queue.
- U6 R1:#6 unique(doc_type,name,owner_type,owner_id) + Cache::lock first-use + 23000-reselect in manager/model.
- U7 R1:#7+AUD:B1 create() numbers via SequenceManager (per-owner atomic); DefaultNumberStrategy suffix random_bytes hex, dot-free, strategies kept for generateNumber() only.
- U8 R1:#8 update(): OwnerWriteGuard re-check, 16-key allowlist (number/type/pdf/timestamps/totals immutable + silently ignored for filament EditDoc compat), status via transitionStatusTo, totals re-derived, template_slug slug-wins + persisted.
- U9 R1:#9 no-items path validates int/non-negative, rejects major-unit aliases; explicit totals contradicting items-derived rejected; ISO-3 currency both paths.
- U10 R1:#10 restore(?summary) guards owner, applies via update(), relabels version. U11 R1:#11 balance sums paid-only; method allowlisted; Paid/paid_at server-set; typed reference passthrough only.
- U12 R1:#12 resolveStateClassFor throws on unknown. U13 R1:#13 dueSoon/overdue limit 500 + orderBy + (status,due_date) index; job memoizes templates.
- U14 R1:#14 DocMail reuses existing pdf_path file; renders on miss. U15 R1:#15 CC string/array, valid ≤320, dedupe, cap 5, log+drop invalid.
- U16 R1:#16 assertValidRecipient (RFC, ≤320/255); reminder job skips invalid with warning. U17 R1:#17 throttle:60,1 on track+share; issued_at tracking TTL (docs.email.tracking.ttl_days, default 180).
- U18 R1:#18 deleteStoredPdf best-effort from deleting. U19 R1:#19 createVersion txn + lockForUpdate + one 23000 retry.
- U20 R1:#20 preview mirrors download OwnerWriteGuard (bound + string). U21 R1:#21 Tiptap renderer MAX_DEPTH=32/MAX_NODES=2000, per-render counters.
- U22 R1:#22 getActiveSequence(...,$forUpdate=true); preview passes false. U23 R1:#23 DocData::from + create reject unknown types; update type immutable.
- U24 R1:#24 create fully transacted + Initial creation version. U25 AUD:B3 PDF options allowlisted/clamped.
- Docs updated: 03-configuration, 04-usage (+Updating Documents/restore), 06-status-management, 99-troubleshooting.
- AUD:Q#11 no-action (tax-scoped §8 row, explicitly out of docs).
- Intake fixes (supervisor, cross-package fallout found via consumer re-run): (1) chip U11's ChipCustomerLink $fillable activated dormant LogsCommerceActivity inserts, breaking CashierChip's isolated TestCase (no activity_log) — added the spatie-v5-shaped table to CashierChipTestCase::defineDatabaseMigrations; (2) SetupPurchaseTest pinned the pre-fix bare retry key — updated to checkout's audited `:attempt:{n}` contract + added first-attempt bare-key case. CashierChip suite green again (579).

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Docs — PASS (212 passed, 575 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentDocs — PASS (63 passed, consumer intact)
- ./vendor/bin/pest --parallel tests/src/CashierChip — PASS (579 passed after intake fixes)
- ./vendor/bin/pest --parallel tests/src/Cashier — PASS (294 passed, chip-adjacent sweep)
- ./vendor/bin/pint --test docs scope — PASS (99 files)
- ./vendor/bin/phpstan analyse packages/docs/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- canonical 000002 edited (owner-scoped doc_number unique, (status,due_date) index); 000003 edited (sequence unique); no corrective migrations

Covered findings: docs 29/29 (27 done + 2 DUPs merged + AUD:B2 blocked-design-conflict + AUD:Q#11 no-action).

Notes:
- Behavior deltas for filament-docs pass: update() immutables ignored, paid_at backdating gone, restore signature-compatible, CC server-filtered.
- NEW-018 recorded below (worker notes trio).

## PACKAGE COMPLETE — docs

Findings completed: 29 / 29

Package verification:
- ./vendor/bin/pest --parallel tests/src/Docs — PASS (212 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding:
- AUD:B2: BLOCKED-design-conflict (DocDownloadController returns BinaryFileResponse|StreamedResponse; enqueue+202 breaks all download callers; mitigated via U14 reuse + pre-generation), supervisor-confirmed signature
- NEW-018 open (worker notes trio)

---

### [DONE] engagement — all 24 (worker repair, 19 units, supervisor-verified)

- U1 R1:#1 share token: generated once in share() (Str::random(32), explicit token honored), passed to generator + stored (round-trip fixed).
- U2 R1:#2+R1:#13 reconcile: per-owner via OwnerBatchRunner inside withOwner(null) (send/match pattern); full scan ordered distinct()->cursor().
- U3 R1:#3 offset reminders: remind_at always resolved at creation (explicit / anchor−offset / now()+1d fallback); conflicts/unresolvable throw; transacted.
- U4 R1:#4+AUD:B5 races: criteria_hash (sha256, saving hook) + owner-aware subscription unique; (collection,bookmark) unique; txn + lockForUpdate + 23000-rescue; re-add clears removed_at (NEW-019).
- U5 R1:#5 notification_class: engagement.notifications.allowed allowlist (default reminder notification); existence + subclass + allowlist at setReminder; out of fillable; dispatch listener re-checks fail-closed.
- U6 R1:#6 per-reminder try/catch → markFailed + continue. U7 R1:#7 cancelled→reactivate-as-created; same-type idempotent; different-type Changed.
- U8 R1:#8 MySQL NULL-unique gap: audit-sanctioned documented limitation (keep rows owner-assigned), docs-only.
- U9 R1:#9 canShare() on EngagementPolicyResolver (default true), enforced in share(); PolicyEnforcementTest updated (breaking contract, zero-BC).
- U10 R1:#10 criteria_hash-indexed candidate narrowing + PHP collision guard. U11 R1:#11+AUD:B8 atomic ±1 listener adjustments (locked, clamped, 23000-rescue; sync semantics kept); recalculate() GROUP BY aggregates.
- U12 R1:#12 dead limit() before chunkById removed. U13 R1:#15 eager bounded collections (orderBy id, limit engagement.state.result_limit=100); stateFor fixed transitively.
- U14 R1:#16 hot-path composites in canonical migrations (reminders/subscriptions/reactions/responses). U15 R1:#17 collection delete chunked in txn.
- U16 R1:#18 EngagementModelGuard (bounded/required strings, arrays, offsets) at every manager entry; share URLs rawurlencoded.
- U17 R1:#19 match context whitelisted (subject_type/id) + HasSubscriptionMatchContext opt-in seam.
- U18 AUD:B4 identical pending reminders dedupe under locked txn (subscriptions via U4/U10 unique).
- U19 AUD:B6 verified by design: generic actor/subject owner-equality unenforceable; row-level isolation proven by isolation tests; default resolver documented as host seam (no code change).
- FIXED-confirmed: AUD:B1/Q#30/Q#2 uniques+locks; AUD:B2/Q#FF2 double-send guards; AUD:B3 share_token unique. No FALSE verdicts in section.
- TDD: RepairRegressionTest (24 tests) run pre-fix — 21 failed with predicted errors, 3 preserved-behavior guards passed.

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Engagement — PASS (73 passed, 260 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentEngagement — PASS (5 passed, consumer intact)
- ./vendor/bin/pint --test engagement scope — PASS (141 files)
- ./vendor/bin/phpstan analyse packages/engagement/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- canonical 000004–000008 edited in place (criteria_hash + uniques + composites); no corrective migrations; no FK; uuid PKs

Covered findings: engagement 24/24 (22 done incl. U8/U19 verification outcomes + R1:#14/AUD:B7 blocked).

Notes:
- Contract deltas for hosts: canShare() required on custom resolvers; anchorless offsets / remind_at+offset / unlisted notification classes now throw; attribute-criteria matching needs HasSubscriptionMatchContext (all documented).
- filament-engagement follow-up: ReminderResource morph-identity TextInputs (R1:#14 remainder) for the filament pass.
- NEW-019/020 fixed inline by worker (recorded below).

## PACKAGE COMPLETE — engagement

Findings completed: 24 / 24

Package verification:
- ./vendor/bin/pest --parallel tests/src/Engagement — PASS (73 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding:
- R1:#14 fillable narrowing: BLOCKED-test-conflict (morph+status mass-assignment pinned; notification_class slice fixed via U5), supervisor-confirmed in test files
- AUD:B7 forOwner preference: BLOCKED-by-design (.ai/multitenancy mandates ambient OwnerScope default; deferred-execution aspect fixed via U13)

---

### [DONE] filament-addressing — all 17 actionable (worker repair, 11 units, supervisor-verified)

Note: supervisor's spawn prompt wrongly said 31; the audit section has 18 lines (17 actionable + 1 FALSE), matching the table row. Worker read the actual section; all 18 addressed.

- R1:#1 CreateAddress resolves AddressResource::getModel() (LogicException unless instanceof Address).
- R1:#2 AddressExporter getModel() from config + modifyQuery OwnerUiScope (matches resource query).
- R1:#3 new PostalCodeImporter (delegates to core ImportPostalCodesAction via ArrayPostalCodeSource) + PostalCodeExporter (config model), wired into ListPostalCodes; features.postal_code_import default added.
- R1:#4 AddressingFilterOptions (300s cached lists keyed per model) in area + country tables; invalidated on create/edit/delete/import.
- R1:#5+AUD:B4 eager loads via modifyQueryUsing on all 5 tables. R1:#6+AUD:B5 state options [] when country blank + partial-select country loads.
- R1:#7 addcslashes LIKE escaping (mirrors core). R1:#8 canAccess() read-only guard on both postcode pages.
- R1:#9 deleted GuardsAddressingUi/ResolvesAddressingResources (zero references, supervisor re-grepped) + no-op override; AddressesRelationManager SCOPED not deleted (documented host API): recordSelectOptionsQuery + OwnerUiScope, Edit/Detach visible()+before() 403.
- R1:#10 minLength(2)+alpha() on iso2, same on iso3 (signatures verified on installed Filament).
- AUD:B1 areas/postcodes/countries confirmed global-by-design (no owner cols); postcode areas filter +is_active.
- R1:#11 ALREADY-FIXED (43 baseline tests exist); AUD:B2 pass confirmed by triple-grep; AUD:B3 FALSE (no badge); AUD:B6/B7 no-action (greps empty).
- Docs same pass: 03-configuration, 04-usage, 99-troubleshooting, CONTEXT.md. NEW-021/022 fixed inline (recorded below).

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/FilamentAddressing — PASS (55 passed, 113 assertions)
- ./vendor/bin/pint --test filament-addressing scope — PASS (66 files)
- ./vendor/bin/phpstan analyse packages/filament-addressing/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- none (adapter has no database dir; core addressing untouched)

Covered findings: filament-addressing 17/17 actionable (+1 FALSE verified).

Notes: no cross-package follow-ups; FilamentCustomers' own AddressesRelationManager unaffected.

## PACKAGE COMPLETE — filament-addressing

Findings completed: 17 / 17

Package verification:
- ./vendor/bin/pest --parallel tests/src/FilamentAddressing — PASS (55 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding: none

---

### [DONE] events — all 30 (worker repair, 20 units, supervisor-verified)

- U1 R1#1 classification race: txn + EventWriteGuard, preloaded taxonomies/terms, unique(event_taxonomy_id,code), 23000-rescue, explicit-global vocab writes.
- U2 R1#3 promote/agent races: txn + LockEventRegistrationScopeAction; promote re-checks under lock; agent registers→ships→passes atomically (paid-capacity default false kept deliberately).
- U3 R1#4/AUD:B3/B4 registration validation: scope linkage, ticket/quantity/price, participant email/age/morph, registrant/parent morphs, forged-id stripping, answer links, 1000-cap collections; createFromOrderItem currency default (R1#14).
- U4 R1#5 status via transitionTo() both update actions (no-op same, throws unknown/illegal). U5 R1#6 eligibility: event blocked-list (draft registers — tested), occurrence allowlist, new session allowlist key + config.
- U6 R1#7 check-in validation (morph both-or-neither + exists, ≤64/5000 caps, UUID verifier, array metadata; 'user' alias kept per test).
- U7 R1#8 indexed idempotency_key column + limit(count+1). U8 R1#9/AUD:B9 finders default 100/max 500, search 25/100, chunkById dispatch/finalize.
- U9 R1#10 command context flags (--owner-type/--owner-id/--global) + chunkById + provider registration (was unregistered — NEW-024).
- U10 R1#11 four new policies (occurrence/session/registration/submission, event-owner-derived, public viewable); Venue excluded (global vocab).
- U11 R1#12 clone via write guards, unknown-relation throws, txn, chunkById. U12 R1#13/AUD:B1 fillable hardened (owner/created_by/timestamps out; forceFill writers; status kept for NOT NULL creation; EventOrganizer already clean).
- U14 R1#15c findBySlug earliest-first + documented. U15 R1#16 occurrence/session status composites + int zero-checks.
- U16 R1#17 missing-role Log::warning (not throw, post-create listener). U17 R1#18 deleted RegisterInput/ParticipantInput/CheckInInput (zero refs); remove() cascade documented (narrowing blocked by test).
- U18 AUD:B2 EventDeleteCascade (35 models + 4 morphs + ticketing/seats, chunked per-model deletes in txns) on 8 deleting hooks.
- U19 AUD:B5 null-matrix verified (ANDed whereHasMorph; regression test, no code change). U20 AUD:B6 indexer verified (id-keyed deletes; ACL comment + test).
- N2 stale capacity config list fixed while editing. Docs: 03-configuration, 04-usage, 05-taxonomy-hierarchy.

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Events — PASS (280 passed, 1250 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentEvents — PASS (18 passed, new policies safe)
- ./vendor/bin/pint --test events scope — PASS (565 files)
- ./vendor/bin/phpstan analyse packages/events/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- canonical 000029 (term unique), 000014 (idempotency_key) + occurrence/session composites edited in place; no corrective migrations

Covered findings: events 30/30 (20 units + 7 blocked-test-conflict/by-design variants + AUD:Q4 unverified-ignored).

Notes:
- Resolves the pre-existing AssignmentRequestActionsTest failure flagged at contacting intake: root cause proven = authz Role::create overwrites explicit team_id with ambient scope. Events tests adapted in-scope (direct query planting); authz-design question recorded as NEW-023 (open).
- Checkout fillable hardening drops order_id/cart_snapshot in 5 events step fixtures — adapted in-scope with unguarded helper (no src change; checkout behavior is the audited one).
- filament-events follow-ups: new policies auto-check (update/delete/closed-view 403 unless event manager); Venue/taxonomy writes + AUD:B7 null-matrix remain theirs.
- NEW-024/025 recorded below (worker-found; N1 fixed, N2 fixed).

## PACKAGE COMPLETE — events

Findings completed: 30 / 30

Package verification:
- ./vendor/bin/pest --parallel tests/src/Events — PASS (280 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding:
- R1#15b/R1#2/R1#6-allowlist/R1#15a/R1#18-narrowing/check-in-alias: BLOCKED-test-conflict variants (documented per-unit above), walk-in pin supervisor-confirmed
- AUD:B7: out-of-scope → filament-events follow-up F5
- NEW-023 open (authz team_id question)

---

### [DONE] feedback — all 30 (worker repair, 27 units, supervisor-verified)

- R1:#1 duplicate copies section-less questions (shared copyQuestion helper). R1:#2 trait delegates to CreateFeedbackFormFromTemplateAction (subject forced to $this).
- R1:#3 per-question NPS/CSAT aggregate FeedbackAnswer scores (key+form constrained); trait averageFeedbackScore likewise.
- R1:#4 OwnerWriteGuard-first on all 10 lifecycle actions (10 cross-owner throws tested). R1:#5 one-response checks match submitted+reviewed (rejected/spam resubmittable, documented).
- R1:#6 max_score sums per-question ScoreCalculator maxima (owner-scoped). R1:#7 completed=submitted+reviewed, pending=submitted-only (both calculators).
- R1:#8 Rule::in choice allowlists + membership closures (scalar AND array matrix accepted — tested scalar shape kept); disabled types always-fail; array-safe normalizer.
- R1:#9 Send marks Sent+sent_at, dispatches FeedbackInvitationSent. R1:#10 respondent allowlist config + FeedbackModelReferenceGuard::resolveRespondent in Start.
- R1:#11 saveQuestion rejects unknown/disabled types + dup keys; (form,key) index→unique in canonical migration.
- R1:#12+AUD:B4 chunked deleting hooks; DeleteFeedbackFormAction whereHas-scoped + chunked (no plucks); question testimonial null-out via whereHas.
- R1:#13+AUD:B8 preloaded ScoreCalculator options; calculateLive 1 aggregate; recalc job ShouldBeUnique per form.
- R1:#14+AUD:B3 shared FeedbackSubmissionGuard (form accepting + invitation valid) in Start and Submit.
- R1:#15 testimonial extraction guarded (OwnerWriteGuard, text-only, strip_tags, 2–2000 chars, null-safe). R1:#16 table_prefix in all 10 canonical migrations (no down() per .ai/database).
- R1:#17 metadata Start→Submit→response. R1:#18 IP bucket alongside token bucket. R1:#19 driver-aware trend dates.
- R1:#20 nps()/csat() return calculate() results (zero in-repo callers; zero-BC). R1:#21 response + slug indexes (slug non-unique, owner-scoped).
- R1:#22 numeric/date coercion guards. R1:#23 IP mb_substr 255. R1:#24 app-side invitation route documented with example.
- AUD:B2 expiry persisted in fresh guarded txn outside the failing one (FeedbackInvitationExpiredException) + rethrow.
- AUD:B5 feedback:prune-expired-invitations (OwnerBatchRunner, chunkById, --dry-run) registered. AUD:B7 token_hash $hidden.
- AUD:B1/Q1/Q2 FIXED-confirmed (race/rescue tests green). AUD:B6 UNVERIFIED out of scope (needs app HTTP test; no package routes).
- Docs same pass: 03-configuration, 04-usage. NEW-026/027 fixed inline (recorded below).

Verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/Feedback — PASS (88 passed, 265 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentFeedback — PASS (8 passed, consumer intact)
- ./vendor/bin/pint --test feedback scope — PASS (131 files)
- ./vendor/bin/phpstan analyse packages/feedback/src --level=6 --no-progress --debug — PASS, no errors

Migration/schema:
- canonical 000003 ((form,key) unique), 000001/000005 (indexes), all 10 (table_prefix) edited in place; no corrective migrations

Covered findings: feedback 30/30 (24 R1 + 6 AUD incl. 3 DUPs; zero blocked).

Notes: no cross-package follow-ups except optional ops-docs mention of the prune command.

## PACKAGE COMPLETE — feedback

Findings completed: 30 / 30

Package verification:
- ./vendor/bin/pest --parallel tests/src/Feedback — PASS (88 passed)
- pint --test — PASS
- phpstan level 6 --debug — PASS

Outstanding: none

---

## PACKAGE COMPLETE — filament-cashier-chip

Findings completed: 30 / 30 (29 done + R1#2 invoice-half BLOCKED-test-conflict; 4 DUPs folded, 2 PASS adopted)

Covered findings: R1#1..#23 + AUD:B1..B7. R1#2 customer-half DONE (unscoped base query for billables without
ownerScopeConfig); invoice-half BLOCKED-test-conflict (CrossTenantIsolationTest.php:100 pins scoping even when
chip.owner.enabled=false; supervisor-confirmed). Worker also proved the verifier's "no such key" dismissal wrong
(chip.owner exists at chip/config/chip.php:80 + ChipModel::$ownerScopeConfigKey) — documented, behavior unchanged
per tested-behavior rule. R1#1 bulk resume uses unpause() not resume() (resume() throws off grace period,
Subscription.php:826). R1#15 premise contradicted (domain invoices() already caps 25) — hardened with explicit
configurable cap anyway. NEW-028 fixed (null-unsafe Discount visible closure).

Package verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/FilamentCashierChip — PASS (125 passed, 327 assertions)
- pint --test packages/filament-cashier-chip tests/src/FilamentCashierChip — PASS (59 files)
- phpstan analyse packages/filament-cashier-chip/src --level=6 — PASS, no errors
- Pinning-test + chip.owner key existence supervisor-confirmed via grep

Outstanding: R1#2 invoice-half (test-conflict escalation); cross-package follow-ups for cashier-chip core
(MRR/normalizeToMonthly absorption, item bounds, widget-predicate indexes — advisory, cashier-chip already COMPLETE)

---

## PACKAGE COMPLETE — filament-authz

Findings completed: 22 / 22 (16 fixed + R1#4b fixed + R1#4a BLOCKED-test-conflict + 5 verified-pass)

Covered findings: 15 R1 CONFIRMED + 7 AUD ADOPTED. R1#4a BLOCKED-test-conflict (CommandsTest.php:86 pins
guard-keyed formatRolesArray; supervisor-confirmed). Verified-pass: AUD#B1 (incorrect premise — Spatie-teams
contract + ImpersonationScopeGuard), B3 (no COUNT badge exists), B4 (Filament v5 auto-eager-loads; verified in
vendor 5.8.0), B5 (relationship+modifyQueryUsing GOOD), B6 (keys via PermissionKeyBuilder). No migrations
(adapter has none; none needed). NEW-029/030/031 fixed; NEW-032 open (case-default inconsistency). Residual note:
policy-generator hostile *action* segments unescaped (info only — discovery actions are fixed identifiers).
Pre-existing ImpersonateControllerTest failure adapted test-only (super-admin role for repaired core take()).

Package verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/FilamentAuthz — PASS (176 passed, 352 assertions)
- pint --test packages/filament-authz tests/src/FilamentAuthz — PASS (62 files)
- phpstan analyse packages/filament-authz/src --level=6 --debug — PASS, no errors (plain run sandbox TCP-blocked)
- R1#4a pinning test supervisor-confirmed via grep

Outstanding: R1#4a (test-conflict escalation); NEW-032 open; per-panel tenant scoping needs authz-core support (advisory)

---

## PACKAGE COMPLETE — filament-affiliate-network

Findings completed: 21 / 21 (20 done + R2:M1 BLOCKED-test-conflict; B5 DUP folded, B3/B4 FALSE verified)

Covered findings: 16 R2 + 7 AUD. R2:M1 BLOCKED-test-conflict (MerchantDashboardPageTest pins merchant scoping +
docs/04-usage.md + docs/05-pages-widgets.md agree; supervisor-confirmed all three). Only behavior-neutral L3
memoization applied to that page. M6 keeps tested behavior for hosts without verification concept (documented
residual). NEW-033 (core applicationStatusForOffer TypeError) fixed by supervisor with red/green proof (see below).

Package verification (supervisor re-ran, post-stash-restore):
- ./vendor/bin/pest --parallel tests/src/FilamentAffiliateNetwork — PASS (86 passed, 180 assertions)
- pint --test — PASS (part of 415-file merged gate)
- phpstan analyse packages/filament-affiliate-network/src --level=6 --debug — PASS, no errors

Outstanding: R2:M1 (human intent decision: merchant-scoped vs network-global dashboard); M6 residual (bind identity
to user id — needs affiliates schema); core admin-bypass solved adapter-side, no core change needed

---

## PACKAGE COMPLETE — filament-cart

Findings completed: 24 / 24 (18 units, 0 blocked; B3/B4/B5 DUPs folded, B1 folded, B2/B6 PASS re-verified)

Covered findings: 17 R1 + 7 AUD. U17 gates dashboard pages via optional monitoring_permission (default preserves
behavior). Supervisor-resolved cross-unit fallout: (1) cart-core R1:#20 user() removal is audit-mandated —
stale CartTest assertion replaced with a removal-pinning test; (2) cart U25 dirty-gate regression (stale child rows
survived clean-header syncs) fixed in NormalizedCartSynchronizer via orphan-row existence checks (NEW-035),
proven by the previously-red filcart test now green + cart suite still 1070 green. NEW-034 fixed (deprecated
actions() call). Residual: per-action RBAC policies need a product decision (broader gap, documented by worker).

Package verification (supervisor re-ran, post-stash-restore + fallout fixes):
- ./vendor/bin/pest --parallel tests/src/FilamentCart — PASS (202 passed, 668 assertions)
- ./vendor/bin/pest --parallel tests/src/Cart — PASS (1070 passed, 2 skipped; no regression)
- pint --test — PASS; phpstan level 6 --debug on filament-cart + cart — PASS, no errors

Outstanding: per-action RBAC product decision (advisory); CartItemsTable dead comment blocks left in place (harmless)

---

## PACKAGE COMPLETE — filament-affiliates

Findings completed: 22 / 22 (15 R1 + 7 AUD; R1#2 incorrect-premise, R1#12-partial test-conflict, AUD:B6 out-of-scope)

Covered findings: R1#1,#3-#11,#13-#15 done; R1#12 atomicity done; AUD:B1/B3/B4/B5/B7 done; AUD:B2 ALREADY-fixed.
R1#2 BLOCKED-incorrect-premise (ScopesByAffiliateOwner global scope on AffiliateFraudSignal + green
OwnerScopingRegressionTest asserting ModelNotFoundException; both supervisor-confirmed). R1#12 transition-guard
half RESOLVED in Q11 revisit — user chose STRICT STEP-BY-STEP:
updateStatus() now routes through status->transitionTo() (illegal jumps
throw TransitionNotFound), action visibility uses canTransitionTo(),
and the reset-to-pending action is removed (no state may enter
Pending). Pins rewritten (refusals throw; legal path
Pending→Approved→Paid verified). FilamentAffiliates 334 green. AUD:B6 out-of-scope (tree math move needs affiliates package;
adapter mitigated with clamps/limits/SQL aggregate). N1 fixture repair (10 pre-existing failures from core
fillable hardening) done test-only. N3 premise contradicted by final core code (UpdatePayoutStatus DOES release
funds idempotently via funds_released_at guard) — redundant adapter call removed by supervisor, suite still green.

Package verification (supervisor re-ran, post-stash-merge + conflict resolution):
- ./vendor/bin/pest --parallel tests/src/FilamentAffiliates — PASS (334 passed, 933 assertions)
- pint --test — PASS (merge-introduced import order fixed)
- phpstan analyse packages/filament-affiliates/src --level=6 --debug — PASS, no errors

Outstanding: R1#12 canonical-transition decision (cross-package, needs affiliates); AUD:B6 core move + partial
unique index for default payout method (advisory follow-ups for affiliates, already COMPLETE)

---

## PACKAGE COMPLETE — filament-communications

Findings completed: 16 / 16 (9 R1 code fixes + 7 AUD verify-only; 0 blocked)

Covered findings: R1#1-#9 done; AUD:B1 info verified (7 OwnerUiScope uses), B2 nav PASS, B3/B4/B5 N/A with
evidence, B6 N/A (vouchers-only citation), B7 instance fixed under R1#8. R1#5 adds per-resource nav-sort offsets
with defaults preserved per pinned test (documented partial). R1#6 adapter-side filter-options helper (domain-enum
half needs frozen communications). R1#7 fixed via documentation per the finding's own "document OR gate"
recommendation. NEW-036 found by worker (vacuous not->toThrow); supervisor refined + fixed repo-wide (see below).

Package verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/FilamentCommunications — PASS (59 passed, 109 assertions)
- pint --test packages/filament-communications tests/src/FilamentCommunications — PASS (32 files)
- phpstan analyse packages/filament-communications/src --level=6 --debug — PASS, no errors

Outstanding: R1#6 domain-enum + R1#7 role-layer follow-ups for communications core (advisory, already COMPLETE)

---

## PACKAGE COMPLETE — filament-docs

Findings completed: 23 / 23 (16 R2 fixed + B1/B2 info verified + B3 DUP via L1 + B4/B5 residuals fixed + B6/B7 pass; 0 blocked)

Covered findings: C1 (per-action abilities via DocPermissions), H1 (dedicated doc.* namespaces; breaking, documented),
H2 (transitionable-states edit form; domain half already fixed in docs core, verified), H3 (payments via recorder
seams; deletes removed — no domain reversal exists), H4 (owner-scoped uniques), M1-M6, L1-L5, all 7 AUD disposed.
Supervisor: fixed pre-existing Pint failure in untouched RecentDocumentsWidget (trivial); fixed NEW-037 (docs-core
owner_scope sync) with red/green proof (see below). NEW-038/039 recorded OPEN.

Package verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/FilamentDocs — PASS (100 passed, 379 assertions)
- pint --test packages/filament-docs tests/src/FilamentDocs — PASS (79 files, incl. supervisor style fix)
- phpstan analyse packages/filament-docs/src --level=6 --debug — PASS, no errors

Outstanding: NEW-038/039 OPEN; docs-core follow-ups (payment void/refund domain method, paid_at decision);
host apps must grant new document* abilities (breaking, documented in 04-usage + 99-troubleshooting)

---

## PACKAGE COMPLETE — filament-customers

Findings completed: 24 / 24 (14 fixed incl. #17 tests + #4 already-fixed + B1/B2/B6/B7 verified + B3/B5 DUPs; #7 + #10-partial BLOCKED)

Covered findings: R1#1-3,#5,#6,#8,#9,#11-17 done; AUD:B4 done. R1#7 BLOCKED-test-conflict
(AddressValidationPageTest pins guest success for in-scope addresses, no actingAs; supervisor-confirmed).
R1#10-partial BLOCKED-test-conflict (PageAccessGuardsTest:60/:78 pin AuthorizationException throws;
supervisor-confirmed; merge()-internal catch was still implemented). NEW-036 5th site applied at intake
(SegmentRebuildAuthorizationTest was worker-unmodified). Test-harness gotcha documented in CONTRIBUTING.md.

Package verification (supervisor re-ran, post NEW-036 site-5):
- ./vendor/bin/pest --parallel tests/src/FilamentCustomers — PASS (54 passed, 135 assertions)
- pint --test — PASS (48 files); phpstan level 6 --debug — PASS, no errors

Outstanding: #7/#10 test-contract unblocks (parent decision); customers-core follow-ups (domain exception from
MergeCustomers, rebuildAny ability — advisory, already COMPLETE)

---

## PACKAGE COMPLETE — filament-contacting

Findings completed: 22 / 22 (15 R1 code fixes + B1 meta/B2 PASS/B3-B7 N/A verified; 0 blocked)

Covered findings: #1 read-only via disabled()+can* overrides (authorize() deliberately avoided to preserve host
policy fallbacks), #2 is_public blank→null, #3 standalone creates removed, #4 importer rules, #5 guard-message
translation (AuthorizationException still propagates per pinned ImporterOwnerIsolationTest — indistinguishability),
#6 RM owner scope via ContactingRelationOwnerScope, #7-#15 done. NEW-040/041 fixed by worker (see below);
NEW-060 (purpose unexposed, functional via DB default) deliberately left, no action (minted from the
worker's dangling "NEW-3" note; see the NEW-060 entry).

Package verification (supervisor re-ran):
- ./vendor/bin/pest --parallel tests/src/FilamentContacting — PASS (36 passed, 4 pre-existing skips, 167 assertions)
- pint --test — PASS (58-file joint gate); phpstan level 6 --debug — PASS, no errors

Outstanding: contacting-core follow-ups (resolve(null,null) parentless, null handle+url persistence, host RM
parent scoping — advisory, already COMPLETE)

---

## PACKAGE COMPLETE — filament-commerce-support

Findings completed: 16 / 16 (9 CONFIRMED incl. 6 R1:#9 sub-units + 7 AUD verified N/A/PASS; 0 blocked)

Covered findings: #1 deep-merge, #2 rename round-trip, #3 typed sorts (differs-from-mounted, documented deviation
from the finding's literal suggestion which breaks drag), #4 validated save, #5 resource registration, #6 sibling
Get, #7 settings fallback, #8 label allowlist, #9a-#9f. Worker sub-fixes folded inseparably (enum nav-group fatal,
ungrouped sort 0, [Unregistered] stale options, eager-load resolver). Supervisor added the missing
TimezoneResource::shouldRegisterNavigation (sibling-mirrored, suite still green).

Package verification (supervisor re-ran, post nav-gating addition):
- ./vendor/bin/pest --parallel tests/src/FilamentCommerceSupport — PASS (41 passed, 114 assertions)
- pint --test — PASS (58-file joint gate); phpstan level 6 --debug — PASS, no errors

Outstanding: none (optional future nicety: prune-stale-[Unregistered]-overrides action, adapter-side)

---

### [DONE] R2: filament-feedback+signals+filament-shipping+tax — all 52 (worker #34 repair, supervisor-verified)

Worker #34 (report recovered from subagent log after parent run died on approval-capacity; edits + report intact,
no intake had run). Count reconciled: 54 verdict lines minus 2 FALSE (H4, AUD:Q7) = 52, no discrepancy.

filament-feedback (5 fixed, 6 verified no-change):
- F1 missing views: provider hasViews + InteractsWithRecord record resolution + 3 new Blade views (view-existence tests).
- F2 9x dashboard recompute: request-scoped FeedbackDashboardMemo shared by all 9 widgets.
- F3 duplicate withCount dropped (vendor-verified counts() applies it). F4 ip_address export column removed + dead
  getColumnsHiddenByDefault override removed (no such Filament method). FF AUD:B4 form.name N+1: with(['form']) on
  both resources.

signals (16 fixed, 2 verified no-change, 1 blocked):
- S1 public auth-linkage spoof: auth_user_* removed from public validation, linkage derives from auth()->user() only.
- S2 raw traits: shared SignalPropertyFilter (allowlist + always-on blocklist). S3 alert get() OOM: cursor() streaming
  (SQL compilation deliberately declined: SignalCondition edge-case drift risk, documented).
- S4/S5 unbounded scans: 90d default window + 25k row caps via signals.reporting.* config. S6 Pg-only dedup:
  driver-agnostic DuplicateKeyViolation. S7 CF headers honored only from trusted proxies. S8 char-vs-byte caps:
  strlen() for payload + key/value checks (latter = NEW-045). S9 seen_at 500: try/catch fallback.
- S11 session read+write: wasRecentlyCreated fast path. S12 growth cascade: chunkById(500) + memoized schema check.
- S13 cookie_value dropped from browser allowlist. SIG AUD:B1 * bypass: blocklist always applies in shared filter.
- SIG AUD:B2 browser idempotency: idempotency_key + duplicate-key rescue→refetch. SIG AUD:B3 revenue cast:
  normalizeRevenueMinor. SIG AUD:B4 query-string write key rejected on all 5 collect routes (422 + zero rows).
- SIG AUD:B5/B6 info notes verified (strictness present; perf covered by S3/S4/S5).

filament-shipping (4 fixed, 7 verified no-change):
- H1 float truncation: MoneyInput::toMinor ((int) round()) at all 16 sites. H2 9 queries: ShippingStatsAggregator
  computes once. H3 unbounded pickup get(): chunkById(200) + testable markFilteredShipmentsPickedUp(). H5 exception
  text in bulk errors: generic message + report($e). FS AUD:B1-B7 verified N/A (eager loads, indexes, cached badges,
  lazy options, SUM(CASE) all present).

tax (6 fixed + 1 partial + 1 via-dup, 2 verified no-change, 2 blocked):
- T1 exemption mass-assignment: explicit allowlist + forced PendingState. T2 stale default zone: clearCache also
  clears defaultResolver. T3 postcode ignored: scopeForAddress documented candidate-only + cursor()->first() streaming.
- T4 unvalidated currency: ISO-shape validation + default fallback (getCurrency/getMoney). T5 no-op commands removed
  (no derived fields exist; any implementation would invent semantics). T6 postcode collapse PARTIAL: numeric ranges
  reject lettered input (alphanumeric legacy path pinned, see blocked). TAX AUD:B1 owner fillable removed on 4
  models (assignOwner direct-set; auto-assign intact). TAX AUD:B2 action layer fixed via T1 (status-fillable blocked).
- TAX AUD:B3 verified harmless (no deleting hook); AUD:B5 already done (owner-aware cache + T3).

No-change with evidence: H4 FALSE (null-owner branch is global-only whereNull pair); AUD:Q7 FALSE (owner-composite
indexes proven by execution probe, removed after); FF AUD:B1/B2/B3/B5/B6/B7 grep-verified; worker also repaired 4
signals integration fixtures via forceFill after sibling workers removed mass assignment (no assertions weakened).

Verification (supervisor re-ran, all match worker claims exactly):
- ./vendor/bin/pest --parallel tests/src/FilamentFeedback — PASS (13 passed, 40 assertions)
- ./vendor/bin/pest --parallel tests/src/Signals — PASS (122 passed, 821 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentShipping — PASS (107 passed, 250 assertions)
- ./vendor/bin/pest --parallel tests/src/Tax — PASS (207 passed, 485 assertions)
- pint --test on worker-touched files — PASS (worker); supervisor removed one dead `use PDOException;` import
  (runtime warning noise) and re-ran the file green (2 passed)

Migration/schema: none (config-only additions under signals.reporting.*).

Covered findings: R2 feedback batch 52/52 (33 fixed-or-partial with code changes + 17 verified no-change + 2
blocked-only lines S10 / TAX AUD:B4, plus split lines T6 + TAX AUD:B2).

Notes:
- Follow-ups: S3 full SQL compilation (needs exact-semantics proof); contract decisions for the 4 BLOCKED items.
- Worker NEWs: NEW-044 (list-props TypeError, fixed), NEW-045 (mb_strlen caps, fixed).

## PACKAGE COMPLETE — R2: filament-feedback+signals+filament-shipping+tax

Findings completed: 52 / 52

Package verification (supervisor re-ran):
- FilamentFeedback 13, Signals 122, FilamentShipping 107, Tax 207 — all PASS
- pint --test — PASS; worker phpstan level 6 --debug — OK x4 (supervisor: no src edits, no re-run needed)

Outstanding:
- S10 (DOWNGRADED): BLOCKED-test-conflict (pinned silent-global-fallback test; fail-closed reverted; needs contract decision)
- T6 remainder: BLOCKED-test-conflict (pinned SW1A alphanumeric-range semantics; numeric guard shipped)
- TAX AUD:B4: BLOCKED-test-conflict (allowlist breaks pinned arbitrary-type lookups; owner-scoped misses, not leaks)
- TAX AUD:B2 status-fillable part: BLOCKED-test-conflict (pinned create-with-status tests; public path fixed via T1)

---

### [DONE] R2: filament-jnt+persons+jnt+filament-orders — all 64 (worker #35 repair, supervisor-verified)

Worker #35 (report recovered from subagent log; edits + report intact). Count adjudicated: all 64 lines are
CONFIRMED/ADOPTED/DOWNGRADED verdicts, so per the table convention (DUP-counted-once included, only
FALSE/FIXED/UNVERIFIED excluded) the unit stays 64: 35 fixed + 4 blocked + 25 verified-no-change (9 DUP via
primaries, 6 OK-info, 10 N/A-here). Worker's "39 true unique" reading is noted but not adopted.

persons (8/8 fixed):
- R2:F11 CreatePersonAction: allowlist validation (name required 1-255, family/middle <=100, gender/status/slug
  checked), InvalidArgumentException. R2:F12 formatted_name N+1: opt-in scopeWithFormattedName() (accessor purity
  pinned, no eager-load inside it). R2:F13 double-assign: DB uniques in canonical 2000_* migrations + actions
  validate + rescue 23000 to existing row. R2:F25/AUD:B1/B8 cascades: DB::transaction + chunkById(500) on Person,
  Title, TitleCategory, CredentialDefinition, Affiliation. AUD:B2 slug race: save() retries once on slug-unique
  23000. AUD:B3 transitionStatus mutate-no-save documented (load-bearing, pinned). AUD:B4 searchable_name/
  published_at removed from fillable (slug/status kept: explicit-slug pinned). AUD:B7 existence checks guarded by
  isDirty.

filament-orders (2/2 fixed):
- R2:F2 OrderTimelinePage paginated(false) removed (RecentOrdersWidget's is safe: limit(10)). R2:F16 note
  content/visibility: form maxLength(2000) + in([...]) plus normalizeNoteInput() server guard.

filament-jnt (3 fixed, 1 blocked):
- R2:F15 infolist caps: infolist.tracking_events_limit/items_limit (default 50) via getStateUsing. R2:F21 badge
  stampede: Cache::flexible([30, 90]) + TTL jitter on tracking puts (lock-based OwnerCache needs frozen
  commerce-support). R2:F22 status mapped 3x/row: memoized getNormalizedStatus() on JntOrder/JntTrackingEvent,
  adapter delegates (pinned reflection test kept green). R2:F20 allowlist BLOCKED-test-conflict (pinned fixture
  requires non-J&T host; fully reverted).

jnt (22 fixed, 3 blocked):
- R2:F1 owner default docs corrected + multitenant MUST-enable warning + provider fail-fast on NullOwnerResolver
  (default false pinned by BC). R2:F3/AUD:B3 webhook bounds: max_biz_content_bytes 1 MiB + max_details 500 (422) +
  TrackingEventHash + preload + insertOrIgnore. R2:F4 bad scanTime: try/catch at all 5 sites (no tryParse in
  installed Carbon). R2:F5 RETURN/RETURNED reorder. R2:F6 fromApiArray: required-key checks throwing
  JntValidationException in all 3 Data classes. R2:F7 passthrough documented unsafe-advanced (validation pinned
  impossible by batch tests). R2:F8 unbounded Concurrency::run: indexed task keys + runTasksInChunks (25).
- R2:F9 per-detail firstOrCreate: shared hash + preload + insertOrIgnore (webhook/sync cross-path dedupe).
- R2:F14 credential singletons: bind (non-singleton) for 4 services. R2:F17/F18/F19 traversal/header-injection/SSRF:
  safeFilename/safeDirectory/filename*/isSafeTestUrl. R2:F23 stored email: FILTER_VALIDATE_EMAIL + Notifiable check
  + TypeError/ValueError catch (+ NEW-046 queue crash fixed here). R2:F24 parseBizContent single-object normalize.
  R2:F26 postcode ^\d{5}$ guard. AUD:B2 inherited-owner guard (saving runs before creating — verified real).
  AUD:B4 event+order atomic (txn + lockForUpdate, sqlite-skipped). AUD:B5 webhook_calls table configurable.
  AUD:B6 order cascade txn. AUD:B7 verify_signature=false fails closed in production. AUD:B12 single-pass max-scan.
  AUD:B13 latestTrackingEventRelation latestOfMany.
- R2:F10 BLOCKED (test-conflict + incorrect-premise: fillable removal contradicts pinned tests; spoof disproven by
  HasOwner guard — proof test added). AUD:B9 BLOCKED-test-conflict (uniform-200 vs 5 pinned status tests).
  AUD:B10 BLOCKED-incorrect-premise (signing input('bizContent') is correct per J&T algorithm).

No-change with evidence: 9 DUP-counted-once closed via primaries; 6 OK-info verified; 10 N/A-here verified.

Verification (supervisor re-ran, all match worker claims exactly):
- ./vendor/bin/pest --parallel tests/src/Persons — PASS (39 passed, 164 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentOrders — PASS (24 passed, 72 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentJnt — PASS (38 passed, 136 assertions)
- ./vendor/bin/pest --parallel tests/src/Jnt — PASS (596 passed, 1806 assertions)
- pint --test — PASS (worker, 51 files); phpstan level 6 --debug — OK x4 (worker)

Migration/schema: canonical 2000_* edits only (persons title/credential uniques; jnt webhook_calls table config).

Covered findings: R2 jnt batch 64/64 (35 fixed + 25 verified-no-change/DUP + 4 blocked).

Notes:
- Follow-ups: F20 fixture decision; AUD:B9 uniform-response decision; F10 fillable decision; batch-sync queueing
  (API change); hard total cap (API change); lock-based OwnerCache stampede (commerce-support).
- Worker NEWs: NEW-046 (anonymous-class queue crash, HIGH, fixed), NEW-047 (swapped args, fixed).

## PACKAGE COMPLETE — R2: filament-jnt+persons+jnt+filament-orders

Findings completed: 64 / 64

Package verification (supervisor re-ran):
- Persons 39, FilamentOrders 24, FilamentJnt 38, Jnt 596 — all PASS
- pint --test — PASS; worker phpstan level 6 --debug — OK x4

Outstanding:
- R2:F20: BLOCKED-test-conflict (fixture pins non-J&T host; escalate: change fixture + re-apply, or accept)
- R2:F10: BLOCKED-test-conflict + incorrect-premise (spoof disproven; fillable removal contradicts pinned tests)
- AUD:B9: BLOCKED-test-conflict (uniform-200 vs pinned distinct statuses; needs product/security decision)
- AUD:B10: BLOCKED-incorrect-premise (current signing correct per J&T algorithm)

---

### [DONE] R2: filament-organizations+products+filament-pricing+references — all 62 (worker #36 repair, supervisor-verified)

Worker #36 (report recovered from subagent log; edits + report intact). Count: 11 + 24 + 15 + 12 = 62, no
discrepancy; plus 2 FALSE lines verified with evidence.

products (24):
- P1+AUD:B3/B7 deleting cascades: txn + chunked per-model deletes (chunkById, reorder() on ordered values).
- P2 SKU dedup: product_id constraint added (sibling collision now fails loudly, no cross-attach). P3 identity
  uniques: env gates removed from 8 migrations (present-state). P4 category parents: saving validation
  (exists/not-self/not-descendant/same-owner) + visited-set getAncestors/getNestedTree + iterative bulk counts.
- P5 double dispatch: explicit dispatches removed (verified no external listeners). P6 collection rules: column +
  operator allowlists + scalar check. P7 unbounded rebuild: matchingProductsQuery() + chunkById attach/detach
  (positions preserved). P8 raw value writes: setCustomAttribute path + ?? $model guards. P9 currency: saving
  validation + uppercase normalization. P10 attributable_type: unconditional [Product, Variant] allowlist.
- P11 N+1: bulk category methods + relation-cache population for variant option lookups (residual: per-variant live
  inventory fan-out needs frozen-inventory bulk API). P12 silent-0 inventory catch: log + local-stock fallback.
  P13 fabricated exception: honest InvalidArgumentException (DB index authoritative). P14 Schema::getColumnListing:
  static per-table cache. P15 job withoutOwnerScope lookup: scoped lookup in job owner context. P16 swatches:
  saving validation + fail-closed getter. AUD:B2/B5 variant guards: updating immutability + owner guard mirroring
  Product. AUD:B4 draft clears published_at. AUD:B8 strip-then-reapply verified correct (comments + test added).
- AUD:B1 BLOCKED-test-conflict (owner fillable removal contradicts pinned mass-assignment tests; guards mitigate).
  AUD:B6 (filament-products query bypass) FIXED AT INTAKE by supervisor (see below) — no longer blocked.

references (12):
- R1/AUD:B3 global slug unique: ReferenceIdentityIndexes (per-owner + global partial uniques, all 3 drivers).
- R2 down() added. R3/AUD:B1/B5 mass delete: children-first per-row delete with events + chunked media + unscoped
  hierarchy collect + per-row owner context + parent validation. R4 ReferencePolicy + Gate registration. R5 field
  validation (year/isbn/url/language/JSON). R6 getTable()/migration fallback aligned to `references`. R7 HasReferenceParts
  on model + docs rewritten. AUD:B2 transitionStatus() + publish auto-stamp. AUD:B4 single canonical per owner scope.

filament-pricing (15):
- F1 FALSE verified (HasOwner global scope + owner-disabled default = global-by-design). F2 morph safety:
  CatalogReference (type allowlist + owner-scoped existence) wired into Prices Create/Edit (+ NEW-049 tiers fix).
- F3 raw settings save: server-side Validator + canAccess()/navigation gate behind authorization.settings_ability
  (fail-closed; settings stay global by Spatie design). F4 simulator: validatedSimulationInput(). F6 LIKE escaping
  at all 11 sites via LikePattern (sqlite fail-closed caveat = NEW-050). F7 tiers N+1: modifyQueryUsing morphWith.
  F8 null-unsafe variant labels: ?-> + fallback. F9 policies: PriceList/Price/PriceTierPolicy + HandlesPricingOwnerScoping
  in adapter. AUD:B4 with('priceable'). AUD:B1/B2 notes verified; B3/B5/B6/B7 N/A verified.
- F5 BLOCKED-out-of-scope (needs frozen pricing migration first; current rule mirrors schema — follow-up for
  pricing-batch worker).

filament-organizations (11):
- O1 dup-slug 500s: unique(ignoreRecord: true) on shared slug field (domain-action move needs frozen orgs pkg).
- O2 revoke row action (Pending-only, membership recheck, ability, delegates to membership action). O3 email oracle:
  trimmed case-insensitive lookup + generic 422. O4 FALSE verified (assert helpers called). O5 unbounded transfer
  options: searchable async select (limit 50). O6 any-user create: per-user create_per_hour rate limit (canCreate
  pinned true). AUD:B1/B2-B6 notes/N/A verified.
- NEW-048 (critical, fixed): all 5 relation-manager action closures type-hinted Organization (Filament cannot
  inject) — every member action threw BindingResolutionException; fixed via getOwnerRecord() + ownerOrganization().

Supervisor intake fixes:
- NEW-043 (checkout idempotency, FIXED): #36's P13 honest-InvalidArgumentException broke
  EnsureCheckoutOfferProduct::handle idempotency (createOrFirst rescues only DB violations; second ensure threw).
  Fixed in checkout with rescue + refetch-if-row-exists (rethrows when no row, so validation errors still surface).
  Checkout suite restored 305+1 failed -> 306 passed / 1105 assertions; pint + phpstan clean.
- Products AUD:B6 (FIXED, was worker-BLOCKED-out-of-scope): the filament-products worker (#37) did not address it,
  so supervisor fixed it here — all 5 filament-products resources now build on parent::getEloquentQuery() instead of
  Model::query()->forOwner() (scoping equivalent via global OwnerScope, fail-closed preserved; withCounts kept).
  FilamentProducts suite still 46 green; pint + phpstan clean.

Verification (supervisor re-ran, all match worker claims exactly):
- ./vendor/bin/pest --parallel tests/src/FilamentOrganizations — PASS (15 passed, 45 assertions)
- ./vendor/bin/pest --parallel tests/src/Products — PASS (633 passed, 1188 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentPricing — PASS (62 passed, 168 assertions)
- ./vendor/bin/pest --parallel tests/src/References — PASS (58 passed, 240 assertions)
- ./vendor/bin/pest --parallel tests/src/Checkout — PASS (306 passed, 1105 assertions, post NEW-043 fix)
- pint --test — PASS (worker 65 files; supervisor 1 checkout + 5 filament-products files)
- phpstan level 6 --debug — OK (worker x4; supervisor checkout + filament-products)

Migration/schema: present-state edits only (products env-gate removal; references identity indexes).

Covered findings: R2 orgs batch 62/62 (58 fixed-or-verified + 2 blocked + AUD:B6 supervisor-fixed + F-label FALSEs).

Notes:
- Follow-ups: F5 pricing per-owner uniques (pricing-batch worker); F9 policies long-term home in pricing; O1
  UpdateOrganizationAction (organizations); O2 resend-with-expiry (membership); per-tenant pricing settings;
  P11 bulk inventory API (inventory); NEW-050 sqlite ESCAPE helper (commerce-support).
- Worker NEWs: NEW-048 (action injection, fixed), NEW-049 (tiers revalidation, fixed), NEW-050 (sqlite LIKE, OPEN).
- Observed (no action): EnforcesOwnerUniqueIdentity probe misses auto-assign-pending creates by design (friendly
  error only for explicit-owner creates; pinned createOrFirst test relies on the DB exception).

## PACKAGE COMPLETE — R2: filament-organizations+products+filament-pricing+references

Findings completed: 62 / 62

Package verification (supervisor re-ran):
- FilamentOrganizations 15, Products 633, FilamentPricing 62, References 58, Checkout 306 — all PASS
- pint --test — PASS; phpstan level 6 --debug — OK

Outstanding:
- Products AUD:B1: BLOCKED-test-conflict (fillable removal contradicts pinned mass-assignment tests; guards mitigate)
- Filament-pricing F5: BLOCKED-out-of-scope (needs pricing migration first; queued for pricing-batch worker)

---

### [DONE] R2: filament-products+organizations+filament-seating+filament-persons — all 31 (worker #37 repair, supervisor-verified)

Worker #37 (report recovered from subagent log; edits + report intact). Count reconciled exactly as 11 + 8 + 5 + 7
= 31 (DUP-counted-once included, FALSE excluded); plus 1 FALSE (products AUD:B1b) verified with evidence.

filament-products (R1:#1-#10 + AUD:B1a):
- R1:#1 category cycle: EditCategory rejects self/descendant parents (cycle-guarded walk, OwnerScope opt-out);
  table depth from one owner-scoped id->parent map (CreateCategory provably safe, unchanged). R1:#2 import:
  maxSize/max_rows caps + strict validateImportRow + atomic txn when skip-errors off. R1:#3 export: cursor() +
  php://output streaming. R1:#4 global uniques: ProductsOwnerScope::scopeUniqueRuleToOwner on all 8 sites (+ 3
  unlisted sites with the same root cause, fixed). R1:#5 price_list_id: OwnerUiScope + OwnerScopedIds revalidation.
  R1:#6 N+1: withCounts in getEloquentQuery/top-selling/category map + loaded-count reuse. R1:#7 authz: can* +
  shouldRegisterNavigation on 5 resources + authorize() on all bulk actions incl. unlisted ProductsTable bulks.
  R1:#8 duplicate: duplicateProduct() (unique slug/SKU loops, full relation copy, txn, post-commit media).
  R1:#9 bulk price: minor-unit math (percent capped 100) + txn + conditional maxValue. R1:#10 BLOCKED-test-conflict
  (4 pinned widget tests pin silent-global fallback; reverted, escalation documented). AUD:B1a DUP of R1:#6 closed.

organizations (R1:#11-#16 + AUD:B1/B2):
- R1:#11 CreateOrganizationAction: typed up-front validation + 250-char slug base (suffix-safe), before retry loop.
  R1:#12 down() on both canonical migrations. R1:#13 state machine: publish refused unless Active; restore forces
  Public->Private with hook. R1:#14 default-allow: default false (all in-repo abilities known). R1:#15 transfer
  morph: getMorphClass() equality (cross-table UUID-collision rejected). R1:#16+AUD:B2 fillable narrowed to
  name/slug/description (sole writer uses new + forceFill; MigrationIndexesTest scaffolding privileged, assertions
  identical). AUD:B1 partial: visibility half fixed (published_at cleared on privatize), status half
  BLOCKED-test-conflict (pinned terminal-timestamp retention).

filament-seating (R1:#17-#20 + AUD:B1):
- R1:#17 widget counts: explicit forOwner() scoping (temp-revert proved coincidence with global OwnerScope; kept as
  mandated explicitness). R1:#18 can* + shouldRegisterNavigation (seat-map.*). R1:#19 dead pages: mount() validates
  seatMapId via OwnerScopedIds (404 otherwise), canAccess()=false as explicit not-enabled state. R1:#20 form: slug
  required + owner-scoped unique (SeatingOwnerScope), version integer>=1 (status enum-cast = frozen-domain
  follow-up). AUD:B1 info PASS verified.

filament-persons (R1:#21-#26 + AUD:B1):
- R1:#21 shared form(Schema) in all 4 relation managers (edit renders + validates). R1:#22 institution options:
  ModelResolver query (null -> []), owner-aware, label fallback, 500 cap. R1:#23 can* + shouldRegisterNavigation on
  all 4 resources. R1:#24 assignment validation: date-order + duplicate guards (edit exclusion via $record) + issuer
  guards + InvalidArgumentException -> ValidationException conversion. R1:#25 guidance: testable
  placementGuidance() early-returns without querying on blank input. R1:#26 languages: Cache::remember 3600
  (Language unscoped, plain cache correct). AUD:B1 BLOCKED-incorrect-premise (persons models have no owner columns;
  enumeration closed by R1:#23 gates instead).

No-change with evidence: products AUD:B1b FALSE (no category attach path in import range); seating AUD:B1 info PASS.

Verification (supervisor re-ran, all match worker claims exactly):
- ./vendor/bin/pest --parallel tests/src/FilamentProducts — PASS (46 passed, 199 assertions, post AUD:B6 fix)
- ./vendor/bin/pest --parallel tests/src/Organizations — PASS (21 passed, 64 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentSeating — PASS (13 passed, 31 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentPersons — PASS (11 passed, 82 assertions)
- pint --test — PASS (worker 148 files); phpstan level 6 --debug — OK x4 (worker)

Migration/schema: organizations down() additions only (rollback-safe).

Covered findings: R2 products batch 31/31 (28 fixed-or-verified incl. 2 DUPs via primaries + 3 blocked).

Notes:
- Follow-ups (frozen): products Category model-level cycle guard + cycle-safe getAncestors()/getDepth(); seating
  SeatMap status enum; persons domain-level date/duplicate guards (optional); custom-ability hosts must allowlist
  under new default-deny.
- Worker NEWs: unlisted unique()/bulk-authz sites fixed inseparably; NEW-051 (widget test wording, OPEN).

## PACKAGE COMPLETE — R2: filament-products+organizations+filament-seating+filament-persons

Findings completed: 31 / 31

Package verification (supervisor re-ran):
- FilamentProducts 46, Organizations 21, FilamentSeating 13, FilamentPersons 11 — all PASS
- pint --test — PASS; phpstan level 6 --debug — OK

Outstanding:
- R1:#10: BLOCKED-test-conflict (4 pinned widget tests pin silent-global fallback; re-apply = wrap invocation in
  withOwner(null) + fail-closed via OwnerUiScope::resolveOwner())
- Organizations AUD:B1 status half: BLOCKED-test-conflict (pinned terminal-timestamp retention; visibility half fixed)
- Filament-persons AUD:B1: BLOCKED-incorrect-premise (no owner columns on persons tables; closed by authz gates)

---

### [DONE] R2: filament-cashier+ticketing+pricing+filament-chip — all 59 (worker #33-retry repair, supervisor-verified)

Retry worker (predecessor #33 killed mid-run by the approval-capacity crash; partial edits kept in full — the
"stillborn ticketing" note proved stale, ticketing was substantially repaired and green). Count: 60 verdict lines
minus 1 FALSE (cashier AUD:B4) = 59, no discrepancy (27 CONFIRMED + 3 DOWNGRADED + 29 ADOPTED).

filament-cashier (16 lines; mostly predecessor work verified and kept):
- R1#18 loadMore clamped (<=50/<=200). R1#19 once() without owner key: request-bound instance memo in all 4
  widgets (zero `once(` remain). R1#20 inverted currency conversion fixed (amount x rate[base]/rate[source]).
  R1#21 12 chunk scans: single per-gateway scan with month bucketing. R1#22 live probes: 60s OwnerCache health
  cache by credential fingerprint + generic UI messages + per-request StripeClient. R1#23 invoice fetch capped
  (<=200 + hasMore). R1#24 export authz: per-record assertInvoiceAccessible + CSV formula escaping + null-safe
  dates. AUD:B1 IDOR mitigated (billable-ownership re-validation on every record action; OwnerScopedQuery +
  SubscriptionPolicy on ViewSubscription). AUD:B3/B5/B6/B7/B8 info verified by empty greps.
- R1#17 BLOCKED-test-conflict (admin lists per-user gateway queries pinned by 3 green tests; owner-wide listing
  design proposed: OwnerScopedQuery on Subscription models via UnifiedSubscription, capped per-billable fan-out
  for invoices). AUD:B2 out-of-scope (frozen filament-cashier-chip; getBillable verified auth-user-bound).
- Worker removed two useless `use ReflectionMethod;` imports (warning noise) in the regression tests.

ticketing (22 lines, all repaired — predecessor work verified and kept):
- R1#1 no policy enforcement: authorizedBy + Gate authorize('transfer') in both actions, canTransfer
  defense-in-depth in service, caller contract documented. R1#2 mixed-owner bulk: assertSingleOwner. R1#3 holder
  hijack: single txn + same-pass validation (incl. owner-tuple) + lockForUpdate + per-pass replication (partial
  unique index declined as non-portable to MySQL; row-lock serialization covers it). R1#4 unbounded issuance:
  ticketing.issuance.max_quantity (500) + chunked inserts + bounded retries. R1#5 bulk bypass: homogeneous-batch
  assertion + afterCommit PassIssued. R1#6+AUD:Q18+AUD:B5 holder mass-assignment: HolderAttributesValidator
  (shape/type-pairing/allow-list/morph/owner-existence) wired into issuance/transfer/cart.
- R1#7 bulk email N+1: previousHolders map + loadMissing + blank skip. R1#8 blank-email guard. R1#9 cart extras:
  reserved-key rejection + cart.max_participants + per-participant validation. R1#10+AUD:B7 getTotalAvailable:
  single SUM(CASE) SQL. R1#11 uniques: composite uniques + transfer_expires_at index + 23000-rescue.
- AUD:Q19+B4 locks/checks via service; AUD:QNN+B1 deleting cascades on Pass/TicketType; AUD:B2 raw status:
  saving validation against TicketTypeStatus (both shapes supported, no enum-cast breakage); AUD:B3 code
  collisions checked; AUD:B6 non-HasOwner guard-skipping by design (documented, entry-point rejection);
  AUD:B8 nested txns: bulk_max_size cap (100).

pricing (8 lines):
- R1#13 currency ignored: ResolvesCurrency trait across resolvers + calculator + currency indexes (predecessor,
  kept). R1#14 stub cart targeting: best-effort priceable resolution + id-only fallback (kept). R1#15+AUD:B3
  fan-out: calculateMany() + interface method (kept). R1#16 indexes: deactivated_at/is_default/is_active/currency
  (kept). AUD:B1 dual flags: activate()/deactivate() + isActive() (kept). AUD:B2 monetary validation in saving
  hooks (kept).
- R1#12 BLOCKED-incorrect-premise with triple proof (Laravel source: foreignUuid defines a uuid column only;
  zero constrained()/cascadeOnDelete() in pricing migrations; .ai/database prescribes foreignUuid; green
  PricingSchemaTest pins no-FK).
- Follow-up (a) IMPLEMENTED (retry worker): price_lists.slug is now per-owner unique
  (price_lists_owner_slug_unique, sibling convention) in the canonical migration; docs + createOrFirst
  idempotency note; OwnerSlugUniquenessTest with pre-fix revert proof. Filament-pricing F5 unblocked.
- NEW-043 verified + regression-tested from the pricing side: no friendly pre-check in Price/PriceList hooks
  (DB sole enforcer, DB-native violation rescued by createOrFirst); double-createOrFirst idempotency test.

filament-chip (14 lines; retry worker's own work except BLOCKED/info):
- R1#25 unbounded get() + PHP sums: PurchaseRevenueExpressions per-driver JSON SQL in all 4 widgets + 120s
  OwnerCache (single-query + exact-value tests). R1#26 period injection: clamp to 7/30/90 (pre-fix proof).
  R1#27+AUD:B4 badges: 60s OwnerCache per owner per resource. R1#28 exporter: mixed formatter, checkout_url
  dropped, owner-scoped modifyQuery (is_test-crash half already false per verdict). R1#30 distinct filters: 300s
  OwnerCache. AUD:B1 bare-query fallback: whereRaw('1 = 0'). AUD:B2 statement actions: resolveScopedCompanyStatement
  + generic messages + Log. AUD:B8 DUP of R1#25 closed. Docs corrected (real ranges + caching/export notes).
- R1#29 BLOCKED-test-conflict (empty-without-context fix contradicts 2 pinned widget tests; escalation: gate the 3
  fallbacks on isExplicitGlobal() + move invocations inside withOwner(null) + assert-empty cases).

No-change with evidence: cashier AUD:B4 FALSE (badge returns null); chip crash-half of R1#28 false (boolean cast);
chip AUD:B3/B5/B6/B7 info verified; cashier AUD:B5/B6/B7 info verified by empty greps.

Verification (supervisor re-ran, all match worker claims exactly):
- ./vendor/bin/pest --parallel tests/src/FilamentCashier — PASS (141 passed, 457 assertions)
- ./vendor/bin/pest --parallel tests/src/Ticketing — PASS (79 passed, 152 assertions)
- ./vendor/bin/pest --parallel tests/src/Pricing — PASS (165 passed, 343 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentChip — PASS (28 passed, 121 assertions)
- Cross-impact (pricing migration): tests/src/Checkout — PASS (306 passed, 1105 assertions);
  tests/src/FilamentPricing — PASS (62 passed, 168 assertions)
- pint --test — PASS (worker all touched; supervisor re-ran migration + new test file)
- phpstan level 6 --debug — OK x4 (worker); supervisor verified per-owner unique present + global unique gone
- No supervisor code edits needed; no NEW issues found by worker (2 hypotheses cleared with source evidence)

Migration/schema: canonical 2000_* only (price_lists per-owner slug unique; ticketing composite uniques +
transfer_expires_at index from predecessor).

Covered findings: R2 cashier batch 59/59 (55 repaired + 3 blocked + 1 out-of-scope note AUD:B2).

Notes:
- Follow-ups: R1#17 owner-wide listing design (needs capped per-billable invoice fan-out decision); R1#29
  explicit-global gating (test updates prescribed); F5 consumer (filament-pricing adapter) may now scope its
  rule; chip date/status indexes un-addable (frozen chip schema); NULL-owner slug dupes possible at DB level
  (privileged global writes only, documented).

## PACKAGE COMPLETE — R2: filament-cashier+ticketing+pricing+filament-chip

Findings completed: 59 / 59

Package verification (supervisor re-ran):
- FilamentCashier 141, Ticketing 79, Pricing 165, FilamentChip 28, Checkout 306, FilamentPricing 62 — all PASS
- pint --test — PASS; phpstan level 6 --debug — OK x4

Outstanding:
- R1#17: BLOCKED-test-conflict (per-user gateway listing pinned by 3 tests; owner-wide design proposed)
- R1#12: BLOCKED-incorrect-premise (foreignUuid creates no FK; repo rules prescribe it; no-FK pinned by test)
- R1#29: BLOCKED-test-conflict (empty-without-context contradicts 2 pinned widget tests; gating + test updates prescribed)
- AUD:B2: out-of-scope (frozen filament-cashier-chip)

---

### [DONE] R2: filament-signals+promotions+filament-vouchers+filament-ticketing — all 70 (worker #39 + #39-retry repair, supervisor-verified)

Worker #39 was killed mid-run by the resource-pressure crash (no report; 69 files / 172 partial edits kept in
tree). Worker #39-retry reconciled every finding against current code instead of redoing work, then finished the
remainder: one critical infinite loop, three broken tests, one stale doc line, and one untested recommendation
(code/prefix alphabet rules, now pinned by a new test). Count reconciled: 70 verdict lines, 0 excluded
(15 signals + 23 promotions + 20 vouchers + 12 ticketing); 57 unique issues after DUP-merge. No
BLOCKED-test-conflict; two partials explained below.

filament-signals (15, all fixed/verified, no retry edits needed):
- Scan actions gained create authorization on all three paths. Unvalidated URL dates: strict Y-m-d sanitizer with
  366-day clamp on mount/filter/updated hooks. Rule pages wire TrackedPropertyMutationGuard on create + edit.
  Report pages: access gate on the shared page (inherited by LiveActivity/ConversionFunnel) + own gate on the
  dashboard. Slug unique scoped by owner_scope matching the DB composite. Source walk capped (500 files / 256KB).
  Bulk create wrapped in a transaction with constraint-retry slug. Scanner excludes admin route prefixes and skips
  parameterized URIs. Relation-column N+1 eager-loaded in core; option plucks replaced by search API + limit(50).

promotions (23, all fixed/verified; one doc line refreshed by retry):
- Per-customer limit fail-closed on unknown usage; per-promo order history via shared lazy loader (exactly 1 scan
  asserted). Analytics owner-scoped + cursor-streamed with narrow columns; deactivation sweep via OwnerBatchRunner +
  forOwner + chunkById(100). CreatePromotion validates name/type/range/non-negative/date-order/code; DeactivatePromotion
  null-safe fresh() + deactivated_at + OwnerWriteGuard. Composite unique(owner,code) in the canonical migration +
  scoped availability check (NULL-tuple fallback enforced at action layer). Percentage uncapped via min() both
  branches; half-up intdiv rounding matches voucher math. Process-wide static cache replaced by container-scoped
  state; promotionables reverse index added; per-order usage dedup via metadata stamp (redelivery-safe).

filament-vouchers (20: loop + 3 tests + 1 new test by retry, rest verified):
- Stored-XSS code-in-action: blade passes getKey(), widget resolves server-side in owner scope. Chart-filter DoS:
  allowlist fallback + GROUP BY; retry also fixed the discarded immutable addDay() result that infinitely looped
  and hung every full-suite run (NEW-052). Manual redeem revalidates (permission gate, eligibility recheck,
  minValue, parse rejection). Money garbage returns null via ?int with all 8 call sites null-safe. Bulk generation:
  transaction + count clamp + 23000 retry. Silent global creation throws unless explicit. Suggestions capped at 50
  with cart resolved once; double usage scan replaced by limit(50) + SQL aggregates; widgets cached in OwnerCache
  30s with a single GROUP BY trend; exporter owner-scoped with memoized resolve. Legacy-DSL edit-hydrate crash
  guarded; nav badges cached; ownership section gated on registry + owner mode; table N+1 eager-loaded with
  usages count; option plucks replaced by relationship/search APIs. Code rule PARTIAL: alpha-dash + percent-100 cap
  + decimal-safe upline shipped, scoped-unique BLOCKED-out-of-scope on the frozen vouchers-core global unique
  (NEW-053). Math-duplication finding PARTIAL: suggestions delegate to the domain calculator, stats to
  getStatistics(), helper documented; upline-widget sub-claim is an incorrect premise (widget lives in frozen
  filament-affiliates).

filament-ticketing (12, all fixed/verified, no retry edits needed):
- Ticketable picker: per-type title/search columns from existing columns + owner-scoped options query, with the
  reference guard wired into create/edit pages. Form gaps closed: scoped unique mirroring DB unique(ticketable,code),
  admits >=1, min/max cross-check, after_or_equal sales dates, price >=0, currency alpha(3)+uppercase (deliberate
  alpha+length deviation instead of a strict allowlist to avoid false rejections where core accepts more). Float
  price + $ prefix replaced by strict minor-units conversion (MYR default; core columns bigInteger + integer cast).
  Full can* authorization on all 4 resources; relation-manager N+1 eager-loaded.

No-change with evidence: signals GOOD-scoping/nav/no-badges/domain-leakage/collection-sums info lines; promotions
10 DUP lines merged; vouchers nav-PASS/collection-sums info lines; ticketing 2 DUP + 5 info/N/A lines. Retry also
corrected 3 broken tests without weakening assertions (cents-vs-major savings data, explicit-global guard context,
registered owner type) and one stale fail-open doc line; pre-fix proofs via temporary revert (alphabet + rounding).

Verification (supervisor re-ran, all match worker claims exactly):
- ./vendor/bin/pest --parallel tests/src/FilamentSignals — PASS (38 passed, 109 assertions)
- ./vendor/bin/pest --parallel tests/src/Promotions — PASS (100 passed, 177 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentVouchers — PASS (69 passed, 347 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentTicketing — PASS (15 passed, 49 assertions)
- ./vendor/bin/pint --test on the four packages — PASS (156 files)
- ./vendor/bin/phpstan analyse on the four src dirs --level=6 — OK, no errors
- Zero tracking-ID leakage in the four packages + four test dirs (supervisor grep sweep)

Migration/schema: promotions canonical migration gained composite unique(owner,code) + promotionables_reverse_index.
No other schema changes.

Covered findings: R2 signals batch 70/70 (57 unique after DUP-merge; 2 partial lines explained above).

ID map (transcribed from the worker report; descriptions above keyed to audit IDs):
- filament-signals FIXED: R2:S1 (scan-action auth), S2 (URL-date sanitizer), S3 (mutation guard), S4
  (report gates), S5 (scoped slug), S6 (scan caps), S7 (bulk txn + slug retry), S8 (admin-route
  exclusion), AUD:B4 (relation eager-load), AUD:B5 (search API + limit). Info/N/A verified: AUD:B1
  (GOOD scoping), B2 (nav PASS), B3 (no badges), B6/B7 (other-package scopes).
- promotions FIXED: R2:P1 (fail-closed limits), P2 (shared usage loader), P3 (scoped analytics), P4
  (scoped deactivation sweep), P5 (create validation), P6 (null-safe fresh + timestamp), P7 (composite
  unique), P8 (percent clamp), P9 (container-scoped state), P10 (reverse index), AUD:B2 (per-order
  dedup), B5 (rounding), B8 (owner guards). DUPs merged: B1, B3, B4, B6, B7, B9, B10, B11, Q#1, Q#2.
- filament-vouchers FIXED: R2:V1 (XSS), V2 (chart DoS + NEW-052 loop), V3 (redeem revalidation), V4
  (money null), V5 (bulk txn), V6 (explicit global), V7 (suggestion caps), V8 (usage aggregates), V9
  (widget cache), V10 (exporter scope), V11 (DSL fallback), V13 (badges), AUD:B1 (ownership gating),
  B4 (eager-loads), B5 (relationship APIs). PARTIAL: V12 (alpha-dash + caps shipped; scoped-unique
  blocked, NEW-053), B6 (upline-widget premise incorrect). DUP/info: B3→V13, B2/B7 N/A.
- filament-ticketing FIXED: R2:T1 (scoped picker + guard), T2 (form validation), T3 (minor-units
  money), T4 (can* authz), T5 (RM eager-loads). DUP/info: AUD:B1→T1, B4→T5, B2/B3/B5/B6/B7 N/A.

Notes:
- Worker NEWs: NEW-052 (redemption-trend infinite loop, fixed), NEW-053 (voucher code scoping ceiling, open).
- Pre-existing condition inherited, not a regression: percentage min() clamp + intdiv rounding were already present.

## PACKAGE COMPLETE — R2: filament-signals+promotions+filament-vouchers+filament-ticketing

Findings completed: 70 / 70

Package verification (supervisor re-ran):
- FilamentSignals 38, Promotions 100, FilamentVouchers 69, FilamentTicketing 15 — all PASS
- pint --test — PASS (156 files); phpstan level 6 — OK x4

Outstanding:
- Voucher code scoped-unique: BLOCKED-out-of-scope (frozen vouchers-core migration holds the global code unique;
  form rule must stay global to match; posture is fail-closed, no 500s)
- Upline-widget math sub-claim: BLOCKED-incorrect-premise (that widget lives in frozen filament-affiliates)

---

### [DONE] R2: filament-promotions+inventory+vouchers+filament-engagement — all 61 (worker #38 + #38-retry repair, supervisor-verified)

Worker #38 was killed mid-run by the resource-pressure crash (no report; 53 files / 157 partial edits kept in
tree, last seen debugging the reservation-concurrency test). Worker #38-retry reconciled every finding against
current code, then finished the remainder: the percentage-redeem zero-value fix, a second LIKE-escape site, the
bulk-inventory-API exposure, and 7 pre-existing PHPStan errors. Count reconciled: 69 verdict lines minus 8 FALSE
= 61 actionable (12 promotions + 21 inventory + 26 vouchers + 10 engagement), no discrepancy; 56 distinct issues
after DUP-merge. Disposition: 41 fixed (39 prior + 2 retry), 12 verified PASS/by-design, 5 accepted-residual or
out-of-scope with in-scope mitigation. Zero test conflicts.

filament-promotions (11 distinct; 6 fixed, 1 mitigated+blocked, 4 PASS):
- Issue-count clamped server-side 1-100 in both actions. Promotion options owner-scoped + limit(200). Form gaps:
  owner-scoped unique, minValue(0) money fields, deactivation via edit-page header action. Exception text replaced
  by generic message + report(). Nav badge cached in OwnerCache 30s. Widget full-table scan mitigated in-scope via
  owner-keyed 60s cached insights; engine root fix BLOCKED-out-of-scope in frozen promotions core. Nav group, table
  columns, and no-collection-sums verified PASS.

inventory (16 distinct; 14 fixed incl. 1 retry, 1 blocked, 1 accepted):
- Morph resolution allowlisted; NULL-owner unique via Cache::lock + 23000/23505 rescue + partial unique;
  nullableUuidMorphs on the reservation morph; level race via rescue + identity re-select; path/cycle/depth guards
  with chunked prefix-swap rebuild; deduction N+1 via loadMissing + single-query preload; PHP sums replaced by SQL
  SUM(CASE); ttlMinutes <= 0 throws; export path asserted safe; owner_* non-fillable with saving guards; direct
  on-hand writes auto-audited as movements; deleting cascades all child rows; reports use grouped aggregates +
  limits + cursors. LIKE injection: first site fixed by prior worker, second (criteria) site still raw — retry
  fixed with the same ESCAPE pattern + regression tests for both (NEW-054). Inbound-ID validation
  BLOCKED-out-of-scope (frozen/host models); mitigation: those IDs are never trusted for scoping. Alloc loop
  accepted residual (inherent, bounded, single txn). DEFAULT-location, suggestion-hook, and alloc-guard lines
  verified FALSE with evidence.

vouchers (20 distinct; 17 fixed incl. 1 retry, 3 accepted/by-design):
- Mass assignment stripped; full validateData; driver-guarded partial index + app-side lock/rescue; stale
  conditions removed per invalid code; session race via Cache::lock; Money currency whitelisted; wallet N+1 via SQL
  predicates + withCount + limit; double-redeem via row-lock transitionTimestampOnce; currency match asserted;
  null-idempotency widened + documented by-design (checkout/manual always supply one); cart totals fail-closed with
  debug log; wallet race via row lock + first-check + 23000 rescue; expire command explicit-global scan +
  per-owner writes; usages fast path + leftJoinSub + history limit. Percentage zero-value FIXED by retry:
  percentageRedeemDiscount recomputes from order subtotal via VoucherDiscountCalculator (caps honored), explicit
  amounts unchanged. Order-lookup and per-user-limits mitigated/accepted (owner-scoped counts; guest skip accepted
  for lack of stable guest identity); cache-bypass verified by-design; unscoped-update and wallet-delete lines
  verified FALSE.

filament-engagement (9 distinct; 2 fixed, 7 PASS):
- Bulk N+1 via BulkRecordProcessor::eachInChunks; guest TypeError via AuthenticatedUser::resolve() in all 8 actions.
  Filter-options line verified FALSE (frozen core models carry owner globals); owner UI scope, config nav group,
  no badge override, scalar columns, enum-only selects verified PASS.

No-change with evidence: 8 FALSE lines proven (inventory R2#7/R2#8/R2#22, vouchers R2#1 + AUD:B8/AUD:B10/AUD:Q#1
DUPs, engagement R2#23); 5 DUP lines merged; 12 PASS/by-design lines cited. Retry added 6 regression tests (all
passing; temp-revert proofs for the redeem fix and the LIKE fix) and exposed the prior worker's bulk availability
API on the Inventory facade docblock + usage docs with 2 tests, fully closing the products #36 follow-up
(backward-compatible pure addition).

Verification (supervisor re-ran, all match worker claims exactly):
- ./vendor/bin/pest --parallel tests/src/FilamentPromotions — PASS (46 passed, 106 assertions)
- ./vendor/bin/pest --parallel tests/src/Inventory — PASS (1159 passed, 6 skipped, 2605 assertions)
- ./vendor/bin/pest --parallel tests/src/Vouchers — PASS (922 passed, 7 skipped, 1798 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentEngagement — PASS (10 passed, 50 assertions)
- ./vendor/bin/pest --parallel tests/src/Checkout/VouchersAdapterTest.php — PASS (5 passed; frozen-consumer safety)
- ./vendor/bin/pint --test on the four packages — PASS (325 files) after supervisor fixed 2 style issues the retry
  worker missed (prior-worker's files): Collection import in BulkRecordProcessor, mb_* in InventoryLocation path
  rebuild; re-verified green (FilamentEngagement 10, hierarchy 36)
- ./vendor/bin/phpstan analyse on the four src dirs --level=6 — OK, no errors
- Supervisor renamed pre-existing 'NEW-001'/'NEW-002' serial literals (tracking-ID collision) to FRESH-* and
  re-ran the file green (30 passed); final tracking-ID sweep clean

Migration/schema: inventory reservation partial unique (pgsql/sqlite) + nullableUuidMorphs; vouchers wallet
partial index (driver-guarded). All canonical 2000_* edits, present-state only.

Covered findings: R2 promotions batch 61/61 (56 distinct after DUP-merge; 5 accepted/blocked lines mitigated).

ID map (transcribed from the worker report; descriptions above keyed to audit IDs):
- filament-promotions FIXED: R2#4 (issue-count clamp), #5 (scoped options + limit; AUD:B5 DUP), #19
  (form gaps), #24 (generic errors), AUD:B1/B3 (cached badge). PASS: AUD:B2 (nav), B4 (scalar
  columns), B6/B7 (no instances). Mitigated+blocked: R2#6 (cached insights live; engine root frozen).
- inventory FIXED: R2#9 (morph allowlist), #10 (NULL-unique lock+rescue), #11 (uuid morphs), #14
  (level-race rescue), #15 (path/cycle/depth), #17 (deduction preload), #20 (SQL sums), #21
  (LIKE ESCAPE both sites, NEW-054), #28 (ttl guard), #29 (export path), AUD:B1 (fillables), B2
  (ledger bypass), B3 (cascades), B6 (report aggregates). FALSE: R2#7, #8, #22. Blocked: AUD:B4
  (IDs never trusted for scoping). Accepted: AUD:B5 (bounded alloc loop).
- vouchers FIXED: R2#2 (mass assignment), #3 (validateData), #12 (partial index), #13 (stale
  conditions), #16+AUD:B4 (session lock), #25 (currency whitelist), #27 (wallet queries), AUD:B1+Q#29
  (redeem lock), B2 (percent recompute), B3 (currency assert), B5 (idempotency), B6 (fail-closed
  totals), B7 (wallet race), B12 (expire scan), B13/B14/B15 (usage fast paths). FALSE: R2#1 (+AUD:B8,
  Q#1 DUPs), AUD:B10. Accepted/by-design: AUD:B9 (scoped lookup), B11 (scoped counts), B16 (cache).
- filament-engagement FIXED: R2#18 (chunked bulk), #26 (guest guard). FALSE: R2#23. PASS: AUD:B1–B7.

Notes:
- Worker NEWs: NEW-054 (second LIKE-escape site, fixed), NEW-055 (PHPStan nullable-union false positive, fixed
  via repo-precedent single-line ignore).
- Products #36 bulk-inventory-API follow-up: fully closed this unit.

## PACKAGE COMPLETE — R2: filament-promotions+inventory+vouchers+filament-engagement

Findings completed: 61 / 61

Package verification (supervisor re-ran):
- FilamentPromotions 46, Inventory 1159 (+6 skipped), Vouchers 922 (+7 skipped), FilamentEngagement 10 — all PASS
- pint --test — PASS (325 files); phpstan level 6 — OK x4; Checkout/VouchersAdapterTest 5 — PASS

Outstanding:
- Promotion widget engine root fix: BLOCKED-out-of-scope (frozen promotions core; cached-insights mitigation live)
- Report inbound-ID validation: BLOCKED-out-of-scope (frozen/host models; IDs never trusted for scoping)
- Alloc loop: accepted residual (inherent, bounded, single txn)
- Order-lookup UUID-guess meta + guest per-user limits: accepted residuals (read-only/scoped; no stable identity)

---

### [DONE] R2: filament-tax+shipping+growth+filament-growth — all 55 (worker #40 repair, supervisor-verified)

Worker #40 (fresh unit, no resume; subagent result envelope unreadable via API — report recovered from the worker
session log). Count reconciled: 62 verdict rows = 40 unique + 9 dups + 6 info + 6 FALSE + 1 UNVERIFIED; 55
actionable = 40 + 9 + 6, no discrepancy. Outcome: 39 fixed, 1 blocked (pinned-test conflict), 6 FALSE verified,
1 UNVERIFIED (no site exists).

filament-tax (7 unique, all fixed):
- T1: policies registered + authorize() on all table actions and all 11 page files + scoped getEloquentQuery in
  all 4 resources. T2: settings save via getState() + authorized save action. T3: exemptable selects wrapped in
  OwnerUiScope. T4: cert unique owner-scoped via modifyRuleUsing. T5: OwnerWriteGuard in approve/renew/delete.
  T6/AUD:B4: N+1 eager loads on resource + widget. AUD:B3: badge cached in OwnerCache 30s. AUD:B5/B6 verified
  FALSE (limited search/lazy closures, relationship selects); AUD:B7 noted (4 uncached counts on small config
  tables, negligible, no action — see intake notes).

shipping (15 unique; 14 fixed, 1 blocked):
- S1 zone IDOR via isOwner() in all policy record methods. S2 tracking oracle via ULID refs + exact-match lookup.
  S3 RMA bypass via transitionTo in approve/reject. S4 per-event exists via whereIn batch fetch. S5 clearCache
  no-op via tracked-keys fallback. S6 postcode compare numeric. S7 location scope via owner-scoped getLocationById.
  S8 weight hydration via SQL sum. S9 op double-create via locked transaction (unique index deliberately declined,
  documented). S10 rate-key owner segment + bounded wait + fail-fast. AUD:B1 ghost waybill via DB::afterCommit.
  AUD:B3 truncation via half-up round. AUD:B6 fillable (no owner_*; mismatch guard kept). AUD:B9 fan-out via
  concurrency timeout + circuit breaker. AUD:B2 fail-open BLOCKED-test-conflict (ShippingRateTest pins fail-open
  for unknown condition types; rewriting that asserted contract prohibited). AUD:B8 UNVERIFIED (no triple-scan
  site; single create loop + SQL sum).

growth (12 unique, all fixed):
- G1 owner key read in both commands. G2 unbounded windows capped + truncated flag. G3 per-row queries via
  run-scoped caches. G4 Readable filter applied in resolve(). G5 canonical anon key shared between attribution
  and storage. G6 middleware fail-closed + log. G7 archive max(0) + chunkById. G8 pg-only CAST → driver-aware.
  G9 user-type filter. G10 timestamp/metadata touch skip. AUD:B1 owner resolve. AUD:B2 silent FX drop surfaced as
  excluded_revenue_minor/excluded_events.

filament-growth (6 unique, all fixed):
- FG1 kill switch gated on growth.settings.manage (hand-rolled page authz; no filament-authz dependency) + save
  authorize. FG2 sequential handles → single handleMany + empty guard. FG3/AUD:B5 options → lazy-search select,
  limit 50, experimentOptions() removed. FG4 raw queries wrapped in OwnerUiScope (results page, widget, variants
  table). FG5 bulk visible via Gate::allows('deleteAny'). AUD:B4 scoped fallback. AUD:B3/B6/B7 verified FALSE.

No-change with evidence: 6 FALSE + 1 UNVERIFIED proven; 9 DUP lines fixed via primaries; 6 info notes need no
action. Worker added 31 regression tests (18 filament-tax + 13 filament-growth, all passing), repaired two
pre-existing Growth test setups (guarded CheckoutSession attributes; assertions unchanged), fixed one Pint style
issue + two stale PHPStan docblocks, and updated package docs (ability table, bounded-metrics config, kill-switch
gate). Worker NEWs: NEW-056 (accessor-column search crash, fixed in tax; same pattern in 3 other packages, open),
NEW-057 (Spatie hasPermissionTo throw, fixed via Gate-only). Also fixed inline: growth settings unvalidated save
(same shape as T2) and VariantResource raw-query fallbacks (same class as FG4).

Verification (supervisor re-ran, all match worker claims exactly):
- ./vendor/bin/pest --parallel tests/src/FilamentTax — PASS (45 passed, 164 assertions)
- ./vendor/bin/pest --parallel tests/src/Shipping — PASS (555 passed, 1 skipped, 1385 assertions)
- ./vendor/bin/pest --parallel tests/src/Growth — PASS (172 passed, 518 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentGrowth — PASS (73 passed, 236 assertions)
- ./vendor/bin/pint --test on the four packages + four test dirs — PASS (341 files) after supervisor fixed 11
  style issues in worker-new test files (imports/mb_*, mechanical); all 4 suites re-ran green post-fix
- ./vendor/bin/phpstan analyse on the four src dirs --level=6 — OK, no errors
- Supervisor renamed the worker's two RoundTwoRegressionTest.php files to RegressionTest.php (repair-reference
  cleanup precedent; no collisions); zero tracking-ID leakage (supervisor grep sweep)

Migration/schema: none reported (policy/config/query-layer repairs only).

Covered findings: R2 tax batch 55/55 (40 unique: 39 fixed + 1 blocked; 6 FALSE + 1 UNVERIFIED verified).

Notes:
- Supervisor correction: unit denominator fixed 37 → 39 (39 table rows; earlier sessions miscounted).
- AUD:B7 tax-stats counts deliberately left uncached (negligible, indexed, 30s poll).

## PACKAGE COMPLETE — R2: filament-tax+shipping+growth+filament-growth

Findings completed: 55 / 55

Package verification (supervisor re-ran):
- FilamentTax 45, Shipping 555 (+1 skipped), Growth 172, FilamentGrowth 73 — all PASS
- pint --test — PASS (341 files); phpstan level 6 — OK x4

Outstanding:
- AUD:B2 shipping fail-open: BLOCKED-test-conflict (pinned fail-open for unknown condition types; needs a
  contract decision: fail-open-by-design vs fail-closed rewrite of the asserted test)
- NEW-056 residual: accessor-column search pattern in filament-pricing/filament-products/filament-organizations

---

### [DONE] R2: membership+moderation — all 33 (worker #41 repair, supervisor-verified)

Worker #41 (fresh unit). Count reconciled: 17 membership (13 R2 M1-M13 + 4 adopted) + 16 moderation (10 R2 D1-D10
+ 6 adopted) = 33, no discrepancy; 25 distinct issues after DUP-merge. Outcome: 31 fixed (code or docs),
2 verified already-fixed (composite uniques + lock/rescue, index names asserted green), 0 blocked, 0 pinned-test
contradictions.

membership (13 unique + DUPs, all fixed or verified):
- M1 re-invite deadlock: expires-if-due inside the locked txn, then fresh invite + event; new
  ExpireMembershipInvitationsAction + membership:expire-invitations command + scheduler docs (AUD:B2 DUP fixed via
  this: valid pendings keep documented no-resend, expired rows transition cleanly). M2: optional token proof via
  matchesToken(), docs prescribe passing it. M3: duplicate onMemberAdded removed; role-change suppresses via
  $notifyAdded=false. M4: explicit null checks throwing RuntimeException on orphans. M5: narrow
  RoleDoesNotExist catch (deliberately not hasRole(), which would break the pinned revocation-failure test). M6:
  optional actor recorded as new cancelled_by column + "Host Authorization" docs (enforced applicant-or-admin
  check deliberately declined: no HTTP/auth surface, would break console cancellations). M7: email/expiry/
  justification/meta/reviewer-note validation. M8: subject lockForUpdate + re-check in role-change AND removal so
  the two serialize. M9: joined_at on insert only. M10 + AUD:B4 DUP: per-connection/table static schema cache +
  flush helper. M11: "Deleting a Subject" docs (revoked_by unsettable in deleting hook). M12 + AUD:B1 + AUD:Q#1:
  fillable narrowed + Pending creating-default, internal writes to forceFill. M13: "Rate Limiting" docs
  (throttling host-owned). AUD:B3 + AUD:Q#2 composite uniques verified already-fixed (migrations + lock/rescue +
  InstallationTest green).

moderation (12 unique, all fixed):
- D1 mutable Carbon: CarbonImmutable::createFromInterface() conversion, signature still accepts CarbonInterface.
  D2: unknown reasons throw InvalidArgumentException (pinned action-level null→Other default untouched). D3:
  opt-in moderation.actors.allowed_types enforced in both traits + morph-alias resolution + docs (default empty
  preserves pinned behavior). D4: ?string $notes through contract → action → trait, persisted + capped. D5 +
  AUD:B2: transitionTo(..., ?Model $liftedBy) records lift attribution, re-activation clears it; stale-expires_at
  sub-claim addressed by the AUD:B3 scope fix instead (clearing on Active would break pinned expiry creation).
  D6: deleting-hook N+1 → single txn + chunkById(500). D7: hook unscoped via withoutGlobalScope(OwnerScope).
  D8 + AUD:B6: opt-in execute($now, withoutEvents: true) bulk path, default path unchanged. D9 + AUD:B4:
  idempotent re-block (returns existing active, extends expiry only when later, indefinite stays indefinite,
  blockable row locked). D10: reason/notes/metadata validation. AUD:B1: Block fillable narrowed, forceFill inside.
  AUD:B3: scopeExpired includes Active + past-due (scope tests still green). AUD:B5: by-design documented.

No-change with evidence: AUD:B3/AUD:Q#2 verified already-fixed (migrations + rescue + index assertions green).
Worker added 30 regression tests (17 membership + 13 moderation, neutrally named RegressionTest.php files, all
passing), adapted existing fixtures only for fillable narrowing (helper + forceFill / unguarded wraps, no pinned
assertion weakened), and proved 6 key fixes fail pre-fix via temporary revert. Worker NEW: NEW-058 (trait method
collision, fixed + covered).

Verification (supervisor re-ran, all match worker claims exactly):
- ./vendor/bin/pest --parallel tests/src/Membership — PASS (145 passed, 308 assertions)
- ./vendor/bin/pest --parallel tests/src/Moderation — PASS (74 passed, 302 assertions)
- ./vendor/bin/pint --test on the two packages + two test dirs — PASS (85 files)
- ./vendor/bin/phpstan analyse on the two src dirs --level=6 — OK, no errors
- Zero tracking-ID leakage (supervisor grep sweep); no supervisor fixes needed

Migration/schema: membership applications gained cancelled_by column (canonical migration + test schema).
No other schema changes.

Covered findings: R2 membership batch 33/33 (25 distinct after DUP-merge; 0 blocked).

## PACKAGE COMPLETE — R2: membership+moderation

Findings completed: 33 / 33

Package verification (supervisor re-ran):
- Membership 145, Moderation 74 — all PASS
- pint --test — PASS (85 files); phpstan level 6 — OK x2

Outstanding: none.

---

### [DONE] R2: seating+orders+filament-events+filament-inventory — all 60 (worker #42-retry repair, supervisor-verified)

Worker #42 (first attempt died to ENOSPC in early research with zero edits; retry started clean after the user
freed 7.5GB). Count reconciled: 62 verdict rows (18 seating + 19 orders + 14 filament-events + 11
filament-inventory) minus 1 UNVERIFIED (seating AUD:B6, UI lives in out-of-scope filament-seating) minus 1 FALSE
(filament-inventory AUD:B4, every resource uses with()) = 60, no discrepancy. Outcome: 51 repaired, 7
already-fixed verified present, 2 PASS-info no-action.

seating (14 repaired, 3 pre-fixed, 1 excluded):
- Double-hold race via retry + lockForUpdate re-check in hold action and allocator. Renderer N+1 via single eager
  load + in-memory bounds. Livewire full-venue load via MAX_SELECTION=100 cap + cached layout per map-version and
  section. Owner copied on hold→allocation convert + section allocation. Bulk insert owner guards via context
  assertion in both creation paths. Chunk option clamped 1..5000. Cascades chunked chunkById(500) on all three
  models. Model-level creating guards on holds + allocations. Livewire auth via rate-limit + seatable morph/model
  validation (selection stays UI-local, holds only via guarded actions). Allocator queries collapsed to a single
  query with CASE preference ordering + skip-locked. Convert locks + partial unique verified pre-fixed.

orders (15 repaired, 4 pre-fixed):
- Zero/negative payment rejected (amount>0, capped at balance due). Trusted totals/items via totals-invariant +
  item-data validation. Item discounts enforced folded per the documented contract. Policy owner-blindness via
  shared relation-authorization trait on all record methods. Intake NULL-unique race via Cache::lock block +
  owner-scoped key + 23000 fallback. Invoice number minted once and persisted with unique-rescue retry (column
  present in canonical migration). Default state Processing → Created. Identity TOCTOU via insert/update conflict
  conversion. Intake compare via mb_trim. Item total overwrite via negative rejection + max(0). Tenant fillables
  narrowed (owner_* removed from all four; status/timestamps consciously retained — load-bearing internal API, no
  HTTP surface, all 5 create sites use literal keys). Scope-disabled inherit via unscoped findOrFail + inherit on
  all three child models. Intake oracle via scope-local forOwner. Txn fresh-read moved outside (per-row creates
  deliberately retained: inherit guard, totals, activity logs).

filament-events (11 repaired, 1 pass, 2 no-instance):
- Record actions moved from headerActions into actions() on all three resources (headers keep import/export).
  Importer cross-owner IDs via OwnerWriteGuard in beforeCreate (sessions + registrations). Unscoped address
  attach scoped. setForRequest(null) removed from all 8 pages. Approval queue authorized + workflow-driven with
  location revalidation. Slug unique owner-scoped on events; occurrence/session global unique deliberately
  retained — those tables intentionally lack owner columns (ScopesByEventOwner via parent event), and owner
  wheres would silently neuter on SQLite (NEW-059); locked by test. LIKE injection via addcslashes + explicit
  ESCAPE. Participant scope via canonical registration.event path. Badge cached 30s. Residual N+1 eager-loaded.
  Event select via lazy search (limit 50).

filament-inventory (9 repaired, 1 pass, 1 false, 1 no-instance):
- Disabled-field read replaced by server-side recompute. Badges cached 30s on all four resources. Widget query
  limits replaced by defaultPaginationPageOption(10). Stats stampede via Cache::flexible. Infolist queries via
  withCount/withSum on the route-binding query + preloaded-attr reads. Location plucks replaced by 6 lazy
  selects. AUD:B4 verified FALSE (with() everywhere).

No-change with evidence: 7 already-fixed lines verified present + green; 2 PASS-info (navigation); 1 FALSE +
1 UNVERIFIED excluded with rationale. Worker added 17 regression tests (all passing) and updated 8 existing test
files where they pinned buggy behavior — supervisor reviewed every diff: action-placement assertions strictly
stronger, aggregator seeding fixed test-side, default-state pins updated to the fixed behavior, policy tests
wrapped in explicit-global, invoice schema setup + coherent totals fixtures. No assertion weakened. Worker NEWs:
NEW-059 (SQLite double-quoted-string trap, reverted + locked by test).

Verification (supervisor re-ran, all match worker claims exactly):
- ./vendor/bin/pest --parallel tests/src/Seating — PASS (92 passed, 194 assertions)
- ./vendor/bin/pest --parallel tests/src/Orders — PASS (371 passed, 902 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentEvents — PASS (22 passed, 203 assertions)
- ./vendor/bin/pest --parallel tests/src/FilamentInventory — PASS (47 passed, 182 assertions)
- ./vendor/bin/pint --test on the four packages + four test dirs — PASS (386 files)
- ./vendor/bin/phpstan analyse on the four src dirs --level=6 — OK, no errors
- Zero tracking-ID leakage (supervisor grep sweep); no supervisor fixes needed

Migration/schema: none (invoice_number column already in canonical migration; all other repairs query/policy/
config layer).

Covered findings: R2 seating batch 60/60 (51 repaired + 7 pre-fixed + 2 info; 1 FALSE + 1 UNVERIFIED excluded).

Count note: the audit's 62 section rows break down as 27 ADOPTED + 25 CONFIRMED + 1 DOWNGRADED (= 53
verdict-actionable) + 7 FIXED + 1 FALSE + 1 UNVERIFIED. The worker counted the 7 already-fixed lines
(convert locks + partial unique, orders cached totals) as completed work rather than excluding them, hence
60 = 53 actionable + 7 FIXED-verified. Conservative direction, label kept; file-wide composition is 1248
assigned verdict-actionable + 7 appendix lines = 1255.

ID map (transcribed from the worker report; descriptions above keyed to audit IDs):
- seating FIXED: R2#1 (hold race; AUD:B2 DUP), #2 (renderer eager-load), #3 (Livewire caps; AUD:B7
  DUP), #4 (owner inherit), #5 (bulk-insert guards), #6 (chunk clamp), #7 (chunked cascades; AUD:B4
  DUP), AUD:B3+Q#2 (model guards), B5 (Livewire auth), B8 (allocator query). Pre-fixed verified:
  AUD:B1+Q#1+Q#3 (convert locks). Excluded: AUD:B6 UNVERIFIED (UI in out-of-scope filament-seating).
- orders FIXED: R2#8 (payment bounds), #9 (totals invariant), #10 (discount folding), #11 (policy
  trait), #12 (intake lock), #13 (invoice persist), #14 (default Created), AUD:B3 (identity TOCTOU),
  B4 (intake compare), B5 (item totals), B6+Q#2 (tenant fillables), B7 (child inherit), B8 (intake
  oracle), B10 (txn read hoist). Pre-fixed verified: AUD:B1, B2, B9, Q#1 (totals + cached cols).
- filament-events FIXED: R2#15 (action placement), #16 (importer guards), #17 (address scope), #18
  (owner wipe removed), #19 (approval auth), #20 (event slug scoped; child global retained, NEW-059),
  #21 (LIKE ESCAPE), AUD:B1 (participant path), B3 (badge cache), B4 (eager-loads), B5 (lazy
  select). PASS/no-instance: AUD:B2, B6/B7.
- filament-inventory FIXED: R2#22 (server recompute), #23+AUD:B3 (badge caches), #24 (widget
  pagination), #25 (flexible cache), AUD:B1/B7 (infolist aggregates), B5 (lazy selects). PASS: AUD:B2.
  FALSE: AUD:B4 (with() everywhere).

## PACKAGE COMPLETE — R2: seating+orders+filament-events+filament-inventory

Findings completed: 60 / 60

Package verification (supervisor re-ran):
- Seating 92, Orders 371, FilamentEvents 22, FilamentInventory 47 — all PASS
- pint --test — PASS (386 files); phpstan level 6 — OK x4

Outstanding: none.

---

# NEW FINDINGS DISCOVERED DURING REPAIR

### NEW-001 — commerce-support — high — CommerceHealthWidget vs ResultStore contract

Location: packages/commerce-support/src/Filament/Widgets/CommerceHealthWidget.php:getHealthResults()

getHealthResults() iterated latestResults() directly and read
->check/->status/->shortSummary/->meta/->ended_at, but spatie/health
1.40 returns ?StoredCheckResults (non-traversable; rows live in
->storedCheckResults as StoredCheckResult with name/label/
notificationMessage/shortSummary/status/meta). With real stored results
the widget iterated public props and hit mapStatus(null) -> TypeError
(500). Fixed in the R1:#13 repair (correct mapping, label fallback to
formatted name); covered by the new single-read/contract test.
FIXED as part of the current repair (inseparable).

### NEW-002 — authz — medium — Blade directives silently unregistered (N1)

Location: packages/authz/src/AuthzServiceProvider.php (directive registration)

`afterResolving('blade.compiler', ...)` never fires when the compiler
resolved before provider boot (Filament boots views early), leaving
@canBeImpersonated etc. unregistered (the R1:#10 fix would have been
inert). Worker fixed with `callAfterResolving`. FIXED during authz pass.

### NEW-003 — authz — low — CommandProhibitor::reset() dropped boot registrations (N2)

Location: packages/authz/src/Support/CommandProhibitor.php

The new reset() test helper wiped self::$commands, dropping
filament-authz's boot-time registrations and silently unprotecting its
commands on later prohibitDestructiveCommands(true). Worker fixed reset()
to keep registrations. FIXED during authz pass.

### NEW-004 — contacting — medium — documented snake_case arrays silently dropped multi-word flags

Location: packages/contacting/src/Data/ContactMethodData.php,
packages/contacting/src/Data/SocialProfileData.php (spatie input mapping)

docs/04-usage.md documents array creates with snake_case keys
('is_primary' => true), but spatie/laravel-data maps input names
verbatim by default, so from()/addContactMethod(array) ignored every
multi-word key (is_primary/is_public/is_verified/country_code/...) and
silently applied defaults. Fixed inline during the contacting pass with
#[MapInputName(SnakeCaseMapper::class)] on both DTOs; pinned by the
documented-array-shape regression test. No camelCase-array callers exist
in-repo (verified by grep), so the mapping change breaks nothing.

### NEW-005 — cart — medium — merged_into_id attribution never persists (worker-found, FIXED in blocked-inventory Wave 2 U8)

Location: cart guest→user migration paths (markSourceCartAsMerged + forget)

Both migration paths markSourceCartAsMerged() then immediately forget()
the marked row, so the mark dies with the row even after the U14 ordering
fix. Follow-up: convert the source cart to a tombstone instead of
deleting it, or remove the merged_into_id mechanism.

Resolution: U8 tombstones the source cart in both migration paths
(clearAll + expired_at, row and merged_into_id kept); covered by
MigrationTombstoneTest (2 tests); full Cart suite green.

### NEW-006 — cart — low — remember-me/social/API logins never migrate guest carts (worker-found, ACCEPTED-limitation)

Location: cart login listeners (Login without Attempting)

Migration triggers only on Attempting→Login pairs; remember-me, social,
and API-token logins (Login without Attempting) never migrate guest
carts. Pre-existing limitation, preserved by the session-stash redesign.
User-accepted as a documented limitation in the blocked-inventory batch;
reopen only if guest-cart loss on those login paths is reported.

### NEW-007 — cart — low — shared-harness InMemoryStorage diverges from hardened swap contract (worker-found, FIXED in blocked-inventory Wave 2 U8)

Location: tests/Support/Cart/InMemoryStorage.php (shared harness, out of worker scope)

Still overwrites on swap conflict while the hardened StorageInterface
contract refuses occupied targets. Update swapIdentifier() to refuse
occupied targets when the harness is next touched.

Resolution: U8 mirrored the hardened contract in the shared harness
(refuse occupied, same-id no-op); covered by InMemoryHarnessSwapTest
(3 tests).

### NEW-008 — cashier-chip — medium — fully-discounted ($0) renewals marked past_due (worker-found, FIXED in blocked-inventory Wave 1 U5)

Location: cashier-chip renewal claim path (amount-bounds validation)

$0 renewals now fail amount-bounds validation and land in past_due.
Needs a product decision (auto-complete $0 renewals vs host handling);
documented in 09-subscriptions.md, not implemented (unrequested behavior).

Resolution: user approved auto-complete in the blocked-inventory batch (Q12).
U5 implements it in RenewSubscriptionsCommand (amount_minor === 0 →
recordSuccess + 'renewed', skipping bounds/payment-method checks);
supervisor verified in code.

### NEW-009 — cashier-chip — low — second distinct purchase for settled period rolls back silently (worker-found, FIXED in blocked-inventory Wave 1 U5)

Location: cashier-chip webhook settle path (purchase_id dedup)

A second *distinct* purchase for an already-settled period rolls back
without extending — safe against gateway double-charges, but the money
needs host reconciliation. No alerting/reconciliation hook exists.

Resolution: U5 added the reconciliation hook in the blocked-inventory batch
(Q13): SyncChipPurchaseStatus logs a `CHIP settled-period purchase rolled
back; host reconciliation required.` warning with context; supervisor
verified in code.

### NEW-010 — communications — high — inbound creation always crashed on non-null rendered_at (worker-found, FIXED)

Location: packages/communications/src/Actions/ReceiveInboundCommunicationAction.php
(migration 000005 communication_contents.rendered_at)

The action never stamped non-nullable `rendered_at`, so every inbound
creation crashed. No prior test covered inbound creation; the worker's
R1:#15 test exposed it. Fixed inline (stamp rendered_at), covered by
`R1#15…` in RepairRegressionTest.php.

### NEW-011 — communications — medium — ApplyProviderEventAction broken with owner scoping disabled (worker-found, FIXED)

Location: packages/communications/src/Actions/ApplyProviderEventAction.php

Unconditional OwnerWriteGuard threw InvalidArgumentException when owner
scoping is off. Fixed inline with an owner-aware finder inside U5. No
dedicated test (suites run owner-enabled); noted for a follow-up worker.

### NEW-012 — communications — medium — unguarded inbound IDs in 9 unnamed actions (worker-found, FIXED in blocked-inventory Wave 1 U4)

Location: RecordProviderEventAction (3 IDs), RecordNotificationSendingAction,
RetryCommunicationDeliveryAction, CancelCommunicationDeliveryAction,
CompleteDeliveryAttemptAction, RecalculateCommunicationStatusAction,
RedactCommunicationPayloadAction, AttachCommunicationReferenceAction,
CancelCommunicationAction

Same unguarded-findOrFail pattern as AUD:B2, but in actions the audit did
not name. Left unchanged for scope discipline. Recommend a follow-up unit
applying the U26 owner-aware guard pattern to all nine.

Resolution: FIXED in blocked-inventory Wave 1 U4 — all nine actions
(including the 3-ID RecordProviderEventAction) now resolve inbound IDs via
OwnerWriteGuard::findOrFailForOwner; supervisor verified each call site in
code; Communications suite 371 passed.

### NEW-013 — commerce-support — high — webhook dedup unique lacks owner dimension (worker-found, FIXED in blocked-inventory Wave 2 U9)

Location: packages/commerce-support/database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub
(UNIQUE(name, event_id, event_type), added during commerce-support R1:#2 repair)

Two owners' identical provider events collapse into one dedup row, so the
second owner's legitimate event is dropped without side effects. Broke
pinned test ChipWebhookOwnerResolutionTest:261 on the clean tree too.
Chip works around it via an extractEventId() override that owner-qualifies
the key. Root fix (owner dimension in the unique, or a documented override
contract for consumers) is supervisor-owned and still open.

Resolution: U9 added the owner dimension (owner_hash in the unique:
stub 000004 updated + additive migration 000006, supportsOwnerDedup
feature detection, Chip extractEventId override intact); covered by
WebhookOwnerDedupTest incl. the two-owner same-event case. Follow-up
fallout NEW-063 (stamped duplicates) fixed by supervisor in intake.

### NEW-014 — chip — medium — logResponse TypeError on non-array JSON bodies (worker-found, FIXED)

Location: packages/chip/src/Clients/Http/BaseHttpClient.php

Once R1:#13 routed the public-key fetch through the pipeline, string
(non-array) JSON bodies TypeErrored in logResponse(). Fixed inline with
an is_array guard, covered by `Repair R1#13`.

### NEW-015 — csuite — low — curated-bundle docs drift cluster, hand-duplicated snippets stale (worker-found, FIXED in blocked-inventory Wave 2 U7)

Location: packages/csuite/docs/03-configuration.md (mostly), 02-installation.md;
commerce-support SetupCommand for the wizard half.

Seven verified drift spots sharing one root cause (hand-duplicated config
snippets with no generation step), left untouched for surgical scope
(none trace to the 8 assigned findings):
1. vouchers snippet shows `vouchers`/`voucher_usages`; real config has
   `vouchers`/`voucher_usage`/`voucher_wallets` + `table_prefix` +
   `json_column_type`; `code` omits `auto_uppercase`.
2. CHIP snippet + env table show flat brand/secret/mode/webhook vars;
   real config uses `environment`/`collect[]`/`send[]`/`owner`/`http`/
   `webhooks` with `CHIP_ENVIRONMENT`, `CHIP_COLLECT_*`, `CHIP_SEND_*`.
3. JNT env shows read-nowhere `JNT_API_KEY`/`JNT_API_URL`; real vars are
   `JNT_ENVIRONMENT`/`JNT_API_ACCOUNT`/`JNT_PRIVATE_KEY`/`JNT_CUSTOMER_CODE`/
   `JNT_PASSWORD`/`JNT_BASE_URL_*`.
4. docs snippet shows 3 tables + `company`/`numbering`/`storage`; real
   config has 14 tables + `defaults`/`payment_methods`/`owner`/`email`/
   `einvoice`/`types`.
5. filament-cart/vouchers/docs snippets use flat `navigation_group`
   (forbidden by .ai/filament) with wrong groups/sorts; real configs use
   nested `navigation.group` + `features.*` flags.
6. commerce-support `commerce:setup` wizard writes the same stale
   `CHIP_*`/`JNT_*` vars no config reads (SetupCommand.php:42-100) —
   needs a coordinated docs+wizard fix owned by commerce-support.
7. 02-installation plugin snippet omits `FilamentJntPlugin` though
   filament-jnt is bundled (info).

Resolution: U7 corrected all 7 spots against the real configs plus the
coordinated SetupCommand wizard fix; covered by SetupWizardEnvKeysTest.

### NEW-016 — customers — medium — getSegmentStats active_count always 0 (worker-found, FIXED)

Location: packages/customers/src/Services/SegmentationService.php
(old getSegmentStats, ~line 208)

Collection `->where('status','active')` compared the cast
`CustomerStatus` enum against a string with `==`, which never matches.
Fixed by the R1#7 aggregate rewrite
(`where('status', CustomerStatus::Active)`); regression test asserts
`active_count=2`.

### NEW-017 — customers — medium — applyConditions ignored value_status key (worker-found, FIXED)

Location: packages/customers/src/Models/Segment.php (applyConditions)

The query path ignored the `value_status` key that `evaluateCondition()`
and the Filament form support, so `value_status`-only conditions
matched-all via query but evaluated in-memory. Unified within the R1#6
fix; covered by the R1#6 regression tests.

### NEW-018 — docs — low — worker notes trio (body-type contract, postgres retry, EditDoc divergence) (FIXED in blocked-inventory Wave 2 U7; part 2 accepted earlier)

Location: packages/docs/src/Services/DocService.php + Doc::casts();
DocSequence::generateNumber / DocService::createVersion; filament EditDoc.

1. `update()` accepts string `body` while `Doc` casts `body` to array —
   strict validation would break filament's RichEditor HTML submits.
   Needs a filament-docs contract decision (Tiptap JSON vs HTML).
2. Unique-retry paths assume MySQL/SQLite semantics — on Postgres a
   caught statement failure aborts the transaction, so reselect-retry
   would not help. No change (drivers in use are MySQL/SQLite); accepted
   in the blocked-inventory batch, reopen only if Postgres is adopted.
3. Filament `EditDoc` still submits now-ignored `doc_number`/`doc_type`/
   totals fields — cosmetic divergence for the filament-docs worker.

Resolution: U7 fixed parts 1 (update() accepts string|array body via
normalizeBody; DocBodyUpdateTest) and 3 (ignored fields filtered
server-side + EditDoc aligned; DocFormImmutableFieldsTest). Part 2
(Postgres retry) stays accepted; reopen only if Postgres is adopted.

### NEW-019 — engagement — low — re-added collection items stuck removed (worker-found, FIXED)

Location: packages/engagement/src/Services/DefaultEngagementManager.php
(addBookmarkToCollection)

`firstOrCreate` never cleared `removed_at` when re-adding a removed
item. Fixed in the R1:#4 repair (re-add clears `removed_at`),
covered by `[R1#4] keeps collection items…`.

### NEW-020 — engagement — low — usage docs showed rejected reminder shapes (worker-found, FIXED)

Location: packages/engagement/docs/04-usage.md

Reminders example used anchorless `offset_minutes` (now correctly
rejected) and a `channels` key the code never reads (`channel` is
canonical). Fixed in the docs pass alongside U3/U5.

### NEW-021 — filament-addressing — low — troubleshooting pointed at nonexistent tests path (worker-found, FIXED)

Location: packages/filament-addressing/docs/99-troubleshooting.md

Verification commands referenced `packages/filament-addressing/tests`
(which does not exist). Corrected to `tests/src/FilamentAddressing`
(docs only).

### NEW-022 — filament-addressing — low — CONTEXT surfaces listed deleted types (worker-found, FIXED)

Location: packages/filament-addressing/CONTEXT.md

"Key surfaces" still listed deleted `GuardsAddressingUi` /
`ResolvesAddressingResources`. Updated to `AddressingFilterOptions`.

### NEW-023 — authz — medium — Role::create discards explicit team_id (worker-proven, FIXED in blocked-inventory Wave 2 U6)

Location: packages/authz/src/Models/Role.php (create override vs
authz.scopes.enforce ambient getPermissionsTeamId())

When `authz.scopes.enforce` is on (default true), `Role::create()`
overwrites a caller-supplied `team_id` with the ambient permissions
team id. Proven by the events worker with a planted-team probe
(planted `…43f8` stored as ambient `…43e2` → lookup miss → silent
no-op → AssignmentRequestActionsTest:132 failure). Events tests
adapted in-scope (direct `Role::query()->create()` planting). Open
question for an authz follow-up: should explicit `team_id` really be
discarded, or should explicit win / mismatch throw? Authz package
itself stays COMPLETE (its 25 tests pin current behavior).

Resolution: supervisor decision — explicit caller team_id wins over ambient.
U6 implemented it (ambient fills only when the key is absent, plus a
teams-aware duplicate check); covered by the added regression test;
Authz suite green (26).

### NEW-024 — events — medium — events:finalize-orders never registered (worker-found, FIXED)

Location: packages/events/src/EventsServiceProvider.php

The `events:finalize-orders` command did not exist at runtime (never
registered via `hasCommand`). Registered in the R1:#10 repair;
covered by the command-context regression tests.

### NEW-025 — events — low — stale capacity config list in docs (worker-found, FIXED)

Location: packages/events/docs/03-configuration.md

Documented a stale `capacity_blocking_statuses` list (`no_show`
instead of `refund_pending`). Fixed while editing that section.

### NEW-026 — feedback — medium — normalizeChoice array-to-string 500 (worker-found, FIXED)

Location: packages/feedback/src/Support/AnswerValueNormalizer.php
(normalizeChoice default branch)

Array values (matrix/likert) hit `(string)$value` in the default
branch (array-to-string 500). Fixed in the R1:#8 repair with an
explicit matrix/likert branch (implode); covered by the scalar+array
acceptance tests.

### NEW-027 — feedback — low — avg() return violates ?float (worker-found, FIXED)

Location: packages/feedback/src/Traits/ReceivesFeedback.php
(averageFeedbackScore)

Bare `avg()` return violates the `?float` signature under strict
types on drivers returning numeric strings. Fixed with float casts
on both paths.

### NEW-028 — filament-cashier-chip — low — null-unsafe Discount visible closure (worker-found, FIXED)

Location: packages/filament-cashier-chip/src/Resources/SubscriptionResource/Schemas/SubscriptionInfolist.php:162

Discount section `visible(fn (Subscription $record)…)` TypeErrors
with null record — same bug class as R1#13. Fixed null-safe during
the R1#13 repair; covered by the null-record regression test.

### NEW-029 — filament-authz — low — missing impersonate.started_message lang key (worker-found, FIXED)

Location: packages/filament-authz/resources/lang/en/filament-authz.php

Controller flashed the raw `started_message` key (missing from
lang file). Key added during the R1#1 repair.

### NEW-030 — filament-authz — low — adapter backTo sanitizers weaker than core (worker-found, FIXED)

Location: packages/filament-authz/src/Http/Controllers/ImpersonateController.php,
packages/filament-authz/src/Actions/LeaveImpersonationAction.php

Adapter-local sanitizer copies lacked the repaired core's
backslash/control-char rejection. Migrated both call sites to core
`BackToUrlSanitizer` during the R1#1 repair.

### NEW-031 — filament-authz — low — resource sections ignored general checkboxListColumns (worker-found, FIXED)

Location: packages/filament-authz/src/Forms/Components/PermissionTabFactory.php

Resource-section columns ignored general `checkboxListColumns()`
(always-set resource default won). Fixed inside the R1#5 repair:
chain is now resource → general → config.

### NEW-032 — filament-authz — low — getPermissionCase code/docs default mismatch (worker-found, FIXED in blocked-inventory Wave 2 U6)

Location: packages/filament-authz/src/FilamentAuthzPlugin.php, docs/03-configuration.md

`getPermissionCase()` code default is `'snake'` but the docs table
says `'camel'` (and the builder default is `'camel'`); the getters
have no internal readers. Needs an intent decision before aligning;
left untouched as out of scope.

Resolution: U6 aligned the code default to 'camel' (docs + builder
agreed 2v1; getters have no internal readers); covered by 2 added
plugin tests.

### NEW-033 — affiliate-network — high — applicationStatusForOffer enum TypeError (worker-flagged, supervisor FIXED)

Location: packages/affiliate-network/src/Services/OfferManagementService.php:115-130

`->value('status')` on the enum-cast column hydrates via `first()`
(Eloquent Builder applies casts), returning `ApplicationStatus`
from a `?string` function → TypeError whenever a network
application row exists (marketplace blade crashed for any user who
applied). Fixed with enum→string normalization; 2 regression tests
added (status string + null case). Red-proofed: pre-fix run fails
with TypeError (28/29), post-fix 29/29; full AffiliateNetwork suite
240 green; PHPStan clean.

### NEW-034 — filament-cart — low — AbandonedCartsWidget deprecated actions() (worker-found, supervisor FIXED)

Location: packages/filament-cart/src/Widgets/AbandonedCartsWidget.php:62

Last remaining deprecated `->actions()` call in the package
(siblings migrated under U1/U7). Migrated to `->recordActions()`;
FilamentCart suite still 202 green.

### NEW-035 — cart — medium — dirty-gate skipped stale child-row cleanup (supervisor-found via filcart suite, FIXED)

Location: packages/cart/src/Snapshots/NormalizedCartSynchronizer.php:123-131

The U25 (AUD:B9) optimization skipped `syncItems`/`syncConditions`
when the snapshot header was unchanged — but orphan child rows then
survived `syncFromCart(empty)`, breaking the convergence contract
(`removes deleted items and conditions` failed 1 vs 0). Fixed with
orphan-row existence checks that force child sync on existing rows;
the previously-red filcart test is the regression proof (now green,
202/202) and the cart suite shows no regression (1070 green).

### NEW-036 — test-infra — medium — not->toThrow vacuous on interface names (worker-found, supervisor refined + FIXED 4/5)

Location: tests/src/FilamentCashierChip/Feature/RepairRegressionTest.php:420,:627,
tests/src/CommerceSupport/OwnerCacheTest.php:223,
tests/src/Membership/Unit/MembershipSubjectGuardTest.php:14, (+1 deferred below)

Worker proved `expect(throwing)->not->toThrow(Throwable::class)`
passes. Supervisor refined via vendor read + throwaway probes
(probes deleted): class names (Exception, RuntimeException)
discriminate correctly; only INTERFACE names are vacuous, because
`class_exists('Throwable')` is false so `toThrow()` takes the
message-substring branch, its assertion fails, and
`OppositeExpectation::__call` swallows that failure into a pass.
Fix: explicit try/catch + `toBeNull()` (pattern red/green-proven
once via probe: quiet passes, throwing fails). Applied to 4 sites
(suites re-verified: FilamentCashierChip 125, OwnerCacheTest 14,
MembershipSubjectGuard 1). 5th site
tests/src/FilamentCustomers/Feature/SegmentRebuildAuthorizationTest.php:65
applied at #31 intake (file was worker-unmodified; FilamentCustomers still 54 green).
NEW-036 now 5/5 FIXED.

### NEW-037 — docs — medium — owner_scope never synced, global slug lock (worker-flagged, supervisor FIXED)

Location: packages/docs/src/Models/DocTemplate.php, DocEmailTemplate.php (creating hooks)

`doc_templates`/`doc_email_templates` carry `UNIQUE(owner_scope, slug)`
but nothing wrote `owner_scope` (always `'global'`), so the unique was
effectively global: cross-owner slug reuse passed the new H4
owner-scoped validation then 500'd at the DB. Supervisor fix (chosen
over the worker's suggested `(owner_type, owner_id, slug)` unique,
which would break the pinned global-dup backstop on MySQL NULL
semantics): sync `owner_scope` from `OwnerScopeKey::forAttributes()`
in `creating` (creating, not saving — HasOwner auto-assigns in its own
creating hook which registers first; owner tuples immutable after).
No migration change needed. Regression test added (cross-owner reuse
OK, same-owner dup throws); red-proofed (pre-fix: owner-B create
throws UniqueConstraintViolationException). Full Docs suite 213 green;
PHPStan clean (added @property annotations).

### NEW-038 — filament-docs — low — getTotalPaid over-restricts with non-Paid rows (worker-found, FIXED in blocked-inventory Wave 2 U7)

Location: packages/filament-docs/src/Actions/RecordPaymentAction.php (getTotalPaid)

Sums all payment statuses while the recorder enforces Paid-only, so
the form `maxValue` can over-restrict when non-Paid rows exist. Kept
out as surgical-scope; needs a small follow-up.

Resolution: U7 filtered getTotalPaid to Paid-only; covered by
RecordPaymentOutstandingTest with mixed-status rows.

### NEW-039 — docs — low — DocPaymentRecorder ignores submitted paid_at (worker-found, FIXED in Q14 revisit)

Location: packages/docs (DocPaymentRecorder stamps now(); UI offers Payment Date picker)

Needs a docs-core decision (honor input vs remove picker) + follow-up.

Resolution: user chose HONOR INPUT in the Q14 revisit (against the
audit-consistent recommendation to remove the picker; backdating is now
a user-accepted capability). DocPaymentRecorder honors a submitted
paid_at (CarbonImmutable|string|DateTimeInterface, invalid rejects),
defaulting to now() when absent; the filament picker (already capped
at today, already passing the value through) needed no change.
Covered by DocPaymentRecorderPaidAtTest (3 tests) + the rewritten
RegressionTest pin (submitted sub-year date stored as given; status
override still forced to Paid).

### NEW-040 — filament-contacting — low — re-import crash on customers unique backstop (worker-found, FIXED)

Location: packages/filament-contacting/src/Imports/* (saveRecord override)

Re-imports of customer emails crashed on the customers-package unique
backstop as blank failed rows (R1:#13's "duplicates every row" premise
was inaccurate for this case). Worker converts QueryException (23000 →
"already exists…") to RowImportFailedException with a descriptive
message; covered by the R1:#13 test. Verified in the 36-green suite.

### NEW-041 — filament-contacting — low — phantom features.snapshots doc key (worker-found, FIXED)

Location: packages/filament-contacting/docs/03-configuration.md

Documented a `features.snapshots` key that doesn't exist in config.
Bullet removed while documenting real flag semantics.

### NEW-042 — orders/checkout — high — doc strictness broke order-doc totals (supervisor-found via smoke suite, FIXED)

Location: packages/orders/src/Actions/Concerns/BuildsOrderDocs.php,
tests/src/Orders/OrderAddresslessInvoiceTest.php,
tests/src/Orders/OrderDocsIntegrationTest.php

Docs R1#9 (strict explicit-totals validation) landed after checkout
completed, silently breaking order→doc generation: `buildItems()`
emits shipping as a document line but `subtotalMinor` was the
shipping-excluded order subtotal, so every shippable order 500'd in
DocService (found red 3x: subtotal 12000 vs 12500). Fix: supply the
lines-derived subtotal (subtotal + shipping) and derive
`taxRateBasisPoints` from the order so doc-side math reproduces the
order tax/total. Also repaired 3 incoherent fixtures (random factory
totals + overridden subtotals; 2 addressless + 1 integration) with
coherent values — address/event assertions untouched. Proof:
DocumentsDispatchedEventTest 4/4, orders suite 336/336 green; Pint +
PHPStan clean. Pre-existing failure count went 2 (checkout) → 1,
remaining 1 being #36's live products edit (re-verify at intake).

### NEW-043 — checkout/products — medium — products friendly duplicate exception broke createOrFirst idempotency (supervisor-found via checkout suite, FIXED)

Location: packages/checkout/src/Actions/EnsureCheckoutOfferProduct.php:28,
packages/products/src/Concerns/EnforcesOwnerUniqueIdentity.php:68

Worker #36's P13 fix (honest InvalidArgumentException from a creating-hook
friendly pre-check) broke EnsureCheckoutOfferProduct::handle idempotency:
`createOrFirst` rescues only DB unique violations, so the second ensure of
the same slug threw instead of returning the existing row (checkout suite
305 passed + 1 failed). Products' behavior is deliberate and pinned, so the
fix went into checkout: rescue InvalidArgumentException around the product
createOrFirst and refetch by slug — rethrowing when no row exists so genuine
validation errors still surface. Proof: EnsureCheckoutOfferProductTest 3/3,
full checkout suite 306/306 (1105 assertions); pint + phpstan clean.
Follow-up: the pricing-batch worker must keep Price/PriceList createOrFirst
idempotency working (same action relies on it).

### NEW-044 — signals — low — list-shaped properties hit string type-hint (worker-found, FIXED)

Location: packages/signals/src/Services/SignalPropertyFilter.php

List-shaped properties (`{"properties": [1,2,3]}`) hit a `string $key`
type-hint and 500'd with TypeError. Worker #34 fixed with an
`is_string($key)` guard in the shared filter; regression test added.
Verified via signals suite 122 green at intake.

### NEW-045 — signals — low — max_string_bytes key/value checks used mb_strlen (worker-found, FIXED)

Location: packages/signals/src/Support/SignalsIngestionRequestValidator.php

Same S8 root cause beyond the cited lines: the key/value byte checks also
used `mb_strlen`. Worker #34 fixed to `strlen()`; covered by the
multibyte-cap test. Verified via signals suite 122 green at intake.

### NEW-046 — jnt — high — metadata-email recipient crashed queued delivery (worker-found, FIXED)

Location: packages/jnt/src/Listeners/SendShipmentNotifications.php,
packages/jnt/src/Notifications/ShipmentEmailRecipient.php (new)

The metadata-email recipient was an anonymous class, but delivery
notifications implement ShouldQueue — every metadata-email notification
crashed on queue workers (`serialize()` on anonymous class). Worker #35
fixed with a named serializable recipient (pinned `->email` shape +
`getKey()` kept); regression test covers the serialize round-trip.
Verified via jnt suite 596 green at intake.

### NEW-047 — jnt — low — swapped invalidFieldValue args (worker-found, FIXED)

Location: packages/jnt/src/Webhooks/JntWebhookProfile.php,
packages/jnt/src/Data/WebhookData.php

Swapped args produced inverted validation messages. Worker #35 fixed while
adding caps. Verified via jnt suite 596 green at intake.

### NEW-048 — filament-organizations — high — member action closures uninjectable, all member management 500'd (worker-found, FIXED)

Location: packages/filament-organizations/src/Resources/OrganizationResource/RelationManagers/*.php,
docs/04-usage.md

All five relation-manager action closures (`addMember`, `changeRole`,
`remove`, `invite`, + the new `revoke` pattern) type-hinted
`Organization $organization`, which Filament cannot inject — every
member/invitation action threw BindingResolutionException at runtime
(proven empirically; member management was entirely non-functional).
Worker #36 fixed via `$livewire->getOwnerRecord()` + `ownerOrganization()`;
new tests fail pre-fix (4x BindingResolutionException) and pass post-fix.
Injectable pattern documented in 04-usage.md. Verified via
FilamentOrganizations suite 15 green at intake.

### NEW-049 — filament-pricing — medium — tiers relation manager missing save-time revalidation (worker-found, FIXED)

Location: packages/filament-pricing/src/Resources/*/RelationManagers/TiersRelationManager.php

Same missing save-time revalidation as finding F2's Prices path. Worker #36
fixed with the same CatalogReference wiring (tierable keys covered in
tests). Verified via FilamentPricing suite 62 green at intake.

### NEW-050 — commerce-support/signals — low — sqlite LIKE has no default escape (worker-found, FIXED in blocked-inventory Wave 2 U9)

Escaped wildcard searches match nothing on sqlite (no default ESCAPE
character) — fail-closed, identical to the addressing/cart precedent.
Proper fix is an explicit-ESCAPE query helper in commerce-support (frozen
for the R2 pricing worker). Follow-up for a commerce-support pass; no
in-scope mitigation available.

Resolution: U9 built the explicit-ESCAPE LikeSearch helper
(escape/contains/startsWith/endsWith/whereLike/orWhereLike, ILIKE on
pgsql) and applied it in signals SignalCondition, filament-pricing
PriceSimulator + tier/price managers, filament-products ProductForm,
and filament-organizations ViewOrganization; covered by LikeSearchTest
(6) + SignalConditionLikeEscapeTest (3). Out-of-scope raw sites
(addressing, cart, filament-cart) left for owning units.

### NEW-051 — filament-products — low — widget test names claim explicit global context (worker-found, FIXED in blocked-inventory Wave 1 U2)

Location: tests/src/FilamentProducts/Integration/WidgetsAndPagesTest.php

Four widget test names claim "explicit global context" while the invocation
sits outside any `withOwner` block (actually unresolved-owner). Test
wording only; resolve together with the R1:#10 escalation (wrap the
invocation in `OwnerContext::withOwner(null, …)` + re-apply fail-closed).

Resolution: FIXED in blocked-inventory Wave 1 U2 — invocations wrapped in
explicit OwnerContext::withOwner(null, …) with fail-closed re-applied;
supervisor verified in code.

### NEW-052 — filament-vouchers — critical — redemption-trend date-fill loop discarded immutable addDay() (worker-found, FIXED)

Location: packages/filament-vouchers/src/Widgets/RedemptionTrendChart.php:132

The chart's date-fill loop called `$date->addDay()` on an immutable date
and discarded the result, so the loop never advanced — an infinite loop
that hung every widget render and every full-suite run. Worker #39-retry
fixed with `$date = $date->addDay()`; no similar discarded-immutable
pattern elsewhere in the four packages (verified by search). Suite proof:
FilamentVouchers 69/69 green post-fix (previously hanging). Verified via
FilamentVouchers suite 69 green at intake.

### NEW-053 — filament-vouchers/vouchers — low — voucher code scoped-unique blocked on frozen core migration (worker-found, WONTFIX-permanent)

The voucher code form rule (alpha-dash + uppercase + percent-100 cap)
must stay global because the frozen vouchers-core migration holds a
global `code` unique; true per-owner code reuse needs that core
migration changed. Current posture is consistent and fail-closed
(global DB unique + matching global form rule, no 500s). User declined
the frozen-core thaw in the blocked-inventory batch: accepted
permanently, do not reopen without an explicit thaw decision.

### NEW-054 — inventory — medium — second LIKE-escape site still interpolated raw input (worker-found, FIXED)

Location: packages/inventory/src/Services/Serial/SerialLookupService.php

The partial-path LIKE search had been escaped, but the criteria-path
site still interpolated raw input into LIKE — `%`/`_` acted as
wildcards. Worker #38-retry fixed with the identical repo-blessed
explicit-ESCAPE pattern plus regression tests for both sites (temp-revert
proof: 2 rows matched instead of 1). Verified via Inventory suite 1159
green at intake.

### NEW-055 — tooling — low — PHPStan false-positive invariance on nullable-union shapes in Collections (worker-found, FIXED)

PHPStan 2.2.13 reports an invariance error when an array shape containing
a nullable union (e.g. `string|null`) is returned as
`Collection<int, shape>` — proven via isolated reproduction (`string|int`
passes, `string|null` fails with identical rendering). Worker #38-retry
resolved with the same targeted single-line ignore the codebase already
uses for this exact tool limitation; no config change. Future workers
hitting this pattern should reuse the ignore, not restructure types.

### NEW-056 — filament-tax — medium — exemptable search filtered on accessor-only columns, any use threw (worker-found, FIXED in tax; residual FIXED in blocked-inventory Wave 2 U9)

Location: packages/filament-tax/src/Resources/TaxExemptionResource/Schemas/TaxExemptionForm.php

The exemptable customer search filtered on `full_name`/`email`, which
are accessor-only (not columns) — any use threw a query exception.
Worker #40 fixed to `first_name`/`last_name`/`company` with a regression
test. The same broken pattern exists in out-of-scope packages —
filament-pricing PriceSimulator, filament-products ProductForm,
filament-organizations ViewOrganization — untouched (those units are
already COMPLETE). Follow-up: sweep those three packages for
accessor-column search filters.

Resolution: U9 swept all three — PriceSimulator and ProductForm fixed
to real columns (PriceSimulatorCustomerSearchTest (2) +
ProductFormCustomerSearchTest (1)); ViewOrganization verified clean
(no accessor-column filters).

### NEW-057 — filament-tax — low — Spatie hasPermissionTo throws for unseeded permissions (worker-found, FIXED)

Location: packages/filament-tax/src/Policies/*

The new tax policies used Spatie `hasPermissionTo()`, which throws for
unseeded permissions instead of denying, and disagreed with the
package's Gate convention. Worker #40 fixed to Gate-only with tests.
Verified via FilamentTax suite 45 green at intake.

### NEW-058 — moderation — low — same-named private helper in both traits fatals on dual-use models (worker-found, FIXED)

Location: packages/moderation/src/Traits/HasBlocks.php, HasModerationActions.php

While adding the actor allowlist, both moderation traits initially
grew a same-named private helper — any model using both traits
fatals with a trait method collision. Worker #41 gave each trait its
own helper name; the regression model uses both traits so the
collision class is now covered. Verified via Moderation suite 74
green at intake.

### NEW-059 — filament-events/sqlite — medium — double-quoted unknown identifiers silently match nothing, blinding validator tests (worker-found, FIXED by revert + locked by test)

Location: tests/src/FilamentEvents/Integration/RecordActionPlacementTest.php
(`keeps occurrence and session slugs globally unique`)

A `Unique` where-clause against a nonexistent column silently matches
nothing on SQLite — quoted unknown identifiers degrade to string
literals instead of erroring — so validator-behavior tests pass while
the rule is neutered (proven with a quoting matrix: quoted → silent 0
rows; unquoted → `no such column`). It caught the worker's own first
attempt at occurrence/session slug scoping, which was reverted; the
same trap would 500 on MySQL/Postgres. Global uniqueness is now
locked by test with an explanatory comment. Lesson for future work:
on SQLite, a passing Unique-rule test proves nothing about scoping —
assert on the rule string (or unquoted probe) as well.

### NEW-060 — filament-contacting — low — purpose field unexposed in adapter forms (worker-found, FIXED in blocked-inventory Wave 2 U6)

Location: packages/filament-contacting/src (zero `purpose` references); core
packages/contacting/src/Models/{ContactMethod,SocialProfile}.php + canonical migrations
2000_01_01_000001/000003 (`purpose` defaults to `'general'`).

Minted from the dangling "NEW-3" note in the filament-contacting intake entry (no such ID existed in
the NEW-nnn scheme; NEW-003 is an unrelated authz issue). Substance: neither adapter form exposes the
`purpose` enum, so every adapter-created record silently takes the core/DB default. Functional today
via the default — deliberately left by the worker, no defect — but host operators cannot set or change
purpose from the panel. Fix (when wanted): add an optional purpose select (ContactPurpose::options())
to the adapter forms; no migration needed.

Resolution: U6 added the optional purpose select (General default) to
both adapter form schemas; covered by 4 added regression tests.

### NEW-061 — inventory — low — reservation lock best-effort, MySQL global refs unserialized (review-found, FIXED)

Location: packages/inventory/src/Services/Stock/CheckoutReservationService.php:44-53
(`reserve()`); migration comment in 2000_09_01_000016 (MySQL relies on "lock plus
rescue").

`reserve()` took the reference lock with a single non-blocking `get()` and
proceeded into the transaction when acquisition failed — i.e. exactly when a
race was happening. The stated backstop (unique-violation rescue) can never fire
for NULL-owner tuples on MySQL, and the partial unique only exists on
pgsql/sqlite, so concurrent same-reference reserves with no owner context on
MySQL could double-create groups and double-allocate stock (self-heals via TTL
expiry; no money moves). Fixed by making the lock blocking (`->block(5, ...)`
per the orders CreateOrder precedent): losers wait, then either see the winner
(idempotent path) or fail closed with LockTimeoutException, which the checkout
step surfaces as a retryable reservation failure. Pinned by
`refuses to proceed while the reference lock is held elsewhere` (fails pre-fix:
no exception, group created). Note: the fork-based concurrency test uses
distinct references per process (stock contention), so it never covered this
same-reference path.

### NEW-062 — commerce-support — low-medium — webhook dedup classifier misses Postgres 23505 (review-found, FIXED)

Location: packages/commerce-support/src/Actions/ProcessWebhookCallAction.php:154-157
(`isUniqueViolation`).

The classifier checked `$e->getCode() === '23000'` only, against the repo's own
23000/23505 convention used by every other rescue (orders, inventory, seating).
On Postgres, duplicate webhook deliveries would throw noisily (stuck rows,
failed jobs) instead of clean-skipping as duplicates — no double-processing
(the claim never completes), but operationally messy and contradicting the
method's own docblock. Fixed with the shared convention
(`errorInfo[0] ?? getCode()` in 23000/23505). Pinned by `treats unique
violations from any driver as duplicates` (crafted 23505/23000/HY000; SQLite can
only produce 23000 end to end, already covered by the unique-claim test).
Verified QueryException propagates driver code + errorInfo from PDO (vendor
read). NEW-013 (missing owner dimension) remains OPEN as recorded.

### NEW-063 — commerce-support/chip — medium — owner-dedup claim left stamped duplicates visible to same-table consumer scopes (supervisor-found via Chip suite, FIXED)

Location: packages/commerce-support/src/Actions/ProcessWebhookCallAction.php (handle duplicate branch)

Pre-U9, the UNIQUE(name, event_id, event_type) violation was the dedup
arbiter: the loser's update failed, so its row kept event_type NULL and
stayed invisible to chip's Webhook model (same `webhook_calls` table,
scope = name + non-null event_type). Post-U9, ownerless deliveries carry
a NULL owner_hash that never collides, so the loser's update succeeds and
stamps event_id/event_type before the app-level duplicate check runs —
the duplicate row then masquerades as a second delivery
(HttpTest 'ignores duplicate webhook payloads' counted 2 instead of 1).
Fixed by clearing the claim stamps (event_id/event_type/owner_*) when a
delivery is marked processed-as-duplicate
(markProcessedAsDuplicate()); the first delivery keeps its stamps, so
column-based duplicate checks (checkout/JNT parent override) are
unaffected. Pinned by the previously failing HttpTest; Chip 1081,
CommerceSupport 331, Jnt 596, Checkout 306 all green after the fix.

## CLEANUP — repair-reference sweep (user-requested)

Removed all audit/repair tracking references from the codebase:
~436 ID tokens (`R1#n`, `R2:Xn`, `AUD:Bn`, `NEW-nnn`, `R1#n`-style
slugs/emails/SKUs) stripped from test names, comments, and literals
across 2 package files + 34 test files (explanatory prose kept);
24 `repair*` test helpers renamed to `regression*`; 21 test files
renamed (`*RepairRegressionTest` → `RegressionTest`,
`VerifiedReviewRegressionTest` → `RegressionTest`,
`NavigationRepairTest` → `NavigationRegressionTest`;
`Round2RepairsTest` already replaced by worker #35 as
`PersonIntegrityGuardsTest`). Tracking IDs now live only in
REPAIR_PROGRESS.md and the audit file. Verified: php -l all touched
files, per-file duplicate-name scan (1 pre-existing dup, unchanged),
zero-refs grep sweeps (case-sensitive + insensitive), smoke suites
(Docs 213, ComSup 41, Checkout RegressionTest 40 green). All 5 live
workers instructed to keep IDs out of the repo going forward.

---

# APPENDIX — CROSS-CUTTING VERDICTS + FINAL VERIFICATION BATCH (2026-09-14)

The audit's 7 appendix lines were never assigned to a work unit. A programmatic
reconciliation of every verdict line plus manual code investigation dispositioned
all 7; the one needing code (G2) got it in this batch. File-wide composition is
1248 assigned verdict-actionable + 7 appendix = 1255 (the audit header total).

## Appendix dispositions (7/7)

- [R1:#27] CONFIRMED "Zero tests present" — STALE, record only. Every package now
  has a central Pest suite (40+ dirs under tests/src, all green at intake).
- [AUD:G1] info PASS (config-based nav groups) — record only, verified still true.
- [AUD:G2] medium perf, 3 uncached badge counts in filament-products — FIXED this
  batch: ProductResource, CategoryResource, CollectionResource badges moved to the
  repo-standard OwnerCache-30s pattern; covered by NavigationBadgeCacheTest.
  FilamentProducts suite 51 green.
- [AUD:G3] medium perf, relation columns without with() — NON-ISSUE, record only:
  Filament v5 auto-eager-loads relation columns (precedent: filament-authz AUD:B4,
  verified in vendor 5.8.0 at intake).
- [AUD:G4] medium perf, pluck+preload whole-table loads — ALREADY FIXED via
  products R1:#26 (language options Cache::remember 3600), record only.
- [AUD:G5]/[AUD:G6] info PASS lines — record only.

## Final verification batch (code)

- Events AUD:B8 (registration bulk creates unchunked) — FIXED: RegistrationService
  gained bulkInsertChildren() (shape-grouped chunked inserts preserving fill rules,
  UUIDs, and timestamps; honors usesTimestamps()). Covered by 4 new tests in
  RegistrationBulkInsertTest; Events suite 284 green (280 + 4).
- commerce-support PHPStan (5 errors, previously "environmentally blocked") —
  FIXED: real OwnerCache::lock API, OwnerBatchRunner invariance, CartContext
  docblocks, TargetingEngine redundant-null cleanup. PHPStan level 6 clean.
- Cross-suite fallout (inventory fillable repair vs old events test) — FIXED
  test-side: EventSessionTicketingActionsTest now seeds reservations via
  incrementReserved() (the post-repair write path) instead of mass assignment;
  verified no src path mass-assigns quantity_reserved and the Filament field is
  display-disabled. Inventory suite 1159 green (+6 skipped).
- UNVERIFIED closures stand as recorded: events Q4 (unverified-ignored), feedback
  B6 (no package routes; host-app residual), shipping B8 (no such code site),
  seating B6 (managing UI in out-of-scope filament-seating). No new action.
- Routed affiliate-network follow-ups closed (owning passes never picked them up;
  verified in code, parked as polish): F1 orders attribution snapshot is a
  NON-ISSUE (RecordNetworkConversionForOrder reads the cookie, no-ops safely
  off-request, and is idempotent via order metadata); F2/F3 adapter surfacing
  (isActive guard, owner-scoped slug rule) is advisory only — core enforces both
  (R1:#7 createLink guards, R1:#11 DB unique) and the adapter slug rule errs
  fail-closed (global unique).

## Final gates (supervisor re-ran)

- ./vendor/bin/pest --parallel tests/src/Events — PASS (284 passed, 1276 assertions)
- ./vendor/bin/pest --parallel tests/src/Inventory — PASS (1159 passed, 6 skipped)
- ./vendor/bin/pest --parallel tests/src/FilamentProducts — PASS (51 passed)
- ./vendor/bin/pest --parallel tests/src/CommerceSupport — PASS (318 passed)
- ./vendor/bin/pest --parallel tests/src/Moderation — PASS (74 passed, NEW-058 holds)
- ./vendor/bin/pint --test on all 11 final-batch files — PASS
- ./vendor/bin/phpstan analyse packages/commerce-support/src + packages/events/src
  --level=6 --debug — OK, no errors (2 events errors found and fixed in-batch;
  --debug required: sandbox blocks PHPStan's TCP parallel runner with EPERM)

Handoff: tree left uncommitted by design; full suite deferred to sharded CI per
user decision. Record is now complete: 1255/1255 with every ID dispositioned.

## Sampled re-review batch (2026-09-14, user-requested)

Deep re-review of 8 highest-risk implementations (code + callers + tests read;
suites re-run): orders intake, voucher double-redeem, inventory races, seating
holds, OrderPolicy trait, promotion limits/rounding, checkout IDOR guard,
NEW-013 assessment. 6 solid as recorded; 2 findings fixed with tests:
NEW-061 (blocking reservation lock) and NEW-062 (23505 webhook classifier).
Gates: Inventory 1160 (+6 skipped), CommerceSupport 319, Pint PASS (4 files),
PHPStan level 6 clean on both packages.

## Blocked-inventory resolution batch (2026-09-14, user-approved with holds)

User approved implementing all blocked-inventory recommendations EXCEPT Q7
(sale-price max), Q11 (affiliate payout shortcuts), Q14 (paid_at honor) —
those 3 held for revisit afterwards. Frozen-core thaw declined (vouchers,
promotions, inbound-ID validation stay frozen; NEW-053 wontfix-permanent).

Accepted without code change (test pins stand as the contract; rationale
already in each intake entry): addressing owner-column backstop, cashier
webhook 404, chip API-only statuses, authz guard-keyed role format, events
7 behavior variants, tax trio (T6 semantics, AUD:B4 misses-not-leaks, AUD:B2
status-fillable with fixed public path), jnt F10 (spoof disproven), products
fillable (guards live), org terminal-timestamp retention, Q4 invoice-half
scoping, Q6 distinct webhook statuses, Q8 merchant-scoped dashboard, Q10
scoping-only address validation, NEW-006 limitation, NEW-018.2 Pg semantics.

Fix batches run as worker waves U1-U9 with supervisor intake (results below
as waves land).

### Wave 1 intake (U1-U5, supervisor-verified green)

- U1 shipping fail-open flip (Q1): accepted as-is; Shipping 555 passed,
  1 skipped.
- U2 signals + products silent-global-fallback flips (Q2, Q3) + NEW-051
  test wording (explicit withOwner(null) + fail-closed): accepted;
  Signals 122 passed.
- U3 customers policy tightening + fillable narrowing (Q5 + R1 item 9
  remainder): accepted; Customers 302 passed. Two fillable-hardening
  fallouts fixed by supervisor with same-pattern test updates (no
  production change): ContactingNullRateOwnershipTest session creation
  wrapped in CheckoutSession::unguarded (guarded grand_total=0 had
  routed the step down the free-order path, yielding the 0.33 null
  rate); CommerceIntegrationTest order-paid case forceFills the owner
  tuple (Order fillable dropped owner_type/owner_id). Correction to
  the interim note: the Signals failure was NOT unrelated — same
  hardening theme, now green.
- U4 communications fillable + owner guards for 9 actions (R1 item 14
  remainder + NEW-012): accepted; all nine call sites verified in code
  (OwnerWriteGuard::findOrFailForOwner, incl. the 3-ID
  RecordProviderEventAction); Communications 371 passed.
- U5 cashier domain batch (Q9, Q12, Q13, R1 item 29): accepted;
  NEW-008 ($0 renewals auto-complete in RenewSubscriptionsCommand) and
  NEW-009 (settled-period reconciliation warning in
  SyncChipPurchaseStatus) verified in code.
- Cross-checks: Contacting 373, Checkout 306, Growth 172, FilamentCart
  202, Events 284 — all passed. Pint PASS on touched files; PHPStan
  level 6 clean on checkout + customers (--debug: sandbox blocks the
  TCP parallel runner).

### Wave 2 scope (U6-U9; supervisor decisions recorded)

Held out: NEW-039 (DocPaymentRecorder paid_at) sits under the Q14
paid_at-honor hold — revisits with Q7/Q11/Q14. Everything else below is
in scope.
- U6 authz/contacting: NEW-023 (explicit caller team_id wins over
  ambient; silent discard is data loss), NEW-032 (align code default
  to 'camel': docs + builder agree 2v1, getters have no internal
  readers), NEW-060 (optional purpose select in adapter forms).
- U7 docs: NEW-015 (7 csuite drift spots incl. coordinated
  commerce-support SetupCommand wizard fix), NEW-018.1 (DocService
  update() accepts string|array body, normalizes — preserves filament
  RichEditor submits), NEW-018.3 (EditDoc stops submitting ignored
  fields), NEW-038 (getTotalPaid sums Paid-only).
- U8 cart: NEW-005 (tombstone the merged source cart instead of
  deleting it — preserves merged_into_id attribution), NEW-007
  (shared-harness InMemoryStorage swapIdentifier refuses occupied
  targets, mirroring the hardened contract).
- U9 support/misc: NEW-013 (owner dimension in the webhook-dedup
  unique: stub + additive migration, Chip override keeps working;
  two-owner same-event regression test), NEW-050 (explicit-ESCAPE
  query helper in commerce-support, applied to the known sqlite LIKE
  sites), NEW-056-residual (sweep filament-pricing PriceSimulator,
  filament-products ProductForm, filament-organizations
  ViewOrganization for accessor-column search filters).

### Wave 2 intake (U6-U9, supervisor-verified green)

- U6 authz/contacting: accepted as-is. Explicit caller team_id wins in
  Role::create (plus teams-aware duplicate check); filament-authz code
  default aligned to 'camel'; optional purpose select in both
  contacting adapter forms. Authz 26, FilamentAuthz 178,
  FilamentContacting 40 passed (+4 pre-existing skips).
- U7 docs: accepted as-is. All 7 csuite drift spots corrected against
  real configs + SetupCommand wizard writes real env keys; DocService
  update() accepts string|array body with normalization, immutable
  fields filtered server-side (+ owner revalidation, fail-closed);
  getTotalPaid sums Paid-only. Docs 216, FilamentDocs 103,
  CommerceSupport 331, Csuite 1 — all passed. NEW-039 untouched (Q14
  hold respected; recorder mtime predates Wave 2).
- U8 cart: accepted as-is. Merged source carts become tombstones
  (content cleared + expired, row + merged_into_id kept) in both
  migration paths; shared-harness InMemoryStorage swap refuses
  occupied targets. Full Cart 1075 passed (+2 pre-existing skips).
- U9 support/misc: accepted with one supervisor fix (NEW-063, below).
  Owner-dim dedup (stub + additive 000006 migration,
  supportsOwnerDedup feature detection, Chip override intact);
  LikeSearch explicit-ESCAPE helper applied in signals/pricing/
  products/organizations; accessor-search sweep fixed PriceSimulator +
  ProductForm (ViewOrganization verified clean). Signals 125,
  FilamentPricing 64, FilamentProducts 52, FilamentOrganizations 16.
- Intake gates re-run by supervisor: Jnt 596, Chip 1081 (+4 skips),
  Checkout 306, CommerceSupport 331 — all passed. Pint PASS (62 Wave 2
  files + 3 supervisor-fix files); PHPStan level 6 clean on all 11
  touched packages. No tracking-ID leakage in Wave 2 files; workers
  did not touch REPAIR_PROGRESS.md; tree left uncommitted.
- Carried notes (not defects): U9 left out-of-scope raw LIKE sites for
  owning units (addressing AddressAreaHierarchy, cart
  CartSnapshotItem/byName, filament-cart ApplyConditionAction/
  AbandonedCartsWidget); ownerless concurrent deliveries dedup at app
  level only (NULLs never collide) — documented in claim().
- Correction to interim notes: no workflow hang occurred. The Wave 2
  workflow completed normally; a slow-suite poll plus macOS missing
  the `timeout` command mimicked a hang. Jnt alone: 596 passed.

### Held-question resolutions (Q7/Q11/Q14, user-decided)

- Q7 sale-price max → SALE PRICE WINS. EnsureCheckoutOfferProduct
  prices the product at the offer priceAmount literally (compare
  untouched); basePriceForProduct deleted; pin rewritten. Checkout
  306 green.
- Q11 affiliate payout shortcuts → STRICT STEP-BY-STEP. Adapter
  updateStatus routes through the canonical transitionTo map,
  visibility honors canTransitionTo, reset-to-pending removed.
  FilamentAffiliates 334 + Affiliates 1157 green. Operator note:
  staff can no longer reset approved conversions to pending or
  pay pending ones in one jump; the path is Pending → Approved →
  Paid (rejections allowed from Pending/Qualified/Approved).
- Q14 paid_at honor → HONOR INPUT (against audit-consistent
  recommendation; backdating user-accepted). Recorder honors a
  submitted paid_at, defaults to now(), rejects garbage. Docs 219,
  FilamentDocs 103 green. NEW-039 FIXED.
