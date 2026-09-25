---
title: Affiliate Network + Affiliates Engine Study
status: research
---

# Affiliate Network end-to-end + engine seam study

Primary-source study of `packages/affiliate-network` (the marketplace) and
`packages/affiliates` (the engine), plus the seam between them. Every claim
below cites the file it was read from. Paths are relative to the commerce
repo root.

> Note: `packages/*/docs/*.md` are the canonical package docs per
> `docs/index.md`; this file is a cross-package research synthesis, which is
> why it lives under `docs/research/`.

## A) `packages/affiliate-network` end to end

### A1. Config surface

All from `packages/affiliate-network/config/affiliate-network.php`:

- Tables: `sites`, `offers`, `offer_categories`, `offer_creatives`,
  `offer_applications`, `offer_links` under prefix `affiliate_network_`.
- `models.affiliate`: null by default; the engine (or a custom model) fills
  it in.
- `owner.*`: multi-tenancy scoping, disabled by default.
- `currency.default`: `MYR`; fallback for reporting and records without a
  currency.
- `offers.require_approval`: default true. Note: no reader of this key was
  found in `src/` — only the per-offer `requires_approval` column is
  enforced (see Open Questions).
- `applications.auto_approve` (false), `applications.cooldown_days` (7).
- `links.parameter`: `anl` — the query param carrying the tracked slug on
  deep-link redirects.
- `cookies.*`: name `affiliate_network_link`, TTL 30 days, secure + httpOnly
  + `lax` by default, optional DNT respect.
- `checkout.*`: disabled by default; when enabled the package appends its
  cookie middleware to the `web` group and listens for orders with a
  720-hour (30-day) attribution window.
- `sync.*`: enabled, `max_subjects` 500, `max_programs` 100.
- `postbacks.*`: disabled by default; prefix `api/affiliate-network`,
  middleware `['api', 'throttle:60,1']`.
- `http.*`: connect timeout 3s, timeout 5s, 1 retry, 1 MiB max response.

### A2. Site registration, verification, statuses

- Statuses are plain strings on `AffiliateSite`: `pending`, `verified`,
  `suspended`, `rejected`
  (`packages/affiliate-network/src/Models/AffiliateSite.php`).
  `isVerified()` requires status `verified` AND non-null `verified_at`.
- `RegisterSite::execute()` (`packages/affiliate-network/src/Actions/RegisterSite.php`):
  validates name/domain/description/catalog_url, lowercases the domain,
  creates the site as `pending` owned by the registering user with a random
  40-char `verification_token`. Duplicate domains are rejected, except a
  same-owner `rejected` domain is resubmitted as `pending` with a fresh
  token. A pending site is marketplace-invisible: offers cannot publish,
  links do not redirect, postbacks are refused.
- Verification strategies (`packages/affiliate-network/src/Strategies/`,
  tagged `affiliate-network.site_verification_strategy` in
  `packages/affiliate-network/src/AffiliateNetworkServiceProvider.php`):
  - `dns` (`DnsVerificationStrategy.php`): a TXT record on the domain whose
    value exactly equals (`hash_equals`) the token.
  - `meta_tag` (`MetaTagVerificationStrategy.php`): homepage HTML contains
    `<meta name="affiliate-network-verify" content="{token}">` (either
    attribute order).
  - `file` (`FileVerificationStrategy.php`): path
    `/.well-known/affiliate-network-verify.txt` serves exactly the token.
- `SiteContentFetcher` (`packages/affiliate-network/src/Support/SiteContentFetcher.php`)
  fetches over https-then-http through `PublicHttpUrlGuard` +
  `PinnedHttpClient`, bounded to the configured max response bytes.
- `SiteVerificationService::verify()` (`packages/affiliate-network/src/Services/SiteVerificationService.php`):
  unknown method or null token returns false; on success sets status
  `verified`, records `verification_method`, stamps `verified_at`.
  `generateToken()` uses the `affiliatenetwork-verify-` prefix.
- Verification is treated as a network fact, not tenant data:
  `AffiliateSite::isVerifiedKey()` checks unscoped by key
  (`packages/affiliate-network/src/Models/AffiliateSite.php`), and
  `scopeWhereSiteVerified()` bypasses the site owner scope
  (`packages/affiliate-network/src/Models/AffiliateOffer.php`).
- Site schema (`.../database/migrations/2000_01_01_000001_...`): uuid id,
  nullable owner morph, name, unique domain, description, status (default
  `pending`), verification_method/token/verified_at, `catalog_url`,
  `catalog_token_encrypted`, `sync_status` (default `never`),
  `last_synced_at`, settings/metadata JSON.
- Deleting a site deletes its offers one by one so each offer's `deleting`
  hook cascades to creatives, applications, links
  (`packages/affiliate-network/src/Models/AffiliateSite.php`).

### A3. Offers: fields, rates, cookies, visibility, approval

- Statuses: `draft`, `published`, `archived`
  (`packages/affiliate-network/src/Enums/OfferStatus.php`). Visibility:
  `public`, `private`, `unlisted`
  (`packages/affiliate-network/src/Enums/OfferVisibility.php`).
- `isActive()` = status published AND within optional `starts_at`/`ends_at`
  (`packages/affiliate-network/src/Models/AffiliateOffer.php`).
- Rate model (same file + migration `2000_01_01_000003_...`):
  - `rate_base_bp` (percentage in basis points) XOR `rate_fixed_minor`
    (flat minor units); `isFixed()` checks the latter; `formattedRate()`
    renders money or `xx.xx%`.
  - `currency` (3-char), `cookie_days`, `volume_tiers` JSON
    (`min_volume_minor`, `rate_bp`, `currency`), `active_promotions` JSON.
  - `rate_source`: `synced` (importer owns the rate block) or `manual`
    (operator overrode). A model `updating` hook flips `manual` on any
    operator write to the rate-block columns, unless the importer flag is
    set; explicitly setting `synced` clears the checksum so the next sync
    re-applies catalog rates.
  - `normalizeVolumeTiers()` stamps each tier with the offer currency,
    falling back to the network default.
- Other fields: `name`, per-site-unique `slug`, `description`, `terms`,
  `is_featured`, `requires_approval` (default true), `landing_url`,
  `restrictions`/`metadata` JSON, `external_program_id`,
  `subject_type`/`subject_key`, `source_url`, `source_checksum`,
  `last_synced_at`, `starts_at`/`ends_at`/`published_at`/`archived_at`.
  Index on `(site_id, external_program_id, subject_key)` supports sync
  upserts.
- `CreateOffer` (`packages/affiliate-network/src/Actions/CreateOffer.php`):
  validates the payload (slug auto-derived, currency uppercased,
  owner-scope guards on site/category), defaults status to `draft`, and
  refuses `published` on unverified sites. Sync internals (`source_checksum`,
  `last_synced_at`) are not fillable; only this action and `UpdateOffer`
  persist them via `forceFill`.
- `SubmitOffer` (`packages/affiliate-network/src/Actions/SubmitOffer.php`):
  merchant self-service; verified sites only, always forced to `draft` —
  publishing stays an explicit operator decision.
- `UpdateOffer` (`packages/affiliate-network/src/Actions/UpdateOffer.php`):
  allow-listed fields only; re-validates site/category moves like create;
  publishing to `published` requires the (possibly new) site to be verified.
- `ArchiveExpiredOffersCommand`
  (`packages/affiliate-network/src/Console/Commands/ArchiveExpiredOffersCommand.php`):
  archives `published` offers whose `ends_at` is older than `--older-than`
  days (default 90), batched per owner, with `--dry-run`.
- Events: `OfferCreated`, `OfferUpdated`
  (`packages/affiliate-network/src/Events/`).

### A4. Creatives and categories

- `AffiliateOfferCreative`
  (`packages/affiliate-network/src/Models/AffiliateOfferCreative.php`,
  migration `2000_01_01_000004_...`): types `banner`, `text`, `email`,
  `html`, `video`; fields name/description/url/file_path/width/height/
  alt_text/html_code/is_active/sort_order/metadata.
- `AffiliateOfferCategory`
  (`packages/affiliate-network/src/Models/AffiliateOfferCategory.php`,
  migration `2000_01_01_000002_...`): owner-scoped, self-parented tree
  (delete re-parents children and nulls offers' `category_id`), slug unique
  per owner.
- Deleting an offer deletes its creatives, applications, links
  (`packages/affiliate-network/src/Models/AffiliateOffer.php`).

### A5. Catalog sync: local-reader vs remote-HTTP, upsert, checksum

- Entry points: `SyncSiteOffersCommand`
  (`packages/affiliate-network/src/Console/Commands/SyncSiteOffersCommand.php`)
  — `affiliate-network:sync-offers {site} [--program=]`; honors
  `sync.enabled`, resolves the site by id-or-domain unscoped, runs in the
  site owner's context, stamps `failed`/`partial` on errors.
- Reader selection (`packages/affiliate-network/src/Services/Catalog/CatalogReaderResolver.php`):
  site with `catalog_url` → `RemoteCatalogClient`; otherwise the local
  reader bound by the engine under string key
  `affiliate-network.catalog.local-reader` (throws `AffiliatesNotInstalled`
  for local sync when the engine is absent). Both implement
  `CatalogReaderInterface::snapshot($site, $programId)` /
  `programIds($site)`
  (`packages/affiliate-network/src/Services/Catalog/CatalogReaderInterface.php`).
- Remote pull (`packages/affiliate-network/src/Services/Catalog/RemoteCatalogClient.php`):
  `GET {catalog_url}/programs` then
  `GET {catalog_url}/programs/{id}/catalog`, bearer token decrypted from
  `catalog_token_encrypted` (fail-closed: undecryptable token throws rather
  than going anonymous), URL-validated, bounded body (1 MiB), JSON decoded.
  Failures surface as `OfferNotFoundException`.
- Upsert (`packages/affiliate-network/src/Services/OfferImportService.php`):
  - Keyed by `(site_id, external_program_id, subject_key)`; one preload
    query per program; capped at `max_subjects` (500) per program and
    `max_programs` (100) per `syncAll`; per-subject failures are logged and
    counted, never aborting the run; site stamped `ok`/`partial`.
  - Checksum = sha1 of effective rates + title + url + currency +
    cookie_days + volume tiers + promotions; identical checksum → `skipped`.
  - New offers are created `draft`/`public` with `rate_source: synced`,
    slug `{subject_type}-{subject_key}-{program-suffix}`, and metadata
    `{subject, catalog_source: local|remote, catalog_version}`.
  - Re-syncs never touch operator-owned lifecycle: status, visibility,
    slug, description are unset from update payloads.
  - Rate lock: if `rate_source` is `manual` and incoming rates differ, all
    rate fields are held back, mirror fields still refresh, the incoming
    checksum is stamped (so the next identical sync skips), result `locked`.
  - Writes go through `CreateOffer`/`UpdateOffer` inside a transaction with
    the `syncingImport` flag set (reset in `finally`, Octane-safe), so the
    rate-lock hook can tell importer writes from operator writes.
  - Relative/non-http(s) catalog URLs map to null at the boundary; title/
    url/currency resolution prefers subject-level over program-level
    (local) or vice versa (remote) via `resolveField()`.
  - Manual offers (`external_program_id` null) are never touched.

### A6. Applications: apply, approve, cooldown

- Statuses: `pending`, `approved`, `rejected`, `revoked`
  (`packages/affiliate-network/src/Enums/ApplicationStatus.php`); schema has
  reason/rejection_reason/reviewed_by/reviewed_at/approved_at/rejected_at/
  revoked_at + unique `(offer_id, affiliate_id)`
  (migration `2000_01_01_000005_...`).
- `ApplyToOffer` (`packages/affiliate-network/src/Actions/ApplyToOffer.php`):
  the offer must be `published` + `public` + on a verified site (resolved
  globally); the affiliate must be `findAccessible()`-visible; writes run in
  the affiliate owner's context. Auto-approves when the offer does not
  require approval OR `applications.auto_approve` is set. Existing
  applications are returned as-is, except `rejected` ones may re-apply only
  after `cooldown_days` (default 7) past `rejected_at` — otherwise
  `ApplicationAlreadySubmittedException`. Race-safe via unique-violation
  catch. Fires `ApplicationSubmitted`.
- `ApproveApplication`
  (`packages/affiliate-network/src/Actions/ApproveApplication.php`):
  re-queries scoped (ownership), then checks the offer's site verification
  unscoped; refuses unverified sites; stamps approved; fires
  `ApplicationApproved`. No listener of either event was found in either
  package's `src/` — approval does not auto-enroll anything by itself.
- `rejectApplication()` / `revokeApplication()` live on
  `OfferManagementService`
  (`packages/affiliate-network/src/Services/OfferManagementService.php`),
  stamping rejected/revoked timestamps.
- Every offer — mirrored or hand-written — uses network applications.
  Joining never requires, resolves, or creates a merchant-side account;
  ids without a merchant affiliate row resolve as the host user (see B3).

### A7. Offer links: sub-ids, deep links, tracking URLs

- `AffiliateOfferLink`
  (`packages/affiliate-network/src/Models/AffiliateOfferLink.php`,
  migrations `2000_01_01_000006/...` + `...000007_...`): attribution record
  per affiliate/offer pair — `offer_id`, `affiliate_id`, nullable `site_id`,
  `sub_id`/`sub_id_2`/`sub_id_3`, counters `clicks`/`conversions`/`revenue`
  (minor, single currency), `is_active`, metadata, plus `link_id` pointing
  at the backing `aiarmada/links` tracked link. Redirect mechanics (slug,
  destination, signed URLs, expiry) live on the tracked link; `code`,
  `target_url`, `custom_parameters`, `expires_at` were dropped from this
  table in migration `...000007`.
- `OfferLinkService::createLink()`
  (`packages/affiliate-network/src/Services/OfferLinkService.php`): requires
  an active offer, a verified site, and (when the offer requires approval)
  an approved affiliate. Target URL = `options['target_url']` (deep link
  override) else the offer's `landing_url` else `https://{site domain}/`;
  must be http(s). Creates the attribution row, then mints a tracked link
  via `CreateLink::run` with a 16-char generated slug (3 attempts on
  collision), destination parameters `{anl: slug, sub1..3}` merged over
  caller `custom_parameters`, `require_signature: true`, subject = the
  offer-link morph, https requirement opted out (merchants may serve plain
  http).
- `generateTrackingUrl()` delegates to `GenerateLinkUrl` on the tracked
  link; `resolveLink($slug)` resolves globally by slug (active links only,
  never eager-loading affiliate identity so it works engine-less).
- `recordConversion()` wraps `RecordNetworkConversion` in the link owner's
  context; `getStats()` returns clicks/conversions/revenue + conversion
  rate + revenue-per-click with formatted money.

### A8. Redirect gate and click mirroring

- Redirects are served by `aiarmada/links` (`/go/{slug}`); the network only
  contributes policy + counters
  (`packages/affiliate-network/routes/api.php` header comment).
- `OfferLinkGate`
  (`packages/affiliate-network/src/Support/OfferLinkGate.php`, bound to
  `LinkGateInterface` in the network provider): ignores non-network
  subjects; otherwise blocks with reasons `link_unknown`, `link_inactive`,
  `offer_inactive`, `site_unverified` (either the link's or the offer's site
  verified passes), `not_approved` (when the offer requires approval, via
  `isApprovedForOffer`, which delegates to program membership for local
  offers).
- `IncrementNetworkLinkClicks`
  (`packages/affiliate-network/src/Listeners/IncrementNetworkLinkClicks.php`),
  subscribed to `LinkClicked` in the network provider: ignores other
  subjects and bot clicks (`$event->click->is_bot`), then `incrementClicks()`
  in the affiliate owner's context.

### A9. Conversion recording: cookie, order, postback

- `RecordNetworkConversion`
  (`packages/affiliate-network/src/Actions/RecordNetworkConversion.php`):
  increments the link counters via `recordConversion()`; on currency
  mismatch vs the link currency it still counts the conversion but records
  zero revenue (warn-logged) so totals never mix currencies; fires
  `NetworkConversionRecorded($link, $counterRevenue, $currency)`; only when
  an `externalReference` is present does it call `PostNetworkConversionToLedger`.
- Cookie capture: `TrackNetworkLinkCookie`
  (`packages/affiliate-network/src/Http/Middleware/TrackNetworkLinkCookie.php`),
  appended to the `web` group only when `checkout.enabled`
  (`packages/affiliate-network/src/AffiliateNetworkServiceProvider.php`):
  reads the slug from `cookies.query_parameters` + `links.parameter`, keeps
  the newest click, resolves + validates the link (not expired, offer
  active), and stores an *encrypted* JSON payload
  `{code, affiliate_id, offer_id, clicked_at}` in the configured cookie.
  `parseCookie()` accepts only decryptable payloads.
- Order path: `RecordNetworkConversionForOrder`
  (`packages/affiliate-network/src/Listeners/RecordNetworkConversionForOrder.php`),
  subscribed to `AIArmada\Orders\Events\CommissionAttributionRequired` when
  `checkout.enabled` + `listen_for_orders` and the orders package exists:
  idempotent on `order.metadata.network_attribution`; resolves the link
  from the cookie; enforces the attribution window (default 720h; malformed
  `clicked_at` treated as expired); records conversion with
  `grand_total`/`currency`/`order_number`; stamps attribution into order
  metadata.
- Postback path: `POST {prefix}/conversions`
  (`packages/affiliate-network/routes/api.php` → `ReportNetworkConversionController`),
  only registered when `postbacks.enabled`. Validates
  site/link_code/external_reference/revenue_minor/currency; authenticates
  via bearer catalog token (`hash_equals` against the decrypted site token;
  401 on mismatch); requires a verified site (403); requires the link to
  belong to that site (404) with an active offer (410); idempotent on
  (link, external reference) via `ledger->findPosted()` — redeliveries
  return the existing state without touching counters; responds with
  `{ok, duplicate, network: stats, conversion: ledger row?}`.
- Commission math lives in `PostNetworkConversionToLedger`
  (`packages/affiliate-network/src/Actions/PostNetworkConversionToLedger.php`):
  no-ops without a bound ledger or without any offer rate; resolves
  currency conversion → link → offer → network default; fixed rate wins,
  else `round(revenue * rate_base_bp / 10000)`; posts a
  `NetworkConversionDraft` to the ledger.

### A10. Stats aggregation

- Network-side stats are live counters, not rollups: `clicks`,
  `conversions`, single-currency `revenue` on each link, plus derived
  conversion rate / revenue-per-click in `OfferLinkService::getStats()`.
- Ledger reconciliation (`NetworkLedgerReconciliationService`,
  `packages/affiliate-network/src/Services/NetworkLedgerReconciliationService.php`,
  requires a ledger): `reconcileLink()` compares link counters against
  `rowsForLink()` grouped per currency leg (conversion counts must match;
  revenue must match within the link currency leg);
  `reconcileOffer()` rolls this up per offer.
- Engine-side aggregation (daily stats, summaries, trends) is covered in B7.

### A11. Merchant step-by-step (network package only)

1. Register the site (`RegisterSite`): name + domain (+ optional
   `catalog_url` for remote merchants). Site is `pending`, token issued.
2. Prove domain control via one strategy: DNS TXT, meta tag, or
   verification file (`SiteVerificationService::verify()`); status →
   `verified`.
3. Submit offers (`SubmitOffer` → always `draft`); an operator publishes
   them (`CreateOffer`/`UpdateOffer` enforce verified-site for `published`).
4. Mirror engine programs (optional): run `affiliate-network:sync-offers`
   per site/program; imported offers arrive `draft` for operator review;
   rate edits lock (`manual`) against future syncs.
5. Review publisher applications: approve (`ApproveApplication`) / reject /
   revoke (`OfferManagementService`).
6. Remote merchants: capture the `anl` slug on arrival (engine merchant
   middleware, see B5) and POST paid sales to `/conversions` with the
   catalog bearer token. Local merchants: enable `checkout` integration and
   conversions are recorded from orders automatically.

### A12. Publisher step-by-step (network package only)

1. Find a `published` + `public` offer on a verified site
   (`resolvePublicOfferOrFail()` / `ApplyToOffer` guards).
2. Apply (`applyForOffer`); instant approval if the offer needs none or
   auto-approve is on; rejection enforces a 7-day re-apply cooldown.
3. Mint links (`createLink`), optionally with `sub_id`/`sub_id_2`/`sub_id_3`
   (forwarded as `sub1..3` params), a deep-link `target_url` override,
   `custom_parameters`, expiry, metadata.
4. Share the tracking URL (`generateTrackingUrl`); redirects are gated
   (`OfferLinkGate`) and human clicks increment counters
   (`IncrementNetworkLinkClicks`).
5. Conversions accrue on the link counters; with the engine installed they
   also post to the ledger and become payable balances (see B6/B7).

### A13. What works with NO engine installed

- The network provider binds `UserKeyAffiliateIdentityResolver` (auth user
  key doubles as affiliate id; user row doubles as owner) and defaults
  `models.affiliate` to the auth user model — but only when the engine
  hasn't already bound/configured its own, so both provider orders work
  (`packages/affiliate-network/src/AffiliateNetworkServiceProvider.php`).
- Fully engine-less: site registration/verification, offer CRUD + archive,
  categories/creatives, applications + approvals + cooldown, link
  minting/resolution/redirect gating, click mirroring, cookie capture,
  order-listener conversions, postback conversions (minus ledger rows),
  link stats.
- Gated on the engine: local catalog sync (throws `AffiliatesNotInstalled`),
  ledger posting (silently counters-only when no ledger is bound —
  `PostNetworkConversionToLedger` returns null), ledger reconciliation
  (throws).
- Remote catalog sync + remote postbacks work engine-less on the network
  side (the *merchant* side runs the engine for those).

## B) THE SEAM between network and engine

### B1. Network-side contracts (`packages/affiliate-network/src/Contracts/`)

- `AffiliateIdentityResolver`: `find()` (explicit global lookup),
  `findAccessible()` (additionally scope-visible), `findIdForVerifiedEmail()`.
  The network only ever handles opaque ids + owner coordinates.
- `NetworkLedger`: `post(NetworkConversionDraft)` (idempotent on
  link + external reference; null = unknown affiliate),
  `findPosted($linkId, $externalReference)`, `rowsForLink($linkId)` for
  reconciliation.
- `SiteVerificationStrategyInterface`: network-internal; not part of the
  engine seam.
- There is no program-membership bridge: the former `LinkedProgramBridge`
  contract was removed. Enrollment for every offer is a network
  application; merchant program memberships are merchant-side only.

### B2. Network-side data (`packages/affiliate-network/src/Data/`)

- `NetworkAffiliate {id, code, email?, ownerType, ownerId}` — identity +
  owner coordinates; never an engine model. `owner()` rehydrates via
  `OwnerContext`.
- `NetworkConversionDraft {linkId, offerId, siteId?, affiliateId, linkCode,
  revenueMinor, currency, externalReference, commissionMinor}` — network
  math already resolved; the ledger only persists/guards/accounts.
- `NetworkPostedConversion {id, affiliateCode, commissionMinor,
  commissionCurrency, status}` — the ledger row as seen by the network
  (`toArray()` is what postbacks return).

### B3. Engine-side adapters (`packages/affiliates/src/Network/`)

- `AffiliatesIdentityResolver`: `find()` looks up `Affiliate` unscoped,
  falling back to the host user when no affiliate row matches (nothing is
  required or provisioned);
  `findAccessible()` enforces the affiliates owner scope via
  `OwnerWriteGuard` when enabled (out-of-scope affiliates fail closed with
  no user fallback; missing rows fall back to the scope-checked user);
  `findIdForVerifiedEmail()` matches the
  `email`/`general` contact method (contact_email is virtual) and requires
  `Active` status.
- `AffiliatesLedger` (see B6 for the full flow): persists network drafts as
  `AffiliateConversion` rows (`conversion_type: network`, `origin:
  network`, `subject_type: network_order`, `network_link_id` set,
  idempotency key `sha256('network|link|reference')`), clamps commission
  via `CommissionCaps`, runs fraud (`analyzeConversion`; failures flip to
  `RejectedConversion`), applies accounting, dispatches
  `AffiliateConversionRecorded`.
- `LocalProgramReader`: shared-DB catalog reader; runs in the *site
  owner's* context; `snapshot()` delegates to `ProgramCatalogService`;
  `programIds()` lists `active()` + `public()` programs capped at
  `max_programs`. Strictly read-only (snapshot never records attribution/
  commission/payout activity).

### B4. Provider wiring on both sides

- Network provider
  (`packages/affiliate-network/src/AffiliateNetworkServiceProvider.php`):
  singletons for verification, offer-management, link, catalog-resolver,
  remote-client, and import services; binds `LinkGateInterface` →
  `OfferLinkGate`; subscribes `LinkClicked` → `IncrementNetworkLinkClicks`;
  conditional default identity resolver + default affiliate model (both
  yield to the engine); tags the three verification strategies; checkout
  integration (cookie middleware + order listener) only when enabled.
- Engine provider
  (`packages/affiliates/src/AffiliatesServiceProvider.php`,
  `registerNetworkSeam()`): skipped entirely when the network package is
  absent (plain merchant installs never load network classes). When
  present, binds `AffiliateIdentityResolver` → `AffiliatesIdentityResolver`,
  `NetworkLedger` → `AffiliatesLedger`, and the local-reader string key →
  `LocalProgramReader`; sets `affiliate-network.models.affiliate` to the
  engine `Affiliate` model unconditionally.

### B5. Engine programs become network offers

- Merchant side: engine `ProgramCatalogService::snapshot()`
  (`packages/affiliates/src/Services/ProgramCatalogService.php`) folds
  deterministic rules (product > category > program) into per-subject
  `effective {commission_type, rate_bp|fixed_minor, applied_rule_ids}`;
  per-affiliate variable extras (volume tiers, promotions) are listed
  separately, never folded; never calls the usage-incrementing
  `calculate()` path. Served over HTTP by `ProgramCatalogController`
  (`packages/affiliates/src/Http/Controllers/ProgramCatalogController.php`):
  `GET programs` (active+public via `ProgramService::getAvailablePrograms`)
  and `GET programs/{id}/catalog` (404 unless active+open), behind
  `EnsureApiAuthorized` bearer check + optional owner middleware, only when
  `affiliates.api.enabled`
  (`packages/affiliates/routes/api.php`,
  `packages/affiliates/src/Support/Middleware/EnsureApiAuthorized.php`).
- Network side: `OfferImportService` upserts one offer per subject as
  described in A5, stamping `external_program_id`, `subject_*`, checksum,
  and `catalog_source` local/remote.
- No approval delegation: every mirrored offer enrolls through network
  applications. Merchant program memberships are merchant-side only —
  `ProgramService::joinProgram`
  (`packages/affiliates/src/Services/ProgramService.php`) stays for portal
  joins: idempotent (existing membership returned), requires `canJoin()`
  (program open + not already member + eligibility rules), default tier
  resolved, status `approved` unless the program requires approval;
  race-safe unique catch; dispatches `AffiliateProgramJoined`. Membership
  statuses: pending/approved/rejected/suspended
  (`packages/affiliates/src/Enums/MembershipStatus.php`).

### B6. A network conversion posts to the engine ledger

`AffiliatesLedger::post()`
(`packages/affiliates/src/Network/AffiliatesLedger.php`):

1. Affiliate lookup unscoped by draft id; on miss, the draft id is
   resolved as a host user and mapped to the Active affiliate holding the
   same account email (joining never creates this linkage). Still
   unknown → null (nothing posted).
2. Existing `(network_link_id, external_reference)` row → return it
   (idempotent).
3. Create `AffiliateConversion`: revenue into `subtotal_minor` +
   `value_minor`, commission clamped by `CommissionCaps` (zero stays zero;
   minimum floors earned commission; maximum caps it —
   `packages/affiliates/src/Services/Commissions/CommissionCaps.php`),
   status `ApprovedConversion` if `affiliates.commissions.auto_approve`
   else configured default (`pending` —
   `packages/affiliates/config/affiliates.php`), owner copied from the
   affiliate, metadata `{offer_id, link_code, site_id}`.
4. Fraud screen (`FraudDetectionService::analyzeConversion`, six tagged
   rules — click/conversion velocity, fast conversion, fingerprint repeat,
   geo anomaly, self-referral); disallowed → `RejectedConversion`.
5. `ApplyConversionAccounting`
   (`packages/affiliates/src/Actions/Conversions/ApplyConversionAccounting.php`):
   per-currency `AffiliateBalance` row (created on demand, race-safe);
   approved → `available_minor` + lifetime; pending/qualified → holding;
   paid → lifetime (and deducts from available when not attached to a
   payout); transitions move/void between holding and available under row
   locks.
6. Dispatches `AffiliateConversionRecorded` with `AffiliateConversionData`.
- Conversion schema
  (`packages/affiliates/database/migrations/2000_01_01_000003_...`): unique
  `idempotency_key`, indexed `network_link_id`, `external_reference`,
  `origin`, `conversion_type`, per-status/timestamp indexes.
- Merchant SDK pieces that feed this from remote stores:
  `CaptureNetworkReferral` middleware stashes the `anl` slug in session
  (`packages/affiliates/src/Merchant/CaptureNetworkReferral.php`);
  `NetworkPostbackClient::report()` POSTs
  `{site, link_code, external_reference, revenue_minor, currency}` with the
  merchant token to the network endpoint
  (`packages/affiliates/src/Merchant/NetworkPostbackClient.php`), configured
  via `affiliates.merchant.*`
  (`packages/affiliates/config/affiliates.php`: `network_url`, `prefix`,
  `site`, `token`, `referral_param`, `session_key`, timeout).

### B7. Stats, reconciliation, payouts (engine side)

- `DailyAggregationService`
  (`packages/affiliates/src/Services/DailyAggregationService.php`): one
  `AffiliateDailyStat` row per affiliate/day/currency (upsert on that key);
  clicks/unique/attributions live only on the affiliate-currency row so
  period sums never double-count; money per leg; multi-currency totals
  converted via `CurrencyConverter` (null when a rate is missing).
  `AffiliateReportService` adds summaries, top affiliates, trends, traffic
  sources, top subjects.
- Network↔ledger reconciliation is `NetworkLedgerReconciliationService`
  (A10); engine-internal payout reconciliation is the separate
  `PayoutReconciliationService` (external payout statuses, reserved funds,
  balance audits).
- Payouts consume approved balances: `CreatePayout` requires
  `available_minor` to cover the total
  (`packages/affiliates/src/Actions/Payouts/CreatePayout.php`).

### B8. Full click-to-payout money walkthrough (both packages)

Actors: **publisher** (affiliate), **network operator** (marketplace host),
**merchant** (store owner). Money flows merchant → publisher; the network
package itself takes no cut in code (no fee/split logic was found — see
Open Questions).

1. Publisher mints a network offer link; the tracked slug + `anl` param +
   sub-ids ride the redirect (`OfferLinkService::createLink`).
2. Shopper clicks; `aiarmada/links` redirects after `OfferLinkGate` policy;
   human clicks increment the network counter (`IncrementNetworkLinkClicks`).
3. Shopper buys. Local: the order listener attributes via the encrypted
   cookie. Remote: merchant session-captured the slug
   (`CaptureNetworkReferral`) and postbacks the paid sale
   (`NetworkPostbackClient` → `ReportNetworkConversionController`).
4. Network increments link counters and computes commission from offer
   rates (`RecordNetworkConversion` → `PostNetworkConversionToLedger`).
5. Engine ledger persists the conversion (clamped, fraud-screened,
   accounted to the publisher's per-currency balance) and emits
   `AffiliateConversionRecorded` (`AffiliatesLedger::post`).
6. Conversion matures/approves (auto-approve config or operator flow);
   approved money lands in `available_minor` (`ApplyConversionAccounting`).
7. Publisher is paid out from available balance (`CreatePayout` et al.);
   the merchant funds this — the engine tracks the liability on
   `AffiliateBalance`, and payout processors (manual/PayPal/Stripe Connect
   in `packages/affiliates/src/Services/Payouts/`) execute the transfer.
8. Finance proves correctness per link/currency leg
   (`NetworkLedgerReconciliationService`) and per payout
   (`PayoutReconciliationService`).

## Open Questions

1. `affiliate-network.offers.require_approval` (default true) has no reader
   in `src/` — only the per-offer `requires_approval` column is enforced
   (`ApplyToOffer`, `OfferLinkService`, `OfferLinkGate`). Dead config or
   consumed by a Filament/admin package not in scope?
2. No fee/revenue-split logic (network cut, merchant billing) was found in
   either package's `src/`. Is the money walkthrough's "merchant pays
   publisher in full" actually the intended model, or does settlement live
   elsewhere?
3. `ApplicationApproved` / `ApplicationSubmitted` have no listeners in
   either package — is enrollment/notification handled by a UI package, or
   genuinely absent?
4. `AffiliateOffer.volume_tiers` / `active_promotions` are display mirrors
   from catalog sync; no code path was found that applies them to actual
   commission math (network math uses only base/fixed rates; engine ledger
   clamps the posted figure). Confirm they are display-only.
5. The engine `RecordCommissionForOrder` listener and the network
   `RecordNetworkConversionForOrder` listener both subscribe to
   `CommissionAttributionRequired` — what prevents double-paying an order
   that carries both a cart affiliate and a network cookie? No
   cross-guard was found in the read sources.
6. `is_featured`, `restrictions`, `settings`, and creative `sort_order`
   semantics are stored but unenforced in `src/` — presumably UI-layer
   concerns; unverified here.
7. Postback `revenue_minor` is trusted from the merchant as-is (no order
   verification on the network side). Is that an accepted trust boundary?
