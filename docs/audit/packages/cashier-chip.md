# Package Audit — `cashier-chip`

## 1. Audit metadata

- **Path:** `packages/cashier-chip`
- **Version:** self.version (monorepo)
- **Package type:** Library (Laravel package, CHIP billing driver)
- **Language/framework:** PHP 8.4 / Laravel
- **Audit date:** 2026-06-27
- **Commit:** 7d1dc95fa
- **Auditor:** Automated (AI)
- **Overall status:** Conditionally ready
- **Overall confidence:** High

## 2. Executive assessment

`aiarmada/cashier-chip` is a well-architected, comprehensive CHIP payment gateway integration providing recurring billing, payment method management, checkout, and webhook handling. The package has substantial strengths: properly designed models with `HasOwner`/`HasOwnerScopeConfig`, 3 idempotent config-driven migrations, 13 documentation files, testing fakes, Octane safety, clean static analysis, and thorough lifecycle management on the Subscription model (1392 lines). PHPStan and Pint both pass.

The package is significantly healthier than its sibling `cashier`. However, there are gaps: test infrastructure exists (Pest.php, TestCase.php) but no actual test files, a declared facade is missing, and there's no changelog.

## 3. Package purpose and responsibility

Laravel Cashier-style billing integration for the CHIP payment gateway. Provides CHIP-specific billable model trait, subscription management (local since CHIP has no native subscriptions), payment/charge handling, checkout sessions, payment method/recurring token management, webhook processing, and invoice rendering.

Owns its own database tables (`cashier_chip_subscriptions`, `cashier_chip_subscription_items`, `cashier_chip_payment_methods`) with config-driven prefixes.

## 4. Consumers and dependencies

### Internal dependencies

| Package | Type | Notes |
|---------|------|-------|
| `aiarmada/commerce-support` | Hard (`self.version`) | Owner scoping, OwnerBatchRunner, OwnerQuery |
| `aiarmada/chip` | Hard (`self.version`) | ChipCollectService, PurchaseData, ClientData, CHIP events |

### External dependencies

| Package | Version | Type | Notes |
|---------|---------|------|-------|
| `php` | `^8.4` | Hard | |
| `spatie/laravel-webhook-client` | `^3.6.2` | Hard | Webhook processing infrastructure |

### Suggested/optional integrations (class_exists-gated)

| Package | Integration |
|---------|-------------|
| `aiarmada/vouchers` | Coupon/discount support |
| `aiarmada/docs` | Invoice PDF rendering |
| `laravel/octane` | Request lifecycle management |

### Known consumers

- `aiarmada/cashier` — Unified billing abstraction consumes CHIP gateway as one of its providers
- `aiarmada/filament-cashier-chip` — Filament admin UI

## 5. Public API and contracts

### Contracts (3 interfaces)

- **`BillableContract`** (12 methods) — CHIP-specific billable model contract
- **`InvoiceRenderer`** (2 methods) — Invoice rendering contract
- **`PaymentMethodStoreInterface`** (8 methods) — Recurring token storage contract

### Billable trait

Composed trait in `Billing/Billable.php` incorporating 7 concern traits: `HandlesPaymentFailures`, `InteractsWithChip`, `ManagesCustomer`, `ManagesInvoices`, `ManagesPaymentMethods`, `ManagesSubscriptions`, `PerformsCharges`. Provides the full public API for billable models.

### Actions (5, plain final classes)

`CancelChipSubscription`, `ChargeChipCustomer`, `CreateChipSubscription`, `RefundChipPayment`, `SyncChipPurchaseStatus` — service objects with explicit methods. Not using `AsAction` (different pattern from `cashier` package).

### Events (9)

All use `Dispatchable` + `SerializesModels`. `PaymentSucceeded`, `PaymentFailed`, `PaymentRefunded`, `SubscriptionCreated`, `SubscriptionCanceled`, `SubscriptionRenewed`, `SubscriptionRenewalFailed`, `SubscriptionResumed`, `SubscriptionUpdated`.

### Console commands (2)

`cashier-chip:renew-subscriptions` — Batch renewal via `OwnerBatchRunner`; `cashier-chip:webhook` — Displays expected webhook URL and instructions.

## 6. Architecture and design

### Strengths

- **Properly designed models:** `Subscription` (1392 lines) with comprehensive lifecycle management, relationship methods, scopes, immutable date casts, factory support, owner scoping, and coupon integration
- **Composed trait pattern:** 10 focused concern traits + 1 main `Billable` trait — good separation of concerns
- **Config-driven database:** Table names, prefixes, JSON column types all configurable
- **Idempotent migrations:** All 3 migrations check `Schema::hasTable()` before creating
- **No constraints in migrations:** Compliant with monorepo guidelines — no `constrained()` or `cascadeOnDelete()`
- **Octane-safe:** Snapshot/restore of static defaults on `RequestReceived` event
- **Owner scoping:** All 3 models use `HasOwner`/`HasOwnerScopeConfig` with config-gated enforcement, cross-tenant write validation in `creating` event
- **Fake/mock support:** `FakeChipClient` and `FakeChipCollectService` for testing
- **BC aliases:** `spl_autoload_register` provides backward compatibility for renamed classes
- **Internal design docs:** `friendly.md` and `lifecycle.md` document refactoring history

### Issues

- **Missing facade file:** `composer.json` registers `Cashier` facade alias to `AIArmada\CashierChip\Facades\CashierChip`, but the facade class doesn't exist. `FakeChipClient` and `FakeChipCollectService` reference a non-existent `Facades\CashierChip` class. BC aliases via `spl_autoload_register` compensate.
- **No custom exception hierarchy:** All 7 exceptions extend `Exception` directly. No base `CashierChipException` for consumers to catch package-wide.
- **`$guarded = []` on Subscription:** Unlike `cashier`'s read-only models, this is a write model. Mass assignment protection is disabled.
- **Enums as class constants:** Subscription status values are class constants, not proper PHP enums. No color/icon/label helpers.
- **Actions not using AsAction:** Inconsistent with `cashier` package which uses `lorisleiva/laravel-actions`. Pattern difference is confusing for developers working across both packages.

## 7. Functional correctness

### Normal path review

- **Subscription lifecycle:** `create()` → `creating` events (owner scoping, trial timestamps) → items creation → event dispatch. Well-structured with proper guards.
- **Cancel/resume/pause:** Clear state machine with proper `ends_at`, `canceled_at`, `paused_at` management. Grace period logic correct.
- **Swap prices:** Transaction-wrapped delete + recreate items with proper owner propagation.
- **Owner scoping in `creating` event:** Fast-path for owner-is-billable, fallback to `forOwner` lookup, cross-tenant protection with `AuthorizationException`.
- **`ChargeChipCustomer` action:** Rate limiting via config, wraps CHIP API call.
- **Webhook listener integration:** 5 listeners gated by `class_exists()` on CHIP events.

### Concerns

- **`$guarded = []` on Subscription:** Real write model with user-attachable data — `fill()` is used extensively. Malicious input could set arbitrary attributes. Since this model uses proper controller/action entry points, the risk is limited but the safety net is absent.
- **`next_billing_at` null handling:** `currentPeriodStart()` and `currentPeriodEnd()` return `null` if `next_billing_at` is null. Callers must handle null.
- **`latestPayment()` returns `null`:** Explicitly a placeholder — no payment tracking separate from subscriptions. Callers must handle.
- **`upcomingInvoice()` / `latestInvoice()` / `invoices()` return `null` or empty:** Explicit placeholders. Callers must handle.
- **`cancel()` falls back to `Carbon::now()`:** If `next_billing_at` is null during cancel, `ends_at` is set to now — immediate cancellation. This is a reasonable fallback but may surprise callers expecting end-of-period cancellation.

## 8. Security

### Positive

- **Owner scoping:** All 3 models use `HasOwner`/`HasOwnerScopeConfig` with config-gated enforcement. Cross-tenant write validation in `Subscription::creating` event with multiple code paths (fast-path, scoped lookup, authorization exception).
- **Webhook signature verification:** Default enabled via config (`webhooks.verify_signature = true`). Uses Spatie webhook client for signature verification.
- **Config-based secrets:** API keys and webhook secrets in config via env variables.
- **`class_exists()` guards:** Conditional integration with vouchers, docs, octane prevents runtime errors when optional packages are missing.
- **No `constrained()` in migrations:** Follows monorepo convention — no DB-level foreign key constraints.

### Gaps

- **`$guarded = []` on all 3 models:** No mass-assignment protection. While underlying entry points gate writes, there's no defense in depth.
- **No rate limiting on charge/subscription actions:** `ChargeChipCustomer` has config-based rate limiting, but subscription creation and cancellation do not.
- **No CSRF protection documented in package own docs** (relies on Spatie webhook client's handling).

## 9. Data integrity and persistence

### Schema

| Migration | Table | Key columns |
|-----------|-------|-------------|
| `2000_03_01_000001` | `cashier_chip_subscriptions` | UUID PK, nullableMorphs owner, uuidMorphs billable, type, chip_id (unique), chip_status, chip_price, quantity, recurring_token, billing_interval, billing_interval_count, 9 timestamp columns + coupon columns + timestamps |
| `2000_03_01_000002` | `cashier_chip_subscription_items` | UUID PK, nullableMorphs owner, subscription_id, chip_id (unique), chip_product, chip_price, quantity, unit_amount + timestamps |
| `2000_03_01_000003` | `cashier_chip_payment_methods` | UUID PK, nullableMorphs owner, uuidMorphs billable, recurring_token, type, brand, last_four(4), is_default, metadata (jsonb configurable) + timestamps |

### Assessment

- **Config-driven table names:** All tables use `getTable()` reading from config with prefix fallback. JSON column type configurable.
- **Idempotent migrations:** All check `Schema::hasTable()` before creating.
- **No DB constraints:** No `constrained()`, no `cascadeOnDelete()` — app-level cascades in `Subscription::deleting` event delete items.
- **Immutable date casts:** All lifecycle timestamps use `immutable_datetime` casts.
- **`$guarded = []`:** All models have no mass-assignment protection — weakest aspect.
- **No `down()` methods:** Per monorepo convention.

## 10. Error handling and resilience

### Exception layer

- **7 exception classes** — all extend `Exception` directly, no base exception
- **Static factory methods** on most exceptions (`InvalidCoupon::notFound()`, `SubscriptionUpdateFailure::incompleteSubscription()`, etc.)
- **Missing:** No `CashierChipException` base — consumers must catch individual exception classes or use generic `\Exception`

### Error handling patterns

- **Webhook listeners** wrap logic in class methods — errors bubble up to the event dispatcher
- **Actions** use explicit exception throwing rather than try/catch swallowing
- **`Subscription::cancel()`** falls back gracefully when `next_billing_at` is null
- **Placeholder methods** (`latestPayment()`, `upcomingInvoice()`, etc.) return `null` or empty collect — no exceptions thrown

## 11. Performance and scalability

### Positive

- **`RenewSubscriptionsCommand`** uses `OwnerBatchRunner` for per-owner iteration — good for multi-tenant
- **`Subscription::items()` eager-loaded** by default — avoids N+1 on subscription queries
- **Immutable date casts** — CarbonImmutable reduces accidental mutation bugs
- **Owner scoping at query level** — proper database-level filtering

### Concerns

- **Owner scoping in `creating` event** performs up to 2 additional queries (owner lookup + `forOwner` exists check) on every subscription create. Acceptable for normal flow but worth noting.
- **`swap()` transaction** deletes and recreates all items — could be expensive for subscriptions with many items.
- **`charge()`** on subscription loads the customer's default payment method on every call rather than caching.

## 12. Configuration

| Key | Required | Default | Notes |
|-----|----------|---------|-------|
| `cashier-chip.database.table_prefix` | No | `cashier_chip_` | Table prefix |
| `cashier-chip.database.json_column_type` | No | `jsonb` | JSON column type |
| `cashier-chip.currency` | No | `MYR` | Default currency |
| `cashier-chip.currency_locale` | No | `ms_MY` | Locale for formatting |
| `cashier-chip.features.owner.enabled` | No | `false` | Multi-tenant owner scoping |
| `cashier-chip.features.owner.include_global` | No | `false` | Include global records |
| `cashier-chip.features.owner.auto_assign_on_create` | No | `true` | Auto-assign owner on creation |
| `cashier-chip.features.owner.validate_billable_owner` | No | `true` | Validate billable belongs to owner |
| `cashier-chip.subscriptions.retry_days` | No | `3` | Retry days |
| `cashier-chip.subscriptions.max_retries` | No | `3` | Max retry attempts |
| `cashier-chip.subscriptions.grace_days` | No | `7` | Grace period days |
| `cashier-chip.path` | No | `chip` | URL path prefix |
| `cashier-chip.webhooks.secret` | Yes* | env | Webhook secret |
| `cashier-chip.webhooks.verify_signature` | No | `true` | Verify webhook signatures |
| `cashier-chip.invoices.renderer` | No | env | Custom invoice renderer |
| `cashier-chip.invoices.paper` | No | `A4` | Paper size |
| `cashier-chip.invoices.vendor_address` | No | env | Vendor address on invoices |
| `cashier-chip.logger` | No | env | Custom logger class |

\* Required when webhook verification is enabled.

## 13. Testing

### Test infrastructure

| File | Purpose |
|------|---------|
| `tests/Pest.php` | Pest config, uses `TestCase` for `Actions/` directory |
| `tests/TestCase.php` | Orchestra Testbench base, loads `LaravelDataServiceProvider`, `SupportServiceProvider`, `CashierChipServiceProvider`, SQLite in-memory, sets test config defaults |

### Test files

**0 test files.** The test infrastructure exists but no actual test classes or Pest test files are present. The `tests/Actions/` directory referenced by `Pest.php` does not exist.

### Testing utilities present

- `Testing/FakeChipClient.php` — Low-level HTTP API faking
- `Testing/FakeChipCollectService.php` — High-level business-logic faking, drop-in for `ChipCollectService`
- `Testing/README.md` — Documents the two fakes and their usage

### Commands

| Command | Result |
|---------|--------|
| `PHPStan level 6` | Passed — 60 files, no errors |
| `Pint` | Passed — 62 files |
| `Tests` | **Not run** — no test files to execute |

### Gaps

- **No unit tests** for Actions, Models, Concerns, or Events
- **Fake utilities exist but aren't exercised** by any test
- **No integration tests** against the migrations

## 14. Documentation and developer experience

### Strengths

- **13 documentation files** covering all topics: overview, installation, configuration, usage, customers, charges, checkout, payment methods, subscriptions, webhooks, testing, API reference, troubleshooting
- **README.md** (374 lines) with comprehensive usage guide, configuration reference, testing instructions
- **CONTEXT.md** with routing info
- **friendly.md** (308 lines) and **lifecycle.md** (315 lines) — detailed internal design docs
- **Testing/README.md** (35 lines) — documents FakeChipClient/FakeChipCollectService
- **Testing fakes** — excellent DX contribution, easy to test in consuming applications
- PHPStan + Pint pass cleanly

### Gaps

- **No CHANGELOG.md**
- **Missing facade file** — `composer.json` declares an alias that doesn't resolve
- **No inline examples** in doc files showing how to use `CashierChip::fake()` for testing

## 15. Observability and operations

- **9 events** provide observability hooks for payment/subscription lifecycle
- **`RenewSubscriptionsCommand`** provides operational tooling for batch renewal
- **`WebhookCommand`** displays expected webhook URL for configuration
- **No health checks or metrics** — package doesn't expose service health
- **No structured logging** in actions — errors throw exceptions but aren't logged at the application level

## 16. Build, CI, release, and deployment

- **No CI configuration** in package (relies on repo-level CI)
- **No build step** — pure PHP package
- **`composer.json` extra** declares provider and alias for auto-discovery (though facade file is missing)
- **No release mechanism** documented
- **3 migrations** that will auto-run in production via `runsMigrations()`/`discoversMigrations()` — needs careful deployment coordination

## 17. Maintainability

### Strengths

- Clean domain namespace organization (`Billing/`, `Payment/`, `Subscription/`, `Invoice/`)
- Well-designed models with clear responsibilities
- 10 focused concern traits + 1 composed Billable trait
- Comprehensive Subscription model with proper lifecycle management
- Migration setup with config-driven table names — no schema lock-in
- Internal design docs track history and rationale

### Issues

- **Missing facade** — undeclared class referenced in composer.json
- **No base exception** — consumers must catch 7+ individual exceptions
- **`$guarded = []`** on all 3 write models
- **No enums** — status values as class constants
- **Action pattern inconsistency** — plain classes vs `AsAction` in sibling `cashier` package

## 18. Cross-package integration

- **cashier:** `aiarmada/cashier` uses `cashier-chip` as a gateway provider (suggested dependency). `GatewayDetector` checks for this package's classes.
- **chip:** Hard dependency — core CHIP SDK providing `ChipCollectService`, `PurchaseData`, webhook events
- **commerce-support:** Heavy dependency on `HasOwner`, `HasOwnerScopeConfig`, `OwnerContext`, `OwnerBatchRunner`, `OwnerQuery`, `OwnerScope`
- **vouchers:** Optional — coupon/discount support via `VoucherService`
- **docs:** Optional — invoice PDF rendering via `DocService`
- **filament-cashier-chip:** Consumer for admin UI

## 19. Positive findings

- **Models with proper ownership:** All 3 models use `HasOwner`/`HasOwnerScopeConfig` with config-gated enforcement — best ownership implementation across packages reviewed so far.
- **Subscription cross-tenant validation:** The `Subscription::creating` event has well-considered multi-path owner validation (fast-path for owner-is-billable, scoped lookup, authorization exception). Thoughtful security design.
- **Testing fakes:** `FakeChipClient` and `FakeChipCollectService` are well-designed testing utilities. No other package in this monorepo has equivalent testing infrastructure.
- **Comprehensive Subscription model:** 1392 lines covering full lifecycle management — creation, trial, pause, cancel, resume, swap, grace period, coupon application, quantity management, and scope filtering.
- **Internal design docs:** `friendly.md` and `lifecycle.md` document the 6-phase refactoring lifecycle. Valuable for ongoing maintenance.
- **Config-driven everything:** Table names, prefixes, JSON column types, owner scoping, coupon behavior — all configurable without code changes.
- **13 documentation files:** Most thoroughly documented package reviewed so far. Every public surface has corresponding docs.

## 20. Detailed findings

### CSP-001 No tests exist despite test infrastructure

- **Package:** cashier-chip
- **Area:** Testing
- **Severity:** Critical
- **Priority:** P0
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** Entire package — `tests/` contains only `Pest.php` and `TestCase.php`
- **Introduced by:** Unknown
- **Related findings:** None

**Observation:** Test infrastructure exists (`tests/Pest.php`, `tests/TestCase.php`, `Testing/FakeChipClient.php`, `Testing/FakeChipCollectService.php`) but no actual test files are present. The `tests/Actions/` directory referenced by `Pest.php` does not exist. No test configuration (`phpunit.xml`) exists.

**Impact:** Code changes to billing logic, subscription management, and payment handling have no regression safety net. The testing fakes are valuable but unused — their correctness is unverified.

**Root cause:** Tests infrastructure was scaffolded but actual test cases were never written.

**Recommendation:** Write tests for:
1. All 3 models (Subscription, SubscriptionItem, StoredPaymentMethod) — factory creation, casts, relationships, scopes
2. All 5 Actions (charge, refund, create subscription, cancel, sync status)
3. Owner scoping on all models — including cross-tenant block tests
4. Subscription lifecycle: create, trial, cancel, resume, pause, swap, grace period
5. Webhook event listeners
6. Console commands
7. Migrations (up/down via RefreshDatabase)

**Acceptance criteria:** Test suite exists with ≥40 tests covering models, actions, owner scoping, and subscription lifecycle. Test command passes.

**Remediation effort:** Large

**Remediation risk:** Medium

### CSP-002 Missing facade file

- **Package:** cashier-chip
- **Area:** Configuration
- **Severity:** Medium
- **Priority:** P2
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `composer.json` extra `aliases` entry, missing `src/Facades/CashierChip.php`
- **Introduced by:** Unknown (possibly Phase 3 reorganization)
- **Related findings:** None

**Observation:** `composer.json` registers `CashierChip` alias → `AIArmada\CashierChip\Facades\CashierChip`, but the file does not exist. The BC autoloader in `CashierChipServiceProvider::registerClassAliases()` handles old short class names but doesn't cover the facade.

**Impact:** `CashierChip::method()` syntax fails. Consumers must use `app('cashier')` or `AIArmada\CashierChip\Billing\Cashier::method()` directly.

**Recommendation:** Create the facade file or remove the alias from `composer.json`.

**Acceptance criteria:** Facade either exists and works, or `composer.json` alias is removed.

**Remediation effort:** Trivial

**Remediation risk:** Low

### CSP-003 No base exception class

- **Package:** cashier-chip
- **Area:** Architecture
- **Severity:** Medium
- **Priority:** P2
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** All 7 exception classes in `src/Exceptions/`
- **Introduced by:** Original design
- **Related findings:** None

**Observation:** All 7 exceptions extend `\Exception` directly. There is no `CashierChipException` base class. Consumers cannot catch all package-specific exceptions in a single catch block.

**Impact:** Error handling for package operations requires catching individual exceptions or using broad `\Exception`. This is inconsistent with `cashier` which has a clean `CashierException` hierarchy.

**Recommendation:** Add `CashierChipException extends \RuntimeException` and make all 7 exceptions extend it.

**Acceptance criteria:** All exceptions extend `CashierChipException`. Consumers can catch a single base type.

**Remediation effort:** Trivial

**Remediation risk:** Low

### CSP-004 No mass-assignment protection on models

- **Package:** cashier-chip
- **Area:** Security
- **Severity:** Medium
- **Priority:** P2
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Subscription/Subscription.php`, `src/Subscription/SubscriptionItem.php`, `src/Payment/StoredPaymentMethod.php`
- **Introduced by:** Original design
- **Related findings:** CSH-003 (same issue in cashier)

**Observation:** All 3 models declare `protected $guarded = []`. The Subscription model extensively uses `fill()` for state transitions (`cancel()`, `resume()`, `pause()`, `swap()`, `applyCoupon()`, etc.).

**Impact:** Malicious input reaching model `fill()` calls could set arbitrary attributes (e.g., `chip_status`, `owner_id`, `owner_type`). Risk is partially mitigated by controller/action level control, but there is no defense in depth.

**Recommendation:** Add `$fillable` or `$guarded` lists to all 3 models. For writable state transitions, use explicit `$this->fill([...])` with controlled attribute arrays (already done in most methods, but the guard is missing).

**Acceptance criteria:** Models have `$fillable` or specific `$guarded` lists. `fill()` calls in lifecycle methods are validated.

**Remediation effort:** Small

**Remediation risk:** Medium (may break existing write paths that rely on mass assignment)

### CSP-005 No CHANGELOG.md

- **Package:** cashier-chip
- **Area:** Documentation
- **Severity:** Low
- **Priority:** P3
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** Repository root
- **Introduced by:** Original
- **Related findings:** None

**Observation:** No `CHANGELOG.md` despite active development with 6 refactoring phases (friendly.md) and a lifecycle migration (lifecycle.md).

**Recommendation:** Add a CHANGELOG.md following Keep a Changelog convention.

### CSP-006 Status values as class constants not enums

- **Package:** cashier-chip
- **Area:** Architecture
- **Severity:** Low
- **Priority:** P4
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `Subscription` class constants for status
- **Introduced by:** Original design
- **Related findings:** None

**Observation:** Subscription statuses (STATUS_ACTIVE, STATUS_CANCELED, etc.) are class constants rather than PHP enums. Unlike `cashier` which has `SubscriptionStatus` and `InvoiceStatus` enums (in the wrong directory, but exist), this package has no enums at all.

**Recommendation:** Consider adding a `SubscriptionStatus` enum with color/icon/label helpers for consistency with the rest of the monorepo.

### CSP-007 Actions pattern mismatch with cashier

- **Package:** cashier-chip
- **Area:** Architecture
- **Severity:** Low
- **Priority:** P4
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** All 5 Action classes in `src/Actions/`
- **Introduced by:** Phase 1 of friendly.md
- **Related findings:** None

**Observation:** Actions in `cashier-chip` are plain final classes with explicit methods. Actions in sibling `cashier` use `lorisleiva/laravel-actions` `AsAction` concern. The inconsistency creates cognitive overhead.

**Recommendation:** Either migrate to consistent pattern across both packages, or accept the difference as intentional (different team, different timeline).

## 21. Unverified concerns and blocked checks

| Concern | Reason | Risk |
|---------|--------|------|
| Integration with CHIP API in live environment | Network access blocked | Medium |
| Webhook signature verification with real payloads | Not executed | Low |
| Owner scoping edge cases (null owners, mixed global/owned) | Not tested | Low |
| `RenewSubscriptionsCommand` with OwnerBatchRunner | Not executed | Low |
| Migration execution on real database | Not executed | Low |
| Facade resolution failure confirmed | Not executed | Low — path confirmed missing via `find` |

## 22. Recommended remediation order

1. **CSP-001** (No tests) — P0 — Critical safety gap
2. **CSP-002** (Missing facade) — P2 — Broken public API
3. **CSP-003** (No base exception) — P2 — Inconsistent error handling
4. **CSP-004** (Mass assignment) — P2 — Defense in depth
5. **CSP-005** (No CHANGELOG) — P3 — Documentation
6. **CSP-006** (Status constants) — P4 — Code quality
7. **CSP-007** (Action mismatch) — P4 — Consistency

## 23. Package-level acceptance checklist

- [x] PHPStan level 6 passes
- [x] Pint passes
- [ ] Tests exist and pass — **FAIL** (no tests)
- [ ] CHANGELOG.md exists — **FAIL**
- [ ] All models have PHPDoc property annotations — **PASS**
- [ ] No `$guarded = []` on write models — **FAIL**
- [ ] Custom exception hierarchy — **FAIL** (no base exception)
- [x] Migrations idempotent and config-driven — **PASS**
- [x] Owner scoping properly implemented — **PASS** (best in repo)
- [x] Testing utilities exist — **PASS**
- [ ] Facade resolves correctly — **FAIL** (missing file)
- [ ] Factories exist for models — **PASS** (SubscriptionFactory, SubscriptionItemFactory)

## 24. Final package rating

| Dimension | Rating | Notes |
|-----------|--------|-------|
| Functional correctness | Good | Well-designed lifecycle management |
| Security | Good | Owner scoping + cross-tenant validation; mass-assignment gap |
| Reliability | Fair | No tests despite testing infrastructure |
| Maintainability | Good | Clean domain structure, internal design docs |
| Test quality | **None** | Infrastructure exists but no test files |
| Documentation | Excellent | 13 doc files + README + Testing README |
| Operational readiness | Fair | Console commands, events; no health checks |
| Integration quality | Good | Well-integrated with chip, commerce-support, cashier |
| Release readiness | **Not ready** | Missing tests + missing facade block release confidence |

## 25. Final conclusion

**Conditionally ready.** The package is the best-designed CHIP billing integration reviewed so far — models with proper ownership, config-driven infrastructure, internal design docs, testing fakes, and comprehensive documentation. The Subscription model lifecycle management is thorough and well-thought-out.

However, the complete absence of tests (despite testing infrastructure and fakes being present), the missing facade file, the lack of a base exception, and mass-assignment gaps prevent a `Ready` rating. These are all addressable with focused effort.

**Summary of findings: 7 (1 Critical, 3 Medium, 3 Low)**
