End-to-end review of packages/filament-affiliate-network (Filament v5 adapter; no routes/migrations/jobs/commands/tests in package — UI + policies + support only; domain lives in affiliate-network). Owner scoping is opt-in (affiliate-network.owner.enabled and affiliates.owner.enabled both default false), but this package explicitly supports enabled=true (Create/Edit pages wrap writes in OwnerContext::withOwner(null)), so scope findings below are real bugs in that mode.

FINDINGS

[H1] bug, HIGH — Verify-site action re-fetch misses scope bypass → 404 on every owned site
File: src/Resources/AffiliateSiteResource/Tables/AffiliateSitesTable.php:71-73
Evidence: `$scopedRecord = OwnerContext::withOwner(null, fn () => AffiliateSite::query()->whereKey(...)->firstOrFail());` — no `->withoutOwnerScope()`. OwnerQuery::applyToEloquentBuilder with null owner constrains to `whereNull(owner_type,id)` (commerce-support/src/Support/OwnerQuery.php:40-43), so with owner.enabled=true this finds only global rows; any owned site throws ModelNotFoundException. Sibling code (OptionsProvider, offer activate/pause) always pairs withOwner(null) with scope removal.
Recommendation: add `->withoutOwnerScope()` to the re-fetch, matching AffiliateOfferResource::getEloquentQuery. Confidence: high.

[H2] bug, HIGH — Admin approve/reject/revoke/bulk-approve fail cross-tenant when affiliates.owner.enabled=true
File: src/Resources/AffiliateOfferApplicationResource/Tables/AffiliateOfferApplicationsTable.php:79-133,144-158
Evidence: table lists all applications via getEloquentQuery()->withoutGlobalScope(ScopesByBelongsToOwner), but approve/reject/revoke call OfferManagementService with the ambient context. Core re-queries with default scope: ApproveApplication::execute does `AffiliateOfferApplication::query()->whereKey(...)->firstOrFail()` and reject/revoke do the same (affiliate-network/src/Actions/ApproveApplication.php, Services/OfferManagementService.php:145-147,165-167). ScopesByBelongsToOwner scopes via affiliate owner ('affiliates.owner'), so cross-owner rows 404, or NoCurrentOwnerException if no owner resolves. Marketplace flow works only because it wraps calls in withAffiliateOwnerContext.
Recommendation: add an explicit-global/admin path in affiliate-network (service accepts bypass or wraps in withOwner(null)+withoutGlobalScope) and call it here; per CONTEXT guardrails the fix belongs in core, this package stays UI-only. Confidence: high.

[H3] bug, HIGH — `affiliate.email` column searchable+sortable on a virtual accessor → SQL error
File: src/Resources/AffiliateOfferApplicationResource/Tables/AffiliateOfferApplicationsTable.php:34-37
Evidence: `TextColumn::make('affiliate.email')->searchable()->sortable()`. Affiliate::email is an Eloquent Attribute accessor over contact_methods (affiliates/src/Models/Affiliate.php:410-420+), and no affiliates migration creates an `email` column (only a creative-type comment mentions 'email'). Filament generates whereHas/order queries against a nonexistent `email` column → QueryException when a user searches or sorts by Email.
Recommendation: drop searchable()/sortable() on that column, or point search at a real relation/column (e.g. contactMethods value). Confidence: high.

[M1] bug, MEDIUM — Merchant dashboard uses ambient owner scope on a network-global admin page
File: src/Pages/MerchantDashboardPage.php:91-139
Evidence: getSitesCount/getVerifiedSitesCount/getActiveOffersCount/getPendingApplicationsCount/getRecentApplications/getTopOffers all query with default scopes, while the page is gated by NetworkAdminAccess (global) and the sibling NetworkStatsAggregator uses OwnerContext::withOwner(null)+scope bypass. With owner.enabled=true the dashboard shows single-tenant (or global-only/empty) numbers, or throws NoCurrentOwnerException — inconsistent with the widgets on the same screen.
Recommendation: mirror NetworkStatsAggregator (explicit global + withoutOwnerScope/withoutGlobalScope) or reuse it. Confidence: high.

[M2] performance, MEDIUM — Marketplace N+1: per-offer status query × up to 50 rows
File: src/Pages/AffiliateMarketplacePage.php:173-183; resources/views/pages/affiliate-marketplace.blade.php:82
Evidence: blade calls `$this->getApplicationStatus($offer)` per card; each call runs OfferManagementService::applicationStatusForOffer (linkedProgram lookup + application value query, plus membership query for program offers) inside withAffiliateOwnerContext. getOffers() caps at 50 with no pagination → ~50–150 extra queries per render.
Recommendation: batch-fetch one status map (offer_id → status) for the rendered page in getOffers() and read from it in the blade. Confidence: high.

[M3] performance, MEDIUM — Unbounded pluck()->all() option lists in selects
File: src/Support/AffiliateNetworkOptionsProvider.php:16-52; used by AffiliateOfferForm.php:29-39, AffiliateOfferCategoryForm.php:26-32
Evidence: verifiedSiteOptions/activeCategoryOptions/parentCategoryOptions load entire tables into Filament Select options (searchable() then filters client-side). Grows linearly with catalog size; also unordered (parent list).
Recommendation: switch site/category/parent fields to relationship() selects with async search (plus existing firstOrFail ID revalidation), or paginate/limit options. Confidence: high.

[M4] bug, MEDIUM — Offer form validation gaps; duplicate slug → raw 500
File: src/Resources/AffiliateOfferResource/Schemas/AffiliateOfferForm.php:47-49,64-84,132-136
Evidence: `slug` is required but has no unique rule, while migration enforces `unique(['site_id','slug'])` (000003). Duplicates surface as QueryException, not a validation error (contrast: site domain and category slug both use unique()). Also: rate_base_bp/rate_fixed_minor are `numeric()` but feed int minor-unit columns (decimals/negatives accepted — money-as-int rule violated at input); currency only maxLength(3); cookie_days unbounded; ends_at may precede starts_at.
Recommendation: add unique(site_id,slug,ignoreRecord) rule, ->integer()->min(0) (+max for bp) on rate fields, size:3/alpha on currency, min(0) on cookie_days, ends_at after-or-equal starts_at. Confidence: high.

[M5] bug, MEDIUM — Category parent assignment allows hierarchy cycles
File: src/Resources/AffiliateOfferCategoryResource/Schemas/AffiliateOfferCategoryForm.php:26-32; Pages/CreateAffiliateOfferCategory.php, EditAffiliateOfferCategory.php mutateFormDataBefore*
Evidence: parent options exclude only the record itself (`excludeId`), and mutate* only revalidates existence. Setting parent to a descendant creates a cycle (ancestor traversal loops). No descendants check anywhere in package or (checked) form path.
Recommendation: validate parent is not a descendant of the record (walk parent chain in core or a rule) — core-side rule preferred per adapter-only guardrail. Confidence: high.

[M6] security, MEDIUM — Affiliate identity resolved by unverified email match
File: src/Pages/AffiliateMarketplacePage.php:128-156
Evidence: getAffiliate() matches `$user->email` (fallback when getEmail() absent) against affiliate contact_methods with no email-verification check, then applyForOffer()/generateLink() act as that affiliate. If the host app lets users change emails without verification, an attacker can claim a victim affiliate's identity and generate tracking links as them.
Recommendation: require verified email (e.g. hasVerifiedEmail / email_verified_at) before resolving, or bind affiliate to user id explicitly. Confidence: med (exploitability depends on host user model).

[M7] bug, MEDIUM — Offer activate/pause bypass the domain service + write outside owner context
File: src/Resources/AffiliateOfferResource/Tables/AffiliateOffersTable.php:100-125
Evidence: actions fetch inside `OwnerContext::withOwner(null)` but call `$scopedRecord->update(['status'=>...])` outside it, and mutate status directly instead of via OfferManagementService/Actions — violating the package's adapter-only guardrail (skips transitions, validation, events, notifications; risks ScopesByBelongsToOwner updating-hook throw when enabled).
Recommendation: move publish/archive transitions into affiliate-network Actions and call them here (wrapped in the same explicit-global context the core action expects). Confidence: high.

[L1] security, LOW — Marketplace page has no canAccess gate or rate limiting
File: src/Pages/AffiliateMarketplacePage.php (whole; contrast MerchantDashboardPage.php:32-45)
Evidence: every resource, both widgets, and MerchantDashboardPage gate on NetworkAdminAccess; the marketplace exposes cross-tenant offers plus state-changing applyForOffer($offerId,$reason)/generateLink($offerId) Livewire actions to any panel user with no throttle and no $reason length cap.
Recommendation: if public-by-design, document it and add rate limits + reason maxLength; otherwise add canAccess(). Confidence: med.

[L2] bug, LOW — getReviewerName can TypeError under strict types
File: src/Resources/AffiliateOfferApplicationResource/Tables/AffiliateOfferApplicationsTable.php:164-176
Evidence: declared `?string` return but returns `$user->name ?? $user->getAuthIdentifier()` (getAuthIdentifier returns mixed, commonly int) and `getName()` mixed. With strict_types=1 a non-string return is a TypeError.
Recommendation: cast: `(string) (...)`. Confidence: med.

[L3] performance, LOW — Merchant dashboard blade runs each query twice
File: resources/views/pages/merchant-dashboard.blade.php:37,43,69,75
Evidence: getRecentApplications() and getTopOffers() each invoked twice (isEmpty + foreach); getStats() runs 4 count queries.
Recommendation: compute once in mount()/cache property. Confidence: high.

[L4] bug, LOW — Site form: no domain format/normalization; status transitions unrestricted
File: src/Resources/AffiliateSiteResource/Schemas/AffiliateSiteForm.php:28-62
Evidence: domain is required+unique but accepts `http://…`/uppercase/whitespace despite helper text; status select permits any transition (e.g. straight to Verified without verification, or away from Verified leaving verified_at stale).
Recommendation: add domain rule + lowercase/trim normalization; route verification through SiteVerificationService or constrain transitions. Confidence: med.

[L5] bug, LOW — NetworkStatsAggregator: uncast sums + hardcoded USD
File: src/Support/NetworkStatsAggregator.php:25-27,44
Evidence: `sum()` returns mixed (driver-dependent int|string) but is passed to formatMinor(int) under strict_types=1 and declared int in the shape; revenue is formatted as 'USD' while offers carry per-record currency (TopOffersWidget already does per-record currency correctly).
Recommendation: cast `(int)` on sums; note multi-currency limitation or aggregate per currency. Confidence: med.

[L6] bug, LOW — TopOffersWidget user sorting applies only within cached top-10-by-clicks
File: src/Widgets/TopOffersWidget.php:32-61
Evidence: ID list cached ordered by clicks; table then `whereKey($ids)` with sortable conversions/revenue columns — re-sorting reorders only the cached 10, silently misleading.
Recommendation: disable column sorting or make sort invalidate/rebuild the ID cache. Confidence: med.

POSITIVES (brief): all Blade output escaped (no {!! !!}); marketplace search is parameterized with %/_ escaping, 3-char minimum, driver-aware like/ilike, and limit(50); resources eager-load relations and bypass scopes explicitly with clear comments; submitted site/category/parent IDs revalidated via whereKey+firstOrFail in Create/Edit mutators; NetworkAdminAccess fails closed (false on empty ability/anonymous user) with super-admin support via FilamentPermission; expensive widgets cached 30s via OwnerCache; money display via MoneyFormatter; nav via config navigation.group + getNavigationGroup per repo rules; no migrations/FKs/SoftDeletes; orchestration mostly via OfferManagementService/OfferLinkService; no Octane-unsafe static request state (OwnerContext is request-scoped; page memoization is instance-level).

ALSO NOTED: package ships zero automated tests (no tests/ dir; only docs/08-testing.md). No mass-assignment, SSRF, path-traversal, deserialization, or injection vectors found in this package's own code; XSS surface is escaped output only.