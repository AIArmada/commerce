# Commerce Monorepo Full Audit — New Findings Only

- **Audit date:** 2026-07-13
- **Repository:** `aiarmada/commerce`
- **Source:** `commerce(3).zip`
- **SHA-256:** `d39f095b9e76f262bf69dcf140757c94a68cd74d8dad2e36f18a42b305804dfa`
- **Scope:** all 63 packages; source, migrations, config, routes, views, tests, package manifests, context files, and repository workflows.
- **Disposition:** This report intentionally excludes every root cause already assigned in the previous architecture handoff.

## Audit method and limits

- Inventoried every package and file class.
- Parsed and searched every package source/config/migration/route/view/test/manifest file for security, correctness, concurrency, money, owner scope, I/O, serialization, queue, and migration patterns.
- Ran PHP syntax lint over 5,759 PHP files: no syntax errors.
- Manually traced each accepted finding through callers, schema, configuration, and tests.
- Dynamic Pest/PHPStan execution is limited by the previously reported runtime-extension problem; this is excluded rather than duplicated.
- “Full audit” means full static coverage plus manual trace of admitted findings. It is not a mathematical guarantee that no defect remains.

## Prior findings excluded

- **PX-01 — Verification environment setup:** Missing PHP DOM/XML, SQLite/PDO SQLite, Composer/Pest/PHPStan baseline. Already owned by prior ENV/GOV work.
- **PX-02 — Repository rule hierarchy discrepancy:** Missing `.ai/rules/index.md` versus existing `.ai/guidelines`. Already owned by prior governance work.
- **PX-03 — Duplicate Inventory commands from Orders:** Payment and cancellation can emit duplicate inventory deduction/release commands. Already owned by prior BUG-INV work.
- **PX-04 — Checkout step graph:** Caller-visible step sequencing, registry/contributor seam, Events checkout contributor. Already owned by prior C01 work.
- **PX-05 — Order intake:** Atomic typed Order creation and durable intake idempotency. Already owned by prior C02 work.
- **PX-06 — Inventory checkout commitment:** Reservation/commit/release lifecycle and Checkout integration. Already owned by prior C03 work.
- **PX-07 — Owner access consolidation:** Shared owner-policy/deletion-test candidate. Already owned by prior C04 work.
- **PX-08 — Promotion/Voucher stacking and commitment:** Dead stacking registrar, combined cap, Voucher/Promotion commitment lifecycle. Already owned by prior C05 work.
- **PX-09 — Shipment/J&T remote operations:** Shipment creation/cancellation uncertainty, idempotency, carrier adapter. Already owned by prior C06 work.
- **PX-10 — Checkout finalization:** Early/duplicated completion, free-order swallowed failure, shared CreateOrderStep integration. Already owned by prior C07 work.
- **PX-11 — Cross-package contract reviews:** Order/Inventory/Discount pre- and post-implementation compatibility gates. Already owned by prior CTR tasks.
- **PX-12 — Prior tracker and documentation governance:** Agent ownership, exact scopes, same-pass documentation, integrated QC. Already owned by prior tracker.

## New findings

### NF-02 — Reusable CHIP credentials and bank data are stored as ordinary plaintext columns

- **Severity:** Critical
- **Category:** Security / payment credentials
- **Packages:** `cashier-chip`, `chip`, `filament-chip`
- **Root cause:** Provider credentials and financial identifiers are modeled as queryable strings or unprotected JSON without a ciphertext/fingerprint split.

**Evidence**
- `packages/cashier-chip/src/Payment/StoredPaymentMethod.php:42-51,93-100` exposes `recurring_token` as fillable with no encrypted cast or hidden list.
- `packages/cashier-chip/database/migrations/2000_03_01_000003_create_chip_payment_methods_table.php:27,35-37` stores and indexes the reusable token in plaintext.
- `packages/cashier-chip/src/Subscription/Subscription.php:105-128` and its migration store another plaintext recurring token.
- `packages/chip/src/Models/Purchase.php:45-76` stores recurring token plus provider client/payment/issuer payloads without serialization protection.
- CHIP BankAccount and Client models persist account, registration, tax, and bank payloads as normal columns/JSON.
- `packages/filament-chip/src/Resources/BankAccountResource/Schemas/BankAccountInfolist.php:48-50`, `BankAccountTable.php:38-42`, and `ClientInfolist.php:121-146` expose full account, registration, and tax values without masking.

**Impact:** A database dump, accidental model serialization, debug output, or read-only database compromise exposes credentials that may authorize future charges and sensitive banking data.

**Required change**
1. Define a single credential protector contract using authenticated encryption and a separate keyed HMAC fingerprint key.
2. Replace each plaintext token with `*_ciphertext` and `*_fingerprint`; query and uniqueness use the fingerprint only.
3. Mark ciphertext, fingerprints, provider payloads, account numbers, and tax identifiers hidden from model serialization.
4. Remove old columns and all dual-read fallback. Existing credentials must be re-tokenized or deliberately purged; do not silently preserve plaintext.
5. Add tests for equality lookup, uniqueness, tampering, key rotation/versioning, serialization, logs, factories, and exports.

**Illustrative code**
```php
$table->text('recurring_token_ciphertext');
$table->char('recurring_token_fingerprint', 64)->unique();

// Never query ciphertext.
$fingerprint = $protector->fingerprint($plainToken);
$model = StoredPaymentMethod::where('recurring_token_fingerprint', $fingerprint)->first();
```

**Tracker tasks:** `SEC-100`, `SEC-201`, `SEC-202`

### NF-05 — Scheduled affiliate payouts can be duplicated and lose remote outcome certainty

- **Severity:** Critical
- **Category:** Financial correctness / concurrency
- **Packages:** `affiliates`
- **Root cause:** Eligibility checks and balance reads occur before the transaction, rows are not locked, and provider calls lack durable operation identity and unknown-outcome reconciliation.

**Evidence**
- `ProcessScheduledPayoutsCommand.php:62-72` loads the entire eligible set with `get()`.
- `ProcessScheduledPayoutsCommand.php:120-139` checks holds and pending payouts outside the write transaction.
- `ProcessScheduledPayoutsCommand.php:147-175` creates a payout and decrements a previously loaded balance without `lockForUpdate()`.
- `StripeConnectProcessor.php:59-91` posts without explicit timeouts or provider idempotency and returns raw exceptions.
- `PayPalProcessor.php:71-114` computes a net amount without rejecting non-positive results and maps transport uncertainty to generic failure.

**Impact:** Concurrent workers can create multiple payouts against the same balance. A timeout after provider acceptance can lead to a retry and duplicate transfer.

**Required change**
1. Chunk candidate affiliates and atomically claim one affiliate/balance under row locks.
2. Create a durable payout operation with a unique business-period/idempotency key before any remote call.
3. Commit local claim state, then call the provider outside the database transaction.
4. Model `pending_remote`, `succeeded`, `failed_definitive`, and `unknown` outcomes; unknown must reconcile before retry.
5. Pass stable provider idempotency keys, configure connect/read timeouts, sanitize provider errors, and reject net amounts <= 0.

**Illustrative code**
```php
DB::transaction(function () use ($affiliateId, $periodKey) {
    $balance = AffiliateBalance::where('affiliate_id', $affiliateId)->lockForUpdate()->firstOrFail();
    return PayoutOperation::firstOrCreate(
        ['affiliate_id' => $affiliateId, 'period_key' => $periodKey],
        ['amount_minor' => $balance->available_minor, 'status' => 'claimed'],
    );
});
```

**Tracker tasks:** `PAY-301`, `PAY-302`

### NF-06 — CHIP subscription renewal can double-charge and treats unknown remote outcomes as failure

- **Severity:** Critical
- **Category:** Financial correctness / subscriptions
- **Packages:** `cashier-chip`
- **Root cause:** Every due subscription is loaded and charged with no lease, row lock, renewal-attempt record, or stable idempotency identity; the network call is inside a database transaction.

**Evidence**
- `RenewSubscriptionsCommand.php:68-73` loads all due subscriptions with `get()`.
- `RenewSubscriptionsCommand.php:108-124` performs the remote charge inside `DB::transaction()`.
- No unique renewal attempt exists for subscription + billing period.
- `RenewSubscriptionsCommand.php:136-147` converts any exception, including timeout-after-charge, into PastDue.
- `RenewSubscriptionsCommand.php:177-185` builds a human reference from type/date instead of a durable operation identifier.

**Impact:** Concurrent schedulers or retries can charge the same billing period more than once. Database locks remain open during network latency, and unknown outcomes are misclassified.

**Required change**
1. Introduce `subscription_renewal_attempts` unique on subscription and billing-period key.
2. Claim/lease attempts in short transactions using row locks; process in chunks.
3. Call CHIP outside the transaction with the attempt UUID as the stable idempotency/reference key.
4. Reconcile unknown outcomes before retry. Only definitive declines should mark PastDue.
5. Add concurrent-worker and timeout-after-provider-commit tests.

**Illustrative code**
```php
$attempt = RenewalAttempt::claim($subscriptionId, $periodKey);
$result = $gateway->charge(idempotencyKey: $attempt->id, ...);
$attempt->recordOutcome($result);
```

**Tracker tasks:** `PAY-310`

### NF-01 — JSON column type bypasses package-owned configuration

- **Severity:** High
- **Category:** Configuration / database portability
- **Packages:** `commerce-support`, `addressing`, `affiliate-network`, `affiliates`, `cart`, `cashier-chip`, `checkout`, `chip`, `communications`, `contacting`, `customers`, `docs`, `engagement`, `events`, `feedback`, `growth`, `inventory`, `jnt`, `membership`, `moderation`, `orders`, `products`, `promotions`, `references`, `seating`, `shipping`, `signals`, `tax`, `ticketing`, `vouchers`
- **Root cause:** The shared helper reads process environment variables directly. Every affected package calls it from migrations but none defines the required package-owned `json_column_type` configuration key.

**Evidence**
- `packages/commerce-support/src/helpers.php:13-31` calls `getenv()` for global and package-specific values.
- 29 package migration families call `commerce_json_column_type(...)` while their config files contain no `json_column_type` key.
- `AGENTS.md` requires package-owned configuration and forbids package behavior from reading environment variables outside config files.

**Impact:** Configuration caching, tests, host applications, and migrations can resolve different schema types. Invalid values are accepted and later invoked as Blueprint method names.

**Required change**
1. Make the helper read only `config("<package>.database.json_column_type")` (or a canonical package path) and validate strictly against `json` and `jsonb`.
2. Add the key to every affected package config. Environment access belongs only in those config files.
3. Delete global and package-specific direct `getenv()` behavior. Do not keep aliases or fallback environment reads.
4. Add migration-resolution tests for every package cohort and an invalid-value fail-fast test.

**Illustrative code**
```php
function commerce_json_column_type(string $package): string
{
    $type = config("{$package}.database.json_column_type");

    if (! in_array($type, ['json', 'jsonb'], true)) {
        throw new InvalidArgumentException("Invalid JSON column type for {$package}.");
    }

    return $type;
}
```

**Tracker tasks:** `CFG-400`, `CFG-411`, `CFG-412`

### NF-03 — Communication destinations use unauthenticated encryption and leak through serialization

- **Severity:** High
- **Category:** Security / cryptography
- **Packages:** `communications`
- **Root cause:** The destination protector implements raw AES-CBC without authentication and reuses the same key for encryption and HMAC. Sensitive model attributes are not hidden.

**Evidence**
- `packages/communications/src/Support/DestinationProtectorService.php:12-31` concatenates IV and CBC ciphertext without a MAC.
- `DestinationProtectorService.php:26` uses non-strict base64 decoding and does not validate minimum length.
- `DestinationProtectorService.php:34-36` uses the encryption key for destination hashing.
- `CommunicationDelivery` and `CommunicationTrackingToken` do not hide ciphertext, hashes, provider identifiers, target URLs, failure messages, or metadata.
- Existing tests only cover successful round-trip/hash/hint, not tampering, truncation, wrong key, or versioning.

**Impact:** Ciphertext can be modified without detection, malformed values can fail unpredictably, key compromise crosses two purposes, and sensitive attributes can leak from `toArray()`/JSON.

**Required change**
1. Replace CBC with versioned authenticated encryption using Laravel Crypt or sodium AEAD.
2. Use independent encryption and fingerprint keys derived with explicit context labels.
3. Fail closed on unknown versions, malformed encoding, truncation, tampering, or wrong keys.
4. Hide all secret and provider diagnostic fields; expose safe DTOs for admin views.
5. Destructively re-encrypt or purge existing values. Do not retain a legacy CBC decrypt branch.

**Illustrative code**
```php
final readonly class ProtectedDestination
{
    public function __construct(
        public string $ciphertext,
        public string $fingerprint,
        public string $hint,
    ) {}
}

// ciphertext format: v1:<authenticated payload>
```

**Tracker tasks:** `SEC-210`

### NF-04 — Public URL validation is vulnerable to DNS rebinding between validation and connection

- **Severity:** High
- **Category:** Security / SSRF
- **Packages:** `commerce-support`, `affiliate-network`, `affiliates`, `signals`, `filament-signals`
- **Root cause:** The guard resolves a hostname to verify public addresses, but consumers then ask the HTTP client to resolve the hostname again.

**Evidence**
- `packages/commerce-support/src/Support/PublicHttpUrlGuard.php:47-113` validates DNS results but returns only a boolean.
- AffiliateNetwork SiteContentFetcher, Affiliates WebhookDispatcher, Signals SignalAlertDispatcher, and FilamentSignals InteractionRuleScanner make a later independent HTTP request.
- Redirects are generally disabled, but the validated IP is not pinned to the actual connection.

**Impact:** An attacker-controlled hostname can resolve publicly during validation and privately during the actual request, reaching loopback, metadata, or internal services.

**Required change**
1. Replace boolean validation with a `ValidatedHttpTarget` containing normalized URL, host, port, and validated public IP set.
2. Make the transport pin the selected IP while preserving the original Host header and TLS SNI (for example cURL `CURLOPT_RESOLVE`).
3. Revalidate every redirect or continue to forbid redirects.
4. Reject mixed public/private answer sets unless policy explicitly selects and pins a public address.
5. Add deterministic rebinding tests: public answer at validation, private answer at connection.

**Illustrative code**
```php
$target = $guard->validate($url);
$response = $pinnedHttp->post($target, $payload); // transport connects to target->ip

```

**Tracker tasks:** `NET-110`, `NET-340`, `NET-341`, `NET-342`

### NF-07 — Public Signals ingestion accepts trusted conversion data with only a browser write key

- **Severity:** High
- **Category:** Security / fraud / abuse
- **Packages:** `signals`
- **Root cause:** One public collection endpoint serves both browser analytics and trusted commerce outcomes. Domain checks rely on request-controlled URL/Origin/Referer, and request shape has no bounded depth or total size.

**Evidence**
- `packages/signals/routes/api.php:12-28` applies only configured `api` middleware by default; no throttle is required.
- `SignalsIngestionRequestValidator.php:13-67` authenticates with the public write key and request-controlled domain evidence.
- `IngestSignalEvent.php:100-138` accepts `revenue_minor`, currency, source IDs, arbitrary traits, and arbitrary property arrays.
- `IngestSignalEvent.php:235-267` allows nested arrays and supports wildcard property passthrough.
- Public input can therefore impersonate conversion events and revenue if downstream reporting trusts them.

**Impact:** Attackers can inflate conversions/revenue, consume storage and CPU, create high-cardinality identities/events, and bypass domain intent with non-browser requests.

**Required change**
1. Split public browser events from trusted server-side outcome ingestion.
2. Public endpoint: event allowlist, no revenue/source transaction identifiers, property count/depth/value limits, request byte limit, property+IP rate limiting.
3. Trusted endpoint: separate secret/signature, replay window, idempotency key, explicit trusted event DTO.
4. Do not treat Origin/Referer as authentication; use them only as browser policy signals.
5. Add abuse, rate, oversize, spoofed revenue, replay, and trusted-signature tests.

**Illustrative code**
```php
POST /collect/browser-event   // public write key, bounded non-financial payload
POST /collect/server-outcome // HMAC/server credential, revenue + transaction IDs

```

**Tracker tasks:** `SEC-240`

### NF-08 — Signals alert delivery is synchronous, unbounded, and records HTTP failures as sent

- **Severity:** High
- **Category:** Reliability / outbound delivery
- **Packages:** `signals`
- **Root cause:** Alert evaluation directly loops all destinations and treats absence of an exception as success; HTTP response status is ignored and raw exception messages are persisted.

**Evidence**
- `SignalAlertDispatcher.php:43-49,86-107` performs destination fan-out synchronously.
- `SignalAlertDispatcher.php:156-183` posts webhooks/Slack without timeouts, retries, or `successful()` checks.
- HTTP 500 responses are recorded as `sent`.
- One exception causes a channel-level failure and raw exception text is stored in `delivery_results`.
- Inline evaluation can execute this path inside ingestion when queueing is disabled.

**Impact:** Ingestion latency and availability depend on third parties. Deliveries are lost, misreported, or duplicated without durable attempts.

**Required change**
1. Create alert-delivery/outbox rows, one per destination, with a unique idempotency key.
2. Queue bounded jobs with connect/read timeouts, success-status checks, retry/backoff, max attempts, and redacted error categories.
3. Keep rule evaluation separate from delivery.
4. Chunk scheduled processing and make partial fan-out observable.
5. Test 500, timeout, retry, duplicate job, partial success, and dead-letter behavior.

**Illustrative code**
```php
SignalAlertDelivery::firstOrCreate([
    'alert_log_id' => $log->id,
    'destination_key' => $destinationKey,
]);
DispatchSignalAlertDelivery::dispatch($delivery->id);
```

**Tracker tasks:** `REL-330`

### NF-09 — Affiliate webhooks are fire-and-forget and silently ignore response failure

- **Severity:** High
- **Category:** Reliability / webhooks
- **Packages:** `affiliates`
- **Root cause:** Webhook delivery is an inline loop with ephemeral random event IDs, no durable attempt, no status check, no timeout policy, and no exception isolation.

**Evidence**
- `packages/affiliates/src/Support/Webhooks/WebhookDispatcher.php:37-59` posts each configured endpoint inline.
- The response is discarded; a 500 response is indistinguishable from success.
- No outbox, attempt model, retry/backoff, stable event ID, or delivery audit exists.

**Impact:** Business events can be permanently lost while the caller believes dispatch completed. Slow or failing endpoints can block originating workflows.

**Required change**
1. Persist one webhook event and one delivery row per endpoint before dispatch.
2. Use a stable event ID and signed canonical payload across retries.
3. Queue delivery jobs with pinned-target transport, timeouts, status validation, retry/backoff, dead-letter state, and redacted evidence.
4. Delete the inline best-effort dispatcher; do not keep a fallback path.

**Illustrative code**
```php
$event = AffiliateWebhookEvent::record($type, $payload);
$event->createDeliveriesForConfiguredEndpoints();
DispatchAffiliateWebhook::dispatch($deliveryId);
```

**Tracker tasks:** `REL-320`, `NET-341`

### NF-11 — Experiment assignment repair randomly changes deterministic allocations

- **Severity:** High
- **Category:** Correctness / experimentation
- **Packages:** `growth`
- **Root cause:** The repair command bypasses the canonical allocator and selects a random variant without checking experiment status, audience, allocation, or weights.

**Evidence**
- `RecomputeExperimentAssignmentsCommand.php:30-44` finds missing variants and uses `inRandomOrder()->first()`.
- `ResolveExperimentAssignment.php:35-64` enforces active status, owner validation, subject identity, transactionality, and the canonical assignment path.
- The repair command reports assignments as recomputed even when no variant exists or dry-run performs no validation.

**Impact:** Repair can corrupt experiment populations, violate weights, assign inactive experiments, and produce non-reproducible analytics.

**Required change**
1. Delete the command or replace it with a repair action that calls the same deterministic allocator as normal assignment.
2. Repair only records with enough canonical subject identity to reproduce allocation; quarantine irreparable rows.
3. Never use random ordering.
4. Test stable repeated repair, weighted allocation, inactive rejection, owner scope, and dry-run counts.

**Illustrative code**
```php
$variant = $allocator->variantFor(
    experiment: $experiment,
    subjectKey: $assignment->subject_key,
);

```

**Tracker tasks:** `QOL-371`

### NF-12 — Filament Event preview renders unsanitized stored description as HTML

- **Severity:** High
- **Category:** Security / stored XSS
- **Packages:** `filament-events`, `events`
- **Root cause:** A free-form textarea feeds a TextEntry configured with `->html()` without sanitization or an explicit trusted-rich-text contract.

**Evidence**
- `packages/filament-events/src/Pages/EventPublicPreview.php:54-57` renders `description` with `->html()`.
- `packages/filament-events/src/Resources/EventResource.php:221-222` accepts description through a plain Textarea.
- No sanitizer or allowlist is applied between storage and rendering.

**Impact:** An editor or imported record can store scriptable markup that executes in an administrator’s Filament session.

**Required change**
1. Choose one model: plain text (remove `->html()`) or sanitized rich text with a documented allowlist.
2. For rich text, sanitize on write and render the sanitized value; reject event handlers, script/style, dangerous URLs, SVG, and form elements.
3. Add tests for `<script>`, `onerror`, `javascript:`, malformed tags, and allowed formatting.

**Illustrative code**
```php
TextEntry::make('description') // escaped by default
// OR state(fn (Event $e) => $sanitizer->sanitize($e->description))->html()

```

**Tracker tasks:** `SEC-230`

### NF-13 — Impersonation changes identity without rotating the session identifier

- **Severity:** High
- **Category:** Security / authentication
- **Packages:** `authz`
- **Root cause:** The manager rewrites guard/session authentication state and saves the same session; neither take nor leave regenerates or migrates the session ID.

**Evidence**
- `packages/authz/src/Services/ImpersonateManager.php:113-175` swaps to the impersonated user and calls `session()->save()` without regeneration.
- `ImpersonateManager.php:181-243` restores the original user and again saves without regeneration.
- Only one Authz test file exists and it does not cover take/leave, nested attempts, cross-guard behavior, session fixation, or rollback.

**Impact:** A session identifier known before the privilege/identity transition remains valid across the transition, increasing fixation and confused-session risk.

**Required change**
1. Regenerate the session ID after successful take and leave, preserving intended data while invalidating the old identifier.
2. Centralize guard switching so quiet and fallback paths have identical session guarantees.
3. On failure, restore auth/session atomically and rotate if identity changed.
4. Add tests asserting session ID changes, old ID invalidation, cross-guard transitions, nested rejection, event failure rollback, and back URL preservation.

**Illustrative code**
```php
$session = request()->session();
// after successful identity switch
$session->migrate(true); // destroy old session ID
$session->regenerateToken();
```

**Tracker tasks:** `SEC-220`

### NF-14 — Docs stores and calculates money as decimal strings and floats

- **Severity:** High
- **Category:** Financial correctness / money
- **Packages:** `docs`
- **Root cause:** The package predates the repository-wide minor-unit money contract and uses decimal schema columns, decimal casts, float DTO fields, float arithmetic, and rounded comparisons.

**Evidence**
- `create_docs_tables.php:56-60` defines decimal subtotal/tax/discount/total.
- `create_doc_extended_tables.php` defines decimal payment amount.
- `Doc.php:321-341` casts monetary values as `decimal:2`; `DocPayment.php:104-112` does the same.
- `DataObjects/DocData.php:31-35` exposes floats.
- `DocService.php:79-83,172,237,311-313` uses float calculations and comparisons.

**Impact:** Rounding and binary-float behavior can produce incorrect totals, payment status, tax, discount, and reconciliation results; currencies with non-2 exponents are not represented correctly.

**Required change**
1. Replace monetary columns and DTO fields with integer `*_minor` plus currency.
2. Perform all arithmetic in integers and use a single currency exponent/rounding policy at input boundaries.
3. Rename fields; do not retain decimal aliases or dual reads.
4. Destructively convert fixtures/factories/tests and original migrations.
5. Add exact arithmetic, large-value, zero-decimal, three-decimal, partial-payment, and cross-currency rejection tests.

**Illustrative code**
```php
$subtotalMinor = array_sum(array_map(
    fn (DocLineData $line) => $line->unitPriceMinor * $line->quantity,
    $lines,
));
$totalMinor = $subtotalMinor + $taxMinor - $discountMinor;
```

**Tracker tasks:** `DATA-350`

### NF-15 — Nullable owner columns defeat unique constraints for global records

- **Severity:** High
- **Category:** Data integrity / multi-tenancy
- **Packages:** `products`, `shipping`, `customers`, `signals`, `docs`
- **Root cause:** Unique indexes include nullable `owner_type` and `owner_id`. PostgreSQL, MySQL, and SQLite permit multiple NULL-containing tuples, while the packages intentionally support global records.

**Evidence**
- Products migrations define owner+slug, owner+SKU, owner+code unique indexes for nullable owners.
- `shipping_zones` uniquely indexes `[owner_type, owner_id, code]` while tests resolve global zones.
- `customer_segments`, Signals segments/goals/reports, and Docs templates/email templates use the same pattern.
- Tests across Products, Customers, Shipping, and Docs explicitly create records with both owner columns null.

**Impact:** Duplicate global slugs, SKUs, codes, goals, segments, and templates can be inserted despite schema names claiming uniqueness. Lookups become nondeterministic.

**Required change**
1. Introduce a non-null canonical `owner_scope_key` (`global` or a stable hash of owner morph + ID) for uniqueness only.
2. Populate and validate the key from the owner tuple in one shared concern; callers must not supply arbitrary values.
3. Replace nullable-owner unique indexes with `owner_scope_key` indexes.
4. Detect and resolve duplicates before adding constraints; no compatibility indexes or old uniqueness assumptions remain.
5. Test global duplicates, per-owner duplicates, same slug across different owners, owner reassignment, and all supported databases.

**Illustrative code**
```php
$table->string('owner_scope_key', 191);
$table->unique(['owner_scope_key', 'slug']);
// owner_scope_key = 'global' or hash(owner_type + ':' + owner_id)

```

**Tracker tasks:** `OWN-120`, `DATA-360`, `DATA-361`, `DATA-362`

### NF-18 — Event notification paths silently do nothing or falsely mark messages sent

- **Severity:** High
- **Category:** Correctness / notifications
- **Packages:** `events`, `filament-events`
- **Root cause:** The default dispatcher is a no-op, while Filament “Send Now” changes database status without invoking any dispatcher or creating delivery records.

**Evidence**
- `EventsServiceProvider.php:489-495` selects `NullEventChangeNoticeNotificationDispatcher` when config is null.
- `NullEventChangeNoticeNotificationDispatcher.php:10-12` has an empty `dispatch()`.
- `DispatchEventChangeNoticeNotifications.php:26-42` creates a pending batch then calls the possibly-null dispatcher.
- `NotificationCenter.php:65-73` labels the action “Send Now” but only writes `status=sent` and `sent_at`.
- `NotificationCenter.php:142-150` creates pending batches without message content or dispatch.

**Impact:** Critical event changes and manual notifications can be recorded as pending or sent without any recipient delivery. Operators receive false success signals.

**Required change**
1. Make missing delivery configuration fail closed when notification functionality is enabled; remove the silent null dispatcher from production registration.
2. Define one Event notification dispatch module that resolves audience, creates durable deliveries, queues delivery, and derives batch status from delivery outcomes.
3. Make Filament actions call that module; never mutate sent status directly.
4. Add tests for absent adapter, configured adapter, zero recipients, partial failure, retry, cancellation, and UI action behavior.

**Illustrative code**
```php
$result = $notificationDispatcher->dispatchBatch($batch->id);
// Batch status is derived from delivery rows; UI never writes `sent` directly.

```

**Tracker tasks:** `COR-270`

### NF-10 — Promotion eligibility recomputation command performs no recomputation

- **Severity:** Medium
- **Category:** Correctness / misleading operations
- **Packages:** `promotions`
- **Root cause:** The command name and success message claim a state-changing operation, but the implementation only lists active promotions.

**Evidence**
- `RecomputePromotionEligibilityCommand.php:17-37` queries active promotions, prints them, and always reports completion.
- `--dry-run` is declared but never changes behavior.
- No eligibility materialization or evaluation action is invoked.

**Impact:** Operators can believe eligibility has been repaired when no state or cache changed.

**Required change**
1. Delete the command, registration, docs, and tests unless the domain explicitly requires persisted eligibility.
2. If persisted eligibility is required, define that model and a real recomputation action first; the command must report actual inspected/changed/failed counts.
3. Do not retain a no-op compatibility alias.

**Illustrative code**
```php
// Preferred until a real materialized eligibility concept exists:
// delete RecomputePromotionEligibilityCommand entirely.
```

**Tracker tasks:** `QOL-370`

### NF-16 — CI does not run dependency vulnerability auditing

- **Severity:** Medium
- **Category:** Security / supply chain
- **Packages:** `repository-root`
- **Root cause:** Quality workflows install dependencies and run style/static/tests but do not execute Composer advisory checks or dependency review.

**Evidence**
- `.github/workflows/ci.yml` runs Rector, Pint, PHPStan, and Pest.
- No workflow or Composer script invokes `composer audit`.
- `.github/skills/laravel-best-practices/rules/security.md:161-164` explicitly recommends automating `composer audit` in CI.

**Impact:** Known vulnerable locked dependencies can merge and release without an automated gate.

**Required change**
1. Add a required `composer audit --locked --no-interaction` job after install.
2. Add scheduled auditing and dependency-review tooling for pull requests.
3. Fail on abandoned/vulnerable packages according to a documented policy; exceptions require expiry and rationale.
4. Do not add an allow-failure legacy path.

**Illustrative code**
```php
- name: Audit locked dependencies
  run: composer audit --locked --no-interaction --format=summary

```

**Tracker tasks:** `SEC-250`

### NF-17 — Affiliate model schema contains an unused plaintext API token column

- **Severity:** Low
- **Category:** Code quality / security clarity
- **Packages:** `affiliates`
- **Root cause:** The migration creates per-affiliate `api_token`, but authorization middleware reads only one global configuration token and no production code reads the model column.

**Evidence**
- `create_affiliates_table.php:19` creates nullable unique `api_token`.
- `EnsureApiAuthorized.php:21` reads `config("affiliates.api.token")`.
- Repository search finds no model-column authentication or rotation path.

**Impact:** The schema suggests a security capability that does not exist, invites plaintext token population, and creates unsupported operational expectations.

**Required change**
1. Remove the column from migrations, model fillable/casts/factories/docs, and any UI.
2. If per-affiliate API credentials are required later, design hashed credentials with scopes, expiry, rotation, and audit as a separate feature.
3. Do not wire this plaintext column into middleware as a shortcut.

**Illustrative code**
```php
// Delete `api_token` from the affiliates table.
// Keep the existing explicit global integration token until a real credential module exists.
```

**Tracker tasks:** `QOL-372`

### NF-19 — Membership invitations retain an insecure plaintext-token mode

- **Severity:** Low
- **Category:** Security hardening / token storage
- **Packages:** `membership`
- **Root cause:** The secure hashed mode is default, but the model contains a configuration branch that stores and compares plaintext tokens.

**Evidence**
- `membership/config/membership.php:15` defaults `hash_tokens` to true.
- `MembershipInvitation.php:118` bypasses hashing when the flag is false.
- Tests verify the secure default but not removal of the insecure mode.

**Impact:** A host application can accidentally disable hashing and turn database disclosure into active invitation takeover.

**Required change**
1. Remove the `hash_tokens` option and plaintext branch entirely.
2. Always store a one-way hash; expose the raw token only once through the creation result/event.
3. Add a test proving no configuration can cause plaintext persistence.

**Illustrative code**
```php
public function setTokenAttribute(string $plain): void
{
    $this->attributes['token'] = hash('sha256', $plain);
}
```

**Tracker tasks:** `SEC-260`
