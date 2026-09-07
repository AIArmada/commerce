# Cashier Audit

## Packages Reviewed
- `aiarmada/cashier` (`packages/cashier`): `src/` (Actions, Cashier.php, Checkout/CartCheckoutBuilder, Concerns, Console/Commands, Contracts, Events, Exceptions, Facades, GatewayManager.php, Gateways/AbstractGateway + Chip/* + Stripe/*, Models, Support), `config/cashier.php`, `routes/web.php`, `composer.json`, `src/CashierServiceProvider.php`, `CONTEXT.md`/`README.md`/`docs/` (8 docs)
- `aiarmada/filament-cashier` (`packages/filament-cashier`): `src/` (Components, CustomerPortal, Pages, Policies, Resources, Support, Widgets), `config/filament-cashier.php`, `resources/views + lang/en + lang/ms`, `composer.json`, `CONTEXT.md`/`README.md`/`docs/`
- No `tests/` in either package (verified). No `database/migrations/` in `cashier` (by design — see below).

## Overall Assessment
- Quality: Mixed. The multi-gateway abstraction shape (contracts + `GatewayManager` + per-gateway adapters) is sound and Octane handling in `Cashier.php` (`rememberOctaneDefaults`/`restoreOctaneDefaults` + `RequestReceived` listener) is exemplary. But the package is a leaky adapter stack: `ChipGateway` reimplements what `cashier-chip`/`chip` already do, webhook handling is a stub, the Filament layer paginates in-memory DTOs disguised as Eloquent models, and `Cashier::supportedGateways()` contradicts `GatewayManager::supportedGateways()`.
- Health: Fair. Core billing reads work, but payment-critical paths (webhook verify/handle, subscription retrieval scoping, currency formatting) each carry a High finding. Zero tests.
- Risks: Cross-tenant subscription read in `ChipGateway::retrieveSubscription()`; wrong-key webhook verification in `ChipGateway::verifyWebhookSignature()`; `cashier_unified_*` tables referenced by models that ship no migrations.
- Refactor size: Medium. ~10–14 files. No migration required (and none should be added — see below).

## Migration Impact
**Migration Required: NO**
`cashier` ships no migrations by design ("No tables are created — subscriptions are stored in the respective gateway package's tables"). That stays. `UnifiedInvoiceRecord`/`UnifiedSubscriptionRecord` point at `cashier_unified_invoices` / `cashier_unified_subscriptions` but those tables must NOT be created — the models must stop masquerading as persisted rows (see A-1) instead of gaining migrations.

| Table | Change | Detail |
|---|---|---|
| `cashier_unified_invoices`, `cashier_unified_subscriptions` | none (no migration) | config keys removed with the models — no tables created, no data migration |
| stripe tables (`subscriptions`, etc.) | none | owned by `laravel/cashier`; loaded via `ConditionalMigrationLoader` fallback only |
| chip tables | none | owned by `cashier-chip` / `chip` |

## Package Responsibilities
- Owns: unified billing facade (`Cashier`, `GatewayManager`, `GatewayContract` + 12 contracts), Stripe + CHIP gateway adapters, 5 thin Actions (`CreatePayment`, `CreateSubscription`, `CancelSubscription`, `RefundPayment`, `SyncWebhook`), cart integration (`CartIntegrationRegistrar`, `CartManagerWithPayment`, `GatewayDetector`), in-memory unified invoice/subscription views, customer portal + admin Filament surface.
- Must NOT own: subscription persistence (belongs to `laravel/cashier` for Stripe, `cashier-chip` for CHIP), CHIP HTTP/webhook verification (belongs to `chip`), renewal scheduling (belongs to `cashier-chip`).

## Architecture Findings
### A-1 Unified records are fake Eloquent models with no tables
- Severity: High
- Location: `packages/cashier/src/Models/UnifiedInvoiceRecord.php`, `packages/cashier/src/Models/UnifiedSubscriptionRecord.php` (`use HasUuids`, `getTable()` → `cashier_unified_*`, `$timestamps=false`), `packages/filament-cashier/src/Resources/UnifiedSubscriptionResource/Pages/ListSubscriptions.php::mapUnifiedSubscription()` (`new UnifiedSubscriptionRecord` + fill), `.../UnifiedInvoiceResource/Pages/ListInvoices.php`, `packages/cashier/src/Support/UnifiedInvoice.php`, `packages/cashier/src/Support/UnifiedSubscription.php`
- Problem: Filament builds "records" via `new UnifiedSubscriptionRecord` in PHP and paginates in-memory collections, while the model class claims a table that has no migration. Every list page loads ALL gateway subscriptions/invoices into memory, maps them, then filters/sorts in PHP (`ListSubscriptions.php:86-226`). Repo-wide grep confirms zero usages outside `cashier`/`filament-cashier` — nothing else depends on these tables, so nothing breaks by fixing it.
- Why It Matters: O(N) gateway fan-out per admin page view (Stripe API + CHIP local queries), no DB pagination, memory blowup, filter/sort drift between the two gateways; operators mistake DTOs for persisted rows.
- Recommended Fix: Convert `UnifiedInvoiceRecord`/`UnifiedSubscriptionRecord` to plain (non-Eloquent) readonly DTOs (or `spatie/laravel-data` like `shipping`/`tax`), rewrite the two List pages to query per-gateway sources with explicit pagination limits; delete `getTable()`/`HasUuids` from both.
- Breaking Change: YES (model class → DTO; any `UnifiedSubscriptionRecord::query()` callers break — none found outside these two pages)
- Affected Packages: `cashier`, `filament-cashier`
- Required Dependent Changes: Rewrite `ListSubscriptions.php`, `ListInvoices.php`, `InvoicesTable.php`, `SubscriptionsTable.php`, `CustomerSubscriptionsQuery.php`, widgets reading these types.
- Migration Required: NO (explicitly: create no tables)

### A-2 `Cashier::supportedGateways()` contradicts the manager
- Severity: High
- Location: `packages/cashier/src/Cashier.php::availableGateways()` (`array_keys(config('cashier.gateways'))`), `::supportedGateways()` (alias of available), vs `packages/cashier/src/GatewayManager.php::supportedGateways()` (filters out `stripe` when `Laravel\Cashier\Cashier` is missing — verified import `use Laravel\Cashier\Cashier;` + `class_exists` guard) + `::supportsGateway()`
- Problem: `Cashier::supportedGateways()` advertises `stripe` even when the Stripe SDK is absent; `createStripeDriver()` then throws `GatewayNotFoundException`. `checkout` (`RegisterBuiltInPaymentProcessors`, `CashierProcessor`) and Filament gateway pages branch on the wrong method and take the failing path at runtime instead of degrading.
- Why It Matters: Misleading capability advertisement → runtime exception on the payment path instead of graceful fallback to CHIP.
- Recommended Fix: Make `Cashier::availableGateways()`/`supportedGateways()` delegate to `GatewayManager::supportedGateways()` (single source of truth).
- Breaking Change: NO (narrows output to truthful set)
- Affected Packages: `cashier`, `checkout`, `filament-cashier`
- Required Dependent Changes: `checkout` processor registration + Filament `GatewayManagement`/`GatewaySetup` pages re-test with/without `laravel/cashier`.
- Migration Required: NO

### A-3 `ChipGateway` duplicates and diverges from `cashier-chip`/`chip`
- Severity: High
- Location: `packages/cashier/src/Gateways/ChipGateway.php` (492 lines), `packages/cashier/src/Gateways/Chip/*` (9 adapter classes: `ChipCheckout`, `ChipCheckoutBuilder`, `ChipCustomer`, `ChipInvoice`, `ChipPayment`, `ChipPaymentMethod`, `ChipSubscription`, `ChipSubscriptionBuilder`, `ChipSubscriptionItem`), vs `packages/cashier-chip/src/Billing/*` + `packages/chip/src/Services/ChipCollectService.php`
- Problem: Three layers model the same CHIP concepts (`cashier` Chip* adapters → `cashier-chip` Billing/Concerns → `chip` Collect API). `ChipGateway::charge()` delegates via `callBillableMethod($billable, 'charge', ...)` magic to whichever `Billable` trait the host model uses, while `refund()`/`retrieveCheckout()`/`retrievePayment()` call `ChipCollectService` directly — two different CHIP clients for one gateway, with `isNotFoundException()` string-matching (`str_contains(lower(message), 'not found')`) as the 404 detector.
- Why It Matters: Every CHIP behavior fix must land in 2–3 places; the string-match 404 detector misclassifies network errors as not-found (returns null → checkout treats as missing payment instead of retrying).
- Recommended Fix: Shrink `ChipGateway` to delegation-only: all CHIP reads/writes go through `cashier-chip` `Cashier::chip()` service; delete the parallel `Gateways/Chip/*` wrappers that add no logic; replace string-match with status-code/exception-type checks (`ChipApiException` code, `getStatusCode()`).
- Breaking Change: YES (adapter class names change; `ChipGateway::client()` return stays but internals change)
- Affected Packages: `cashier`, `cashier-chip`, `chip`, `checkout` (`Integrations/Payment/CashierProcessor.php`, `CashierChipProcessor.php`), `filament-cashier`
- Required Dependent Changes: Update imports of `AIArmada\Cashier\Gateways\Chip\*` to the surviving classes.
- Migration Required: NO

### A-4 Webhook handling is a stub; verification uses the wrong key path
- Severity: Critical
- Location: `packages/cashier/src/Gateways/ChipGateway.php::handleWebhook()` (returns null; comment says "managed by cashier-chip's webhook controller"), `::verifyWebhookSignature()` (hand-rolled `openssl_verify` with `$this->client()->getPublicKey()` company key), `packages/cashier/src/Actions/SyncWebhook.php` (dispatches `WebhookReceived` → `$gateway->handleWebhook()` → dispatches `WebhookHandled` unconditionally, even when handler returned null), `packages/cashier/routes/web.php` (empty `Route::middleware('api')->prefix('cashier')->group(...)` with no routes)
- Problem: `SyncWebhook` announces success for unhandled payloads; CHIP verification here uses the company public key while real webhook events are verified in `chip/WebhookService` against per-webhook keys (`resolveVerificationPublicKeys`) — a payload passing one path fails the other. Stripe path: `StripeGateway::handleWebhook` + `webhookSecret()` need the same audit (verify against `cashier.gateways.stripe.webhook_secret`, fail closed).
- Why It Matters: Payment webhooks are the money path — silent drops + dual verification logic = missed payments, double fulfillment, or forged-event acceptance if any path degrades to accept-all.
- Recommended Fix: Delete `ChipGateway::verifyWebhookSignature()`/`handleWebhook()` and route all CHIP webhooks through `chip` (`WebhookService` + `ProcessChipWebhook` idempotency); make `SyncWebhook` return the handler result and only dispatch `WebhookHandled` on actual handling; delete the empty route group or register real endpoints.
- Breaking Change: YES (webhook entry points move to `chip`/`cashier-chip` controllers)
- Affected Packages: `cashier`, `chip`, `cashier-chip`, `checkout` (`CheckoutSpatieSignatureValidator`, `ProcessCheckoutPaymentNotification`)
- Required Dependent Changes: `checkout` webhook wiring points at the surviving validators; remove `cashier` webhook route references.
- Migration Required: NO

### A-5 Unscoped cross-tenant subscription read
- Severity: Critical
- Location: `packages/cashier/src/Gateways/ChipGateway.php::retrieveSubscription()` (`CashierChip::$subscriptionModel::find($subscriptionId)`), `packages/cashier/src/Gateways/AbstractGateway.php:250-266`, `packages/cashier/src/Support/OwnerScopedQuery.php`
- Problem: Re-check outcome (tightened 2026-09-07): `retrieveSubscription()` fetches any subscription by id with no owner check — verified `::find($subscriptionId)` with no scope. The `AbstractGateway` helper itself is less naive than first worded: it strips the global `OwnerScope` then re-applies owner filtering via `OwnerQuery::applyToEloquentBuilder()` with `OwnerContext::resolve()` — the defect is that `retrieveSubscription()` (and any path bypassing that helper) never enters it, so `include_global` semantics and the `assertResolvedOrExplicitGlobal` guard are bypassed on the money-read path.
- Why It Matters: One IDOR away from cross-tenant subscription/invoice disclosure; checkout's `CashierProcessor` calls these retrievals during payment confirmation.
- Recommended Fix: Resolve via `OwnerWriteGuard::findOrFailForOwner()` / `ResolveOwnedModelOrFailAction` (per multitenancy rules) or `Model::forOwner($owner)`; delete the hand-rolled `resolveOwnerQuery` in favor of model scopes.
- Breaking Change: NO (adds authorization where none existed; cross-tenant callers were already invalid)
- Affected Packages: `cashier`, `cashier-chip`, `checkout`
- Required Dependent Changes: `checkout/Integrations/Payment/CashierProcessor.php` + `CashierChipProcessor.php` pass session owner into retrievals.
- Migration Required: NO

### A-6 Octane-unsafe gateway client caching
- Severity: Medium
- Location: `packages/cashier/src/Gateways/ChipGateway.php::$chipService` (memoized `ChipCollectService`), `GatewayManager::buildGateway()` (check whether drivers are memoized by `Illuminate\Support\Manager`)
- Problem: `Cashier` statics are Octane-safe (remember/restore), but the per-gateway `ChipCollectService` (holding brand_id/API key/public-key cache) is memoized on a potentially long-lived gateway instance. If the manager memoizes drivers across Octane requests, credential/config rotation mid-deploy serves stale keys.
- Why It Matters: Stale brand/key → charges against the wrong CHIP brand.
- Recommended Fix: Do not memoize the service (resolve from container per call) or flush gateway instances on `RequestReceived` alongside `Cashier::restoreOctaneDefaults()`.
- Breaking Change: NO
- Affected Packages: `cashier`
- Required Dependent Changes: None.
- Migration Required: NO

## Code Quality Findings
### C-1 `Money::$currency($amount, true)` signature misuse
- Severity: High
- Location: `packages/cashier/src/Cashier.php::formatAmount()` (`Money::$currency($amount, true)->format($locale)`)
- Problem: Re-check outcome (corrected 2026-09-07): the bug is `cashier`-only, NOT mirrored in `cashier-chip`. Akaunting `Money::__callStatic()` treats a boolean second positional as `$convert` (`$convert = isset($arguments[1]) && is_bool($arguments[1]) && $arguments[1]` → `new self($arguments[0], new Currency($method), $convert)`), so passing `true` with an already-minor-unit `$amount` converts cents-as-majors (100x display error: MYR 1000 displays as RM1,000.00 instead of RM10.00). `cashier-chip/Billing/Cashier.php::formatAmount()` is CORRECT (`new Money($amount, new Currency($currency), false)` with `convert=false` and an explicit comment "expects amount in cents/minor units") — do not touch it except to converge both on the shared formatter.
- Why It Matters: Money-display bug on every invoice, email, and Filament widget using the `cashier` helper.
- Recommended Fix: Pin the exact Akaunting call (`new Money($amount, new Currency($currency), false)`), add a unit test with MYR 1000 → `RM10.00`, and route all display through `commerce-support` `FormatsMoney`/`MoneyNormalizer`.
- Breaking Change: NO
- Affected Packages: `cashier`, `filament-cashier`
- Required Dependent Changes: Snapshot any golden invoice/portal strings that encoded the wrong format.
- Migration Required: NO

### C-2 Exception taxonomy duplication
- Severity: Low
- Location: `packages/cashier/src/Exceptions/` (flat `PaymentFailedException`, `GatewayNotFoundException`, …) + `Exceptions/Gateway/` + `Exceptions/Payment/` + `Exceptions/Subscription/` + `Exceptions/Webhook/` (same names redeclared in subnamespaces)
- Problem: Two parallel exception trees (`Exceptions\GatewayNotFoundException` vs `Exceptions\Gateway\GatewayNotFoundException`, etc.) — catch blocks catch one and miss the other.
- Why It Matters: Payment-failure handling (`HandlesPaymentFailures`, checkout `PaymentFailed` transitions) can miss the sibling type.
- Recommended Fix: Keep one tree (the namespaced set), delete the flat duplicates, update `use` imports.
- Breaking Change: YES (class names)
- Affected Packages: `cashier`, `checkout`, `filament-cashier`
- Required Dependent Changes: Update catch blocks.
- Migration Required: NO

### C-3 Duplicated cart-inventory ownership (not dead keys)
- Severity: Medium
- Location: `config/cashier.php::cart` (`allocate_inventory`, `inventory_ttl_minutes`, `validate_stock`, `failure_mode`, `hard_failure_codes`), `packages/cashier/src/Support/CartIntegrationRegistrar.php`, `CartManagerWithPayment.php`, `packages/cashier/src/Checkout/CartCheckoutBuilder.php:59-61`
- Problem: Re-check outcome (corrected 2026-09-07): these keys are NOT dead — `cashier`'s own `CartCheckoutBuilder` reads `cashier.cart.allocate_inventory` / `inventory_ttl_minutes` / `validate_stock`, and `CartIntegrationRegistrar` reads `enabled` / `clear_on_success` / `failure_mode`. The real gap is duplication, not absence: `checkout` (the canonical inventory owner via `ReserveInventoryStep`) does not read `cashier.cart.*` (repo-wide grep confirms zero `cashier.cart` reads in `checkout/src`), so operators tuning `cashier.cart.*` change only the legacy `CartCheckoutBuilder` path while checkout's reserve-before-payment path follows `checkout.integrations.inventory.*` — two sources of truth for reserve-before-payment, TTL, and retry windows.
- Why It Matters: Operators tune `cashier.cart.*` and nothing changes at checkout.
- Recommended Fix: Declare `checkout.integrations.inventory` canonical; make `CartCheckoutBuilder` delegate to (or read) the checkout keys instead of its own, then delete the duplicated `cart.inventory/failure` keys from `cashier.php` once no in-repo reader remains; keep only cart-metadata keys here.
- Breaking Change: YES (config keys removed after delegation)
- Affected Packages: `cashier`, `checkout`
- Required Dependent Changes: `CartCheckoutBuilder` delegation first; then any deploy reading `cashier.cart.allocate_inventory` moves to `checkout.integrations.inventory.*`.
- Migration Required: NO

## Laravel-Specific Findings
- L-1 (Compliant): PHP `^8.4`; no migrations (by design); no FK violations (nothing to violate); `HasUuids` on the two record models (to be removed with A-1).
- L-2 (Medium): `CashierServiceProvider::loadStripeMigrationFallbacks()` uses `ConditionalMigrationLoader::loadDirectoryIfMissing()` on vendor path — fragile across `laravel/cashier` versions; pin the supported major and fail fast with the existing clear error message instead.
- L-3 (Low): Empty route group in `routes/web.php` still boots a route file on every request — delete the file + `registerRoutes()` until real endpoints exist.

## Filament Adapter Findings
- Thin-adapter check: FAIL (structurally). `UnifiedInvoiceResource`/`UnifiedSubscriptionResource` reimplement gateway aggregation in page classes (`ListSubscriptions`, `ListInvoices`, `SubscriptionsTable`, `InvoicesTable`, `SubscriptionForm`, `InvoiceForm`) instead of calling domain queries; widgets (`TotalMrrWidget`, `TotalSubscribersWidget`, `GatewayBreakdownWidget`, `GatewayComparisonWidget`, `UnifiedChurnWidget`) compute MRR/churn by iterating in-memory collections.
- Domain leak: `CustomerPortal` pages (`BillingOverview`, `ManagePaymentMethods`, `ManageSubscriptions`, `ViewInvoices`) + `Support/CustomerSubscriptionsQuery.php` encode gateway-specific status mapping that belongs in the gateway adapters.
- Duplication: `filament-cashier` (unified) vs `filament-cashier-chip` (CHIP-specific) ship parallel subscription/invoice/payment-method pages + MRR/churn widgets for the same underlying `cashier-chip` rows — pick one surface per audience (unified admin vs CHIP billing portal) and delete the other.
- Dependency direction: Correct on paper (`filament-cashier` → `cashier`), but `CustomerPortal/BillingPanelProvider` + `ChipGateway::customerPortalUrl()` hardcode Filament panel route names (`filament.{panel}.pages.dashboard`) — domain package knows UI route names. Invert: Filament registers its portal URL with the gateway; domain never builds Filament URLs.
- Navigation: COMPLIANT with a caveat. Resources/Pages use `getNavigationGroup()` from `filament-cashier.navigation.group`; no static `$navigationGroup` on resources (the only `$navigationGroup` hit is the plugin's private instance property in `FilamentCashierPlugin.php:33` + `navigationGroup()` setter/`getNavigationGroup()` — the sanctioned plugin-override pattern, not the banned static). Keep the plugin pattern, delete any per-resource hardcoded strings (none found).

## Database Findings
- No `cashier` migrations — compliant by design. Do not add `cashier_unified_*` migrations (see Migration block).
- `Unified*Record::$fillable` includes `user_id` alongside `billable_type/billable_id` — dual identity for the same owner; another reason to demote these to DTOs with one `billable` reference.

## Model / Domain Findings
- `SubscriptionStatus`/`InvoiceStatus` support enums are display-only groupings over gateway statuses — fine, but they must derive from the gateway adapters' canonical statuses, not re-list them (currently drifting from `cashier-chip/Enums/SubscriptionStatus`).
- `PaymentOperationLimiter` (60 attempts/60s per `cashier.payment_operations.rate_limiting`) vs `cashier-chip` per-billable charge limiter (30/min) — two rate limits on one charge path; keep the per-billable one, delete or delegate the generic one.

## Security Findings
- S-1 (Critical): See A-4/A-5 — webhook verify/handle + unscoped retrieval. Fix before any other payment work.
- S-2 (Medium): `config/cashier.php` secrets (`STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `CHIP_BRAND_ID`) have no boot-time presence validation (verified `CashierServiceProvider::bootingPackage()` has no secret assertions); Stripe calls fail deep in the stack. Demoted from High per rubric (missing fail-fast is a maintainability gap, not a vulnerability). Add `bootingPackage()` assertions (fail fast in non-testing envs) without logging secret values.
- S-3 (Medium): `CartManagerWithPayment::forOwner(Model $owner)` re-wraps the cart manager but callers can still reach the inner `$cart` unwrapped — audit that no Filament/portal path retains the pre-wrap instance.
- S-4 (Low): `#[SensitiveParameter]` is correctly used on `ChipGateway::charge()` payment-method param — extend to any logging of `$options` containing tokens.

## Performance Findings
- P-1: Admin list pages fan out to Stripe API per row — paginate at the gateway, cache `GatewayBreakdownWidget`/`TotalMrrWidget` aggregates, never `->get()` all subscriptions.
- P-2: `GatewayDetector` runs per request — memoize per billable.

## Testing Findings
- T-1 (Medium): Zero tests. Demoted from High per severity rubric (testability gaps are Medium). Minimum Pest suite: gateway capability matrix (with/without `laravel/cashier`), `Cashier::supportedGateways()` truthfulness, `ChipGateway::retrieveSubscription` owner denial, webhook verify-fail-closed, `formatAmount` MYR golden test, Filament list pages with stubbed gateways (assert bounded query/API call count).

## Cross-Package Dependency Impact
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `checkout` (`Integrations/Payment/CashierProcessor.php`, `Support/RegisterBuiltInPaymentProcessors.php`, `CheckoutSpatieSignatureValidator.php`) | `GatewayManager`, `Cashier::gateway()`, `cashier.gateways.stripe.webhook_secret` | A-2/A-4/A-5 change gateway resolution + secrets + retrieval auth | Resolve via manager truth; read Stripe secret from Stripe-owned config; pass session owner |
| `cashier-chip` | `ChipGateway` delegation target | A-3 inverts the dependency (cashier delegates to cashier-chip, never the reverse) | Expose stable `Cashier::chip()` service contract |
| `chip` | webhook verification delegation | A-4 routes verification to `chip` | No `chip` change; `cashier` deletes its copy |
| `filament-cashier-chip` | parallel admin surface | Adapter dedup decision | Keep one admin surface per audience |
| `cart` | `CartIntegrationRegistrar` macros | Config key removals (C-3) | Move inventory keys to `checkout` |

## Recommended Refactor Plan
1. Fix webhook verify/handle + unscoped retrieval (A-4, A-5, S-1/S-2) first — money + isolation.
2. Unify gateway capability truth (A-2) + Octane client caching (A-6).
3. Demote unified records to DTOs; rewrite Filament lists/widgets (A-1).
4. Collapse `ChipGateway` to delegation; delete parallel adapters + flat exceptions (A-3, C-2).
5. Fix `formatAmount` + delete dead cart-inventory keys (C-1, C-3); delete empty routes (L-3).
6. Write the Pest suite (T-1).

## Files Likely to Change
- `packages/cashier/src/Gateways/ChipGateway.php`, `Gateways/Chip/*.php`, `Gateways/AbstractGateway.php`, `Gateways/StripeGateway.php`
- `packages/cashier/src/Cashier.php`, `GatewayManager.php`, `Actions/SyncWebhook.php`, `Support/OwnerScopedQuery.php`, `Support/UnifiedInvoice.php`, `Support/UnifiedSubscription.php`, `Models/Unified*.php`, `Support/CartManagerWithPayment.php`, `Support/PaymentOperationLimiter.php`
- `packages/cashier/config/cashier.php`, `routes/web.php`, `src/CashierServiceProvider.php`
- `packages/filament-cashier/src/Resources/UnifiedSubscriptionResource/**`, `Resources/UnifiedInvoiceResource/**`, `Support/CustomerSubscriptionsQuery.php`, `Widgets/*.php`, `CustomerPortal/**`, `Pages/GatewayManagement.php`, `Pages/GatewaySetup.php`, `Policies/*.php`
- `packages/checkout/src/Integrations/Payment/CashierProcessor.php`, `Support/RegisterBuiltInPaymentProcessors.php`, `Webhooks/CheckoutSpatieSignatureValidator.php`

## Files / Code That Should Be Removed
- `packages/cashier/src/Models/UnifiedInvoiceRecord.php` + `UnifiedSubscriptionRecord.php` as Eloquent models (replace with DTOs; verified no external consumers via repo-wide grep — only `filament-cashier` list pages reference them).
- `packages/cashier/src/Gateways/ChipGateway.php::verifyWebhookSignature()` + `::handleWebhook()` stub (route to `chip`; verified stub returns null + empty route group in `routes/web.php`).
- `packages/cashier/routes/web.php` empty route group (verified file contains only an empty `->group(function (): void {})`).
- Flat duplicates in `packages/cashier/src/Exceptions/` (`CashierException.php` siblings that redeclare `Gateway/*`, `Payment/*`, `Subscription/*`, `Webhook/*` names — keep the namespaced tree).
- `config/cashier.php::cart.{allocate_inventory,inventory_ttl_minutes,validate_stock,failure_mode,retry_window_minutes,hard_failure_codes}` keys (verified read by `cashier`'s own `CartCheckoutBuilder` but unread by `checkout`, the inventory owner — remove only after `CartCheckoutBuilder` delegates to `checkout.integrations.inventory.*`).
- `packages/cashier/src/Gateways/Chip/ChipCheckout.php`, `ChipInvoice.php`, `ChipPayment.php`, `ChipSubscription.php`, `ChipSubscriptionItem.php`, `ChipPaymentMethod.php`, `ChipCustomer.php` wrappers that add no logic over `cashier-chip`/`chip` types (delete after A-3 delegation; verify each call site first).
- No migrations removed. No legacy shims preserved.

## Final Recommended Architecture
`cashier` becomes a true thin multiplexer: contracts + `GatewayManager` (truthful capabilities) + tiny delegation-only adapters + cart metadata glue. Persistence lives in `laravel/cashier` (Stripe) and `cashier-chip` (CHIP); HTTP + webhook verification live in `chip`; renewals live in `cashier-chip`; `checkout` orchestrates. Filament shows gateway-sourced data through bounded, paginated queries — never in-memory model fakes.
