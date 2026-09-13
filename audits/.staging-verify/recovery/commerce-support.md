End-to-end review: packages/commerce-support (foundation: owner scoping, webhooks, targeting, money, reference data). Inspected: config, all 7 models, OwnerContext/OwnerScope/HasOwner/OwnerQuery/OwnerRouteBinding/OwnerWriteGuard/OwnerCache/OwnerFilesystem/OwnerBatchRunner/OwnerSignedDownload/OwnerJobContext, Resolve* actions, ProcessWebhookCallAction, webhook processor/validator/profile, PinnedHttpClient/PublicHttpUrlGuard/SystemPublicDnsResolver, TargetingEngine + context + evaluators, MoneyNormalizer/MoneyFormatter, Filament widget/navigation/pages, all 12 migrations, seed actions, Setup/Install/Boost/Publish commands, UpsertEnv/Symlink/ProjectRoot actions, HasCommerceAudit/LogsCommerceActivity, helpers, service provider, health blade.

FINDINGS

1. [high/security] packages/commerce-support/src/Models/Report.php:47 — Report mass-assignment of privileged + polymorphic fields
Fillable includes reportable_type/id, reporter_type/id, reviewed_by_type/id, status, severity, resolution, internal_notes. Any ::create($input)/fill() path lets a caller file a report as another user, self-assign reviewer, and set status=resolved/severity. Same pattern in SavedSearch.php:34 (user_type/user_id, searchable_type/id fillable, no HasOwner) and NotificationPreference.php:37 (user_type/user_id fillable). Evidence: `protected $fillable = ['reportable_type','reportable_id','reporter_type','reporter_id','report_type','status','severity',... 'reviewed_by_type','reviewed_by_id',...]`. Recommendation: remove *_type/*_id and workflow fields (status/severity/reviewed_*/resolution/internal_notes) from $fillable; set them server-side from auth context + explicit transitions. Confidence: high.

2. [high/bug] packages/commerce-support/src/Actions/ProcessWebhookCallAction.php:29 — Webhook dedup race + long lock hold
Dedup check (isDuplicateProcessedEvent, a plain exists() in CommerceWebhookProcessor.php:107) runs inside the same lockForUpdate transaction as processEvent. Two concurrent deliveries of the same event_id can both pass exists() before either commits → double processing (double charge/fulfill). Also processEvent (arbitrary side effects) runs while holding the row lock → lock contention/timeouts. Evidence: `DB::transaction(... lockForUpdate ... if duplicate...; $processEvent($eventType,$payload); $locked->update(...))`. Recommendation: enforce a UNIQUE(name, event provider id) column or advisory lock/psql upsert on (name, event_id) before processing; move processEvent outside the row-lock transaction (claim-then-process with status=processing). Confidence: high.

3. [medium/security] packages/commerce-support/src/Actions/ProcessWebhookCallAction.php:65 — Full exception string persisted to webhook_calls
Catch block stores `(string) $e` (message + trace + paths, often SQL/fragments of payload) into the `exception` text column, readable by anyone with DB/webhook-read access. Evidence: `'exception' => (string) $e`. Recommendation: store only class + message truncated (e.g. Str::limit($e->getMessage(), 2000)), log full trace server-side. Confidence: high.

4. [medium/security] packages/commerce-support/src/Targeting/TargetingContext.php:303 — Promotion targeting trusts client-spoofable headers
getChannel() trusts X-Channel/X-Sales-Channel, getCountry() trusts CF-IPCountry/X-Country/X-Geo-Country, getReferrer() trusts Referer, falling back to metadata the caller supplies. A shopper setting headers/UTM can steer channel/country/device rules to unlock vouchers. Evidence: `$this->request->header('X-Channel') ?? $this->request->header('X-Sales-Channel')`. Recommendation: document as untrusted input; resolve channel/country server-side (session/storefront config, verified geo-IP) for discount-gating rules; treat header-derived values as hints only. Confidence: med.

5. [medium/security] packages/commerce-support/src/Support/OwnerFilesystem.php:53 — Traversal check is a substring block, bypassable
`str_contains($relativePath,'..') || str_starts_with('/'` misses encoded variants (`..%2f`, `.%2e/`), backslash/absolute Windows paths (`C:\`, `\foo`), and does not normalize `./` or resolve symlinks; disk is the unqualified default (could be public). Evidence lines 60-66. Recommendation: normalize (rawurldecode repeatedly, unify separators), reject any segment === '..' or '' after split, reject absolute/drive-letter paths, and pin to an explicit private disk or allowlist. Confidence: med.

6. [medium/bug] packages/commerce-support/src/Support/OwnerContext.php:160 — fromTypeAndId() builds phantom owners without existence check
Instantiates any Eloquent-resolving owner_type with the given id and returns it as the owner context; used by OwnerBatchRunner/ParsedOwnerTuple/OwnerUiScope/OwnerJobContext. Orphaned or poisoned (see finding 1) owner tuples yield a working "owner" scope instead of failing. Evidence: `$owner = new $resolved; $owner->setAttribute(...); return $owner;`. Recommendation: add an opt-in `fromTypeAndIdOrFail` that actually queries (exists/select) and use it on trust boundaries (jobs, route binding, batch runner). Confidence: med.

7. [medium/performance] packages/commerce-support/database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub — webhook_calls has zero indexes
No index on name/status/processed_at, yet every delivery runs the JSON-path dedup query (payload->event_id/id + payload->event_type + name + processed_at). Full scans that degrade linearly with table growth. Recommendation: add composite index (name, processed_at) plus a stored/generated event-id column with unique index (also fixes finding 2). Confidence: high.

8. [medium/performance] packages/commerce-support/src/Support/OwnerBatchRunner.php:87 — Unbounded distinct owner-tuple load
`DB::table(...)->select(...)->distinct()->get()` loads every owner tuple into memory; no chunking. Malformed tuples throw mid-run after partial callbacks (no atomicity). Evidence lines 92-96 + collectForOwners loop. Recommendation: chunk/distinct-cursor the tuple scan; validate tuples before running any callback. Confidence: high.

9. [medium/bug] packages/commerce-support/src/Support/OwnerCache.php:126 — forgetOwner() silently no-ops on non-taggable drivers; remember() has no stampede guard
Tag flush is wrapped in try/catch(Throwable){} so file/database/array drivers silently keep stale owner keys; remember() has no lock → thundering herd on expensive callbacks. Evidence lines 130-146. Recommendation: fail loudly or track an owner version key for invalidation on drivers without tags; use Cache::lock around remember() rebuilds. Confidence: high.

10. [medium/performance] packages/commerce-support/src/Targeting/TargetingContext.php:135,279 — N+1 in targeting evaluation
isFirstPurchase() runs `$user->orders()->count()` per evaluation with no memoization (repeated across rules); getProductCategories() lazy-loads `categories` per cart line. Evidence lines 135-136, 279-285. Recommendation: memoize counts/segments per TargetingContext instance; eager-load categories once per evaluation. Confidence: med-high.

11. [medium/bug] packages/commerce-support/src/Concerns/LogsCommerceActivity.php:74 + HasCommerceAudit.php:175 — Activity/audit logs capture fillable/PII by default
Loggable attributes default to `$this->fillable` (includes *_type/*_id and whatever the model fills), and the audit sensitive-field list omits email/phone/address/postcode/name/dob. Evidence: `return $this->fillable;` and the 10-item sensitive list. Recommendation: default loggable to an explicit per-model allowlist and extend sensitive fields (or invert: exclude PII unless allowlisted). Confidence: med.

12. [low-medium/bug] packages/commerce-support/src/Support/MoneyNormalizer.php:32 — Float path violates minor-units rule
toDollars()/format() do `$cents/100` float division (display drift, e.g. large values) and MoneyFormatter decimal paths also divide in float before number_format. Evidence lines 32-50. Recommendation: keep integer math until the final string (intdiv + str_pad) for display; keep float helpers clearly labelled display-only. Confidence: high (impact med).

13. [low/bug] packages/commerce-support/src/Filament/Widgets/CommerceHealthWidget.php:57 + resources/views/widgets/health-status.blade.php:6,21,46 — Health results loaded 3x per render; preg_split unchecked
Blade calls getOverallStatus(), getStatusCounts(), getHealthResults(), each re-running latestResults(); formatCheckName() (line 185) passes preg_split() result directly to implode (false → TypeError). Recommendation: memoize getHealthResults() per request; guard `preg_split(...) ?: []`. Confidence: high.

14. [low/performance] packages/commerce-support/src/Actions/SeedCurrenciesAction.php:25, SeedTimezonesAction.php:25, SeedLanguagesAction.php:27 — Reference-data seeding is N+1 without a transaction
Per-row select + insert/update (~2 queries x ~150+ rows), no transaction → slow and partially applied on failure. Recommendation: wrap in DB::transaction and use upsert() keyed by code/name. Confidence: high.

15. [low/bug] packages/commerce-support/src/Targeting/TargetingEngine.php:156 — Unbounded expression recursion (DoS)
evaluateExpression()/validateExpression() recurse on merchant/admin-supplied and/or/not nesting with no depth or node cap → deep payload can exhaust stack. Recommendation: enforce max depth (e.g. 10) and max node count in validate() before evaluating. Confidence: med.

16. [low/security] packages/commerce-support/src/Http/PinnedHttpClient.php:28 — DNS-pin only enforced on curl transports
CURLOPT_RESOLVE pinning is skipped silently for IP literals (fine) but throws only when curl missing; non-curl HTTP drivers would ignore `curl` options, losing the DNS-rebinding protection PublicHttpUrlGuard validated. Recommendation: assert the HTTP client is curl-backed (or refuse to send) when a resolve entry is required. Confidence: low-med.

17. [low/bug] packages/commerce-support/src/Actions/UpsertEnvVariablesAction.php:19 — Non-atomic .env rewrite
File::get → File::put with no lock/backup; duplicate keys leave stale earlier lines; every value is force double-quoted (changes `true`/numeric semantics). Recommendation: file lock + backup, collapse duplicates, preserve quoting style when value needs no quoting. Confidence: med.

18. [low/performance] packages/commerce-support/src/Traits/HasOwner.php:334 — getOwnerDisplayNameAttribute lazy-loads owner per row
`$this->owner` per model → N+1 in any list rendering the attribute. Recommendation: document eager-loading (`with('owner')`) or cache per (type,id) per request. Confidence: high.

19. [low/bug] taggables morph-id type is fixed UUID (1970_01_01_000001 stub) while commerce morph keys may be int/ulid → tagging int-PK models breaks; several 2025_* stubs have no down(). Recommendation: use commerce_morph_key() for taggable_id; add down() methods. Confidence: med.

POSITIVES (brief)
- Owner isolation is well-architected: request-attribute storage + Octane flush (OwnerContext), global scope fail-closed without context, HasOwner blocks promotion/demotion/reassignment, OwnerUiScope/OwnerScopedIds fail closed, OwnerScopeKey hashes scope keys, NeedsOwner fails closed.
- SSRF guard is strong: PublicHttpUrlGuard enforces http(s), no creds/fragments, standard ports, FQDN + DNS→public-IP-only validation (incl. NAT64/multicast checks), pinned transport with redirects disabled.
- Webhook security basics right: hash_equals HMAC validation, lockForUpdate idempotency attempt, transactional status updates.
- Repo-rule compliance observed: uuid PKs, no FK constraints/cascades in migrations, no SoftDeletes, Actions for orchestration, money stored int minor units, JsonDisplay escapes output, health widget gated by Gate ability, TargetingEngine fails closed on invalid rules.
- No eval/shell/deserialization-of-input, no raw user input in SQL (single whereRaw is constant '1 = 0'), no {!! !!} unescaped output in package blade.