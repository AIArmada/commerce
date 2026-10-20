---
title: Commerce Audit Follow-up — Worker Handoff
status: fixes-required
reviewed: 2026-10-18
reviewed_head: 05510acc4a7c6debbb73766bfe11433c8893b891
review_surface: tracked and untracked working-tree implementation
packages: [authz, cart, products, checkout, orders, signals]
---

# Commerce audit follow-up — worker handoff

## Outcome and scope

**Follow-up is required.** This review checked the working-tree implementation against the [original 16-finding handoff](commerce-audit-remediation-handoff-2026-10-17.md). Seven original findings are still open, and six additional defects or incomplete boundary cases were reproduced. Passing existing suites does not close these findings: the focused audit probes assert the defective behavior and currently pass.

The implementation is uncommitted. The recorded HEAD identifies the base, not a commit containing the fixes. Preserve the current tracked and untracked work; do not reset, replace, or discard it. This document is the next implementation assignment. The original handoff remains the specification for unchanged findings.

### Binding constraints

- Implement the corrected contracts directly. **No backward-compatibility shims, aliases, dual API paths, or legacy semantics.** Update callers and canonical docs together.
- **Edit owning migrations in place** for schema changes. Add no incremental schema migrations or historical-data backfills. Verify fresh schemas on dedicated disposable databases. Never rebuild an application database.
- Keep tenancy enforcement in the domain and shared `commerce-support` primitives. Permission to read a global reference does not authorize extending a global aggregate from a tenant context.
- Keep money in integer minor units plus an explicit currency. Preserve actual payment identities and owner boundaries across retries.
- Read `CONTEXT-MAP.md`, the owning package contexts, and applicable repository rules before implementation. Include paired adapters when a public contract changes.

### Status of the original assignment

| Original ID | Review result | Remaining work |
| --- | --- | --- |
| AZ-01 | Unresolved references now fail closed in the built-in resolver modes | AZ-04 fixes the new configured-resolver regression |
| AZ-02 | Open; reproduced | Remove stale wildcard/super-admin verdicts |
| AZ-03 | Open; reproduced | Correct multiple wildcard segments |
| CART-01 | Owner-partitioned ambient manager implemented; new isolation tests pass | Retain this behavior |
| CART-02 | Atomic snapshots and guarded replacements implemented; incomplete across the full operation | CART-04, CART-05, CART-06 |
| CART-03 | UUID-safe identifier resolver and helpers implemented | Retain UUID and invalid-ID regression coverage |
| PROD-01 | Reparenting is rejected; global-parent lineage remains incomplete | PROD-04 |
| PROD-02 | Attribute definition/target checks and immutable identity implemented | Keep permitted global definitions distinct from global target mutation |
| PROD-03 | Open; reproduced | Combination identity remains based on colliding SKU text |
| ORD-01 | Child parent/payment-reference guards implemented | Retain direct-model and owner-disabled regressions |
| ORD-02 | Partial settlement, payable-state gate, invoice gate, and fulfillment gating implemented | ORD-04 extends refund handling to the now-supported split-payment model |
| ORD-03 | Cancellation remainder and per-payment reservation logic implemented | ORD-04 applies the same limits to other refund paths |
| CHK-01 | Open; reproduced | The order-total check does not claim or fence gateway attempts |
| SIG-01 | Open; reproduced | No recursive privacy filtering change |
| SIG-02 | Open; reproduced | No currency-aware reporting/schema change |
| SIG-03 | Open; reproduced | Observation bounds can still move backward |

## Implementation sequence

1. Add permanent failing regressions for the findings below. Reverse the observed-defect expectations: assert rejection, correct isolation, retained data, or the correct event.
2. Fix AZ-04 and AZ-02 first because they affect authorization boundaries; finish AZ-03 in that package pass.
3. Finish checkout attempt ownership (CHK-01), then cart transfer/removal atomicity (CART-04 through CART-06).
4. Centralize refund allocation (ORD-04) before expanding split-payment integration tests.
5. Fix catalog global-parent lineage and combination identity (PROD-04 and PROD-03).
6. Implement Signals privacy, currency, and chronology changes (SIG-01 through SIG-03), including reporting consumers and fresh-schema checks.
7. Run the verification gates, update owning package docs, and report every ID with source and permanent test references.

Completion means every finding has a behavior-level regression and passes its completion gate. Existing broad suites and PHPStan alone are insufficient.

## Authorization

### AZ-04 — P1: Switch the resolver that actually controls permission teams

**Source:** `packages/authz/src/Authz.php:128–135`; resolver selection in `AuthzServiceProvider`; `Support/AuthzScopeContext.php`; `Support/AuthzScopeTeamResolver.php`.

**Confirmed failure:** Configure Spatie's `DefaultTeamResolver` and rebuild the permission registrar. Assign a permission only under team A. Calling `withScope(B)` leaves `getPermissionsTeamId()` equal to A; `userCanInScope(user, ability, B)` returns true using A's grant. `withScope(null)` also keeps A. The new implementation switches `AuthzScopeContext` for every non-owner resolver, but the configured resolver need not read that context.

**Required fix:**

1. Dispatch scope switching according to the resolver actually bound to the registrar. Preserve the specialized override and restoration semantics for the built-in Authz and owner-context resolvers.
2. For default/custom resolvers using Spatie's team API, set the requested team ID on the active resolver and restore its previous value in `finally`. If a resolver cannot honor the requested scope, fail explicitly before invoking the callback; never execute under the previous team.
3. Retain AZ-01's distinction between explicit global null and a failed non-null resolution. Do not convert a scope-resolution error or callback failure into global access.
4. Clear the user's scope-sensitive loaded relationships and package verdict state on entry and exit. An exception in a nested scope must restore the outer resolver state.

**Permanent tests:** Extend the Authz scope unit tests with default and custom resolvers, A-only/B-only grants, explicit global, nested switching, thrown callbacks, rejected references, and previous-team restoration. Verify both callback `getPermissionsTeamId()` and Gate results; a boolean result alone can hide a resolver mismatch.

**Done when:** No supported configuration can check B or global using A's team. No schema change is required.

### AZ-02 — P2: Make wildcard authorization reflect revocation immediately

**Source:** `packages/authz/src/Support/WildcardPermissionCache.php:27–49,60–76`; Gate registration and permission/role mutation paths.

**Confirmed failure:** Grant `audit.*`, check `audit.delete` through Gate, revoke the grant, and check again. Direct `hasPermissionTo()` is false while Gate still allows the ability. The grant list and super-admin verdict memoization remain unchanged from the original audit.

**Required fix:** Execute the original AZ-02 specification. Prefer removing the extra package memoization and reading current scoped assignments. If retaining caching, invalidate at every supported assignment mutation boundary, including direct grants/revocations, role grants/revocations, sync operations, and super-admin role removal. Correct already-loaded user instances as well as fresh instances. Callers must not need to clear an internal cache after a normal revoke.

**Permanent tests:** Grant/check/revoke/check and deny/grant/check; direct and role-based wildcards; role permission sync; super-admin removal; two loaded instances of one user; owner/team switching in one lifecycle. Preserve the package's deliberately documented super-admin scope semantics.

**Done when:** The first Gate check after a supported mutation sees current assignments. No schema change is required.

### AZ-03 — P2: Evaluate all wildcard segments

**Source:** `packages/authz/src/Services/WildcardPermissionResolver.php:33–36,51–56`.

**Confirmed failure:** `*.view.*` fails to match `orders.view.own`, while `*.view.own` matches it. The trailing `.*` shortcut treats an earlier wildcard as literal text.

**Required fix:** Implement one consistent matcher that expands all wildcard segments, quotes literal regex characters, honors the configured permission separator, and preserves documented trailing-subtree semantics. Remove the incorrect literal-prefix shortcut or limit it to patterns proven to contain no earlier wildcard.

**Permanent tests:** Exact/global grants; beginning/middle/end/multiple wildcards; nested abilities; negative near-matches; literal regex punctuation; configured separator. Exercise Gate as well as the matcher.

**Done when:** Supported patterns authorize exactly their intended abilities. No schema change is required.

## Checkout and cart

### CHK-01 — P1: Claim and fence gateway attempts before external payment creation

**Source:** `packages/checkout/src/Services/CheckoutService.php:107–119,416–473`; `Steps/ProcessPaymentStep.php:102–147`; callback and compensation actions.

**Confirmed failure:** Load two instances of one pending session. Process the first to completion, then process the stale instance. The processor receives `createPayment()` twice, the completed session is reopened, and its `payment_id` changes from the first payment to the second. This still reproduces after the added `CreateOrderStep` amount/currency reconciliation.

**Required fix:**

1. Lock and refresh the authoritative session before any transformer/state mutation or terminal eligibility decision. A stale instance must never save `Processing` over the committed terminal state.
2. Claim one immutable attempt identity/generation under that lock. Only the claimant may call the gateway; concurrent callers observe the existing in-flight/redirect/completed outcome. Reuse existing attempt fields if sufficient, otherwise add only the fields needed to distinguish claims and generations.
3. Keep gateway I/O outside the transaction. Build its idempotency key from the committed attempt. A crashed/unknown outcome must reconcile that same attempt rather than create a new charge.
4. Re-lock before recording a gateway result, failure, compensation, or completion. Require the same attempt identity/generation. A late result cannot overwrite a callback winner or a later explicitly authorized retry.
5. Allow a new attempt only from a verified retryable result. Count each claim once and preserve attempt limits. Tie orphan compensation to the losing attempt's payment, never the winner's payment.
6. Retain the new order/session amount and currency comparison. Verify it remains effective for live-cart drift and existing-order retries.

**Permanent tests:** Sequential stale replay; two process calls; process versus retry; callback completing before create response; late failure/result after a winner; cancellation; gateway timeout and recovery; transformer writes; explicit retry limits. Count gateway calls and prove one order/fulfillment/revenue outcome. Add a real-engine claim race, not only an injected stale instance.

**Schema:** If needed, edit `packages/checkout/database/migrations/2024_01_01_000001_create_checkout_sessions_table.php` in place. Start new fields clean. Update checkout payment-flow docs.

**Done when:** Replaying or racing one logical checkout cannot create a second active charge or overwrite its winning payment.

### CART-04 — P1: Consume the exact guest revision atomically with the target merge

**Source:** `packages/cart/src/Actions/MigrateGuestCartToUserAction.php:206–245,257–318`; `Storage/StorageInterface.php`; `Storage/DatabaseStorage.php`; in-memory storage and migration services.

**Confirmed failures:**

- A guest cart contains A. Migration reads it; another cart adds B successfully; migration commits only A to the target and blindly clears the source. B disappears.
- Two migrations read the same guest payload and use different target identifiers. Both return success and both targets receive A. Target revision checks do not guard consumption of the shared source.

The existing PostgreSQL migration race test covers a competing **target** write. It does not exercise these source races.

**Required fix:**

1. Put both source consumption and target merge behind one coherent storage transaction boundary. Validate the source row UUID and version captured with its content, as well as the target revision. A changed or already-consumed source must conflict before the operation can commit.
2. Replace the blind `clearAll()` source tombstone with a guarded source mutation that includes content clearing, `merged_into_id`, expiry, and version advancement. Do not record merge attribution with an unguarded identifier-only update.
3. A source conflict must roll back all target item/condition/metadata writes, then recompute the whole merge from fresh snapshots. The loser of two consumers must not copy the same payload a second time.
4. Acquire locks in a consistent deterministic order if using locking. Make repeated calls after successful consumption a no-op. Ensure duplicate-key handling remains usable inside PostgreSQL transactions/savepoints.
5. Apply the storage contract to database and in-memory implementations and every migration entry point. Storage collaborators must use the same connection/transaction as the transfer. Emit `CartMerged` only for the committed transfer.

**Permanent tests:** Source add/update/remove and metadata/condition changes during transfer; two consumers into the same and different targets; target races; source delete/recreate; rollback after target write; repeat after success; owner isolation. Use deterministic interleaving plus independent PostgreSQL connections for the two-source-consumer race.

**Schema:** Existing UUID/version/tombstone fields should suffice. If another field is indispensable, edit the owning carts migration in place. Update cart storage/migration docs.

**Done when:** Every successful source mutation is either transferred coherently or retained; one source revision is consumed at most once.

### CART-05 — P2: Discard mutation results from losing retry attempts

**Source:** `packages/cart/src/Traits/ManagesItems.php:95–101,121–169,186–213`; retry callbacks in `Traits/ManagesStorage.php`.

**Confirmed failure:** Start with A quantity 1. Cart X decrements it by 1 while cart Y increments it between X's snapshot and write. X loses its first write and recomputes correctly: persisted quantity is 1. However, `$removed` from the losing removal attempt remains set, so X emits `ItemRemoved` and emits no `ItemUpdated`. The returned item also comes from the losing attempt. `remove()` additionally ignores `mutateItems()`'s false/no-commit result and can emit a second removal when another writer already removed the item.

**Required fix:** Make result data attempt-local. Reset all callback output references before each attempt, or return the committed mutation result together with its revision from the mutation helper. Dispatch and return only the successful attempt's result. A retry that finds nothing to mutate must not dispatch, clear/destroy, or return an earlier attempt's item. Review every closure capturing output by reference, including condition and buyable operations.

**Permanent tests:** Removal-to-update retry; update-to-removal retry; two removers where the loser becomes a no-op; item absent on retry; exhausted conflicts. Assert persisted data, returned item, exact event type/count, and absence of cleanup for non-committing attempts.

**Done when:** Each successful mutation emits its matching event once; failed/no-op retries emit no mutation event. No schema change is required.

### CART-06 — P1: Guard automatic empty-cart cleanup against newer writes

**Source:** `packages/cart/src/Traits/ManagesItems.php:164–166,215–217,346–356`; `Traits/ManagesInstances.php` destroy/clear; storage deletion/reset contract.

**Confirmed failure:** Remove the last item with `empty_cart_behavior=destroy`. An `ItemRemoved` listener successfully adds a different item before `remove()` returns. Cleanup uses the previous empty collection and calls unguarded `destroy()`, deleting the newly added item and its row. A separate concurrent request can hit the same interval. The `clear` behavior has the analogous risk.

**Required fix:** Treat automatic empty cleanup as part of the mutation's consistency boundary. Either decide and apply cleanup in the same guarded storage operation, or conditionally clean up only if the committed row UUID/version is still unchanged and it remains empty. If a newer write wins, preserve it and skip automatic cleanup. Re-reading `isEmpty()` followed by a blind delete is still a race. Retain explicitly requested `clear()`/`destroy()` semantics separately from automatic cleanup.

**Permanent tests:** Add from an event listener between item commit and cleanup; independent concurrent add; metadata/condition updates; delete/recreate; both destroy and clear modes; preserve mode. Assert the newer state survives and cleanup events reflect actual cleanup.

**Done when:** Automatic empty-cart handling cannot erase a later successful write. Update the storage contract/implementations and docs; a new schema field should not be necessary.

## Orders and products

### ORD-04 — P1: Enforce per-payment refund capacity on every refund path

**Source:** `packages/orders/src/Actions/RegisterOrderRefund.php:121–145,230–245`; `Transitions/RefundProcessed.php`; refund submission/completion and adapters.

**Confirmed failure:** Settle a 10,000 order using two 5,000 payments. `createPending(order, 7000, ...)` succeeds and attaches the entire 7,000 refund to one 5,000 payment. The aggregate order ceiling passes, but the referenced provider payment has insufficient capacity. Cancellation's new allocator does not protect this entry point. Immediate refund processing still selects the first payment too.

**Required fix:**

1. Extract one allocation rule used by cancellation, pending provider refunds, and immediate refunds. Calculate capacity from actual received payments minus their completed and pending refunds. Never infer refundable cash from `grand_total` on an unpaid order.
2. Use a clear public contract. A refund against a specified payment must reject amounts above that payment's capacity. An order-level refund spanning payments must create explicit per-payment allocations/reservations with stable identities. Update return types and all callers directly when adopting multiple rows; do not retain a single-row compatibility facade.
3. Serialize capacity checks and reservations under the order lock with a consistent order/refund/payment lock order. Bound every allocation in both order and payment dimensions, including when another refund completes or fails.
4. Preserve idempotency across retries and provider IDs. Give each allocation the correct payment gateway/currency, and never submit one payment's refund using another payment's provider identity. Review terminal payment status updates so each fully refunded payment is marked consistently.
5. Route cancellation through the shared allocator instead of maintaining two different refund algorithms. Preserve cancellation remainder behavior already covered by its new tests.

**Permanent tests:** Two 5,000 receipts with a 7,000 refund; prior refund on the first receipt; different gateways; pending/failed/completed reservations; retries; specified-payment over-capacity rejection; cancellation after an earlier split refund; provider completion races; never-paid order rejection; exact cached totals and events.

**Schema:** Prefer existing refund/payment identity fields. If a parent request/allocation identity is needed, edit `packages/orders/database/migrations/2000_11_01_000005_create_order_refunds_table.php` in place. Update service contracts, Filament actions, provider integrations, and refund docs together.

**Done when:** Pending plus completed refunds never exceed money received globally or for any referenced payment.

### PROD-04 — P2: Match child ownership to the aggregate parent, including global parents

**Source:** `packages/products/src/Models/Option.php:168–197`; `Models/OptionValue.php`; `Models/AttributeValue.php`; `Models/Product.php:833–843`; `tests/src/Products/OptionParentageIntegrityTest.php`.

**Confirmed failure:** Create a global product. With tenant A active and `include_global=true`, create an option against it. The option becomes tenant-owned. Delete the product in explicit-global context: the option remains with a dangling `product_id` because the global parent's cascade cannot see tenant-owned children. The new parentage test currently accepts this creation, contrary to the original handoff's global aggregate rule.

**Required fix:**

1. Separate global-reference reads from aggregate child writes. A product's option and an option's values must share the exact parent owner tuple, including global-only tuples.
2. Creating children of a global aggregate requires explicit-global context and global child ownership. A tenant with `include_global=true` may read allowed global records but cannot attach its own option/value lineage to them.
3. Apply the same target-lineage rule to attribute values. A permitted global **attribute definition** may still describe a tenant-owned product; that does not permit a tenant-owned value on a global **target**.
4. Enforce the rule regardless of automatic owner assignment settings. Validate explicit submitted owner tuples as well as defaults. Keep existing persisted-parent immutability and owner-disabled existence checks.
5. Replace the permissive test expectations with rejection and add a valid explicit-global creation/deletion case. Do not solve this by making normal cascades scan all tenants; correct the creation boundary.

**Permanent tests:** Global parent under tenant context with include-global on/off; explicit supplied owner tuples; auto-assign on/off; same-owner valid lineage; foreign parent; global definition on tenant target; tenant value on global target rejected; parent deletion leaves no descendant rows created through supported paths.

**Done when:** Supported creation cannot produce a child lineage invisible to its parent's legitimate cascade. No schema change is required.

### PROD-03 — P2: Identify variants by option combination, not generated SKU text

**Source:** `packages/products/src/Strategies/MatrixVariantGenerator.php:89–117`; SKU generation; `Actions/GenerateVariants.php`; variant and option-value association models.

**Confirmed failure:** A product with Color values Black and Blue returns two generated entries but only one unique variant ID and one persisted variant. Both default codes collide, and the generator reuses by SKU without comparing memberships.

**Required fix:** Execute the original PROD-03 specification. Canonicalize combination identity from the product and sorted option-value IDs. Reuse only a variant with exactly that membership. Generate stable distinct default SKUs even when labels/codes collide. An explicitly configured/custom SKU collision must fail clearly and atomically, rather than silently reusing a different combination. Define regeneration behavior after label edits independently of mutable label text, and serialize concurrent generators or enforce a suitable application identity boundary.

**Permanent tests:** Black/Blue; punctuation/code collisions; options with equal value labels; reruns; renamed values; reordered options; changed membership; custom SKU collision; concurrent generators; direct-consumer/owner isolation. Assert exact option membership as well as distinct IDs/counts.

**Schema:** If persisting a combination key, edit `packages/products/database/migrations/2001_01_01_000004_create_product_variants_table.php` in place. Fresh identity starts clean; no legacy combination reconstruction. Update variant configuration and generation docs.

**Done when:** Every returned combination represents its own correct persisted membership, and reruns are idempotent.

## Signals

### SIG-01 — P2: Filter blocked personal-data keys recursively

**Source:** `packages/signals/src/Services/SignalPropertyFilter.php:38–49`; public event ingestion and identify traits.

**Confirmed failure:** With `property_allowlist=['*']`, identifying a user with `traits.context.email` persists the nested email. Arrays pass the top-level check without filtering their contents.

**Required fix:** Execute the original SIG-01 specification. Recursively apply the blocked-key rule to nested maps and arrays within lists. Define allowlist behavior explicitly, preserve list shape, and bound traversal/payload size. Apply the shared filter consistently to untrusted event properties and identity traits. Preserve the intentional trusted server recorder contract; do not accidentally turn browser payload fields into trusted outcomes.

**Permanent tests:** Nested email/phone/name/metadata; mixed map/list nesting; wildcard and explicit allowlists; null/scalar values; maximum nesting; public HTTP ingestion and identify persistence. Assert stored JSON, not just the filter's immediate return value.

**Done when:** Blocked fields cannot survive by nesting them on an untrusted surface. No schema change is required.

### SIG-02 — P2: Preserve currency through ingestion, aggregation, reports, and alerts

**Source:** `packages/signals/src/Services/SignalMetricsAggregator.php:30–59,103–138`; `Actions/IngestSignalEvent.php`; event/property/daily-metric models; dashboard/report/alert services and `filament-signals` consumers.

**Confirmed failure:** A MYR property accepts trusted 10,000-MYR and 10,000-USD outcomes, then produces one `revenue_minor=20000` daily total with no valid single monetary meaning.

**Required fix:** Implement the original chosen contract directly:

1. Each property has one immutable reporting currency. Reject nonzero revenue in a different currency atomically at all ingestion/write boundaries. Browser revenue stays zero.
2. Every daily metric carries its property's explicit currency. Single-property results return amount plus currency. Multi-property money is grouped by currency; replace the old ambiguous scalar contract and update all callers.
3. Keep event/session/conversion counts independent of currency buckets so counts are not duplicated. Keep single and batch rollups equivalent.
4. Revenue alerts must select a currency or reject ambiguous multi-currency rules. Update saved report validation, dashboard trends/summaries, exports, and UI formatting together.
5. Keep `OrderPaid` amounts as actual per-payment receipts, each once. Preserve automatic-listener failure isolation/reporting for a rejected mismatch. Add no exchange-rate conversion or historical currency guessing.

**Permanent tests:** Foreign nonzero revenue rejected without related partial rows; zero browser revenue; immutable property currency; same-currency rollup; two different-currency properties in summaries/trends/reports/alerts; partial order receipts recognized once; batching/counts/tenant isolation.

**Schema:** Edit `packages/signals/database/migrations/2001_01_01_000005_create_signals_daily_metrics_table.php` in place for required currency. Edit property/event owning migrations only if needed. Update Signals and paired adapter/reporting docs with the replacement result contracts.

**Done when:** No supported monetary result silently combines different currencies.

### SIG-03 — P2: Maintain chronological identity observation bounds under races

**Source:** `packages/signals/src/Actions/IdentifySignalIdentity.php:38–67,104–117`; identity resolution from event ingestion.

**Confirmed failure:** Identify an external ID at October 17, then at October 1. Its persisted `last_seen_at` becomes October 1. The first-seen path also preserves the first arrival rather than necessarily the earliest observation.

**Required fix:** Resolve and update under a transaction/row lock. Use the minimum accepted observation for `first_seen_at` and maximum for `last_seen_at`. Apply the same rule to normal existing rows, inserts, duplicate-create recovery, and concurrent requests. Pass event observation time through identity resolution and document one precedence rule. Keep auth-user resolution, owner boundaries, and privacy filtering intact.

**Permanent tests:** Newer then older; older then newer; equal timestamps; trusted ingestion; public identify; concurrent updates; duplicate-create winner recovery; both supported PostgreSQL isolation levels. Assert earliest/latest bounds and retention/activity results.

**Done when:** Accepted late events and concurrent requests cannot shrink identity activity bounds. Existing columns suffice.

## Verification record and required worker gates

### Re-audit evidence

Fourteen focused observed-defect probes reproduced the thirteen findings: guest-source loss and duplicate consumption are two probes for CART-04. Temporary probes were removed after preserving local copies/logs in `/tmp`. The steps above are self-contained; the worker must create permanent regressions and must not depend on temporary files surviving.

The initial default Signals engine run used its documented default port and failed to connect; rerunning with the confirmed local PostgreSQL configuration succeeded. A connection failure is not a product failure or a passed test.

| Check | Result |
| --- | --- |
| Authz package suite | 40 passed |
| Cart package suite | 1,051 passed; 5 skipped: 3 PostgreSQL cases subsequently passed separately, 2 opt-in stress cases not enabled |
| Products package suite | 492 passed |
| Orders package suite | 439 passed; 4 PostgreSQL cases skipped in the default run, then all 4 passed separately on PostgreSQL |
| Checkout package suite | 269 passed |
| Signals package suite | 264 passed; 22 PostgreSQL cases skipped in the default run, then all 22 passed separately on PostgreSQL |
| Cart PostgreSQL engine suite | 3 passed, 95 assertions |
| Orders PostgreSQL engine suite | 4 passed, 250 assertions |
| Signals PostgreSQL engine suite | 22 passed, 679 assertions |
| Changed FilamentCart tests | 26 passed across snapshot cleanup, normalized synchronization, and condition batch removal |
| New FilamentProducts relationship-boundary tests | 2 passed |
| PHPStan level 6 | All six core source packages and `filament-products` passed with the existing repository configuration; `--debug` used |
| Focused observed-defect probes | 14 passed, confirming the 13 open findings rather than their correction |

The six default package runs total **2,555 passed**. All **29 PostgreSQL engine cases passed** when configured explicitly. The two opt-in cart stress cases remain unrun. Adapter checks add 28 passing tests; the 14 audit probes are separate evidence of defects.

Audit logs are local `/tmp/commerce-followup-*-tests.txt`, `/tmp/commerce-followup-*-phpstan.txt`, and `/tmp/commerce-reaudit-*-probes.txt`. The successful Signals engine rerun uses `commerce-followup-signals-engine-configured-tests.txt`. These logs are evidence from this review; the worker must generate new results after implementation.

### Required worker verification

1. Keep the existing tests proving the completed fixes. Add focused regressions before changing behavior and run them red/green. For observed defects, passing an assertion that the bug occurs is evidence only, not acceptance.
2. Every Pest invocation includes `--parallel`, and output is captured with `2>&1 | tee /tmp/<specific-log>.txt`. Use `set -o pipefail`. Run package directories separately; the installed runner accepts one positional path.
3. Run the six scoped suites, changed adapter tests, and affected contract consumers. Analyze each changed source package at PHPStan level 6 with existing ignore configuration; do not suppress new defects. Run Pint only on changed PHP files/packages.
4. Run the PostgreSQL engine suites on dedicated disposable databases, then add engine coverage for the newly identified source-transfer, cleanup, checkout-claim, identity-bound, and refund-allocation races. Existing engine passes cover only their existing assertions.
5. Verify schema changes by creating fresh isolated schemas from the edited owning migrations. Confirm JSON columns use the configured JSON type and no foreign-key constraints/cascades were introduced.
6. Run end-to-end scenarios: owner A/B/global cart switching; guest transfer with a concurrent source write; partial receipts followed by a split refund/cancellation; stale checkout replay; revoked wildcard access; global-parent mutation rejection; nested privacy and mixed-currency reports.

Example command shape:

```sh
set -o pipefail
./vendor/bin/pest --parallel tests/src/Cart 2>&1 | tee /tmp/followup-cart-tests.txt
./vendor/bin/phpstan analyse packages/cart/src --level=6 --no-progress --memory-limit=1G 2>&1 | tee /tmp/followup-cart-phpstan.txt
```

Use PHPStan `--debug` if the environment blocks its parallel socket; this keeps level/scope unchanged. For engine tests, configure the relevant `CART_ENGINE_PGSQL_*`, `ORDERS_ENGINE_PGSQL_*`, and `SIGNALS_ENGINE_PGSQL_*` variables explicitly for the disposable environment. Use only `carteng_`, `ordeng_`, and `sigeng_` database prefixes accepted by the respective test bases. No machine-specific host/user values belong in committed configuration.

### Delivery checklist

- [x] AZ-04: actual configured resolver switches and restores correctly.
- [x] AZ-02: revoked wildcard/super-admin access disappears immediately.
- [x] AZ-03: multiple wildcard segments and separators work.
- [x] CHK-01: gateway attempts are claimed and every result is fenced.
- [x] CART-04: source consumption and target transfer are atomic.
- [x] CART-05: only committed retry results drive returns/events.
- [x] CART-06: empty-cart cleanup preserves newer successful writes.
- [x] ORD-04: every refund path respects payment and order capacity.
- [x] PROD-04: aggregate children share exact parent lineage, including global rows.
- [x] PROD-03: combination identity is independent of colliding SKU labels.
- [x] SIG-01: nested blocked data is removed before persistence.
- [x] SIG-02: monetary results are currency-explicit end to end.
- [x] SIG-03: identity bounds remain chronological under races.
- [x] Previously completed original fixes remain covered and passing.
- [x] New contract callers and canonical docs are updated; migrations are edited in place.
- [x] Package/adapter checks and required real-engine races pass; any unavailable check is identified precisely.
- [x] No compatibility layer, legacy data conversion, incremental migration, unrelated rewrite, or dependency change was introduced.

The final worker report must link implementation and permanent tests for each ID, give actual verification results, and list breaking contracts/schema changes. Do not declare the original audit complete while any of its seven open findings or this follow-up's six additional findings remain unresolved.
