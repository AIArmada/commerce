# Commerce Monorepo — All Findings, Issues, Bugs & Priorities

> One source of truth for every problem found during the 57-package audit.
> Generated 2026-06-27. Commit `7d1dc95fa`. Contains **69 findings** (40 domain, 29 filament).

---

## Contents

1. [Bugs (will crash or misbehave at runtime)](#1-bugs)
2. [Security Issues](#2-security-issues)
3. [Owner Scoping Gaps](#3-owner-scoping-gaps)
4. [Zero Test Coverage](#4-zero-test-coverage)
5. [Infrastructure & Build Issues](#5-infrastructure--build-issues)
6. [Navigation Violations](#6-navigation-violations)
7. [Missing Exception Hierarchies](#7-missing-exception-hierarchies)
8. [Stale Documentation](#8-stale-documentation)
9. [Missing Composer Dependencies](#9-missing-composer-dependencies)
10. [Config Staleness](#10-config-staleness)
11. [Code Quality Issues](#11-code-quality-issues)
12. [Design & Architecture Concerns](#12-design--architecture-concerns)
13. [Prioritized Remediation Roadmap](#13-prioritized-remediation-roadmap)

---

## 1. Bugs

### B1. Promotions — wrong column name (will SQL error)

| Field | Value |
|-------|-------|
| **Package** | `promotions` |
| **File** | `packages/promotions/src/Listeners/MarkPromotionAsUsedOnOrderPlaced.php:28` |
| **Code** | `$promotion->increment('times_used')` |
| **Problem** | Model has `usage_count` column, not `times_used`. Will throw SQL error on first execution. |
| **Fix** | `$promotion->increment('usage_count')` |
| **Severity** | Medium — fails loudly |

### B2. Affiliate Network — dead archive command

| Field | Value |
|-------|-------|
| **Package** | `affiliate-network` |
| **File** | `packages/affiliate-network/src/Console/Commands/ArchiveExpiredOffersCommand.php:36` |
| **Code** | `->where('status', 'active')` |
| **Problem** | `AffiliateOffer` uses `OfferStatus` enum with values `draft`/`published`/`archived`. No `active` status → archives **zero records**. |
| **Fix** | `->where('status', OfferStatus::Published)` |
| **Also** | Line 43 uses `'archived'` string instead of `OfferStatus::Archived` |
| **Severity** | Medium — silently does nothing |

### B3. Chip — `isTest()` defaults `true`

| Field | Value |
|-------|-------|
| **Package** | `chip` |
| **File** | `packages/chip/src/Events/WebhookReceived.php:310-311` |
| **Code** | `return $this->payload['is_test'] ?? true` |
| **Problem** | If `is_test` key is absent, defaults to `true`. Real events misclassified as test. |
| **Also** | `PaymentRefunded.php:85-87` same pattern |
| **Fix** | Default to `false` |
| **Severity** | Medium — could silently lose production webhooks |

### B4. Chip — `Webhook.php` public `$guarded`

| Field | Value |
|-------|-------|
| **Package** | `chip` |
| **File** | `packages/chip/src/Models/Webhook.php:54` |
| **Code** | `public $guarded = []` |
| **Problem** | Public visibility allows external modification. Combined with `[]`, any attribute mass-assignable. |
| **Fix** | `protected $guarded = []` + explicit `$fillable` |
| **Severity** | Critical |

### B5. Cashier — error swallowing (silent failures)

| Field | Value |
|-------|-------|
| **Package** | `cashier` |
| **Files** | `StripeGateway.php:165,179,193,207,339` + `ChipGateway.php:168,192,211,231,377` |
| **Code** | `catch (Throwable) { return null; }` in all 10 retrieval methods |
| **Problem** | Network errors, auth failures, rate limits — all silently return `null`. Indistinguishable from "not found". |
| **Fix** | Log exception, distinguish 404 from 5xx/network errors |
| **Severity** | High — outages degrade silently |

### B6. Cashier — schema introspection on every query

| Field | Value |
|-------|-------|
| **Package** | `cashier` |
| **File** | `packages/cashier/src/Support/OwnerScopedQuery.php:68-73` |
| **Code** | `Schema::hasColumn()` called on every owner-scoped query. No caching. |
| **Severity** | Medium — ~5-10ms per query |

### B7. FraudDetectionService — wrong rule dispatch (method-not-found crash)

| Field | Value |
|-------|-------|
| **Package** | `affiliates` |
| **File** | `packages/affiliates/src/Services/FraudDetectionService.php` (constructor) |
| **Problem** | Constructor adds **all rules** to **both** `clickRules` and `conversionRules` arrays regardless of which interface each rule implements. If a conversion-only rule (`analyzeConversion`) is evaluated as a click rule, PHP throws `method not found`. |
| **Fix** | Gate rule registration by `instanceof` check: only add to `clickRules` if rule implements `AnalyzesClickFraud`, only to `conversionRules` if it implements `AnalyzesConversionFraud`. |
| **Severity** | High — runtime crash on mismatched rule evaluation |

### B8. Authz — `clearCache()` has empty body

| Field | Value |
|-------|-------|
| **Package** | `authz` |
| **File** | `packages/authz/src/Authz.php:88-89` |
| **Problem** | Public API method `clearCache()` is empty. `flushPermissionCache()` handles actual cache clearing, but callers using `clearCache()` get a no-op. |
| **Fix** | Implement body or delegate to `flushPermissionCache()` |
| **Severity** | Medium — public API silently does nothing |

### B9. Authz — `getEmailColumn()` always returns `'email'`

| Field | Value |
|-------|-------|
| **Package** | `authz` |
| **File** | `packages/authz/src/Console/Commands/SuperAdminCommand.php` |
| **Problem** | `getEmailColumn()` hardcodes `'email'` regardless of the guard's user model column name. |
| **Fix** | Read from user model or guard configuration |
| **Severity** | Medium — wrong behavior on non-standard user models |

### B10. Authz — `ImpersonateManager` uses wrong guard

| Field | Value |
|-------|-------|
| **Package** | `authz` |
| **File** | `packages/authz/src/ImpersonateManager.php:358` |
| **Code** | `auth()->guard()` (default guard) instead of the specific guard being updated |
| **Problem** | Password hash update in session targets default guard, not the impersonated guard |
| **Fix** | Use the specific guard context |
| **Severity** | Medium |

### B11. Checkout — `transitionStatus()` bypasses HasStates

| Field | Value |
|-------|-------|
| **Package** | `checkout` |
| **File** | `packages/checkout/src/...` (transitionStatus method) |
| **Code** | `DB::table(...)->where(...)->update(['status' => ...])` instead of `$this->status->transitionTo(...)` |
| **Problem** | Spatie ModelStates transition events are **NOT dispatched** for programmatic transitions. Listeners expecting `CheckoutStatusChanged` events miss the update. |
| **Fix** | Use `$this->status->transitionTo(...)` |
| **Severity** | Medium — event-driven integrations break silently |

### B12. DocPayment — raw string status (no enum, no validation)

| Field | Value |
|-------|-------|
| **Package** | `docs` |
| **File** | `packages/docs/src/Models/DocPayment.php` |
| **Code** | `'status' => 'string'` in `$casts` |
| **Problem** | Status is an unvalidated string — any value accepted. No documented valid values or transition logic. |
| **Fix** | Add a `DocPaymentStatus` enum with safe transitions |
| **Severity** | Medium — risk of invalid states in payment records |

### B13. DocShareLink — public `$plainToken` leaks on serialization

| Field | Value |
|-------|-------|
| **Package** | `docs` |
| **File** | `packages/docs/src/Models/DocShareLink.php` |
| **Problem** | `$plainToken` is a public property. If a collection of share links is serialized to JSON or logged, the plaintext token leaks. |
| **Fix** | Make protected, add `$hidden` array, expose only via accessor |
| **Severity** | Medium — token leaks in logs or API responses |

### B14. Affiliates — convoluted attribute fallback for commission_type

| Field | Value |
|-------|-------|
| **Package** | `affiliates` |
| **File** | `packages/affiliates/src/Services/Commission/AffiliateCommissionRule.php:140-151` |
| **Problem** | `calculateCommission()` uses `getAttribute()` → `instanceof` → `getRawOriginal()` → cast to string fallback chain instead of relying on the casted enum directly. |
| **Fix** | Use `$this->commission_type` directly |
| **Severity** | Low — works but fragile |

---

## 2. Security Issues

### S1. `$guarded = []` on payment models (Critical)

Mass-assignment protection fully disabled on 8 models across 3 payment packages:

| Package | File | Line |
|---------|------|------|
| `chip` | `ChipModel.php` | 42 |
| `chip` | `ChipIntegerModel.php` | 39 |
| `chip` | `Webhook.php` | 54 |
| `cashier-chip` | `Subscription.php` | 122 |
| `cashier-chip` | `SubscriptionItem.php` | 62 |
| `cashier-chip` | `StoredPaymentMethod.php` | 39 |
| `cashier` | `UnifiedSubscriptionRecord.php` | 20 |
| `cashier` | `UnifiedInvoiceRecord.php` | 20 |

**Impact:** Any `Model::create($request->all())` or `$model->fill($input)` can set arbitrary attributes — status, amounts, owner fields.

**Priority: Day 1**

### S2. Cross-tenant data leak — filament-affiliates (Critical)

6 of 14 resources never call `->forOwner()`:

| Resource | File |
|----------|------|
| `AffiliateConversionResource` | `Resources/AffiliateConversionResource.php:26-28` |
| `AffiliateLinkResource` | `Resources/AffiliateLinkResource.php:73-76` |
| `AffiliateRankHistoryResource` | `Resources/AffiliateRankHistoryResource.php:68-71` |
| `AffiliateCreativeResource` | `Resources/AffiliateCreativeResource.php:70-73` |
| `AffiliateSupportTicketResource` | `Resources/AffiliateSupportTicketResource.php:76-79` |
| `AffiliateTaxDocumentResource` | `Resources/AffiliateTaxDocumentResource.php:70-73` |

### S3. Cross-tenant data leak — filament-docs (High)

| Surface | Issue |
|---------|-------|
| `DocTemplateResource` | No `getEloquentQuery()` override |
| `DocSequenceResource` | No `getEloquentQuery()` override |
| `DocEmailTemplateResource` | No `getEloquentQuery()` override |
| `DocStatsWidget` | bare `Doc::query()` |
| `RecentDocumentsWidget` | bare `Doc::query()` |
| `StatusBreakdownWidget` | bare `Doc::query()` |
| `RevenueChartWidget` | bare `Doc::query()` |
| `AgingReportPage` | bare `Doc::query()` |
| `PendingApprovalsPage` | bare `Doc::query()` |

### S4. Filament-signals — SavedSignalReportResource unscoped

`packages/filament-signals/src/Resources/SavedSignalReportResource.php:28-30` — returns `parent::getEloquentQuery()->with(...)` with no `->forOwner()`.

### S5. Promotions listeners — unscoped queries

`MarkPromotionAsUsedOnOrderPlaced.php` and `ReevaluatePromotionsOnCartUpdated.php` query without `->forOwner()`.

### S6. Cashier — no rate limiting on payment operations

No rate limiting on `charge()`, `refund()`, `createSubscription()` in `StripeGateway.php`.

### S7. JNT — hardcoded test credentials in config

| Field | Value |
|-------|-------|
| **Package** | `jnt` |
| **File** | `packages/jnt/config/jnt.php` |
| **Problem** | J&T public sandbox credentials shipped as defaults when `JNT_ENVIRONMENT=testing`. These are documented as test credentials but shipping them in config is unusual — env-only would be safer. |
| **Severity** | Low — test credentials only |

### S8. JNT — `JntWebhookLog` lacks `$fillable`/`$guarded`

| Field | Value |
|-------|-------|
| **Package** | `jnt` |
| **Problem** | Extends Spatie's `WebhookCall` which defines neither `$fillable` nor `$guarded`. Risk of mass-assignment on webhook payload data stored in shared `webhook_calls` table. |
| **Severity** | Low |

### S9. DocShareLink — public `$plainToken` (same as B13)

See B13. Plaintext token exposed on serialization.

### S10. Affiliate Network — `@` operator suppressing DNS errors

| Field | Value |
|-------|-------|
| **Package** | `affiliate-network` |
| **File** | `SiteContentFetcher.php` (dns resolution) |
| **Code** | `@dns_get_record(...)` |
| **Problem** | `@` suppresses DNS errors. Acceptable since both methods handle `false` returns gracefully, but pattern discouraged. |
| **Severity** | Informational |

---

## 3. Owner Scoping Gaps

All critical gaps captured in [S2](#s2-cross-tenant-data-leak--filament-affiliates-critical) through [S5](#s5-promotions-listeners--unscoped-queries) above.

### Additional scoping concerns

| Package | Detail | Severity |
|---------|--------|----------|
| `filament-growth` | 11 query sites rely on automatic global `OwnerScope` | Low |
| `filament-authz` | `PermissionResource` uses `->withoutGlobalScopes()` — would bypass OwnerScope | Low |
| `filament-pricing` | `TiersRelationManager` uses `method_exists($model, 'scopeForOwner')` | Low |
| `events` | 47/54 models lack direct `HasOwner` — rely on service provider scope registration | Informational |
| `commerce-support` | `OwnerContext` static `$fallback` mutable — Octane workers could see stale values | Low |

---

## 4. Zero Test Coverage

11 packages have **zero test files**:

| Severity | Package | Uncovered surface |
|----------|---------|-------------------|
| **Critical** | `cashier` | Entire package — `tests/` missing despite declared in composer.json |
| **Critical** | `cashier-chip` | `Pest.php` exists, zero tests, `tests/Actions/` dir missing |
| **Critical** | `docs` | 14 migrations, 13 models, 11 enums, state machine |
| **High** | `filament-products` | 6 resources, 19 pages, 4 widgets |
| **High** | `filament-inventory` | 6 resources, 9 widgets, 7 actions (largest filament package) |
| **High** | `filament-signals` | 7 resources, 11 report pages, 3 widgets |
| **High** | `filament-growth` | 2 resources, 6 pages, 3 standalone pages, 2 widgets |
| **High** | `filament-vouchers` | 3 resources, 8 pages, 8 widgets |
| **High** | `filament-promotions` | 1 resource, 4 pages, 2 widgets |
| **High** | `filament-pricing` | 2 resources, 2 standalone pages |
| **High** | `filament-cashier-chip` | 3 resources, 6 admin pages, 4 portal pages, 7 widgets |

**Total uncovered:** ~39 resources, ~71 pages, ~44 widgets.

**Thin:** `filament-contact` (1 test), `filament-communications` (10 tests, all blocked by autoloading bug below).

---

## 5. Infrastructure & Build Issues

### I1. Monorepo autoloading bug — blocks ALL test suites (Critical)

| Field | Value |
|-------|-------|
| **File** | `tests/src/CommerceSupport/OwnerResolvers/FixedOwnerResolver.php` |
| **Problem** | `FixedOwnerResolver` lives at `tests/src/CommerceSupport/OwnerResolvers/` but its PSR-4 namespace `AIArmada\Commerce\Tests\Support\OwnerResolvers` maps to `tests/src/`. The class should be at `tests/src/Support/OwnerResolvers/`. **Every test suite that depends on `FixedOwnerResolver` fails with `class not found`.** |
| **Impact** | Blocks all package test suites from running in the monorepo. |
| **Fix** | Move the file to match its namespace: `tests/src/Support/OwnerResolvers/FixedOwnerResolver.php` |
| **Severity** | **Critical** — blocks CI for every package |

### I2. No package-local test runner config

| Package | Problem |
|---------|---------|
| `checkout` | No `phpunit.xml`/`pest.xml` in package — cannot test independently |
| `chip` | No package-local config — 98 tests require PostgreSQL `commerce_test` database |
| `docs` | No `phpstan.neon` or `pint.json` — relies on project-level tooling |

### I3. Dead autoload declarations

| Package | Problem |
|---------|---------|
| `feedback` | `composer.json` declares `database/factories/` autoload mapping — directory doesn't exist |
| `shipping` | Factory namespace declared in autoload — directory absent |
| `shipping` | `registerEventListeners()` wired but empty |
| `shipping` | `registerCommands()` wired but empty |

### I4. Cashier — prior audit claimed ~20 tests, zero exist

| Field | Value |
|-------|-------|
| **Package** | `cashier` |
| **Problem** | The previous audit stub claimed "~20 test files" and "Tests — PASS". Neither claim was accurate — tests do not exist. |

---

## 6. Navigation Violations

### Static `$navigationGroup`

| Package | File | Line | Code |
|---------|------|------|------|
| `filament-orders` | `OrderFulfillmentPage.php` | 20 | `protected static $navigationGroup = 'Sales'` |
| `filament-orders` | `OrderTimelinePage.php` | 19 | `protected static $navigationGroup = 'Sales'` |

Blocks `CommerceNavigation` runtime override.

### Static `$navigationSort`

| Package | Resource/Page | Line |
|---------|--------------|------|
| `filament-engagement` | 7 resources (Follow, Bookmark, BookmarkCollection, Response, Reaction, Subscription, Reminder) | 27-29 |
| `filament-cart` | CartDashboard, CartItemResource, ConditionResource, LiveDashboardPage | 21-36 |
| `filament-shipping` | ShippingDashboard (hardcoded `'Shipping'` group string) | 32 |
| `filament-shipping` | ManifestPage (hardcoded `'Shipping'` group string) | 53 |
| `filament-cashier` | Both resources (dead `$navigationSort` alongside `getNavigationSort()`) | — |

---

## 7. Missing Exception Hierarchies

18 packages have **zero or inadequate** custom exceptions:

| Severity | Package | Existing exceptions | Issue |
|----------|---------|-------------------|-------|
| High | `docs` | 2 standard exceptions | No base for financial document engine |
| Medium | `feedback` | 0 | 38 actions, 16 events |
| Medium | `engagement` | 0 | 28 events, 15 contracts, 7 services |
| Medium | `signals` | 0 | 83 src files, 26 services |
| Medium | `growth` | 0 | 6 actions, 4 enums |
| Medium | `orders` | 0 | 10 actions, 14 events, 12-state state machine |
| Medium | `promotions` | 0 | 5 actions, 4 events |
| Medium | `moderation` | 0 | 2 actions |
| Medium | `communications` | 0 | 31 actions, 20 events, 16 models |
| Medium | `cashier-chip` | 7 | All extend `\Exception` — no `CashierChipException` base |
| Low | `products` | 0 | 5 actions, 5 events |
| Low | `contacting` | 0 | 10 actions |
| Low | `customers` | 0 | 5 actions, 4 events |
| Low | `inventory` | 2 | Both extend `\Exception` — no `InventoryException` base |
| Low | `membership` | 0 | 10 actions |
| Low | `pricing` | 0 | 4 actions, 2 events |
| Low | `tax` | 1 | Missing `TaxCalculationException` |
| Low | `references` | 0 | Entire package |

**Good:** events (14), vouchers (9), checkout (7), shipping (4), jnt (4), commerce-support (4).

---

## 8. Stale Documentation

### Flat `navigation_group` in docs (8 packages)

Config uses nested `navigation.group` but docs show flat `navigation_group`:

| Package | Files affected |
|---------|---------------|
| `filament-growth` | `docs/03-configuration.md` |
| `filament-jnt` | `docs/03-configuration.md`, `02-installation.md`, `README.md` |
| `filament-promotions` | `docs/03-configuration.md`, `README.md` |
| `filament-vouchers` | `README.md` |
| `filament-cart` | `docs/03-configuration.md` |
| `filament-chip` | `README.md` |
| `filament-pricing` | 4 doc examples show deprecated `static $navigationGroup` |
| `filament-affiliates` | `docs/03-configuration.md` |
| `filament-tax` | 2 doc examples show deprecated `static $navigationGroup` |

### Phantom config keys in docs

| Package | Phantom keys |
|---------|-------------|
| `filament-orders` | `features.enable_invoice_download`, `tables.poll_interval`, `tables.date_format` |
| `filament-shipping` | 5 keys documented but absent from config |
| `filament-events` | Missing 2 resources from docs; non-existent env var documented |

### Other stale docs

| Package | Issue |
|---------|-------|
| `contacting` | Stale 2104-line implementation spec at package root (`specification.md`) |
| `addressing` | README version mismatch in install example |

---

## 9. Missing Composer Dependencies

| Package | Missing (used but not declared) |
|---------|---------------------------------|
| `tax` | `spatie/laravel-data`, `spatie/laravel-settings`, `spatie/laravel-model-states`, `owen-it/laravel-auditing`, `akaunting/money` |
| `cashier` | `laravel/cashier` (hard `use`, declared as `suggest` only) |

---

## 10. Config Staleness

| Package | Issue |
|---------|-------|
| `filament-customers` | 3 keys consumed in Plugin, absent from config: `features.merge_customers`, `features.segment_rebuild`, `features.address_validation` |
| `filament-cashier` | `billing_portal.login_enabled` read in code, not defined in config |
| `filament-chip` | `tables.amount_precision` defined in config, never read |

---

## 11. Code Quality Issues

| Severity | Package | Issue | Detail |
|----------|---------|-------|--------|
| **High** | `cashier` | Duplicate `Billable` trait | Identical at `src/Billable.php` and `src/Concerns/Billable.php` |
| **High** | `cashier` | `CurrencyFormatter` unnecessary | Thin wrapper over commerce-support's `MoneyFormatter`, no added value |
| **High** | `affiliates` | Inline balance logic in model | 50+ lines of balance logic in `AffiliateConversion::booted()` `created` event — extract to Action |
| **Medium** | `products` | `$guarded = ['id']` | 10 models — deviates from `$fillable` convention |
| **Medium** | `customers` | `$guarded = ['id']` | 5 models |
| **Medium** | `growth` | Owner columns in `$fillable` | `owner_type`, `owner_id` in `$fillable` on 3 models — violates convention |
| **Medium** | `cashier-chip` | Missing facade file | `composer.json` aliases `CashierChip` → `Facades\CashierChip` but `src/Facades/` doesn't exist |
| **Medium** | `chip` | Mutable datetime casts | Purchase model uses `datetime` instead of `immutable_datetime` |
| **Low** | `filament-events` | Orphaned resource | `EventRegistrationParticipantResource` implemented but never registered |
| **Low** | `filament-addressing` | Orphaned schema | `AddressCountryFormSchema` never used |
| **Low** | `filament-commerce-support` | Dead code | No-op loop in `buildSidebarForForm()` |
| **Low** | `products` | Duplicated policy helpers | `belongsToOwner()`/`isGlobalModel()` duplicated across 6 policy files |
| **Low** | `filament-cart` | Stale `.bak` file | In tests directory |
| **Low** | `cashier` | Commented-out route stubs | `routes/web.php` |
| **Low** | `checkout` | Unused `CheckoutStatus` enum | Dead code alongside Spatie ModelStates |
| **Low** | `vouchers` | `VoucherAssignment` not auditable | Assignment revocations untracked |
| **Low** | `vouchers` | Duplicated constant | `VOUCHER_METADATA_KEY` string duplicated in two classes |
| **Low** | `inventory` | Some models not `final` | 7 models use `class` instead of `final class` |
| **Low** | `inventory` | Deprecated exception retained | `InsufficientStockException` marked `@deprecated` but still shipped |
| **Low** | `engagement` | No enum usage | Class constants instead of PHP enums |
| **Low** | `signals` | No enum usage | Class constants instead of PHP enums |
| **Low** | `cashier-chip` | No enum usage | Status values as class constants |
| **Low** | `references` | `getPart()` accepts `string` not enum | Inconsistent with sibling methods |
| **Low** | `checkout` | 11 JSON columns on `checkout_sessions` | Queries on JSON paths can't use standard B-tree indexes |
| **Low** | `checkout` | 8 hard deps on sibling packages | Highest fan-out in monorepo |
| **Low** | `membership` | Contracts lack defaults | `app()->bound()` checks with no null/no-op defaults |
| **Low** | `moderation` | No contracts/interfaces | Actions registered as concrete singletons |
| **Low** | `signals` | No domain events | All 21 listeners consume external events |
| **Low** | `jnt` | Legacy command stubs | 4 flat-namespace stubs shipped as dead code |
| **Low** | `jnt` | No `booted()` cascades | No delete cascades on related records |

---

## 12. Design & Architecture Concerns

| Severity | Package | Concern |
|----------|---------|---------|
| Medium | `addressing` | `area_sources` config key exists but only consumed by command — no service provider auto-registration |
| Medium | `checkout` | 8 hard dependencies — most likely package to experience cascading breakage |
| Low | `commerce-support` | `NoCurrentOwnerException` extends `RuntimeException` instead of `CommerceException` — inconsistent hierarchy |
| Low | `commerce-support` | `OwnerContext` static `$fallback` mutable — Octane workers could see stale values |
| Low | `events` | 47/54 models lack direct `HasOwner` — rely on service provider scope registration |
| Low | `engagement` | No `booted()` cascades on `BookmarkCollection` — orphaned `BookmarkCollectionItem` rows possible |
| Low | `filament-shipping` | Owner scoping code repetitive — same 10-line pattern repeated 9 times |
| Low | `filament-pricing` | `TiersRelationManager` uses `method_exists` instead of `OwnerQuery::applyToEloquentBuilder()` |

### Consistency Issues

| Issue | Packages |
|-------|----------|
| 3 different `$guarded`/`$fillable` approaches | chip, cashier, cashier-chip, products, customers, all others |
| 4 different owner scoping patterns in Filament | All filament packages |
| Action pattern mismatch | cashier (`AsAction`) vs cashier-chip (plain classes) |
| PHP enums vs class constants | engagement, signals, cashier-chip |
| `down()` in migrations | Convention forbids; chip (9/9) and customers (7/8) have them |
| No CHANGELOG.md | 0 of 57 packages |
| No package-local test config | checkout, chip, docs |

---

## 13. Prioritized Remediation Roadmap

### Phase 1 — This Week (Critical)

| # | Task | Effort | Packages |
|---|------|--------|----------|
| 1 | Replace `$guarded = []` with `$fillable` on all payment models | 1h | chip, cashier-chip, cashier |
| 2 | Add `->forOwner()` to 6 unscoped filament-affiliates resources | 30m | filament-affiliates |
| 3 | Fix promotions `times_used` → `usage_count` | 5m | promotions |
| 4 | Fix affiliate-network `ArchiveExpiredOffersCommand` | 5m | affiliate-network |
| 5 | Fix chip `WebhookReceived::isTest()` default to `false` | 5m | chip |
| 6 | Add explicit owner scoping to filament-docs resources/widgets/pages | 1h | filament-docs |
| 7 | Fix `FixedOwnerResolver` path to match PSR-4 namespace | 5m | ALL (blocks CI) |
| 8 | Fix FraudDetectionService rule dispatch | 1h | affiliates |

### Phase 2 — Next Week (High)

| # | Task | Effort | Packages |
|---|------|--------|----------|
| 9 | Add test suite to cashier (≥50 tests) | 2-3d | cashier |
| 10 | Add test suite to docs (≥30 tests) | 2d | docs |
| 11 | Fix cashier error swallowing with logging + typed exceptions | 4h | cashier |
| 12 | Fix `clearCache()` empty body in authz | 15m | authz |
| 13 | Fix authz `getEmailColumn()` to read from guard config | 15m | authz |
| 14 | Fix authz `ImpersonateManager` wrong guard | 15m | authz |
| 15 | Fix checkout `transitionStatus()` to use HasStates | 1h | checkout |
| 16 | Add `DocPaymentStatus` enum | 30m | docs |
| 17 | Fix `DocShareLink` public `$plainToken` | 15m | docs |
| 18 | Add `->forOwner()` to promotions listeners | 30m | promotions |
| 19 | Add owner scoping to cashier-chip dashboard widgets | 1h | filament-cashier-chip |
| 20 | Cache `Schema::hasColumn()` in OwnerScopedQuery | 1h | cashier |
| 21 | Create missing cashier-chip facade file | 30m | cashier-chip |
| 22 | Add test suites to filament-inventory, filament-products | 2-3d | filament |

### Phase 3 — Week 3-4 (Medium)

| # | Task | Effort | Packages |
|---|------|--------|----------|
| 23 | Add base exception hierarchies to 18 domain packages | 4-5d | See section 7 |
| 24 | Fix doc/config mismatches (flat `navigation_group` → nested) | 2d | 8 filament packages |
| 25 | Add CHANGELOG.md to all packages | 2d | All 57 |
| 26 | Fix navigation violations (static → config-driven) | 1d | 7 filament packages |
| 27 | Remove duplicate `Billable` trait + `CurrencyFormatter` | 30m | cashier |
| 28 | Extract balance logic from `AffiliateConversion::booted()` | 1h | affiliates |
| 29 | Remove duplicate policy helpers in products | 30m | products |
| 30 | Fix mutable datetime casts in chip | 5m | chip |
| 31 | Remove stale assets (orphaned, dead code, `.bak`) | 1h | multiple |
| 32 | Remove stale implementation spec from contacting | 5m | contacting |

### Phase 4 — Ongoing (Low)

| # | Task | Effort | Packages |
|---|------|--------|----------|
| 33 | Add missing composer deps to tax | 15m | tax |
| 34 | Standardize `$fillable` on products/customers | 1h | products, customers |
| 35 | Standardize PHP enums (engagement, signals, cashier-chip) | 1d | 3 packages |
| 36 | `down()` cleanup in chip and customers migrations | 1h | chip, customers |
| 37 | Fix `getPart()` type hint in references | 5m | references |
| 38 | Remove deprecated exception `InsufficientStockException` | 5m | inventory |
| 39 | Mark models `final` in inventory | 15m | inventory |
| 40 | Remove legacy command stubs in jnt | 5m | jnt |
| 41 | Remove unused `CheckoutStatus` enum | 5m | checkout |
| 42 | Standardize owner scoping pattern in filament layer | 1d | all filament |
| 43 | Add tests to remaining untested filament packages | 5d | All untested |

---

*For the full analysis, see `10-CROSS-CUTTING-FINDINGS.md`, `11-RISK-REGISTER.md`, and the 57 individual reports in `packages/`.*
