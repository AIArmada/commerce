---
title: Commerce Audit Remediation — Worker Handoff
status: implementation-plan
prepared: 2026-10-17
reviewed_head: 05510acc4a7c6debbb73766bfe11433c8893b891
packages: [authz, cart, products, checkout, orders, signals]
---

# Commerce audit remediation: worker handoff

## 1. Assignment

Implement and verify the 16 defects listed below across `authz`, `cart`, `products`, `checkout`, `orders`, and `signals`. The preceding audits reproduced every defect. This document is the implementation specification; the worker should not need the original chat.

The user requested this handoff document, not implementation in the planning chat. No production code was changed while writing it. A worker executing this plan should implement the fixes locally, retain permanent regression tests, update affected package documentation, and return evidence against every acceptance criterion.

### Binding constraints

- **No backward compatibility.** Replace the incorrect behavior and update every in-repository caller. Remove superseded signatures, aliases, fallback paths, and tests that encode the defects. Do not introduce compatibility switches or keep two implementations.
- **Edit owning migrations in place.** Add necessary columns, tables, or indexes to their owning migration files. Do not create incremental alteration migrations, legacy-data backfills, upgrade adapters, or old-column detection. New fields start with their new meaning.
- Exercise schema changes on disposable test databases. Editing a migration does not authorize rebuilding an application database.
- Follow the repository's PHP 8.5+, Laravel 13, Filament 5, UUID, immutable-date, owner-scoping, and application-integrity contracts. Use no database foreign-key constraints, cascades, or soft deletes.
- Keep core behavior in its core package. Change adapters and consumers only where the new contract requires it; read their contexts first.
- Check `commerce-support` before creating a shared ownership or context helper. Prefer one existing primitive or narrow shared correction over duplicated package-specific checks.
- Preserve unrelated work. Do not change dependencies, publish packages, deploy, or perform destructive Git operations as part of this assignment.

### Read before implementation

1. Root `AGENTS.md`, `CONTEXT-MAP.md`, `.ai/rules/index.md`, and matching rule files; search `.ai/rules` for the subject being changed.
2. Each affected package's `CONTEXT.md`, then `docs/01-overview.md`, `03-configuration.md`, `04-usage.md`, and `99-troubleshooting.md`.
3. `packages/commerce-support/CONTEXT.md` and its ownership/context documentation for boundary changes.
4. The paired Filament context before changing any adapter, form, policy integration, widget, or report consumer.
5. Installed dependency versions before relying on framework/plugin APIs. The audits used PHP 8.5.0, Laravel 13.35.0, Filament 5.9.0, Spatie Permission 8.3.0, and Spatie Model States 2.14.2; verify the current installation.

Source paths below are repository-relative. Resolve symbols in the current checkout rather than relying on historical line numbers.

## 2. Inventory and execution order

| ID | Priority | Package | Defect |
| --- | --- | --- | --- |
| AZ-01 | P1 | authz | Unresolved requested scope becomes a global permission check |
| AZ-02 | P2 | authz | Memoized wildcard grants survive revocation |
| AZ-03 | P2 | authz | Trailing-wildcard shortcut mishandles earlier wildcards |
| CART-01 | P1 | cart | Ambient facade retains a previous owner's storage |
| CART-02 | P1 | cart | Version checking does not protect the original read |
| CART-03 | P2 | cart | Migration identity helpers truncate UUID user IDs |
| PROD-01 | P1 | products | Existing options can be reparented across owners |
| PROD-02 | P2 | products | Attribute values accept another owner's definition |
| PROD-03 | P2 | products | Different option combinations collapse onto one generated SKU |
| ORD-01 | P1 | orders | Child parent references are validated only at creation |
| ORD-02 | P1 | orders | Partial payments start fulfillment and block the remaining payment |
| ORD-03 | P1 | orders | Cancellation reserves the original paid amount again |
| CHK-01 | P1 | checkout | Stale session processing can create a second payment |
| SIG-01 | P2 | signals | Nested personal data bypasses the privacy blocklist |
| SIG-02 | P2 | signals | Revenue totals add minor units from different currencies |
| SIG-03 | P2 | signals | Identify requests move activity timestamps backwards |

Execute in these slices, keeping each slice independently reviewable:

1. AZ-01, CART-01, PROD-01, PROD-02, ORD-01: restore boundaries.
2. CART-02 and CART-03: correct cart persistence and identity.
3. ORD-02 and ORD-03: correct money/lifecycle behavior.
4. CHK-01: serialize payment attempts using the corrected cart/order contracts.
5. PROD-03: make variant identity independent of display names.
6. AZ-02 and AZ-03: correct authorization invalidation and matching.
7. SIG-01, SIG-02, SIG-03: correct privacy and analytics.
8. Run cross-package integration checks, update canonical docs, and complete the delivery checklist.

For each ID: first add a test asserting the **desired** behavior and show it fails on the current implementation; implement the smallest complete fix; then run the regression and related existing tests. Audit probes that asserted the buggy result are evidence only, not suitable permanent assertions.

## 3. Authorization fixes

### AZ-01 — Fail closed when a requested scope cannot resolve

**Observed:** With `authz.scopes.auto_create = false`, passing a persisted model without an `AuthzScope` to `userCanInScope()` resolves to `null`. A user with a global permission is then allowed for that unrelated requested scope. Reproduced with the dedicated `AuthzScopeTeamResolver` enabled.

**Start in:**

- `packages/authz/src/Authz.php`: `withScope()`, `userCanInScope()`, `resetScopeState()`.
- `packages/authz/src/Support/AuthzScopeResolver.php` and `AuthzScopeTeamResolver.php`.
- Existing `tests/src/Authz/Unit/*Scope*Test.php` and authorization tests.

**Required change:**

1. Distinguish an explicitly requested global scope (`null`) from a non-null input that cannot resolve. Invalid types, unsaved models, deleted `AuthzScope` objects, and unresolved model scopes must never select global permissions.
2. Make failed non-null resolution throw a precise domain/authorization exception before the callback executes or the active team changes. Keep the boolean permission helper fail-closed: return `false` for that classified resolution failure, without swallowing database/infrastructure errors or exceptions raised by the callback itself.
3. Keep explicit `withScope(null, ...)` as the deliberate global operation. Keep `userHasPermissionAcrossScopes()` as the explicit cross-scope surface.
4. Restore both the prior scope identity and its override semantics on every exit path, including scope setup/reset failures. Inspect the configured team resolver: Authz-scope IDs and raw owner IDs are different namespaces. Route each supported mode intentionally; never resolve one namespace as the other and fall through to null.
5. Reuse existing context/resolver primitives; no fallback to the authenticated user's default scope when requested resolution fails.

**Regression gate:** Global permission does not satisfy a failed non-null scoped check; valid model and scope-ID checks work; explicit global still works; nested scope calls and throwing callbacks restore entry context. Test both the Authz scope resolver and the owner-context team resolver modes that the package supports.

**Schema:** None expected.

### AZ-02 — Make authorization reflect permission mutations immediately

**Observed:** `Gate::forUser($user)->allows('audit.delete')` stays true after the same user's `audit.*` permission is revoked, even though `hasPermissionTo()` returns false. The package's request-local cache is not invalidated by Spatie mutations.

**Start in:** `Support/WildcardPermissionCache.php`, `Support/UserRoleChecker.php`, `AuthzServiceProvider::registerGateHooks()`, `Authz::clearCache()`, and `Concerns/SyncsRolePermissions.php`.

**Required change:**

1. Prefer removing the package's extra memoization of security verdicts and querying current scoped assignments through the established Spatie APIs. Ensure loaded user/role relations cannot resurrect a revoked grant.
2. If retaining memoization is justified by measured behavior, implement invalidation at the actual assignment/role-permission mutation boundaries, including mutations through Spatie APIs and the core sync trait. Do not require each caller to remember `Authz::clearCache()`.
3. Cover direct grants, role-derived wildcard grants, role removal, permission synchronization, and cached super-admin verdicts. Re-evaluation must also observe newly granted access, not only revocations.
4. Keep caches/context state scoped to the request/job and keyed by the relevant user, guard, and scope. Do not add static mutable counters or globally flush shared permission caches on every check.
5. Preserve the existing documented super-admin assignment semantics; this work fixes stale verdicts, not the separately intentional cross-scope super-admin policy.

**Regression gate:** Check → revoke/sync/remove → check denies in the same request, including two loaded instances of the same user. Check → grant → check allows. Scope A/B decisions remain isolated. Existing explicit cache-clearing and impersonation behavior remain correct under the final API.

**Schema:** None expected.

### AZ-03 — Match every wildcard segment

**Observed:** `*.view.*` does not match `orders.view.own` because the optimized trailing `.*` branch treats the earlier `*` literally.

**Start in:** `Services/WildcardPermissionResolver.php`.

**Required change:** Use one correct matcher or restrict the prefix optimization to patterns with no earlier wildcard. Keep regex metacharacters escaped and define the existing trailing-subtree semantics explicitly. Honor the configured permission separator consistently with `PermissionKeyBuilder`; avoid maintaining incompatible wildcard grammars.

**Regression gate:** Exact names, `*`, `orders.*`, `*.view.*`, `orders.*.own`, multiple wildcard segments, escaped metacharacters, non-matches, and supported non-default separators. Check the matcher through Gate as well as directly. A broader wildcard must not accidentally match unrelated resource prefixes.

**Schema:** None expected.

## 4. Cart fixes

### CART-01 — Resolve ambient carts for the current owner

**Observed:** Resolving the Cart facade in owner A, then entering owner B through `OwnerContext::withOwner()`, retains A's manager/storage. B can read A's items and write into A's cart within the same request/job. This is a same-lifecycle context-switch defect, not a claim that scoped bindings necessarily leak between separate requests.

**Start in:**

- `CartServiceProvider::registerCartManager()`, `registerFactories()`, and `registerStorage()`.
- `CartManager`, `Services/CartFactory`, `Snapshots/CartInstanceManager`.
- `Snapshots/CartSyncManager`, `SyncNormalizedCartJob`, and `NormalizedCartSynchronizer`.

**Required change:**

1. Separate the ambient facade's current-owner resolution from explicitly owner-pinned carts. The ambient manager must select storage using the current resolved or explicit-global context at each public entry point; `forOwner($owner)` must remain deliberately pinned.
2. Keep manager state and caches partitioned by owner identity: current cart, identifier/instance overrides, UUID lookup cache, and associated-model/pipeline state. An A → B → A sequence must restore A's own state without exposing it in B.
3. Fix both the facade and container-resolved `CartManagerInterface` surface. Merely clearing a facade instance in one listener misses direct consumers and nested contexts.
4. Reuse `OwnerScopeKey` and the existing owner resolver. A missing owner still fails closed; explicit global must remain distinct. Support deliberate global cart selection without making `null` an ambient-owner fallback.
5. Make snapshot resolution use an explicitly owner-pinned cart for the snapshot. Snapshot synchronizers must derive and validate ownership against the source cart, rather than silently labeling a cart with a different ambient owner.
6. Revalidate checkout, webhook, migration, and queued snapshot paths that resolve carts after changing context. Returned concrete carts may retain their explicitly selected owner; the default ambient manager must not silently retain an old selection.

**Regression gate:** In one lifecycle, A → B → A and A → explicit global → A isolate reads/writes, same identifiers, current instance/identifier state, and `getById()` results. Run the tests after the facade has already been resolved. Include a snapshot job/resolution path and nested owner contexts. No cache entry from one owner is returned in another.

**Schema:** None expected.

### CART-02 — Protect the complete cart read/modify/write operation

**Observed:** Two interleaved normal `add()` calls both succeed, but one item disappears. `performCasUpdate()` reads the current version at write time, so its compare-and-swap does not cover the stale content read that produced the write payload. Retrying the same payload can overwrite a newer successful mutation again.

**Start in:** `Storage/StorageInterface.php`, `Storage/DatabaseStorage.php`, `Testing/InMemoryStorage.php`, `Traits/ManagesItems.php`, `ManagesStorage.php`, `ManagesConditions.php`, `ManagesMetadata.php`, and the migration actions.

**Required change:**

1. Introduce a single coherent mutation boundary. Either read a complete versioned cart snapshot atomically and require its expected version on replacement, or run the read and mutation inside a storage-owned locked transaction. Select one primary contract and update callers together.
2. With an expected-version design, return the revision with the items/conditions/metadata snapshot used for computation. A separate later `getVersion()` read does not establish the required guarantee.
3. A conflict must reject the stale replacement. Bounded retries may rerun the entire domain mutation against fresh content; never retry the same precomputed stale array.
4. Cover items, conditions, metadata, combined writes, clears, first creation, guest merges, and identifier swaps where they perform read/modify/write. Keep one documented locking order for operations touching two carts.
5. Preserve atomic creation using the existing owner/identifier/instance uniqueness identity. Distinguish a missing cart from a stale cart that was deleted/recreated; an old snapshot must not overwrite a replacement row with a new UUID.
6. Update the in-memory implementation and test doubles to enforce the same contract. Emit mutation events and snapshot synchronization only for the successful committed mutation, not each failed retry.
7. Make required revisions/mutation arguments explicit. Do not keep a blind-write compatibility overload or optional revision that bypasses the new invariant.

**Regression gate:** Deterministically interleave real `Cart::add()`/`update()` calls between read and write. Either retain both successful additions or reject/recompute one; never silently lose one. Test same-item increments, item/metadata coexistence, concurrent first creation, deletion/recreation, and guest merges. Add at least one real supported database-engine race test; sequential tests labeled “concurrent” are insufficient.

**Schema:** Existing `carts.version` and owner uniqueness may suffice. Edit `packages/cart/database/migrations/2000_02_01_000001_create_carts_table.php` only if additional identity/index changes are necessary.

### CART-03 — Preserve UUID identifiers in migration helpers

**Observed:** Two UUID users beginning `019a1234-...` both resolve to `"19"` through `CartMigrationService::getCurrentIdentifier()`. Other public identity/swap helpers accept only integer user IDs.

**Start in:** `Services/CartMigrationService.php`, login/migration actions/listeners, and their callers.

**Required change:** Accept the repository's scalar user-key contract consistently, preserve UUID strings byte-for-byte, remove integer coercion, and use explicit null/empty checks instead of truthiness. Update `getIdentifier()`, `getCurrentIdentifier()`, `getUserIdentifier()`, and `swapGuestCartToUser()` together. Use the same identifier resolution as the main cart manager rather than maintaining diverging rules. An authenticated user with an invalid/missing ID must fail clearly, not inherit an unrelated guest or truncated numeric identity.

**Regression gate:** Distinct UUIDs with the same prefix remain distinct; a UUID beginning with letters is preserved; guest session identity works; login migration targets the exact UUID. Supported scalar IDs remain deterministic without an integer-only branch or legacy adapter.

**Schema:** None expected; cart identifiers are already strings.

## 5. Product/catalog fixes

### PROD-01 — Keep catalog parentage immutable

**Observed:** An owned `Option` can update `product_id` to another owner's product while retaining its original owner. The parent guard runs only in `creating`. `Variant` already provides a useful parent-immutability precedent.

**Start in:** `Models/Option.php`, `OptionValue.php`, `Variant.php`, and the relevant relation managers/actions.

**Required change:** Make persisted `Option.product_id` and `OptionValue.option_id` immutable, regardless of whether owner enforcement is enabled. Reject before side effects. Validate the referenced parent and exact lineage at creation using shared owner write primitives. Preserve intentional global-read rules without allowing tenant-owned children to mutate global parent aggregates. Update in-repository callers to create a new record when identity/parentage changes; do not add a reparenting escape hatch.

**Regression gate:** Cross-owner and same-owner reparent attempts fail; nonexistent parents fail; normal value/name edits succeed; direct models, relation creates, and actions obey the same rule. Test explicit-global and missing-context behavior. Rejection leaves owner tuples, old-parent children, option pivots, and counts unchanged.

**Schema:** No new foreign-key constraints. Existing UUID parent columns should suffice.

### PROD-02 — Validate attribute definitions and target lineage

**Observed:** A value attached to owner A's product accepts owner B's `attribute_id`. The value saves, but the owner-scoped `attribute` relation resolves to null.

**Start in:** `Models/AttributeValue.php`, `Traits/HasAttributes.php`, `Actions/ApplyAttributeChanges.php`, and attribute editing consumers.

**Required change:**

1. Validate both ends: the definition exists and is allowed for the target's owner, and the attributable product/variant exists in the allowed owner context. Owner validity on the value row alone is insufficient.
2. Keep persisted `attribute_id` and the attributable type/ID tuple immutable. Revalidate references on saves that can affect them; direct model writes must not bypass the action's checks.
3. Keep the attributable type allowlist and morph-map handling. Validate existence even when owner scoping is disabled. Use the repository's explicit include-global policy consistently; do not permit an accidental global fallback.
4. Reject invalid references before serializing/casting values or creating rows. Keep existing locale uniqueness and typed-value behavior.
5. Check sibling catalog relationship write paths exposed by these fixes for the same missing inbound-ID validation, particularly any edited `sync()`/`attach()` calls. Implement server-side checks at the owning write boundary rather than relying on form options or scoped relationship reads.

**Regression gate:** Foreign/missing definitions, foreign/missing targets, forged morph types, and persisted identity edits fail without partial writes. Valid same-owner and deliberately permitted global definitions work. Typed values and locale isolation still work through direct models and canonical actions.

**Schema:** Existing `2001_01_01_000012_create_product_attribute_values_table.php` owns value identity indexes if a change is necessary; retain clean null-locale uniqueness semantics.

### PROD-03 — Separate option-combination identity from generated SKU text

**Observed:** Default two-character option codes make “Black” and “Blue” collide. The second combination reuses the first variant by SKU, and the returned two-element collection contains the same variant ID twice.

**Start in:** `Strategies/MatrixVariantGenerator.php`, `Models/Variant.php`, `Models/OptionValue.php`, `Jobs/GenerateVariantsJob.php`, variant migrations, and generation tests.

**Required change:**

1. Define a deterministic combination key from the product ID and sorted option/value IDs. Labels and mutable positions must not be the identity.
2. Reuse an existing variant only when its actual combination matches. A matching SKU alone is not proof of identity. Reconcile manually created variants from their option-value membership or fail with an explicit collision; do not silently attach a different combination.
3. Generate a unique default SKU using a readable label plus a stable identity suffix, or another deterministic collision-free representation. Keep the default within column limits. Custom patterns that yield collisions must fail explicitly rather than collapse combinations.
4. Persist a combination key if needed and enforce uniqueness per product with the appropriate nullable semantics for non-generated variants. No fallback that interprets an old SKU as a combination key.
5. Keep sync and queued generation on the same implementation. Handle concurrent generation/retries with the database identity, preserving accurate `VariantsGenerated` contents. Every returned entry represents one distinct requested combination.

**Regression gate:** Black/Blue, renamed values, reordered options, repeated generation, two products, two owners, custom-pattern collisions, and concurrent generation. The generated count equals the matrix count; returned IDs and pivot combinations are distinct and correct. Retain cap/queue-threshold tests.

**Schema:** Edit `packages/products/database/migrations/2001_01_01_000004_create_product_variants_table.php` for a combination key/index if required. No backfill or old-SKU interpretation.

## 6. Order fixes

### ORD-01 — Enforce child reference integrity on updates

**Observed:** An order item belonging to tenant A can change `order_id` to tenant B's order because the parent check runs only during creation. The child retains A's owner tuple. Parent moves also threaten the cached payment/refund totals.

**Start in:** `Models/OrderItem.php`, `OrderNote.php`, `OrderPayment.php`, `OrderRefund.php`, and `Actions/Concerns/AssertsOrderOwnerBoundary.php`.

**Required change:**

1. Make persisted child `order_id` immutable for all four child types. Keep owner tuples immutable through `HasOwner`. Cover direct attribute assignment plus `save()`, not only mass assignment.
2. Validate parent existence and owner-safe access before creation; retain existence validation in owner-disabled mode.
3. For refunds, validate that non-null `payment_id` identifies a payment on the same order and owner boundary. Keep that association immutable once persisted. Provider transaction IDs may still change through the classified pending-refund completion operation.
4. Reuse shared guards; factor a package-local child-lineage helper only if the repeated order-specific rule warrants it. Cached totals must not change when validation fails.
5. Update any related form/action callers affected by immutable references. Preserve supported status/amount changes and their atomic cached-total accounting.

**Regression gate:** Every child type rejects same-owner and cross-owner parent moves; refunds reject payments from another order, including another order of the same owner. Missing references fail. Valid creates and supported updates succeed. Verify paid/refunded/pending totals on the original and attempted target orders.

**Schema:** No foreign keys or cascades. Existing parent columns suffice.

### ORD-02 — Fulfill only once the order is fully paid

**Observed:** A 1,000 payment against a 10,000 order transitions to `Processing` and stages fulfillment with 9,000 still due. A subsequent 9,000 payment fails on `Processing → Processing`.

**Chosen contract:** Support partial payments. An order remains `PendingPayment` until its accepted completed payments cover the stored order total. Record actual money received on each payment; enter fulfillment exactly once when the balance reaches zero.

**Start in:** `Transitions/PaymentConfirmed.php`, `Actions/RegisterOrderPayment.php`, `Models/Order.php`, `OrderPayment.php`, `Support/OrderOutbox.php`, `States/OrderStatus.php`, and payment event consumers.

**Required change:**

1. Lock/reload the order, resolve payment idempotency, validate amount/state, and persist the payment atomically. Keep the outstanding-balance ceiling and mismatched replay rejection.
2. After refreshing cached paid totals, transition to `Processing` and stamp `paid_at` only on the first fully-paid transition. Earlier partial payments retain their own payment timestamps without marking the order fully paid.
3. Stage/dispatch `OrderProcessingStarted` and `OrderFulfillmentRequired` only on that transition. Partial payments and idempotent replays must not create extra outbox rows or fulfillment effects.
4. Keep `OrderPaid` tied to each newly accepted payment with its actual amount, so Signals records every receipt exactly once. Inspect invoice/notification/commission/inventory listeners: full-order fulfillment and full-payment claims must wait for full settlement; payment acknowledgements can describe the actual partial payment.
5. Validate current lifecycle under the lock. Do not accept new payment identities on canceled, fraud, refunded, or otherwise non-payable orders merely because a balance field is positive. Keep the existing free-order confirmation path separate.
6. In checkout's `CreateOrderStep`, reconcile the paid amount/currency against the actual created/reused order as well as the checkout session. A live-cart reprice must not turn checkout's “fully paid” completion into an underpaid fulfilled order.

**Regression gate:** 1,000 + 9,000 pays a 10,000 order with one processing/fulfillment transition; 9,001 final payment fails; transaction replays remain idempotent; simultaneous final payments cannot overpay or double-fulfill. Test actual committed outbox/events, not only in-memory state. Recheck free orders, live-cart price/currency drift, and per-payment Signals revenue.

**Schema:** None expected. If new lifecycle fields are justified, edit the owning order/payment migrations directly.

### ORD-03 — Cancel using the remaining refundable balance

**Observed:** After a settled refund of 3,000 on a 10,000 payment, cancellation creates another pending refund for 10,000, producing 13,000 of settled plus reserved refunds.

**Start in:** `Transitions/OrderCanceled.php`, `Actions/RegisterOrderRefund.php`, `Transitions/RefundProcessed.php`, `RefundCompleted.php`, and `Models/Order::getRemainingRefundable()`.

**Required change:**

1. Under the order lock, calculate paid minus settled refunds minus pending reservations. Create cancellation refund reservations only for the positive remainder. Trigger this from actual received money, not an `isPaid()`/`paid_at` guard: after ORD-02 a partially paid order still needs its received money refunded on cancellation. A never-paid order must not use the order-total fallback as refundable cash.
2. Route cancellation reservations through the same validation/accounting boundary used by ordinary pending refunds. Do not maintain a second direct-create path with a weaker ceiling.
3. Because ORD-02 supports multiple payments, allocate refunds against actual remaining refundable payments. Do not reserve the entire order balance against an arbitrary first payment. Reuse allocation primitives/contracts already present in `RefundAllocationValidator` and payment consumers where applicable.
4. Define a stable cancellation reservation identity so retries do not reserve twice. Keep provider submission claims and terminal completion/failure semantics intact.
5. Use a consistent order/refund/payment lock order, and ensure cancellation still suppresses pending fulfillment in the same transaction.

**Regression gate:** With 10,000 paid and 3,000 settled, cancellation reserves 7,000; with another 2,000 pending, it reserves 5,000; no remainder creates no new refund. Multiple payments stay within each payment's remaining balance. Retried/concurrent cancellation and refund completion cannot exceed total paid or double-reserve.

**Schema:** Reuse refund identity where possible. Edit `2000_11_01_000005_create_order_refunds_table.php` in place if a reservation identity/index is needed. Add no data-reinterpretation logic.

## 7. Checkout fix

### CHK-01 — Claim payment attempts before gateway I/O

**Observed:** A stale `CheckoutSession` object still in `Pending` can process after the persisted row has completed. The pre-lock transition overwrites `Completed` with `Processing`; payment executes again. Two payment creations for one session were reproduced.

**Start in:** `Services/CheckoutService.php`, `Steps/ProcessPaymentStep.php`, `Services/StepExecutor.php`, `Actions/HandleCheckoutPaymentCallback.php`, `ProcessCheckoutPaymentNotification.php`, `Models/CheckoutSession.php`, payment processors, and `Support/ChipPurchasePayloadBuilder.php`.

**Required change:**

1. Move authoritative lifecycle checks, transformations that persist data, transition to processing, and payment-step decisions behind a row lock and refresh. Never write state from the stale caller before inspecting the persisted row.
2. Claim one active payment attempt atomically before releasing the transaction. The existing monotonic `payment_attempts` can serve as a generation/fence if used consistently. Move its increment into the claim; the step must not increment it again.
3. Completed sessions return their persisted successful result without another gateway call. Canceled/expired sessions reject processing without compensation or reopening. Already pending/in-flight attempts return the existing redirect/processing result, rather than creating a new attempt.
4. Freeze the attempt identity, amount, currency, gateway/provider, and idempotency key for the external call. Carry this identity through the processor contract and supported gateway metadata. Keep namespaced `chk_<session-uuid>` references and trusted gateway selection.
5. After I/O, lock/reload and persist the result only if that attempt still owns completion. A stale attempt must not overwrite a callback's terminal result, another attempt's payment ID, or current payment evidence.
6. Apply the same claim/fence rules to `retryPayment()` and callback completion. Retry must be an explicit transition from a verified retryable attempt; unknown gateway outcomes are reconciled using the same idempotency identity, not treated automatically as permission to charge again.
7. Keep gateway HTTP outside database transactions. A callback arriving before the create response, and a worker failure after gateway acceptance, need deterministic reconciliation. Verify which identity evidence each installed gateway can return before choosing its implementation.
8. Route superseded/late payment results through existing compensation primitives deliberately. A caller that merely discovers an already-completed session must not refund the winning payment. Do not let an old attempt compensate or mutate the current attempt.

**Regression gate:** Stale pending model after completion produces no second create; two simultaneous process calls create one active attempt; duplicate redirects/retries are stable; callback during gateway I/O wins safely; delayed older results cannot regress state; unknown-outcome retry reuses the frozen identity; free checkout still completes without a payment. Include real row-lock concurrency coverage and gateway mocks that assert exact create/void/refund counts.

**Schema:** If existing attempt/state fields cannot express the claim safely, add the minimum durable fields to `packages/checkout/database/migrations/2024_01_01_000001_create_checkout_sessions_table.php`. Do not encode an operational lifecycle only in an ambiguous JSON boolean. New fields have clean semantics and immutable timestamp casts.

## 8. Signals fixes

### SIG-01 — Apply the personal-data blocklist recursively

**Observed:** With an allowed container or wildcard allowlist, `traits.context.email` and `traits.context.phone` persist. The filter checks only top-level keys.

**Start in:** `Services/SignalPropertyFilter.php`, `Actions/IdentifySignalIdentity.php`, browser event/pageview ingestion, and privacy tests.

**Required change:** Traverse accepted nested arrays and list elements, removing blocked personal-data keys at every depth. Apply the same rule to traits and untrusted event properties. Keep allowlist semantics explicit: an allowed container does not exempt its descendants from the blocklist. Preserve permitted scalar values, nulls, and list shape deliberately; remove empty containers consistently. Reuse the existing HTTP depth/key/byte bounds and define bounded behavior for direct action calls. Keep trusted commerce recording on its explicitly documented trust contract rather than silently broadening this change to every source.

**Regression gate:** Top-level, nested object, list-of-objects, and mixed-depth blocked keys are removed; allowed values survive; wildcard never overrides the blocklist. Verify persisted identity traits and event properties through public endpoints and direct actions. No nested data survives solely because its parent key was allowed.

**Schema:** None expected.

### SIG-02 — Make every monetary aggregate currency-explicit

**Observed:** Trusted MYR 10,000 and USD 10,000 events for one property become a single 20,000 daily revenue total. Dashboard/report/alert sums can also combine different properties' currencies.

**Chosen contract:** Each tracked property has one immutable reporting currency. Nonzero recognized revenue must use that currency. Cross-property outputs group monetary values by currency; there is no automatic FX conversion or undifferentiated mixed-currency scalar.

**Start in:**

- `Actions/IngestSignalEvent.php`, `IngestTrustedSignalOutcome.php`, `Models/SignalEvent.php`, `TrackedProperty.php`.
- `Services/SignalMetricsAggregator.php`, `SignalsDashboardService.php`, `SignalAlertEvaluator.php`, revenue-bearing report services and saved definitions.
- Trusted order/outcome recorders and `filament-signals` consumers of monetary results.

**Required change:**

1. Normalize/validate the property and event currency. Reject nonzero revenue in another currency before any identity/session/event writes. Apply the invariant on direct model writes as well as action/HTTP paths; owner-safe property lookup remains mandatory.
2. Make property currency immutable after creation so historical amounts cannot be relabeled by editing configuration. Keep zero-revenue event handling explicit: it must not contribute foreign monetary amounts.
3. Store the reporting currency on newly produced daily metric rows and update both single-date and batched aggregation. Keep one row per property/date; do not duplicate non-monetary counts into per-currency rows.
4. For single-property outputs, return the amount with its currency. For multi-property outputs, return a currency-keyed monetary result and update every consumer. Replace the old scalar contract; do not expose both scalar and grouped versions for compatibility.
5. Revenue alerts spanning properties must select an explicit currency or reject an ambiguous monetary rule. Keep event/conversion counts independent of currency selection. Update saved report validation and UI formatting accordingly.
6. Preserve the current financial semantics: actual `order.paid` amounts recognize revenue; checkout snapshots and refunds do not become new positive revenue. A strict recorder mismatch should follow the existing automatic-listener failure reporting/isolation contract.

**Regression gate:** A foreign nonzero amount is rejected atomically; browser revenue remains zero; MYR-only sums remain correct. Two different-currency properties produce separate MYR/USD totals across dashboard trends, summaries, reports, and alerts. Property currency edits fail. Batched and single-property rollups agree and retain correct counts and tenant isolation.

**Schema:** Edit `packages/signals/database/migrations/2001_01_01_000005_create_signals_daily_metrics_table.php` for an explicit required reporting currency. Edit the owning property/event migration only if another schema change is necessary. No historical currency guessing or backfill; the new reporting contract starts clean.

### SIG-03 — Keep identity activity bounds chronological

**Observed:** Identifying the same person at October 17 and then with an October 1 `seen_at` replaces `last_seen_at` with October 1, distorting active-user and retention reports.

**Start in:** `Actions/IdentifySignalIdentity.php`, duplicate-key winner recovery, and identity/retention tests.

**Required change:** Resolve and serialize the identity update in a transaction. Set `first_seen_at` to the earliest accepted observation and `last_seen_at` to the latest; do not compute these from an unlocked stale instance. Apply the same rule to existing identities, new inserts, duplicate-create recovery, and concurrent requests. Preserve current privacy filtering and identity-reference constraints. Explicitly carry event observation time into identity resolution where event ingestion currently omits it; use one documented time precedence instead of divergent clocks.

**Regression gate:** Newer then older, older then newer, repeated timestamps, concurrent updates, and duplicate-create races maintain `first_seen_at <= last_seen_at`, preserve earliest/latest bounds, and do not regress active-user/retention results. Include trusted event ingestion and public identify paths.

**Schema:** Existing timestamps suffice; no history reinterpretation/backfill.

## 9. Verification and integration gates

### Baseline from the audits

| Package | Existing tests passed | Skipped |
| --- | ---: | ---: |
| checkout | 266 | 0 |
| orders | 405 | 0 |
| signals | 264 | 22 PostgreSQL engine/concurrency tests without their disposable database configuration |
| authz | 27 | 0 |
| cart | 1,000 | 2 opt-in stress tests |
| products | 477 | 0 |

All six source packages passed the repository-configured PHPStan level 6 analysis. These are historical results, not implementation acceptance. The audits used 16 focused reproductions; they were removed from the repository afterward. Temporary copies/logs may exist in the original machine's `/tmp`, but this plan deliberately does not depend on those files.

### Required checks during implementation

- Run each new regression before and after its fix. Keep it in the appropriate existing test directory, reusing existing fixtures and owner-scoping contract suites where practical.
- Every Pest/PHPUnit invocation must include `--parallel` and log output with `2>&1 | tee /tmp/<specific-log>.txt`. Use `set -o pipefail` so a failed runner is not reported as successful because `tee` succeeded.
- The installed parallel runner accepts one positional path. Run package directories separately; do not pass six independent paths to one parallel command.
- Use meaningful row-lock races on a disposable supported database for checkout attempt claims, order settlement/refunds, cart writes, and identity timestamps. SQLite plus deterministic interleaving proves the original bug but does not prove database-engine locking correctness.
- For Signals' existing engine suite, follow `packages/signals/docs/99-troubleshooting.md`: use only its disposable `sigeng_`-prefixed database configuration. Report skipped/unavailable engine tests explicitly.
- Run Pint only on changed PHP files or affected packages. Run PHPStan per affected source package. Keep all existing ignore lists unchanged unless separately justified by a root-cause analysis.

Example package sweep, run once for each affected package after its focused regressions pass:

```sh
set -o pipefail
./vendor/bin/pest --parallel tests/src/Authz 2>&1 | tee /tmp/audit-fix-authz-tests.txt
./vendor/bin/pest --parallel tests/src/Cart 2>&1 | tee /tmp/audit-fix-cart-tests.txt
./vendor/bin/pest --parallel tests/src/Products 2>&1 | tee /tmp/audit-fix-products-tests.txt
./vendor/bin/pest --parallel tests/src/Orders 2>&1 | tee /tmp/audit-fix-orders-tests.txt
./vendor/bin/pest --parallel tests/src/Checkout 2>&1 | tee /tmp/audit-fix-checkout-tests.txt
./vendor/bin/pest --parallel tests/src/Signals 2>&1 | tee /tmp/audit-fix-signals-tests.txt
```

Run the relevant paired-adapter tests and direct consumers when changing contracts. Search all callers of `StorageInterface` mutations, `CartManagerInterface`, currency-bearing report results, payment events, custom attributes, and generated variant identity. Package-local green tests are insufficient when a changed public signature is still called elsewhere.

For static analysis, substitute the affected package:

```sh
set -o pipefail
./vendor/bin/phpstan analyse packages/cart/src --level=6 --no-progress --memory-limit=1G 2>&1 | tee /tmp/audit-fix-cart-phpstan.txt
```

If the environment prevents PHPStan from opening its local parallel socket, rerun with `--debug` to disable its parallel execution. Keep the same package scope and level; do not suppress the error or claim a blocked run passed.

### Cross-package acceptance scenarios

1. One request processes owner A, owner B, and an explicit-global cart in sequence; each checkout/order/snapshot keeps the correct owner and UUID identifier.
2. Two cart changes race; checkout starts from the accepted cart revision and cannot erase a successful item mutation.
3. A positive checkout completes once; a stale process call and duplicate webhook create no additional payment, order, stock deduction, commission, or revenue event.
4. A partially paid order stays pending; the final accepted payment starts fulfillment once. Signals recognizes the two actual payments, each exactly once.
5. A partially refunded order is canceled; settled plus pending refunds never exceed paid money, globally or per payment.
6. A forged order/product/attribute reference is rejected at the server boundary through direct models and UI actions, without changing rows or cached totals.
7. Nested personal data is removed before persistence; backdated observations cannot shrink identity activity bounds; different currencies never become one money scalar.
8. Revoked wildcard access immediately disappears, unresolved scopes cannot access globals, and supported wildcard patterns authorize exactly their intended abilities.

### Documentation and breaking changes

Update the owning package's canonical docs in the same pass as behavior/API/config changes. Document new invariants and copy-paste-ready examples, especially cart mutation contracts, partial payment lifecycle, refund allocation, scope-resolution failures, variant identity, and Signals currency results.

Explicitly state that callers must adopt the new APIs/schema. Describe the source/configuration changes consumers need; provide no compatibility adapters, legacy aliases, historical-data conversion, or incremental schema migration sequence. If a clean schema is required for testing or installation, say so without executing a destructive rebuild against an application database.

## 10. Completion checklist for the worker

- [ ] AZ-01: unresolved scope fails closed; context restoration is proven.
- [ ] AZ-02: grants/revocations are reflected immediately through Gate.
- [ ] AZ-03: multiple wildcard segments and separators work correctly.
- [ ] CART-01: ambient and explicitly pinned owner behavior is isolated.
- [ ] CART-02: stale read/modify/write cannot silently lose mutations.
- [ ] CART-03: UUID user identifiers survive every migration helper.
- [ ] PROD-01: option/value parentage cannot be reassigned.
- [ ] PROD-02: attribute definitions and attributable lineage are validated.
- [ ] PROD-03: each combination has a distinct, stable identity.
- [ ] ORD-01: all child parent/payment references obey integrity rules.
- [ ] ORD-02: partial receipts remain pending; full settlement fulfills once.
- [ ] ORD-03: cancellation reserves only the remaining refundable balance.
- [ ] CHK-01: stale/concurrent processing cannot create a second active charge.
- [ ] SIG-01: personal-data filtering is recursive on untrusted surfaces.
- [ ] SIG-02: every monetary result is currency-explicit.
- [ ] SIG-03: identity observation bounds remain chronological under races.
- [ ] Changed migrations were edited in place and verified on disposable schemas.
- [ ] All changed contracts have updated callers and canonical docs.
- [ ] Focused tests, package sweeps, relevant adapter tests, Pint, and PHPStan pass.
- [ ] Real-engine checks and any unavailable/skipped verification are identified precisely.
- [ ] No backward-compatibility paths, backfills, dependency changes, unrelated edits, or machine-specific tracked paths were introduced.

The final worker report must map each ID to its implementation and permanent regression test, list the actual verification results, identify any unresolved limitation, and call out every breaking API/schema change. A completed plan means all 16 IDs are addressed; do not mark an untested workaround or an unimplemented finding complete.
