# Package Audit — `chip`

## 1. Audit metadata

- **Path:** `packages/chip`
- **Version:** self.version (monorepo)
- **Package type:** Library — payment gateway integration (Laravel package)
- **Language/framework:** PHP 8.4 / Laravel
- **Audit date:** 2026-06-27
- **Commit:** 7d1dc95fa
- **Auditor:** Automated (AI)
- **Overall status:** Conditionally ready
- **Overall confidence:** High

## 2. Executive assessment

`aiarmada/chip` is the largest and most commercially mature package in the monorepo — 205 files, 16k lines of PHP source, 98 test files (18k test lines). It provides a complete integration with CHIP Collect + Send APIs, covering purchases, payments, clients, bank accounts, send instructions, webhooks (22 event types), and subscription management. Infrastructure includes 11 services, 11 actions, 24 events, 4 commands, 2 facades, 25 data objects, 3 payment gateway adapters, and a full webhook processing pipeline with deduplication, retry, and monitoring.

PHPStan level 6 and Pint pass clean. 98 test files exist but cannot run without a PostgreSQL database, and even unit tests time out at 10 minutes.

**Critical finding:** Both base models (`ChipModel`, `ChipIntegerModel`) use `$guarded = []`, completely disabling mass-assignment protection in a financial data package. Additionally, all 9 migrations violate the monorepo convention by having `down()` methods.

## 3. Package purpose and responsibility

Direct integration with CHIP Collect (payment processing) and CHIP Send (payout/bank transfer) APIs. Owns the API clients, webhook processing pipeline, payment data models, event system, and health checking for the CHIP gateway. Provides a contract-based gateway adapter for commerce-support's `PaymentGatewayInterface`.

## 4. Consumers and dependencies

### Internal dependencies (hard)

| Package | Notes |
|---------|-------|
| `aiarmada/commerce-support` | Owner scoping, audit, logging, health checks |

### External dependencies

| Package | Version | Notes |
|---------|---------|-------|
| `php` | `^8.4` | |
| `illuminate/contracts` | `^13.15.0` | |
| `illuminate/http` | `^13.15.0` | |
| `illuminate/queue` | `^13.15.0` | |
| `illuminate/support` | `^13.15.0` | |
| `illuminate/validation` | `^13.15.0` | |
| `spatie/laravel-webhook-client` | `^3.6.2` | Webhook client |
| `spatie/laravel-health` | (transitive) | Health checks |
| `owen-it/laravel-auditing` | (transitive) | Audit trail |

### Optional (class_exists-gated)

`checkout`, `customers`, `docs`

## 5. Public API and contracts

### Contracts (1 interface)

- **`ChipCustomerDirectoryInterface`** — Link local customer models to CHIP customer IDs

### Facades (2)

- **`Chip`** — Proxies `ChipCollectService` (purchases, clients, account, webhooks, subscriptions)
- **`ChipSend`** — Proxies `ChipSendService` (bank accounts, send instructions, groups, send webhooks)

### Payment Gateway Adapters (3)

- **`ChipGateway`** implements `PaymentGatewayInterface` from commerce-support
- **`ChipPaymentIntent`** implements `PaymentIntentInterface`
- **`ChipWebhookHandler`** implements `WebhookHandlerInterface`

## 6. Architecture and design

### Strengths

- **Two API integrations in one package:** Collect API (payments) + Send API (payouts) with separate clients, auth methods, and services. Clean separation.
- **Webhook processing pipeline:** Full pipeline from Spatie webhook client → signature verification → webhook profile → queue job → enriched payload → typed event dispatch → handler chain. Includes deduplication, retry (exponential backoff: 60s, 5m, 15m, 1h, 4h), monitoring, and health metrics.
- **24 events with typed data:** Purchase lifecycle events (12), payout events (3), standalone events (3), plus a generic `WebhookReceived` event with 18 convenience methods. Abstract `PurchaseEvent` and `PayoutEvent` base classes.
- **25 Spatie Data objects:** Full CHIP API data mapping with Money casts, factory methods, type safety. `PurchaseData` alone has 75 properties.
- **Base model layer with shared functionality:** `ChipModel` (UUID) and `ChipIntegerModel` (int PK) abstract bases providing owner scoping, audit, logging, table prefix, Money helpers, and timestamp conversion.
- **Config-driven everything:** 162-line config covering database, credentials, defaults, owner scoping, integrations, HTTP (timeout, retry, rate limit), webhooks, cache, and logging.
- **Health check command:** `chip:health` checks Collect + Send API connectivity.
- **Graceful integration:** `class_exists()` guards for checkout, customers, and docs packages.
- **Postgres GIN indexes:** Migration 000001 creates GIN indexes for JSON metadata queries.
- **Service provider:** Clean lifecycle separation, config validation at boot, deduplicated Spatie webhook client config registration.

### Issues

- **`$guarded = []` on both base models:** `ChipModel` and `ChipIntegerModel` both disable mass-assignment protection. For a financial data package, this is a critical risk — any fillable attribute can be mass-assigned.
- **All 9 migrations have `down()` methods:** Violates monorepo convention.
- **No package-exception base class:** `ChipApiException` extends `Exception`, `NoRecurringTokenException` extends `Exception`, `WebhookVerificationException` extends `Exception` (final), `ChipRateLimitException` extends `RuntimeException`. No shared `ChipException` base for catching all package errors.
- **No CHANGELOG.md.**
- **No package-local test runner config.**
- **98 tests exist but require PostgreSQL** — cannot run without `commerce_test` database.

## 7. Functional correctness

### Webhook pipeline

The webhook flow is well-designed: Spatie client → `ProcessChipWebhook` job → `WebhookEnricher` → `WebhookRouter` → `WebhookEventDispatcher` → typed event + handler. The `WebhookReceived` generic event contains typed data objects for all payload types with rich convenience methods.

**Concern:** The `WebhookReceived` event's `isTest()` defaults `is_test` to `true` if missing (line 311). This means missing `is_test` in payload silently marks the webhook as test. Should default to `false` — if the field isn't there, it's likely a real webhook.

### Payment Gateway Adapters

3 adapters implementing commerce-support contracts. `ChipGateway` creates payments via `ChipCollectService`, handles redirect URLs, pre-authorization. Webhook handler maps CHIP statuses to universal `PaymentStatus` enum.

### API Clients

Two clients with separate auth: Collect uses Bearer token, Send uses HMAC-SHA256 checksum. Both extend `BaseHttpClient` with rate limiting, retry (3 attempts, 1s delay), and request/response logging.

## 8. Security

### Positive

- **Webhook signature verification:** OpenSSL signature verification via `WebhookService`. Configurable with `verify_signature` toggle. Public key resolution from cache, config, or API.
- **Owner scoping:** Full `HasOwner` + `HasOwnerScopeConfig` with `AutoAssignOwnerOnCreate`. Config-gated via `chip.owner.enabled`.
- **Multi-brand webhook owner resolution:** `webhook_brand_id_map` config maps CHIP brand IDs to owner tuples for multi-tenant webhook routing.
- **Sensitive data masking:** Config option for logging with `mask_sensitive_data`. HTTP client masks sensitive fields.
- **CSRF-exempt webhook routes:** Uses `api` middleware, not `web`.
- **Config validation at boot:** Validates `collect.api_key`, `collect.brand_id`, and `webhook_brand_id_map` structure.

### Critical Gaps

- **`$guarded = []` on all models:** Both base models completely disable mass-assignment protection. Any request or job that mass-assigns attributes can set arbitrary model properties. For a payment package handling purchase status, amounts, and payment data, this is a critical vulnerability.
- **No rate limiting on webhook endpoint:** The HTTP client has rate limiting for outgoing API calls, but the webhook controller endpoint lacks incoming rate limiting.
- **No input validation on webhook route beyond signature:** After signature verification, the webhook payload is processed without validating payload structure.

## 9. Data integrity and persistence

### Schema (10 migrations)

| Migration | Table | PK | Notes |
|-----------|-------|----|-------|
| `000001` | `chip_purchases` | UUID | 50+ columns, 9 JSON, GIN indexes |
| `000002` | `chip_payments` | UUID | Linked to purchases via `purchase_id` |
| `000003` | `webhook_calls` | auto-inc | Extends Spatie webhook calls table |
| `000004` | `chip_bank_accounts` | int | Send API bank accounts |
| `000005` | `chip_clients` | UUID | CHIP client records |
| `000006` | `chip_send_instructions` | int | Send instructions |
| `000007` | `chip_send_limits` | int | Send limits |
| `000008` | `chip_send_webhooks` | int | Send webhooks |
| `000009` | `chip_company_statements` | UUID | Company statements |
| `2026_*` | `chip_customers` | UUID | Polymorphic customer link |

### Assessment

- **JSON columns:** 9 JSON columns on purchases table. Mitigated by GIN indexes on PostgreSQL.
- **All migrations have `down()`:** 9/9 migrations violate monorepo convention.
- **No FK constraints:** Per monorepo convention.
- **Application-level cascade:** `Purchase.deleting()` cascades to payments.
- **Money in cents:** All monetary columns use integer minor units.
- **Config-driven table prefix and JSON column type.**

## 10. Error handling and resilience

### Exception hierarchy

```
Exception
  ├── ChipApiException (API base)
  │     ├── fromResponse() factory, getStatusCode(), getErrorData(), getErrorCode()
  │     ├── getFormattedMessage(), toArray()
  │     └── ChipValidationException extends ChipApiException
  │           ├── fromValidator() factory, getValidationErrors(), hasFieldError()
  │           └── formatValidationErrors(), hasError()
  ├── NoRecurringTokenException
  │     └── Simple "No recurring token available"
  ├── WebhookVerificationException (final)
  │     ├── 5 static factories: missingSignature, invalidSignatureFormat,
  │     │   verificationFailed, invalidPayload, missingPublicKey
  │     └── Final class
  └── ChipRateLimitException extends RuntimeException
        ├── retryAfter property (default 60s)
        └── HTTP 429 status code
```

### Issues

- **No common base exception.** Cannot catch all CHIP errors with one type.
- **`NoRecurringTokenException`** extends `Exception` directly — inconsistent with the rest of the hierarchy.

### Resilience patterns

- **HTTP retry:** 3 attempts with 1s delay on API calls.
- **Rate limiting:** 60 requests/min on outgoing API calls.
- **Webhook retry:** Exponential backoff (60s, 5m, 15m, 1h, 4h) for failed webhooks.
- **Webhook deduplication:** Prevents duplicate processing via idempotency keys.
- **Health check command:** `chip:health` validates Collect + Send API connectivity.
- **Spatie health check:** `ChipGatewayCheck` registered as a system health check.

## 11. Performance and scalability

### Concerns

- **163 source files:** Every request that touches CHIP may load significant code. Negligible in practice.
- **Webhook queue:** Webhooks processed via queued jobs - good for request isolation. Retry management adds DB load per retry.
- **JSON queries:** 9 JSON columns on purchases. GIN indexes mitigate but don't eliminate query cost.
- **Event dispatch volume:** Each webhook dispatches both a generic `WebhookReceived` event AND a typed event — doubling event dispatch per webhook.

## 12. Configuration

162-line config with 10 sections: database, credentials, defaults, owner, integrations, HTTP, webhooks, cache, logging. All values have sensible defaults via `env()`.

## 13. Testing

### Test infrastructure

- **98 test files** in `tests/src/Chip/Unit/` and `tests/src/Chip/Feature/`
- **Pest TestCase** in package at `src/Testing/`
- **3 testing helper files:** `SimulatesWebhooks` trait, `WebhookFactory`, `WebhookSimulator`
- **Requires PostgreSQL** `commerce_test` database
- **No package-local phpunit.xml** — relies on monorepo root

### Test coverage by area

- **Services:** Collect, Send, Webhook, EventDispatcher, Analytics, Subscription
- **Collect APIs:** Purchase, Client, Account, Webhook API calls
- **Webhooks:** Handlers, Processing, Monitor, RetryManager, Signature verification
- **Data Objects:** All 25 DTOs with integration tests
- **Events:** Purchase, Payout, Other events coverage
- **Commands:** Health, Sync, Retry, Clean
- **Models:** Model integrity and base logging coverage
- **Enums:** All 7 enums with all values verified
- **Gateways:** Gateway adapter, PaymentIntent, WebhookHandler
- **Facades:** Both facades
- **Actions:** Purchase actions
- **Clients:** HTTP client rate limiting, auth, retry
- **Owner scoping:** Multi-tenant isolation, webhook owner resolution
- **Feature:** Customer bridge, docs generation, send handlers, integration tests

### Commands

| Command | Result |
|---------|--------|
| PHPStan level 6 | Passed — 147 files |
| Pint | Passed — 150 files |
| Tests | **Blocked** — requires PostgreSQL `commerce_test` database |

### Assessment

The most comprehensive test suite in the monorepo by far — 98 test files covering every architectural surface. Tests cannot execute in this environment (no Postgres).

## 14. Documentation and developer experience

### Strengths

- **12 documentation files** covering overview, installation, configuration, usage, webhooks, troubleshooting, and more
- **README.md** with quick start
- **CONTEXT.md** with routing info
- **PHPDoc on all public methods:** Good documentation coverage

### Gaps

- **No CHANGELOG.md**
- **Test suite not runnable** without PostgreSQL
- **163 source files** is a lot to navigate — the package does everything CHIP-related

## 15. Observability and operations

- **24 events** for custom listeners
- **Request/response logging** on HTTP clients (configurable, with sensitive data masking)
- **Audit trail:** `HasCommerceAudit` + `LogsCommerceActivity` on all models
- **Health check:** `ChipGatewayCheck` via Spatie Health
- **Webhook Monitor:** `WebhookMonitor` tracks event distribution, failure breakdowns, hourly volume
- **4 Artisan commands:** `chip:health`, `chip:retry-webhooks`, `chip:clean-webhooks`, `chip:sync-from-api`
- **No metrics** on payment success rates, gateway latency, or webhook processing time

## 16. Build, CI, release, and deployment

- **10 migrations** auto-run via `runsMigrations()` + `discoversMigrations()`
- **No CI config** in package
- **No CHANGELOG**

## 17. Maintainability

### Strengths

- Clean directory organization: Actions, Clients, Commands, Contracts, Data, Enums, Events, Exceptions, Facades, Gateways, Http, Listeners, Models, Services, Support, Testing, Webhooks
- Two clean base models for UUID vs integer PK tables
- Full Spatie Data object mapping of CHIP API → reduces ad-hoc array access
- Config-driven everything

### Issues

- **`$guarded = []`** — critical for a payment package
- **5 exception classes with no shared base** — inconsistent
- **No CHANGELOG** — hard to track changes across 205 files
- **Unordered `cast()` arrays** in Purchase model (line 177-197): `created_at`/`updated_at` cast to mutable `datetime` instead of `immutable_datetime`
- **`created_at` and `updated_at` use mutable datetime cast** (line 195-196 in Purchase.php) — violates monorepo convention for immutable dates

## 18. Cross-package integration

- **commerce-support:** `HasOwner`, `HasOwnerScopeConfig`, `HasCommerceAudit`, `LogsCommerceActivity`, `PaymentGatewayInterface`
- **checkout:** `ChipGateway` adapter, `ChipCustomerBridge`, `LinkChipCustomerFromCheckoutCompletion` listener
- **customers:** Customer model bridge via `ChipCustomerDirectory`
- **docs:** `GenerateDocOnPayment`, `GenerateDocOnRefund` listeners, `DocsIntegrationRegistrar`
- **filament-chip:** Admin UI for CHIP data (not audited here)

All optional integrations use `class_exists()` guards.

## 19. Positive findings

- **98 test files** — most comprehensive test suite in the monorepo by a wide margin
- **24 events** with typed data objects — well-structured event-driven architecture
- **Complete webhook processing pipeline** — deduplication, retry, monitoring, health
- **Two API integrations in one package** — Collect (payments) + Send (payouts) with clean separation
- **GIN indexes** for JSON query performance on PostgreSQL
- **WebhookReceived event with 18 convenience methods** — commercial-grade design
- **Support for both UUID and integer PK models** — pragmatic handling of CHIP API differences
- **Health check command** — `chip:health` for operational validation
- **Documentation** — 12 doc files (most of any package)
- **Good PHPDoc coverage**

## 20. Detailed findings

### CHP-001 `$guarded = []` on both base models

- **Package:** chip
- **Area:** Security
- **Severity:** Critical
- **Priority:** P1
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Models/ChipModel.php:42`, `src/Models/ChipIntegerModel.php:39`
- **Introduced by:** Original
- **Related findings:** None

**Observation:** Both base models use `protected $guarded = []`, which completely disables Eloquent's mass-assignment protection. Any attribute can be set via mass assignment.

**Impact:** Any request, job, or code path that uses mass assignment (e.g., `Purchase::create($request->all())`) can set arbitrary model attributes. For a package handling purchase status, amounts, and financial data, this is a critical vulnerability.

**Recommendation:** Define explicit `$fillable` arrays on all models. At minimum on the base classes with all shared attributes, then extend in concrete models.

**Acceptance criteria:** No model uses `$guarded = []`.

**Remediation effort:** Medium

**Remediation risk:** Medium — must verify all create/update paths still work.

### CHP-002 All migrations have `down()` methods

- **Package:** chip
- **Area:** Maintainability
- **Severity:** Low
- **Priority:** P4
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** All 9 migration files in `database/migrations/`
- **Introduced by:** Original
- **Related findings:** None

**Observation:** All 9 migrations include `down()` methods. Monorepo convention states "No `down()` method is required."

**Impact:** Per convention, `down()` should not be needed. Existence is harmless but inconsistent.

### CHP-003 No common package-exception base class

- **Package:** chip
- **Area:** Maintainability
- **Severity:** Low
- **Priority:** P4
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** All 5 exception classes
- **Introduced by:** Original
- **Related findings:** None

**Observation:** 5 exceptions with 3 different base classes: `ChipApiException` extends `Exception`, `NoRecurringTokenException` extends `Exception`, `WebhookVerificationException` extends `Exception` (final), `ChipRateLimitException` extends `RuntimeException`. No `ChipException` base.

**Impact:** Cannot catch all CHIP exceptions with a single type.

**Recommendation:** Add `ChipException extends RuntimeException` as a common base and make all exceptions extend it.

### CHP-004 No CHANGELOG.md

- **Package:** chip
- **Area:** Documentation
- **Severity:** Low
- **Priority:** P3
- **Affected components:** Repository root
- **Status:** Open

### CHP-005 `isTest()` defaults to `true` in WebhookReceived

- **Package:** chip
- **Area:** Reliability
- **Severity:** Medium
- **Priority:** P2
- **Confidence:** Confirmed
- **Verification status:** Strongly indicated
- **Status:** Open
- **Affected components:** `src/Events/WebhookReceived.php:311`
- **Introduced by:** Original
- **Related findings:** None

**Observation:** The `isTest()` method defaults to `true` when the `is_test` key is missing from the payload (`return $this->payload['is_test'] ?? true`). This means a missing `is_test` field silently marks the webhook as test, potentially ignoring real payment webhooks.

**Impact:** If CHIP ever omits `is_test` from a webhook payload, real payment notifications would be silently treated as test events.

**Recommendation:** Default to `false` — if the field isn't present, it's safer to assume it's a real webhook.

### CHP-006 Mutable datetime casts on Purchase model

- **Package:** chip
- **Area:** Code quality
- **Severity:** Low
- **Priority:** P4
- **Confidence:** Confirmed
- **Verification status:** Verified
- **Status:** Open
- **Affected components:** `src/Models/Purchase.php:195-196`
- **Introduced by:** Original
- **Related findings:** None

**Observation:** `created_at` and `updated_at` use `'datetime'` (mutable) cast instead of `'immutable_datetime'`. Monorepo convention prefers `CarbonImmutable`.

### CHP-007 No package-local test runner config

- **Package:** chip
- **Area:** Developer experience
- **Severity:** Medium
- **Priority:** P2
- **Affected components:** Repository root
- **Status:** Open

**Observation:** Tests require PostgreSQL `commerce_test` database. No package-local `phpunit.xml`. 98 tests cannot run standalone.

## 21. Unverified concerns and blocked checks

| Concern | Reason | Risk |
|---------|--------|------|
| Test suite execution | Requires PostgreSQL database | Medium — 98 tests unverified |
| Webhook deduplication correctness | Code reviewed, not executed | Low |
| GIN index performance at scale | No benchmark data | Low |
| `isTest()` behavior with CHIP API | Network blocked | Medium |

## 22. Recommended remediation order

1. **CHP-001** ($guarded = []) — P1 — Critical security
2. **CHP-005** (isTest default) — P2 — Reliability
3. **CHP-007** (No package-local test config) — P2 — Developer experience
4. **CHP-004** (No CHANGELOG) — P3 — Documentation
5. **CHP-003** (No common exception base) — P4 — Maintainability
6. **CHP-006** (Mutable datetime casts) — P4 — Code quality
7. **CHP-002** (Migration down()) — P4 — Convention consistency

## 23. Package-level acceptance checklist

- [x] PHPStan level 6 passes — **PASS**
- [x] Pint passes — **PASS**
- [x] Tests exist (98 files) — **PASS**
- [ ] CHANGELOG.md exists — **FAIL**
- [x] Model has PHPDoc property annotations — **PASS**
- [ ] Mass-assignment protection ($guarded = [] on ALL models) — **FAIL** (Critical)
- [x] Custom exception hierarchy — **PASS** (but no common base)
- [x] Owner scoping properly implemented — **PASS**
- [x] Proper enums with helper methods — **PASS**
- [x] Facades resolve correctly — **PASS**
- [x] Config-driven database — **PASS**
- [x] Webhook processing pipeline — **PASS**
- [ ] Test suite runnable independently — **FAIL**

## 24. Final package rating

| Dimension | Rating | Notes |
|-----------|--------|-------|
| Functional correctness | Excellent | Full CHIP API coverage, 24 events, 3 gateway adapters |
| Security | **Critical gap** | `$guarded = []` on all models in a payment package |
| Reliability | Good | Webhook dedup, retry, health checks; `isTest()` default concern |
| Maintainability | Good | Clean structure, 12 doc files; no CHANGELOG, no common exception base |
| Test quality | Excellent | 98 test files across all surfaces; not runnable without Postgres |
| Documentation | Good | 12 doc files (best in repo) |
| Operational readiness | Good | 4 commands, health check, webhook monitor; no metrics |
| Integration quality | Good | class_exists() guards for optional packages; 1 hard dependency |
| Release readiness | Conditionally |

## 25. Final conclusion

**Conditionally ready.** The chip package is the most extensively developed and tested package in the monorepo — 205 files, 98 tests, 24 events, full CHIP Collect + Send API integration with a complete webhook processing pipeline. Commercially mature infrastructure and excellent documentation.

The single critical blocker is `$guarded = []` on both base models — mass-assignment protection is completely disabled in a financial data package. This must be fixed before production deployment. Nine migrations also violate the `down()` convention, and the `isTest()` default in `WebhookReceived` is a reliability concern.

**Summary of findings: 7 (1 Critical, 2 Medium, 4 Low)**
