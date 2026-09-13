Review of packages/filament-affiliates (Filament adapter, no models/migrations/routes/tests of its own — correct per CONTEXT.md guardrails).

FINDINGS

1) severity: high | category: bug/security | file: src/Resources/AffiliateResource/Schemas/AffiliateForm.php:196-197
Title: Hidden owner_type/owner_id are dehydrated — arbitrary owner assignment via form tampering
Description: The "Portal Access" section uses `Hidden::make('owner_type')` / `Hidden::make('owner_id')` which are dehydrated by default, so a crafted request can set any owner_type/owner_id directly on the Affiliate, bypassing the linked_user afterStateUpdated logic. There is no validation of the morph class or of the target user id.
Evidence: `Hidden::make('owner_type'), Hidden::make('owner_id'),` with no `->dehydrated(false)`; `linked_user` is `->dehydrated(false)` but the Hiddens carry the values.
Recommendation: Make both Hiddens `dehydrated(false)` and resolve owner server-side in Create/Edit pages (validate user exists), or add strict `Rule::in` on owner_type + exists rule on owner_id.
Confidence: high

2) severity: high | category: security | file: src/Actions/UpdateAffiliateFraudSignalStatus.php:27-29 (same in src/Actions/BulkFraudReviewAction.php:63-65)
Title: Fraud-signal status change re-fetches record without owner scope — cross-owner write
Description: Both actions re-query via unscoped `AffiliateFraudSignal::query()->whereKey(...)->firstOrFail()`, while the resource list itself is owner-scoped via whereHas(affiliate). The Gate `update` policy checks only permissions, not ownership. A user with fraud.update permission can mutate a signal from another owner by submitting its id. Payout actions in the same package correctly use OwnerWriteGuard; these do not.
Evidence: `$signal = AffiliateFraudSignal::query()->whereKey($record->getKey())->firstOrFail();` with no OwnerWriteGuard / whereHas-affiliate scoping.
Recommendation: Re-fetch through the same owner-scoped query as the resource (whereHas affiliate + OwnerQuery) or add OwnerWriteGuard support for signals; add a cross-owner regression test.
Confidence: high

3) severity: high | category: bug | file: src/Pages/PayoutBatchPage.php:159-179
Title: Manual "Reject" bypasses payout domain action and never releases reserved funds
Description: The reject action does `$payout->update(['status' => FailedPayout::class, ...])` directly instead of using UpdatePayoutStatus / PayoutReconciliationService. Unlike ProcessAffiliatePayout::recordResult (which calls releaseReservedFunds on failure), this path leaves funds reserved forever while reporting the payout as failed.
Evidence: `'status' => FailedPayout::class, 'metadata' => ...` direct update + `$payout->events()->create(...)` with no releaseReservedFunds call.
Recommendation: Route rejection through the domain action and call releaseReservedFunds (or move rejection into affiliates package as CONTEXT.md requires — no business rules in this adapter).
Confidence: high

4) severity: high | category: security | file: src/Pages/ManageAffiliateCommissionSettings.php:13-75
Title: Commission settings page has no authorization and no validation
Description: The page defines no canAccess()/authorization (unlike FraudReviewPage/PayoutBatchPage/ReportsPage), so any authenticated panel user who can reach the slug can read and overwrite global multi-level commission rates. save() casts unchecked input (`(float)($row['rate']??0)/100`) with no range/count validation, allowing negative/huge rates and unbounded level rows.
Evidence: class has mount/save/addLevel/removeLevel and getNavigationGroup/Sort but no canAccess; save() maps raw rows with no validator.
Recommendation: Add canAccess via FilamentPermission ability, validate rates (numeric, min 0, max sane bound) and cap level count.
Confidence: high

5) severity: medium | category: security | file: src/Pages/Portal/PortalRegistration.php:82-103,200-297
Title: Registration override: raw user mass-assignment + unauthenticated affiliate-code enumeration oracles
Description: (a) handleRegistration does `$userData=$data; unset(affiliate fields); ::create($userData)` trusting the Livewire data array (relies solely on model casts for password hashing — verify User has hashed cast). (b) checkCodeAvailability/checkReferralCode are public, unauthenticated, unthrottled Livewire actions returning existence of any affiliate code — an enumeration oracle. (c) Referral resolution via findByCode/findActiveAffiliateByCookie is not owner-scoped, allowing cross-owner upline grafting; the affiliate_code unique rule ignores owner scope.
Evidence: `$user = $this->getUserModel()::create($userData);` ; `Affiliate::query()->whereRaw('LOWER(code) = ?',...)->exists()` in two public methods; `app(AffiliateLookup::class)->findByCode($data['referral_code'])`.
Recommendation: Explicitly pick name/email/password (+Hash::make fallback), add rate limiting, scope lookup + uniqueness by owner.
Confidence: med

6) severity: medium | category: bug/security | file: src/Pages/Portal/PortalConversions.php:52-64
Title: orWhereIn without grouping leaks/overmatches conversions
Description: `$query->where('affiliate_id',$id); if(...){$query->orWhereIn(...)}` produces `A OR B`, so any subsequent AND constraints (Filament search/sort scopes) bind only to the second branch (`A OR (B AND C)`). Descendant ids are also fetched from AffiliateUpline without owner scoping.
Evidence: `->where('affiliate_id', $affiliateId); ... $query->orWhereIn('affiliate_id', $descendantIds);`
Recommendation: Single `whereIn('affiliate_id', [$own, ...$descendants])`; scope the upline lookup to the owner.
Confidence: high

7) severity: medium | category: security | file: src/Services/PayoutExportService.php:138-148,285-324,42-48
Title: Export has CSV formula injection, unescaped PDF/HTML meta block, unsanitized filename
Description: affiliate_code/external_reference are written raw to CSV/XLSX (cells starting with =,+,-,@ execute on open). buildPdfHtml interpolates `$payout->reference`, status and totals unescaped into title/meta (only table cells use htmlspecialchars). Filenames use raw reference in Content-Disposition.
Evidence: `getRowData` returns raw casts; `$csv->insertOne($this->getRowData(...))`; `<title>Payout Report - {$payout->reference}</title>`; `sprintf('%s.csv', $payout->reference)`.
Recommendation: Prefix/sanitize leading =,+,-,@ (e.g. prepend '), escape all meta interpolations, sanitize filename (allowlist [A-Za-z0-9-_]).
Confidence: high

8) severity: medium | category: bug | file: src/Actions/BulkPayoutAction.php:57-61
Title: Bulk payout always reports success even when every payout fails
Description: `$failed` is counted but never surfaced; `sendSuccessNotification()` runs unconditionally and `success()` only reflects processed>0, so an all-failed run still shows a success toast.
Evidence: `if ($processed > 0) { $this->success(); } $this->sendSuccessNotification();` with `$failed++` unused.
Recommendation: Branch notifications on processed/failed counts (success/partial/failure), matching PayoutBatchPage's counted message.
Confidence: high

9) severity: medium | category: security | file: src/Pages/Portal/PortalLinks.php:83-129
Title: generateLink trusts public Livewire property; host check is incomplete
Description: generateLink is a public action reading public $targetUrl with no validator call (the `->url()` rule exists only on the header-action form, bypassable). The guard compares only exact host: rejects valid subdomains/case variants, allows non-http(s) schemes to the same host, and builds generatedShortLink by naive path concatenation. Code is appended without urlencoding.
Evidence: `public string $targetUrl`; `$targetHost !== $allowedHost` strict compare; `$this->generatedLink = $this->targetUrl . ...`.
Recommendation: Validate targetUrl server-side (url rule + allowed schemes http/https + host allowlist incl. subdomains), urlencode code.
Confidence: high

10) severity: medium | category: performance | file: src/Widgets/UplineVisualizationWidget.php:99-160; src/Concerns/InteractsWithAffiliate.php:234-247; src/Pages/Portal/PortalDashboard.php:32-52
Title: Unbounded recursive/N+1 reads on dashboard and upline widget
Description: PortalDashboard::getViewData fires ~8 separate queries plus unbounded getDownlines()->get(); PortalSupport/PortalPrograms/PortalCreatives load all tickets/programs/creatives with per-row queries (getMembership + creatives per program, creatives per program). UplineVisualizationWidget::buildNode recurses with a query per node and unbounded breadth; public $depth is user-controllable (deep-tree DoS); calculateAverageChildren loads every affiliate-with-children into memory on each render.
Evidence: `->get()` with no limit in getDownlines; `->flatMap(fn...->creatives()->...->get())`; `if ($currentDepth < $this->depth)` with `public int $depth`; `->withCount('children')->get()` then `->avg(...)`.
Recommendation: Paginate/limit breadth, cap depth server-side (ignore/ clamp client value), aggregate averages in SQL, cache dashboard aggregates.
Confidence: high

11) severity: medium | category: performance | file: src/Pages/PayoutBatchPage.php:91-103; src/Services/PayoutExportService.php:31-49; src/Widgets/PerformanceOverviewWidget.php:26-97
Title: Per-row queries, full-collection exports, and aggressive widget polling
Description: PayoutBatchPage payout_method column queries payoutMethods per row (N+1, payee eager-loaded but methods not). CSV/XLSX/PDF exports load all conversions into memory then print (not true streaming) — OOM on large payouts. PerformanceOverviewWidget runs 7 aggregates every 30s, RealTimeActivityWidget polls every 10s, AffiliateStatsAggregator 8 queries per render — no caching.
Evidence: `getStateUsing(... $payee->payoutMethods()->...->first())`; `foreach ($payout->conversions ...)`; `protected ?string $pollingInterval = '30s'/'10s'`.
Recommendation: Eager-load default payout method (withAggregate), chunk/cursor exports with streamed CSV, cache widget aggregates with short TTL.
Confidence: high

12) severity: medium | category: bug | file: src/Resources/AffiliateConversionResource/Tables/AffiliateConversionsTable.php:132-148; src/Pages/Portal/PortalProfile.php:113-136
Title: Direct state mutation bypasses transitions; payout-method switch is non-atomic
Description: updateStatus assigns `$conversion->status = new $statusClass` + save, bypassing any transitionTo guards/auditing, and allows pending→paid / rejected→paid jumps (visibility only excludes the target state). PortalProfile flips is_default off then creates/updates in two queries with no transaction — concurrency can yield zero or multiple defaults.
Evidence: `$conversion->status = new $statusClass($conversion); ... return $conversion->save();`; `::where(...)->update(['is_default'=>false]); ... ::create([... 'is_default'=>true])`.
Recommendation: Use the domain transition action with allowed-transition validation; wrap default-switch in a transaction with a unique partial index on (affiliate_id) where is_default.
Confidence: med

13) severity: low | category: security | file: resources/views/pages/portal/links.blade.php:26,40,56; dashboard.blade.php:118,251; vouchers.blade.php:58
Title: Affiliate/voucher codes interpolated into JS single-quoted strings — potential XSS on non-alphaDash codes
Description: `x-on:click="navigator.clipboard.writeText('{{ $code }}')"` — Blade HTML-escapes quotes to &#039; but the browser HTML-decodes attribute values before JS parsing, so a code containing `'` breaks out of the JS string. Portal registration enforces alphaDash, but the admin AffiliateForm code field has no alphaDash rule, so such codes can exist. (support.blade.php correctly uses @js() — copy that pattern.)
Evidence: `writeText('{{ $affiliateCode }}')` etc. vs admin form code field with only required/maxLength/unique.
Recommendation: Use `@js($code)` in all handlers and add alphaDash validation to the admin code field.
Confidence: med

14) severity: low | category: bug | file: src/Resources/AffiliatePayoutResource.php:78-86; src/Resources/AffiliatePayoutResource/Pages/CreateAffiliatePayout.php:32-38; src/Widgets/PayoutQueueWidget.php:51-54; src/Widgets/RealTimeActivityWidget.php:56-64
Title: Unscoped/unbounded affiliate picker; unconditional OwnerWriteGuard; hardcoded divideBy:100
Description: Payout form loads ALL affiliates via unscoped pluck (memory + cross-owner options; create page revalidates but the picker still leaks). CreateAffiliatePayout calls OwnerWriteGuard unconditionally while every other call site gates on affiliates.owner.enabled — inconsistent, likely breaks when owner mode is off. Money columns hardcode divideBy:100, wrong for zero-decimal currencies (JPY etc.), inconsistent with InteractsWithAffiliate::formatAmount which handles them.
Evidence: `Affiliate::query()->orderBy('name')->pluck('name','id')->all()`; unconditional `OwnerWriteGuard::findOrFailForOwner`; `->money(..., divideBy: 100)`.
Recommendation: Scope + searchable-lazy options, gate the guard on config, use MoneyFormatter for money columns.
Confidence: med

15) severity: low | category: bug | file: src/Pages/ReportsPage.php:101-124; src/Pages/PayoutBatchPage.php:224-238
Title: Unvalidated custom dates can 500; batch header aggregates duplicate queries
Description: CarbonImmutable::parse($this->startDate/$endDate) on unvalidated Livewire input throws on malformed dates; no start<=end check; live regeneration on every change. getViewData runs count + sum + grouped queries separately (3 queries where 1-2 suffice).
Evidence: `'custom' => $this->startDate ? CarbonImmutable::parse($this->startDate) : ...`; three separate pending queries.
Recommendation: Validate date|before_or_equal:endDate, debounce regeneration, combine aggregates.
Confidence: high

POSITIVES (brief)
- Owner scoping is consistently applied in most admin resources (OwnerUiScope/forOwner) and payout write paths revalidate via OwnerWriteGuard; fraud-signal resource scoping via whereHas(affiliate) is the right pattern (actions just fail to reuse it).
- ProcessAffiliatePayout uses lockForUpdate + idempotent reconcile logic with lease expiry and reserved-funds release — solid double-processing protection.
- Most Blade output uses {{ }} escaping; support reply ticket lookup is correctly constrained to the caller's affiliate_id; PortalLinks has a same-host guard (just incomplete); money is int minor units throughout.
- No raw SQL injection (all whereRaw use bindings), no eval/unserialize/deserialization, no file upload/path traversal, no SSRF fetchers, no Octane-unsafe static mutable state (only a config cache on the panel provider), no FK violations (adapter has no migrations).