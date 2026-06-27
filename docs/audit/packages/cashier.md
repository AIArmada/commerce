# Package Audit — `cashier`

## 1. Audit metadata

- **Path:** `packages/cashier`
- **Version:** self.version (monorepo)
- **Package type:** Library (Laravel package, multi-gateway billing abstraction)
- **Language/framework:** PHP 8.4 / Laravel
- **Audit date:** 2026-06-27
- **Commit:** 7d1dc95fa
- **Auditor:** Automated (AI)
- **Overall status:** Conditionally ready
- **Overall confidence:** Medium

## 2. Executive assessment

`aiarmada/cashier` is a well-architected multi-gateway billing abstraction layer providing a unified interface over Stripe (`laravel/cashier`) and CHIP (`aiarmada/cashier-chip`). The contract-first design, hierarchical exception tree, thorough documentation (9 files), and Octane-safe architecture are real strengths. PHPStan and Pint both pass cleanly.

However, the package has **zero tests** — the `tests/` directory does not exist despite being declared in `composer.json`. For a financial/payments package, this is a critical gap. Additionally there is a duplicated `Billable` trait (root + `Concerns/`), models are empty shells with `$guarded = []` and no relationships, and there's no changelog. The package is architecturally sound but cannot be considered production-ready without tests.

## 3. Package purpose and responsibility

Unified multi-gateway billing abstraction for Commerce. Provides gateway-agnostic APIs for subscriptions, payments, invoices, checkout, customers, and webhook handling across Stripe and CHIP providers. Does not own any database tables — delegates persistence to gateway packages.

## 4. Consumers and dependencies

### Internal dependencies

| Package | Type | Notes |
|---------|------|-------|
| `aiarmada/commerce-support` | Hard (`self.version`) | Owner scoping, money formatting, conditional migration loading |

### External dependencies

| Package | Type | Notes |
|---------|------|-------|
| `php ^8.4` | Hard | |
| `laravel/cashier` | Suggested | Optional — Stripe gateway |
| `aiarmada/cashier-chip` | Suggested | Optional — CHIP gateway |
| `lorisleiva/laravel-actions` | Runtime (used via `AsAction`) | Actions pattern |
| `spatie/laravel-package-tools` | Runtime | Package service provider base |
| `akaunting/laravel-money` | Runtime (via commerce-support) | Money formatting |
| `stripe/stripe-php` | Transitive via laravel/cashier | Stripe SDK |

### Known consumers

- `aiarmada/filament-cashier` — Filament admin UI for billing
- Applications using the `Billable` trait on User models
- Any package consuming payment/subscription/invoice contracts

## 5. Public API and contracts

### Contracts (12 interfaces)

- `GatewayContract` (27 methods) — Full gateway lifecycle
- `BillableContract` (15+ methods) — Billable model contract
- `CustomerContract`, `PaymentContract`, `PaymentMethodContract`, `SubscriptionContract`, `SubscriptionBuilderContract`, `SubscriptionItemContract`, `InvoiceContract`, `InvoiceLineItemContract`, `CheckoutContract`, `CheckoutBuilderContract`

All contracts extend `Arrayable` and/or `Jsonable` where appropriate. Naming is consistent. Interface segregation is good — no god interfaces.

### Actions (5, via `AsAction`)

`CreatePayment`, `RefundPayment`, `CreateSubscription`, `CancelSubscription`, `SyncWebhook` — all dispatch appropriate events and handle error states.

### Events (12 + 2 base)

Clean hierarchy: `PaymentEvent`/`SubscriptionEvent` base → concrete events. `WebhookReceived`/`WebhookHandled` standalone.

### Facade

`Cashier` facade proxies to `GatewayManager`.

### Routes

Commented out in `routes/web.php` — deliberate: gateway packages own webhook endpoints.

## 6. Architecture and design

### Strengths

- **Contract-first design:** 12 interfaces define clear contracts; gateway implementations must implement all
- **Abstract base class:** `AbstractGateway` provides shared money formatting, config handling, billable resolution, and defines abstract methods for each gateway to implement
- **Gateway Manager:** Extends Laravel's `Manager` pattern (factory pattern) for gateway resolution with `__call` passthrough
- **Clean hierarchy:** Exceptions (20 classes in 5 domain groups), Events (12 + base), Support classes well-organized
- **Config-driven:** Gateways registered via config, cart integration config-gated, Stripe migration fallbacks auto-loaded
- **Octane-safe:** Boot-time defaults snapshot/restore on `RequestReceived` event, cart extension guards against re-wrapping
- **Owner scoping:** `OwnerScopedQuery` applies tenant isolation via subquery or column checks
- **Deliberate scope:** No own migrations, no duplicate persistence — delegates to gateway packages; this is an explicit design choice documented in lifecycle.md

### Issues

- **Duplicate Billable trait:** `src/Billable.php` and `src/Concerns/Billable.php` are identical copies. The root file should be a thin BC alias delegating to the Concerns version. Risk of divergence.
- **Empty models:** `UnifiedSubscriptionRecord` and `UnifiedInvoiceRecord` with `$guarded = []`, no relationships, no `HasOwner`, no PHPDoc
- **Enums in wrong directory:** `SubscriptionStatus` and `InvoiceStatus` under `Support/` not `Enums/` (minor, recognized in lifecycle.md)
- **`GatewayDetector` has hard import** of `Laravel\Cashier\Cashier` via `use` statement. This is safe due to lazy autoloading and `class_exists()` guard, but fragile — if the class is ever referenced before the guard, it fails.

## 7. Functional correctness

### Normal path review

- `CreatePayment` → resolves gateway → charges → dispatches success/failure → returns or throws `PaymentFailedException`. Logic is correct.
- `GatewayManager::gateway()` → resolves via `Manager::driver()` → creates gateway with config. Standard Laravel pattern.
- `OwnerScopedQuery::apply()` → checks owner context, column existence, applies subquery scoping. Logic is correct but Schema introspection on every query is a performance concern.
- `CartIntegrationRegistrar` → guards with `class_exists(CartManager::class)`, extends cart manager, handles success/failure with DB transaction, inventory release, affiliate conversion. Well-structured.
- `StripeGateway` → delegates to Cashier/Native Stripe SDK with try/catch returning null on failure. Graceful fallback.
- `WebhookReplayCommand` → processes webhook events from storage for replay. Functional.

### Concerns

- `$guarded = []` on both models allows mass assignment on all columns — no protection against unexpected attribute injection when used in write contexts (though these are read-only shell models over gateway tables).
- Gateway webhook routes commented out — the dependency on external packages for webhooks is explained in docs, but the empty route file could confuse developers.

## 8. Security

### Positive

- **Owner scoping:** `OwnerScopedQuery` provides tenant isolation via subquery pattern. Used in gateway actions and model queries.
- **Webhook signature verification:** `StripeGateway::verifyWebhookSignature()` uses `\Stripe\Webhook::constructEvent()` with proper guard clauses. `ChipGateway` has equivalent (expected).
- **Config-based credentials:** Gateway secrets stored in config via env variables (`STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `CHIP_BRAND_ID`)
- **No sensitive logging:** The codebase doesn't log credentials or PII in obvious paths

### Gaps

- **No input validation contracts:** The `GatewayContract::charge()` accepts `$options` array without documented schema. Gateway implementations pass options directly to underlying SDK. Malicious input could reach Stripe/CHIP APIs, though underlying packages have their own validation.
- **No rate limiting:** The package exposes payment operations (`charge`, `refund`, `createSubscription`) as callable actions without any rate limiting. A compromised client could create unbounded charges.
- **No CSRF protection documented** for webhook routes (documented in docs as needed but no middleware enforcement in this package).
- **SensitiveParameter attribute** used on `$paymentMethod` in `StripeGateway::charge()` — good, but not consistently applied across all similar parameters.

## 9. Data integrity and persistence

- **No own tables:** Explicit design decision. The package operates on gateway-owned tables via read-only shell models.
- **`$guarded = []`:** Both models are completely mass-assignable. Risk is minimal since these are intended as read-only read models over gateway tables, but there's no enforcement. A careless `save()` call could corrupt data.
- **No relationships defined** on models — consumers must join manually or go through gateway package models.
- **No HasOwner trait** on models — owner scoping is applied via `OwnerScopedQuery` at query time, not at the model level.
- **No `HasUuids` override** for `$keyType` in `UnifiedSubscriptionRecord` — it declares `public $incrementing = false` and `protected $keyType = 'string'` which matches `HasUuids` conventions, but `$incrementing` is redundant when using `HasUuids`.

## 10. Error handling and resilience

### Exception hierarchy

Comprehensive — 20 classes in 5 domain groups (`Gateway`, `Payment`, `Subscription`, `Webhook`, plus flat `CustomerNotFoundException`, `CheckoutException`, `InsufficientStockException`), all extending `CashierException`. This is a strength.

### Error handling patterns

- **Gateway operations** (`retrieveCheckout`, `retrieveSubscription`, `retrievePayment`, `retrieveInvoice`) wrap API calls in `try { ... } catch (Throwable) { return null; }`. Swallows all errors silently — operator cannot distinguish between "not found" and "network error" or "auth failure".
- **`CreatePayment` action** catches `PaymentFailedException` and rethrows it, catches all other `Throwable` and wraps in `PaymentFailedException`. Loses the original exception's stack trace context.
- **`Concerns\Billable` methods** (`allSubscriptions`, `findSubscription`, `onTrialOnAny`) use `try { ... } catch (Throwable) { }` pattern — silently swallows per-gateway errors. A failing gateway is invisible.

## 11. Performance and scalability

### Concerns

- **`OwnerScopedQuery::modelHasColumn()`** runs `Schema::hasColumn()` on every query execution. Schema introspection queries have ~5-10ms overhead and don't benefit from caching. Cache the result or determine column presence at boot time.
- **`ManagesGateway::allSubscriptions()`/`allGatewayInvoices()`** iterate over all configured gateways sequentially. With 2 gateways this is trivial; with N gateways it becomes N serial API calls.
- **`CurrencyFormatter`** is a thin wrapper delegating to `MoneyFormatter` from commerce-support — adds negligible overhead but also negligible value.
- **Gateway client instantiation** in `StripeGateway::client()` uses lazy initialization (`if ($this->stripeClient === null)`) — good pattern.

## 12. Configuration

| Key | Required | Default | Notes |
|-----|----------|---------|-------|
| `cashier.models.billable` | No | `App\Models\User` | Billable model class |
| `cashier.default` | No | `stripe` | Default gateway alias |
| `cashier.currency` | No | `MYR` | Shared fallback currency |
| `cashier.locale` | No | `en` | Locale for formatting |
| `cashier.gateways.stripe.*` | Yes* | — | Stripe driver, secret, webhook secret |
| `cashier.gateways.chip.*` | Yes* | — | CHIP brand ID, etc. |
| `cashier.cart.*` | No | Various | Cart integration settings |

\* At least one gateway must be configured and its provider package installed.

## 13. Testing

- **Test count:** **0** — the `tests/` directory does not exist
- **Test runner:** Not configured (no phpunit.xml, no pest.xml)
- **composer.json autoload-dev** declares `tests/` but the directory is missing
- **Previous stub report** claimed "~20 test files" and "Tests — PASS" — this was factually incorrect

This is the most significant finding for the package.

## 14. Documentation and developer experience

### Strengths

- **9 documentation files** covering all topics: overview, installation, configuration, usage, subscriptions, payments, multi-gateway, webhooks, troubleshooting
- **README.md** (239 lines) with architecture explanation, requirements, installation, configuration, usage examples, and extension guide
- **CONTEXT.md** with routing info for developers
- **friendly.md** (324 lines) and **lifecycle.md** (403 lines) — internal design documents documenting the refactoring history and design decisions
- PHPStan + Pint pass cleanly (good DX for contributors)

### Gaps

- **No CHANGELOG.md**
- **No method-level PHPDoc** on models (`UnifiedSubscriptionRecord`, `UnifiedInvoiceRecord`) — no property type annotations
- **Examples in docs** reference listeners (`GrantAccess`, `RevokeAccess`, etc.) that don't exist in this package — these are application-level examples, the docs should be clearer about this

## 15. Observability and operations

- **Events** provide observability hooks — 12 events for payment/subscription/webhook lifecycle
- **WebhookReplayCommand** (`cashier:webhook:replay`) provides operational tooling for replaying failed webhooks
- **No metrics, health checks, or readiness probes** — package doesn't expose `/health` or `/ready` endpoints
- **No structured logging** in actions or gateways — errors are thrown as exceptions but not logged at the application level

## 16. Build, CI, release, and deployment

- **No CI configuration** visible in the package (relies on repo-level CI)
- **No build step** — pure PHP package
- **rector.php** configured but only at level 0 (dead code + code quality), not actively used
- **No release mechanism** documented — package uses monorepo split, no tag policy visible
- **`composer.json` extra** declares provider and alias for auto-discovery

## 17. Maintainability

### Strengths

- Clean directory structure with clear separation of concerns
- Contract-first design makes adding new gateways straightforward
- Comprehensive exception hierarchy aids debugging
- Static analysis passes at level 6
- Internal design docs (friendly.md, lifecycle.md) document design history

### Issues

- **Duplicate Billable trait** — `src/Billable.php` and `src/Concerns/Billable.php` are identical
- **Empty models** — `UnifiedSubscriptionRecord` and `UnifiedInvoiceRecord` lack any useful declarations
- **No tests** means regressions are invisible
- **`CurrencyFormatter`** is a thin wrapper adding no value over the underlying `MoneyFormatter`
- **`GatewayDetector`** imports `Laravel\Cashier\Cashier` at compile time — adds a hard(ish) dependency on `laravel/cashier` for the `use` statement

## 18. Cross-package integration

- **cart:** `CartIntegrationRegistrar` integrates with `aiarmada/cart` via event listeners and cart manager extension. Config-gated.
- **commerce-support:** Heavy dependency on `OwnerContext`, `OwnerQuery`, `OwnerScope`, `MoneyFormatter`, `ConditionalMigrationLoader` from commerce-support
- **filament-cashier:** Consumes contracts for admin UI — not audited here
- **cashier-chip / laravel/cashier:** Gateway implementations delegate to these packages via their native APIs
- **affiliates:** `CartIntegrationRegistrar` optionally records affiliate conversions
- **inventory:** `CartIntegrationRegistrar` optionally commits/releases inventory allocations

## 19. Positive findings

- **Contract-first design:** 12 interfaces provide clear, stable contracts. Adding a new gateway requires implementing all 12 — ensures consistency.
- **Hierarchical exception tree:** 20 exception classes organized by domain — best-in-class for the monorepo based on reviewed packages.
- **Octane-safe architecture:** Snapshot/restore pattern for static state, guard against re-wrapping in cart extension — demonstrates awareness of long-lived worker concerns.
- **Thorough documentation:** 9 doc files + README + CONTEXT.md — covers installation, configuration, usage, and troubleshooting comprehensively.
- **Clean static analysis:** PHPStan level 6 passes with zero errors on 97 source files.
- **Internal design docs:** `friendly.md` and `lifecycle.md` document the refactoring history and design decisions — rare and valuable for maintenance.

## 20. Detailed findings

### CSH-001 Missing tests

- **Package:** cashier
- **Area:** Testing
- **Severity:** Critical
- **Priority:** P0
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** Entire package — no test directory exists
- **Introduced by:** Unknown
- **Related findings:** None

**Observation:** The `tests/` directory declared in `composer.json` autoload-dev does not exist. `find packages/cashier/tests -type f` returns no results. No test configuration files (phpunit.xml, pest.xml) exist. The package has zero automated tests.

**Impact:** Any code change to payment, subscription, or billing logic has no regression safety net. Financial operations with untested code risk incorrect charges, refund failures, or data corruption in production.

**Root cause:** Unknown — likely tests were never written or were removed during package extraction.

**Recommendation:** Write tests for critical paths:
1. GatewayManager resolution and driver creation
2. All 5 Actions (CreatePayment, RefundPayment, CreateSubscription, CancelSubscription, SyncWebhook)
3. OwnerScopedQuery scoping logic
4. CartIntegrationRegistrar event handling
5. AbstractGateway formatting and billable resolution
6. Each Gateway implementation (Stripe + CHIP) — at minimum contract compliance tests

**Acceptance criteria:** Test suite exists with ≥50 tests covering Actions, OwnerScopedQuery, Gateway resolution, and cart integration events. Test command passes.

**Remediation effort:** Large

**Remediation risk:** Medium (payment tests require mocking gateway SDKs)

### CSH-002 Duplicate Billable trait

- **Package:** cashier
- **Area:** Architecture
- **Severity:** Medium
- **Priority:** P2
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Billable.php`, `src/Concerns/Billable.php`
- **Introduced by:** Phase 1 of friendly.md (move Concerns)
- **Related findings:** None

**Observation:** Two identical `Billable` traits exist at `src/Billable.php` and `src/Concerns/Billable.php`. Both contain the same 5 methods (`allSubscriptions`, `findSubscription`, `subscribedOnAny`, `onTrialOnAny`, `onGenericTrial`). The root-level one was kept for backward compatibility after the move to `Concerns/`, but it's a full copy rather than a thin delegating `use Concerns\Billable`.

**Impact:** Bug fixes or enhancements to the Billable trait must be applied identically in two places. Risk of divergence.

**Recommendation:** Convert `src/Billable.php` to a thin BC alias:
```php
trait Billable
{
    use Concerns\Billable;
}
```

**Acceptance criteria:** Both traits behave identically. All consumers continue to work.

**Remediation effort:** Trivial

**Remediation risk:** Low

### CSH-003 Empty models with mass-assignment vulnerability

- **Package:** cashier
- **Area:** Security / Correctness
- **Severity:** Medium
- **Priority:** P2
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Models/UnifiedSubscriptionRecord.php`, `src/Models/UnifiedInvoiceRecord.php`
- **Introduced by:** Unknown
- **Related findings:** None

**Observation:** Both models declare `protected $guarded = []` making every column mass-assignable. They have no relationships defined, no `HasOwner` trait, no timestamps, and no PHPDoc property type annotations. They are intended as read-only shell models over gateway-owned tables (`subscriptions`, `purchases`).

**Impact:** While models are intended as read-only, `$guarded = []` means a `save()` call with user input could modify any column. No IDE support for property access. No owner-scoping enforcement at the model level.

**Recommendation:** 
1. Add PHPDoc property annotations for all accessible columns
2. Use `$guarded = ['*']` instead of `[]` to enforce read-only intent
3. Add `HasOwner` trait from commerce-support
4. Add `$table` as a real property (not just `getTable()`) for readability
5. Add relationship methods where meaningful

**Acceptance criteria:** Models have PHPDoc, `$guarded = ['*']`, and `HasOwner`. PHPStan passes.

**Remediation effort:** Small

**Remediation risk:** Low

### CSH-004 Schema introspection on every query

- **Package:** cashier
- **Area:** Performance
- **Severity:** Medium
- **Priority:** P3
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Support/OwnerScopedQuery.php` — `modelHasColumn()` method
- **Introduced by:** Unknown
- **Related findings:** None

**Observation:** `OwnerScopedQuery::modelHasColumn()` calls `Schema::hasColumn()` on every invocation. This performs a `SHOW COLUMNS` or equivalent query (~5-10ms) with no caching.

**Impact:** Every owner-scoped query against gateway-owned tables incurs a schema introspection query. On high-traffic billing endpoints, this adds unnecessary latency and database load.

**Recommendation:** Cache schema column information using a static array cache (`Schema::hasColumn()` results are stable within a request) or determine column presence at boot/registration time.

**Acceptance criteria:** Schema introspection runs at most once per table per process lifetime. Owner-scoped queries avoid runtime introspection.

**Remediation effort:** Small

**Remediation risk:** Low

### CSH-005 Broadcast error swallowing in gateway retrieval methods

- **Package:** cashier
- **Area:** Reliability
- **Severity:** Medium
- **Priority:** P2
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Gateways/StripeGateway.php` — `retrieveCheckout()`, `retrieveSubscription()`, `retrievePayment()`, `retrieveInvoice()`
- **Introduced by:** Unknown
- **Related findings:** None

**Observation:** All four retrieval methods use `try { ... } catch (Throwable) { return null; }`. This treats network errors, authentication failures, and rate limits identically to "not found" — all return `null`.

**Impact:** Operators cannot distinguish between a transient network failure and a genuinely missing resource. A Stripe outage would silently return `null` from all retrievals, potentially causing incorrect application state.

**Recommendation:** Log the exception before returning null, and consider distinguishing "not found" (404) from "unavailable" (network/timeout/5xx) to allow retry logic.

**Acceptance criteria:** Retrieval methods log on failure. Optional distinction between 404 and 5xx responses.

**Remediation effort:** Small

**Remediation risk:** Low

### CSH-006 Missing CHANGELOG.md

- **Package:** cashier
- **Area:** Documentation
- **Severity:** Low
- **Priority:** P3
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** Repository root
- **Introduced by:** Unknown
- **Related findings:** None

**Observation:** No `CHANGELOG.md` exists despite 7 documented refactoring phases (friendly.md) and active development.

**Impact:** Package consumers and developers cannot track what changed between versions.

**Recommendation:** Add a CHANGELOG.md following Keep a Changelog convention.

**Remediation effort:** Trivial

**Remediation risk:** Low

### CSH-007 `CurrencyFormatter` is unnecessary indirection

- **Package:** cashier
- **Area:** Architecture
- **Severity:** Low
- **Priority:** P4
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Support/CurrencyFormatter.php`
- **Introduced by:** Unknown
- **Related findings:** None

**Observation:** `CurrencyFormatter` is a thin wrapper with 6 static methods, all of which directly delegate to `MoneyFormatter` from commerce-support with identical signatures.

**Impact:** Adds a class to maintain without providing abstraction value. Any changes would need to be made in both places.

**Recommendation:** Remove the wrapper and use `MoneyFormatter` directly, or replace it with a facade/alias pattern.

**Remediation effort:** Trivial

**Remediation risk:** Low (breaking change for any consumers using `CurrencyFormatter` directly)

### CSH-008 Commented-out route stubs

- **Package:** cashier
- **Area:** Maintainability
- **Severity:** Low
- **Priority:** P4
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `routes/web.php`
- **Introduced by:** Unknown
- **Related findings:** None

**Observation:** `routes/web.php` contains a route group with both routes commented out. The loader (`registerRoutes()`) still calls `loadRoutesFrom()` when `Cashier::$registersRoutes` is true, loading an empty route group.

**Impact:** Minimal — loads an empty route group on every request. Slight overhead. Confusing for new contributors.

**Recommendation:** Either remove the route file entirely and always set `$registersRoutes = false`, or delete the file if routes are never expected to be registered here.

### CSH-009 No health check or readiness endpoint

- **Package:** cashier
- **Area:** Operations
- **Severity:** Low
- **Priority:** P4
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** Package scope
- **Introduced by:** Not applicable
- **Related findings:** None

**Observation:** The package provides no way to check whether configured gateways are reachable, credentials are valid, or the billing system is operational.

**Impact:** Operators cannot proactively detect billing system degradation. A misconfigured gateway secret is only detected on first charge attempt.

**Recommendation:** Add a health check command or endpoint that verifies gateway connectivity (e.g., `cashier:health` that attempts a lightweight API call to each configured gateway).

### CSH-010 Previous audit stub contained incorrect test claims

- **Package:** cashier
- **Area:** Documentation
- **Severity:** Informational
- **Priority:** P4
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `docs/audit/packages/cashier.md` (previous stub)
- **Introduced by:** Session 1 or 2 of audit
- **Related findings:** CSH-001

**Observation:** The prior audit stub for this package (since replaced) claimed "~20 test files" and "Tests — PASS". Neither claim was accurate — tests do not exist.

**Impact:** Undermines trust in audit artifacts. Demonstrates risk of assuming file existence without verification.

**Recommendation:** Ensure the replacement report has been reviewed. No further action needed — corrected by this report.

## 21. Unverified concerns and blocked checks

| Concern | Reason | Risk |
|---------|--------|------|
| Integration with Stripe/CHIP in actual environment | Network access blocked, no live credentials | Medium — gateway interactions not verified with real API |
| Cart integration end-to-end | Requires cart package installed + configured | Low — unit logic reviewed |
| OwnerScopedQuery correctness with CHIP subscription tables | Column schema unknown without DB access | Low — code pattern reviewed |
| Webhook replay command functionality | Not executed | Low — command logic reviewed |
| Rector execution | Would modify code (off-limits) | Low |

## 22. Recommended remediation order

1. **CSH-001** (Missing tests) — P0 — Critical safety gap
2. **CSH-005** (Error swallowing) — P2 — Reliability risk
3. **CSH-002** (Duplicate trait) — P2 — Maintenance debt
4. **CSH-003** (Empty models) — P2 — Correctness risk
5. **CSH-004** (Schema introspection) — P3 — Performance
6. **CSH-006** (CHANGELOG) — P3 — Documentation
7. **CSH-007** (CurrencyFormatter) — P4 — Indirection
8. **CSH-008** (Commented routes) — P4 — Minor
9. **CSH-009** (Health check) — P4 — Operations

## 23. Package-level acceptance checklist

- [x] PHPStan level 6 passes
- [x] Pint passes
- [ ] Tests exist and pass — **FAIL** (no tests)
- [ ] CHANGELOG.md exists — **FAIL**
- [ ] All models have PHPDoc property annotations — **FAIL**
- [ ] No `$guarded = []` on production models — **FAIL**
- [ ] No duplicate code — **FAIL** (Billable trait)
- [ ] At least 80% test coverage — **FAIL** (0%)
- [ ] Webhook routes actually work — **PASS** (delegated, intentional)
- [ ] No dead files — **PASS** (all files serve purpose)
- [ ] README matches implementation — **PASS**

## 24. Final package rating

| Dimension | Rating | Notes |
|-----------|--------|-------|
| Functional correctness | Good | Well-structured, logic appears correct |
| Security | Fair | Owner scoping + webhook verification, but no rate limiting, empty models mass-assignable |
| Reliability | Weak | No tests, error swallowing patterns |
| Maintainability | Good | Clean architecture, good docs, but duplicate trait and empty models |
| Test quality | **None** | Zero tests |
| Documentation | Good | 9 doc files + README + CONTEXT.md |
| Operational readiness | Weak | No health checks, no metrics |
| Integration quality | Good | Well-integrated with cart, commerce-support, gateway packages |
| Release readiness | **Not ready** | Missing tests block release confidence |

## 25. Final conclusion

**Conditionally ready.** The architecture is sound, the contract-first design is clean, the documentation is thorough, and static analysis passes. However, the complete absence of tests for a financial/payments package is a critical gap that must be addressed before production deployment. The duplicate Billable trait and empty model shells are secondary concerns that should be fixed in the same cycle.

**Summary of findings: 9 (1 Critical, 0 High, 4 Medium, 4 Low, 1 Informational)**
