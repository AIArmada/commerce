# Audit Report: `affiliates`

**Package:** `aiarmada/affiliates`  
**Surface:** domain  
**Family:** growth-and-incentives  
**Audited:** 2026-06-27 — Commit `7d1dc95fa`  

---

## Purpose

Full-featured affiliate marketing and referral tracking: attribution, commissions, payout workflows, fraud detection, program/tier management, MLM/network support, and affiliate analytics.

## Architecture — PASS

Service-oriented with clean `Actions/` (single-purpose), `Services/` (orchestration), `Models/`, `States/` (Spatie model-states), `Contracts/` (extensibility seams), and `Support/` (integration glue). 28 models, 16 services, 10 events, 4 contracts, 5 artisan commands, 3 attribution strategies, 6 fraud rules, 4 bonus rules, 13 enums, 4 DTOs. Provider registers tagged strategy/rule collections for extensibility and singletons for all major services.

## Implementation Quality — PASS

- All models use `getTable()` with config-driven table names (26 migrations)
- `HasUuids` on all models
- `HasOwner` + `HasOwnerScopeConfig` on tenant-owned models (Affiliate, Conversion, Attribution, Payout, Program)
- `ScopesByAffiliateOwner` on subsidiary models scoped by `affiliate_id` (Balance, Link, FraudSignal, Membership, etc.)
- `ScopesByProgramOwner` on CommissionRule, CommissionPromotion
- Immutable datetime casts on lifecycle columns (`*_at`)
- Money in minor units (`_minor` columns) throughout
- `AffiliateConversion` uses `spatie/laravel-model-states` for conversion lifecycle (Pending → Qualified → Approved → Paid / Rejected)
- `AffiliatePayout` uses model-states for payout lifecycle (Pending → Processing → Completed / Failed / Cancelled)
- `Affiliate` uses model-states for status (Draft → Active / Paused / Disabled)
- `AffiliateBalance` has correct increment/decrement methods with min-guards (`releaseFromHolding`, `deductFromAvailable`)
- Application-level cascades in `booted()` `deleting` events — no DB cascadeOnDelete
- Cross-tenant protection: `AffiliateLink::guardProgramReference` and `AffiliateProgramMembership::guardProgramReferences` throw `AuthorizationException` on cross-tenant references
- Backward-compatible aliases on Conversion model: `subjectIdentifier`/`cartIdentifier`, `externalReference`/`orderReference`, `valueMinor`/`totalMinor` — thoughtful migration path between neutral and cart-order terminology

## Contracts — PASS

4 interfaces provide clean extensibility:
- `AttributionStrategy` — pluggable attribution models
- `FraudRule` — pluggable fraud detection
- `PayoutProcessorInterface` — pluggable payout processors (PayPal, Stripe, Manual)
- `PerformanceBonusRule` — pluggable bonus calculations

## Security — PASS

- Owner scoping on all tenant models via `HasOwner` + `OwnerScope` global scope
- Cross-tenant guard on `AffiliateLink.program_id` and `AffiliateProgramMembership.program_id`/`tier_id`
- Config-gated features (auto_approve, fraud detection, cookies, API auth)
- `cleanString()` utility on Affiliate model for input sanitization
- No SQL injection vectors visible
- `class_exists()` guards for optional packages (vouchers, cart, orders)

## Tests — PASS

**101 test files found** in `tests/src/Affiliates/Unit/` (67 files) plus `tests/src/FilamentAffiliates/Unit/` (13 files) and related integration tests. Coverage spans:
- All 28 model tests (CRUD, scopes, state transitions, cascades)
- Service tests (AffiliateService, PayoutService, ReportService, FraudDetection)
- Action tests (code generation, conversion recording, link generation)
- DTO tests (AffiliateData, ConversionData, AttributionData)
- State machine tests (AffiliateStatus, ConversionStatus)
- Integration tests (owner scoping, voucher listener, discount conditions, API controller)
- Filament-specific tests (policies, actions, plugin registration)

No coverage gaps identified.

## Documentation — PASS

14 doc files covering:
- `01-overview.md` — purpose, architecture diagram, multi-tenancy, integrations
- `02-installation.md` — install steps with Composer and service provider
- `03-configuration.md` — all config keys documented with tables
- `04-usage.md` — common usage patterns
- `05-models.md` — all 28 models documented
- `06-services.md` — all services with method signatures
- `07-programs.md` — program management workflows
- `08-payouts.md` — payout lifecycle and processors
- `09-fraud-detection.md` — fraud rule system
- `10-multi-tenancy.md` — owner scoping deep-dive
- `11-commands.md` — Artisan commands
- `12-events.md` — domain events
- `13-api.md` — REST API endpoints
- `99-troubleshooting.md` — common issues

All files have YAML frontmatter with `title:`. Follows the required doc structure conventions.

## Issues Found

**Minor:**
1. `AffiliateCommissionRule::calculateCommission()` (line 140-151) uses a convoluted raw-attribute-access pattern for `commission_type` instead of relying on the casted enum directly. The fallback chain (`getAttribute` → `instanceof` → `getRawOriginal` → cast to string) suggests the cast may not always be loaded. If the relationship loads the model without casts, this is a real (if rare) issue.
2. `FraudDetectionService::__construct()` adds all rules to both `clickRules` AND `conversionRules` arrays regardless of whether each rule implements both `analyzeClick()` and `analyzeConversion()`. Not all rules support both interfaces, so this could cause `method not found` errors at runtime if a conversion-only rule is evaluated as a click rule (or vice versa). The contract `FraudRule` should probably define separate methods, or the service should type-check before calling.
3. `AffiliateConversion::booted()` `created` event has significant inline balance logic (lines 171-224) that would be better extracted into an Action or Service. The method handles auto-approval, balance creation, holding credit, and available credit in one block.
4. No version constraint in `composer.json` (`"version"` key absent). This is a monorepo convention issue noted elsewhere.

## Integration — PASS

- `class_exists()` guards for optional packages: `cart`, `vouchers`, `orders`
- `CartIntegrationRegistrar` decorates the cart manager
- `VoucherIntegrationRegistrar` hooks into voucher application events
- `RecordCommissionForOrder` listener bridges `CommissionAttributionRequired` event from orders
- Filament admin surfaces in `filament-affiliates` (separate package)

## Final Status

**Ready with minor improvements.** Excellent overall quality — comprehensive domain model, strong multi-tenancy, extensive tests, thorough documentation. The fraud rule dispatch issue (issue #2) could cause runtime errors and should be addressed.

## Summary

| Category | Result |
|----------|--------|
| Purpose | Clear |
| Architecture | Clean, service-oriented, extensible |
| Implementation | Solid — minor issues noted |
| Security | Strong owner scoping + cross-tenant guards |
| Tests | 101 test files, no gaps |
| Docs | 14 files, well-structured |
| Integration | Proper optional-package pattern |
