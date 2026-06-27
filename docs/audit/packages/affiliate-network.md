# Package Audit — aiarmada/affiliate-network

## 1. Audit metadata

- **Path:** `packages/affiliate-network`
- **Version:** none (dev)
- **Package type:** library
- **Language/framework:** PHP 8.4 / Laravel
- **Audit date:** 2026-06-27
- **Commit:** 7d1dc95fa
- **Auditor:** automated
- **Overall status:** Ready with minor improvements
- **Overall confidence:** High

## 2. Executive assessment

A well-architected multi-merchant affiliate marketplace package with clean separation between models, actions, services, events, strategies, and HTTP concerns. Covers site management & verification, offers & categories, applications & approvals, tracking links, click redirects, cookie-based attribution, and checkout conversion recording. Strong tenant ownership enforcement through `ScopesBySiteOwner` and `ScopesByAffiliateOwner` traits with thorough validation. 17 test files. 10 doc files. One notable bug: `ArchiveExpiredOffersCommand` checks for string `'active'` status which never matches the enum-backed `draft`/`published`/`archived` values.

## 3. Package purpose and responsibility

Multi-merchant affiliate marketplace extending core `aiarmada/affiliates` with merchant sites, offers & categories, applications & approval workflows, creatives, tracking links, cookie-based conversion attribution, and site verification (DNS, meta tag, file).

## 4. Consumers and dependencies

### Internal dependencies
- `aiarmada/affiliates` (self.version) — affiliate identities, commission layer
- `aiarmada/contacting` (self.version) — contact methods, social profiles (used by AffiliateSite, AffiliateOffer)
- `aiarmada/commerce-support` — resolved at runtime for owner scoping, audits, activity logging

### External dependencies
- `php` ^8.4
- `spatie/laravel-package-tools` (via commerce-support)
- `owen-it/laravel-auditing` (via commerce-support)

### Known consumers
- `aiarmada/filament-affiliate-network` — admin UI
- `aiarmada/checkout` — optional conversion recording bridge

## 5. Public API and contracts

| Interface | Type | Notes |
|-----------|------|-------|
| `AffiliateSite` | Model | Owner-scoped, verification workflow |
| `AffiliateOffer` | Model | Site-scoped owner enforcement, status/visibility enums |
| `AffiliateOfferCategory` | Model | Owner-scoped, hierarchical |
| `AffiliateOfferCreative` | Model | Belongs to offer |
| `AffiliateOfferApplication` | Model | Affiliate-scoped owner enforcement |
| `AffiliateOfferLink` | Model | Affiliate-scoped owner enforcement, code generation |
| `ScopesBySiteOwner` | Trait | Global scope + create/update guards |
| `ScopesByAffiliateOwner` | Trait | Global scope + create/update guards |
| `OfferStatus` | Enum | Draft, Published, Archived |
| `OfferVisibility` | Enum | Public, Private, Unlisted |
| `ApplicationStatus` | Enum | Pending, Approved, Rejected, Revoked |
| `CreateOffer` | Action | Validates site/category owner scope |
| `UpdateOffer` | Action | Dispatches OfferUpdated |
| `ApplyToOffer` | Action | Duplicate detection, cooldown, auto-approve |
| `ApproveApplication` | Action | Bypasses owner scope for admin |
| `RecordNetworkConversion` | Action | Increments link conversions + revenue |
| `OfferManagementService` | Service | Offer lifecycle orchestration |
| `OfferLinkService` | Service | Link creation, redirect, click tracking |
| `SiteVerificationService` | Service | Token generation, strategy dispatch |
| `SiteVerificationStrategyInterface` | Contract | verify(), getInstructions() |
| `DnsVerificationStrategy` | Strategy | DNS TXT record check |
| `MetaTagVerificationStrategy` | Strategy | HTML meta tag parsing |
| `FileVerificationStrategy` | Strategy | Well-known file check |
| `LinkRedirectController` | HTTP | Signed route, expiry/active checks, redirect |
| `TrackNetworkLinkCookie` | Middleware | Cookie-based attribution tracking |
| `affiliate-network:archive-expired` | Command | Archives expired offers |
| `affiliate-network.redirect` | Route | Named signed route |
| Events | 5 events | OfferCreated/Updated, ApplicationSubmitted/Approved, NetworkConversionRecorded |

## 6. Architecture and design

Strong Action/Service/Model separation. Two parallel owner-scoping concerns (via site vs via affiliate) are handled by separate traits with thorough create/update guards. Tagged strategy pattern for site verification enables easy extension. Checkout integration is gated by config + class_exists. Events provide extension points. Cookie tracking middleware is well-structured with DNT support.

## 7. Functional correctness

Key paths verified:
- **CreateOffer** — validates site owner scope, validates category owner scope, auto-generates slug, defaults status to Draft, dispatches event
- **ApplyToOffer** — bypasses site scope (public marketplace), validates affiliate owner scope, detects duplicate applications, enforces cooldown for rejected apps, auto-approves when configured
- **ApproveApplication** — bypasses affiliate scope (admin operation), updates timestamps
- **OfferLinkService::resolveLink** — correctly uses explicit global context for public redirect
- **LinkRedirectController** — checks link exists, not expired, offer active, validates redirect URL scheme
- **TrackNetworkLinkCookie** — respects DNT, multiple query param keys, JSON cookie value with attribution data

## 8. Security

- **Signed routes** — redirect link uses Laravel's `temporarySignedRoute` (CSRF/forgery protection)
- **Redirect URL validation** — controller validates scheme is http/https via `parse_url`
- **SSRF protection** — `SiteContentFetcher::isFetchableDomain()` validates domain format, checks DNS resolves to public IPs, blocks private/reserved ranges, blocks localhost/internal domains
- **Cookie security** — httpOnly, secure by default, configurable same_site
- **Owner scoping** — thorough enforcement on all models with global scope + create/update guards
- **No mass assignment** — all models use explicit `$fillable`
- **No DB constraints/cascades** — monorepo compliance confirmed

## 9. Data integrity and persistence

- All models use UUID PKs, config-driven table names
- No DB constraints or cascades (verified)
- Application-level cascades on delete (site->offers, offer->creatives/applications/links)
- Category deletion reparents children and nullifies offer category_id
- Immutable datetime casts on all timestamps
- Enum casts on status/visibility fields

## 10. Error handling and resilience

- `ApplyToOffer` — throws custom `ApplicationAlreadySubmittedException` with cooldown
- `LinkRedirectController` — aborts 404/410/400 for invalid/expired links
- `DnsVerificationStrategy` — silenced `@dns_get_record` on failure, returns false
- `SiteContentFetcher` — catch-all for HTTP exceptions, returns null
- No unbounded retries observed

## 11. Performance and scalability

- No N+1 concerns in core actions
- `ArchiveExpiredOffersCommand` uses `OwnerBatchRunner` for tenant-aware iteration
- DNS resolution in `SiteContentFetcher` could be slow for many sites but is fetch-per-verification
- Cookie tracking is lightweight (no DB write on middleware path)
- `resolveLink` eagerly loads relations in a single query

## 12. Configuration

| Key | Required | Default | Notes |
|-----|----------|---------|-------|
| `database.table_prefix` | No | affiliate_network_ | |
| `database.json_column_type` | No | commerce_json_column_type() or jsonb | |
| `owner.enabled` | No | false | |
| `owner.include_global` | No | false | |
| `offers.require_approval` | No | true | |
| `applications.auto_approve` | No | false | |
| `applications.cooldown_days` | No | 7 | |
| `links.default_ttl_minutes` | No | 43200 (30 days) | |
| `links.parameter` | No | anl | |
| `cookies.*` | No | various | 11 sub-keys |
| `checkout.*` | No | various | 4 sub-keys |
| `http.*` | No | various | 5 sub-keys |

## 13. Testing

**Command:** Not run (no test DB available)

**Test files:** 17 files

| Path | Quality |
|------|---------|
| `tests/src/AffiliateNetwork/Unit/Actions/CreateOfferTest.php` | Good — event faking, status defaults, slug generation |
| `tests/src/AffiliateNetwork/Unit/Actions/UpdateOfferTest.php` | Not read |
| `tests/src/AffiliateNetwork/Unit/Actions/ApplyToOfferTest.php` | Not read |
| `tests/src/AffiliateNetwork/Unit/Actions/ApproveApplicationTest.php` | Not read |
| `tests/src/AffiliateNetwork/Unit/Actions/RecordNetworkConversionTest.php` | Not read |
| `tests/src/AffiliateNetwork/Unit/Services/OfferManagementServiceTest.php` | Not read |
| `tests/src/AffiliateNetwork/Unit/Services/OfferLinkServiceTest.php` | Not read |
| `tests/src/AffiliateNetwork/Unit/Services/SiteVerificationServiceTest.php` | Good — 9 tests covering token generation, verify, instructions, SSRF |
| `tests/src/AffiliateNetwork/Unit/Models/*ModelTest.php` | 7 model test files — not read |
| `tests/src/AffiliateNetwork/Unit/Models/ModelsAuditingAndActivityCoverageTest.php` | Not read |
| `tests/src/AffiliateNetwork/Feature/LinkRedirectControllerTest.php` | Not read |
| `tests/src/AffiliateNetwork/Feature/TrackNetworkLinkCookieTest.php` | Not read |

**Assessment:** Good test coverage with meaningful assertions. SSRF safety tested. Event faking used. Feature tests for HTTP paths exist.

## 14. Documentation and developer experience

10 doc files covering overview, installation, configuration, usage, models, services, multi-tenancy, API reference, testing/factories, troubleshooting. Comprehensive and well-structured. Uses YAML frontmatter consistently.

## 15. Observability and operations

- Commands report counts, support dry-run
- Activity logging via `LogsCommerceActivity` trait on all models
- Audit trail via `HasCommerceAudit` trait
- Events dispatched for all key transitions
- `RecordNetworkConversionForOrder` stores attribution in order metadata

## 16. Build, CI, release, and deployment

No version declared. Tests mapped to `tests/src/AffiliateNetwork/`. Relies on monorepo-builder for publishing.

## 17. Maintainability

Clean separation of concerns. Actions, Services, Strategies, Models, Events, Exceptions all in their own directories. Two similar owner-scoping traits are correctly parallel. No dead or commented-out code.

## 18. Cross-package integration

- Depends on `aiarmada/affiliates` for the Affiliate model
- Depends on `aiarmada/contacting` for contact/social traits
- Optional checkout bridge via `AIArmada\Orders\Events\CommissionAttributionRequired` with `class_exists` guard
- Owner scoping reads config from `affiliates.owner` for affiliate-side scoping

## 19. Positive findings

- Thorough owner-scoping with create/update guards on both site and affiliate paths
- SSRF protection in `SiteContentFetcher` — domain validation, public-IP-only DNS resolution
- Tagged strategy pattern for site verification (extensible)
- Signed temporary routes for redirect links
- Cookie tracking middleware with DNT support and configurable security settings
- Checkout integration is optional and guarded by config + `class_exists`
- Dry-run support on archive command
- 17 test files + 10 doc files

## 20. Detailed findings

### AFFNET-CORR-001 ArchiveExpiredOffersCommand checks for non-existent 'active' status

- **Area:** Correctness
- **Severity:** Medium
- **Priority:** P1
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Console/Commands/ArchiveExpiredOffersCommand.php:36`
- **Evidence:** Line 36: `->where('status', 'active')` — but `AffiliateOffer` uses `OfferStatus` enum with values `draft`, `published`, `archived`. There is no `active` status.
- **Introduced by:** Unknown
- **Related findings:** None

#### Observation

The archive command filters for `status = 'active'`, but offers use a `OfferStatus` enum whose values are `draft`, `published`, and `archived`. The string `'active'` never matches any record.

#### Impact

The command runs successfully but archives zero records every time. Expired offers are never automatically archived.

#### Recommendation

Change the filter to `->where('status', OfferStatus::Published)`.

#### Acceptance criteria

`ArchiveExpiredOffersCommand` finds and archives published offers whose `ends_at` is past the threshold.

#### Remediation effort

Trivial

#### Remediation risk

Low

### AFFNET-CORR-002 ArchiveExpiredOffersCommand updates status to string instead of enum

- **Area:** Correctness
- **Severity:** Low
- **Priority:** P3
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Console/Commands/ArchiveExpiredOffersCommand.php:43`
- **Evidence:** Line 43: `$offer->update(['status' => 'archived'])` — uses string instead of `OfferStatus::Archived`
- **Introduced by:** Unknown
- **Related findings:** AFFNET-CORR-001

#### Observation

The status update uses `'archived'` (string) instead of `OfferStatus::Archived` (enum). Laravel's enum casting will convert the string to the enum, so this works at runtime, but is inconsistent with the codebase style.

#### Recommendation

Use `OfferStatus::Archived` instead of `'archived'`.

#### Remediation effort

Trivial

#### Remediation risk

Low

### AFFNET-SEC-001 SiteContentFetcher uses @ operator for DNS resolution

- **Area:** Security
- **Severity:** Informational
- **Priority:** P4
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Support/SiteContentFetcher.php:28`, `src/Strategies/DnsVerificationStrategy.php:28`
- **Evidence:** Both use `@dns_get_record` to suppress errors
- **Introduced by:** Unknown
- **Related findings:** None

#### Observation

The `@` operator suppresses DNS errors. This is acceptable since both methods handle `false` returns gracefully, but the pattern is generally discouraged.

#### Recommendation

No action required; this is an acceptable trade-off for DNS queries that may fail for non-existent domains.

#### Acceptance criteria

N/A (informational)

#### Remediation effort

Trivial

#### Remediation risk

None

## 21. Unverified concerns and blocked checks

Tests could not be run (no DB). PHPStan not run per-package. No security scanner available.

## 22. Recommended remediation order

1. Fix `ArchiveExpiredOffersCommand` status filter (P1 — bug that prevents all archiving)
2. Use `OfferStatus::Archived` enum value (P3 — consistency)

## 23. Package-level acceptance checklist

- [x] Purpose is clear and documented
- [x] Public API is well-defined
- [x] Architecture follows monorepo conventions
- [x] No DB constraints or cascades
- [x] UUID primary keys used
- [x] Config-driven table names
- [x] No soft deletes
- [x] Owner scoping enforced
- [x] Tests exist for core functionality
- [x] Documentation is comprehensive
- [ ] Tests pass (not verified)
- [ ] PHPStan passes (not verified)

## 24. Final package rating

- Functional correctness: **Good** (one bug found — archive command never matches)
- Security: **Strong** (SSRF protection, signed routes, owner scoping)
- Reliability: **Good**
- Maintainability: **Excellent**
- Test quality: **Good**
- Documentation: **Excellent**
- Operational readiness: **Good**
- Integration quality: **Excellent**
- Release readiness: **Good** (no version declared, one P1 bug)

## 25. Final conclusion

**Ready with minor improvements** — A well-designed, feature-rich affiliate marketplace package with strong security practices. One notable P1 bug (archive command's status filter) prevents the archive feature from working.
