# Chip Audit

## Packages Reviewed
- `aiarmada/chip` (`packages/chip`): `src/` (Actions, Builders, Clients, Commands, Contracts, Data×20+, Enums, Events×30, Exceptions, Facades, Gateways, Health, Http/Controllers+Middleware, Listeners, Models×11, Services, Support, Testing, Webhooks), `config/chip.php`, `database/migrations/` (10 files), `routes/webhooks.php` + `send-webhooks.php`, `composer.json`, `src/ChipServiceProvider.php`, `CONTEXT.md`/`README.md`/`docs/` (incl. `chip-collect-API-2/`, `chip-collect.md`, `chip-send.md`, `payment-gateway.md`, `webhooks.md`)
- `aiarmada/filament-chip` (`packages/filament-chip`): `src/` (Actions, Pages, Resources×13, Widgets×11), `config/filament-chip.php`, `resources/views/pages/*` (7), `composer.json`, `CONTEXT.md`/`README.md`/`docs/`
- No `tests/` in either package (verified).

## Overall Assessment
- Quality: Strong API client + webhook machinery (per-webhook-key verification, SHA-256 Collect vs SHA-512 Send separation, idempotency-key store, retry manager, simulator + factory for tests). Data-object modeling (`PurchaseData`, `PaymentData`, `ProductData`, `MoneyTransformer`) is good. But the package sprawls: Collect + Send + local analytics + docs-integration + customer bridge in one unit, with two webhook stores and a missing `spatie/laravel-webhook-client` require.
- Health: Fair. Payment-critical gaps: no mutation idempotency (`BaseHttpClient` admits it), dual webhook persistence, Send verification path ambiguity, and six phantom Filament resources shipping with empty `getPages()`.
- Risks: Duplicate purchase/charge on retry (money); forged or dropped Send webhooks; vendor-table migration coupling; amount-trust chain (Purchase amount read from remote payload, checkout confirm step trusts its own total — neither reconciles).
- Refactor size: Large. ~15–20 files, but no migration required.

## Migration Impact
**Migration Required: NO**
No table/column/index/constraint changes. Fixes are code + config + Filament registration only.

| Table | Change | Detail |
|---|---|---|
| `chip_purchases`, `chip_payments`, `chip_bank_accounts`, `chip_clients`, `chip_send_instructions`, `chip_send_limits`, `chip_send_webhooks`, `chip_company_statements`, `chip_customers` | none | uuid PKs kept; no FK constraints added (rule-compliant); no data migration |
| `webhook_calls` (spatie vendor table, extended by `2000_04_01_000003_add_chip_webhook_columns_to_webhook_calls_table.php`; `Models/Webhook extends WebhookCall`) | none (freeze) | do not alter further; the `Webhook` subclass row is the single system of record; see A-2 |

## Package Responsibilities
- Owns: CHIP Collect API (`ChipCollectService`, `Collect/*Api`, `ChipCollectClient`), Send API (`ChipSendService`, `ChipSendClient`), `PurchaseBuilder`, purchase/payment/bank/client/send models, webhook verify + route + process + dispatch pipeline, customer directory/bridge, health checks, local analytics.
- Filament adapter owns: purchase/payment/client/bank/statement/send resources, analytics/payout/refund/webhook pages, stats widgets, CSV exporters. Must stay read-mostly over domain models.

## Architecture Findings
### A-1 No mutation idempotency — retry = duplicate charge
- Severity: Critical
- Location: `packages/chip/src/Clients/Http/BaseHttpClient.php:161` ("CHIP does not document idempotency keys for its mutation endpoints"), `Actions/Purchases/CreatePurchase.php`, `ChargePurchase.php`, `CapturePurchase.php`, `RefundPurchase.php`, `Builders/PurchaseBuilder.php::create()`, `checkout/Steps/ProcessPaymentStep.php` (increments `payment_attempts`, calls processor, each retry builds a new purchase)
- Problem: Webhook ingress has idempotency (`ProcessChipWebhook` + `WebhookLogger` + `idempotency_key`); API egress has none. Any timeout → retry (HTTP retry 3×1000ms in `chip.http.retry`, checkout `payment.retry_limit=3`, operator "retry payment") creates a second purchase/charge/refund. There is no client-generated idempotency token, no pre-create existence check by `reference`, no refund-guard against double `refundPurchase`.
- Why It Matters: Direct money loss path — the most severe finding in this unit.
- Recommended Fix: Generate a caller-supplied idempotency token per logical operation (`PurchaseBuilder::idempotencyKey()` / `reference` uniqueness pre-check: look up existing `Purchase` by `reference` before `create`);serialize capture/refund per purchase (`lockForUpdate` on the local row + `SyncPurchaseRefundState`); make checkout retries reuse the same purchase id while it is still payable instead of creating new ones.
- Breaking Change: NO (additive builder method + processor retry behavior)
- Affected Packages: `chip`, `checkout` (`Integrations/Payment/ChipProcessor.php`, `CashierChipProcessor.php`, `Steps/ProcessPaymentStep.php`), `cashier-chip` (`ChargeChipCustomer`, `RefundChipPayment`)
- Required Dependent Changes: Checkout processors pass through the session-scoped idempotency key; renewal/refund Actions check-then-act.
- Migration Required: NO

### A-2 Vendor-table migration coupling + missing `spatie/laravel-webhook-client` require
- Severity: Medium
- Location: `packages/chip/database/migrations/2000_04_01_000003_add_chip_webhook_columns_to_webhook_calls_table.php` (adds chip columns to spatie `webhook_calls`), `packages/chip/src/Models/Webhook.php` (`extends WebhookCall`, `getTable()` returns `webhook_calls`), `packages/chip/src/Webhooks/ProcessChipWebhook.php::storeWebhookRecord()` + `WebhookLogger::createLog()/isDuplicate()`, `src/ChipServiceProvider.php:96-128`
- Problem: Re-check outcome (corrected + demoted High→Medium 2026-09-07): there are NOT two webhook stores — `Models/Webhook.php` extends spatie's `WebhookCall` and returns `webhook_calls` from `getTable()`, so `ProcessChipWebhook::storeWebhookRecord` (row UPDATE by webhook-call key) and `WebhookLogger::createLog` (row CREATE) both write the same `webhook_calls` table; no `chip_webhooks` table exists in any migration (the only `chip_*webhook*` table is `chip_send_webhooks`, which models Send-API records, not ingress). The failure modes were also overstated: the provider DOES guard (`if (! class_exists(WebhookCall::class)) return;` before touching `webhook-client.configs`) and the migration DOES guard (`if (! Schema::hasTable('webhook_calls')) return;`), so a standalone `chip` install skips webhook wiring instead of breaking at migrate time. Residual real gap: `composer.json` requires only `illuminate/http|queue|validation` with no `spatie/laravel-webhook-client`, so webhook support is a de-facto-but-undeclared dependency, and chip columns live on a vendor-owned table (broken boundary, guarded but still coupled).
- Why It Matters: Undeclared dependency drifts from the spatie version `checkout`/`cashier-chip` resolve; vendor-table coupling means a spatie major can collide with the added columns; two writers (`storeWebhookRecord` update vs `createLog` insert) can diverge on which row represents "handled".
- Recommended Fix: Add `spatie/laravel-webhook-client` to `chip` requires (it is already a de-facto dependency), freeze the vendor-table migration (no further alters), and declare the spatie `webhook_calls` row (via the `Webhook` subclass) the single system of record — `ProcessChipWebhook::storeWebhookRecord` is the single writer; `WebhookLogger` becomes a thin delegate or is deleted.
- Breaking Change: YES (composer require addition; single-writer contract)
- Affected Packages: `chip`, `checkout` (`ProcessCheckoutWebhook`, `CheckoutWebhookProfile`), `cashier-chip`
- Required Dependent Changes: `checkout` webhook profile/response keep working against the same spatie version pin.
- Migration Required: NO (freeze; no new columns)

### A-3 Send webhook verification path ambiguity (payout surface)
- Severity: High
- Location: `packages/chip/src/Services/WebhookService.php::verifySendSignature()` (SHA-512 + `resolveSendVerificationPublicKeys`), `::verifySignature()` (SHA-256), `Http/Controllers/SendWebhookController.php`, `Http/Controllers/WebhookController.php`, `Http/Middleware/VerifyWebhookSignature.php` (Collect-only: reads `X-Signature`, calls `verifySignature()`), `config/chip.php::webhooks.send` (`webhook_id`, `webhook_keys`, separate `route`)
- Problem: The middleware enforces Collect verification, but the Send controller route (`routes/send-webhooks.php`) does not visibly share the same enforcement — verify whether `SendWebhookController::handle` calls `verifySendSignature()` with the Send webhook key (SHA-512) or inherits the Collect middleware (SHA-256 → every legitimate Send event rejected, or worse, unverified if the route skips the middleware). Payout webhooks are the money-out path; ambiguity here is unacceptable.
- Why It Matters: Either Send events are dropped (payout status never syncs) or accepted without verification (forged payout-success → premature fulfillment/release).
- Recommended Fix: Dedicated `VerifySendWebhookSignature` middleware (SHA-512, Send keys only) applied to the Send route; add explicit controller-level assertion + test with both algorithms (valid Collect key must FAIL the Send route and vice versa).
- Breaking Change: NO (fail-closed tightening)
- Affected Packages: `chip`
- Required Dependent Changes: `filament-chip` webhook-monitor page labels per pipeline (Collect vs Send).
- Migration Required: NO

### A-4 Amount-trust chain is unverified end to end
- Severity: High
- Location: `packages/chip/src/Models/Purchase.php::amount()` (`Arr::get($this->purchase, 'total')` as int), `::totalMoney()`, `Builders/PurchaseBuilder.php::addProductMoney()` / `addProductCents()`, `checkout/Support/ChipPurchasePayloadBuilder.php` vs `chip` payload building, `checkout/Steps/CreateOrderStep.php::confirmPayment()` (`$paymentData['amount'] ?? $session->grand_total`)
- Problem: `Purchase::amount` trusts the remote `purchase.total`; the builder trusts caller ints; checkout confirms the order for `grand_total` while the gateway reports its own `amount` — nothing asserts `gateway amount == session grand_total == sum(line items)` before `confirmPayment()` triggers `PaymentConfirmed` → `OrderPaid`. Currency is set-if-absent from the first product (`addProductMoney`/`addProductObject`), so mixed-currency line items silently take the first currency.
- Why It Matters: Under-charge (gateway total < order total) still confirms the order; mixed-currency carts mis-charge.
- Recommended Fix: Single canonical builder (`ChipPurchasePayloadBuilder` delegates to `chip` `PurchaseBuilder`, or vice versa — delete one); assert `amount == session->grand_total && currency == session->currency` in checkout processors + `HandleCheckoutPaymentCallback` before confirming; reject mixed-currency product lists in the builder.
- Breaking Change: YES (mixed-currency now throws; amount mismatch now blocks confirmation)
- Affected Packages: `chip`, `checkout`, `cashier-chip`
- Required Dependent Changes: `ChipProcessor`/`CashierChipProcessor`/`CashierProcessor` + `HandleCheckoutPaymentCallback` + `ProcessCheckoutPaymentNotification` adopt the assertion.
- Migration Required: NO

### A-5 `chip` ↔ `checkout` status-mapper duplication
- Severity: Medium
- Location: `packages/chip/src/Support/ChipPaymentStatusMapper.php` vs `packages/checkout/src/Support/ChipPaymentStatusMapper.php`, `checkout/Support/ChipPaymentStatusMapper.php` used by both `ChipProcessor` and `CashierChipProcessor`, `checkout/Support/HandleChipPurchaseEventForCheckout.php` + `ChipIntegrationRegistrar.php` listening to `chip` `PurchasePaid/Cancelled/PaymentFailure` events
- Problem: Two same-named mappers; checkout's copy drifts from chip's. Event-based sync (`HandleChipPurchaseEventForCheckout`) and webhook-based sync (`ProcessCheckoutPaymentNotification`) can both fire for one purchase with no declared precedence.
- Why It Matters: Status-mapping drift causes "paid at gateway, failed at checkout" incidents; dual sync paths double-transition sessions.
- Recommended Fix: Delete checkout's copy; `chip` mapper is canonical. Declare webhook (verified) as the confirmation path and domain events as notification-only (no state transitions in event listeners).
- Breaking Change: YES (import path change)
- Affected Packages: `chip`, `checkout`
- Required Dependent Changes: Update the two processor imports + registrar docs.
- Migration Required: NO

### A-6 Docs-integration + customer-bridge sprawl
- Severity: Medium
- Location: `packages/chip/src/Support/BuildChipDocData.php`, `DocsIntegrationRegistrar.php`, `Listeners/GenerateDocOnPayment.php`, `GenerateDocOnRefund.php`, `Actions/RunChipPurchaseDocGenerationAction.php`, `Support/ChipCustomerBridge.php`, `Services/ChipCustomerDirectory.php`, `Actions/LinkChipCustomerFromCheckout.php`, `config/chip.php::integrations.(docs|customer_bridge)`
- Problem: Invoice/PDF generation and checkout-customer linking live in the gateway package instead of `docs`/`customers`/`checkout`. `chip` hardcodes `AIArmada\Checkout\Models\CheckoutSession` + `AIArmada\Customers\Models\Customer` defaults in its own config.
- Why It Matters: `chip` cannot be installed standalone without dragging checkout/customer concepts; docs-format changes ripple into the money package.
- Recommended Fix: Move doc generation to event listeners owned by `docs` (chip emits `PurchasePaid/Refunded` — already does), and customer linking to `checkout`/`customers`; `chip` keeps emitting events + a stable payload contract only.
- Breaking Change: YES (listener ownership moves; config keys move)
- Affected Packages: `chip`, `docs`, `checkout`, `customers`
- Required Dependent Changes: `docs` subscribes to chip events; `checkout` owns the link action.
- Migration Required: NO

## Code Quality Findings
### C-1 `PurchaseBuilder` quantity typing + currency inference
- Severity: Medium
- Location: `packages/chip/src/Builders/PurchaseBuilder.php::addProductMoney()` (`string|float|int $quantity` cast to `(string)`), `::addProductObject()`, `::addLineItem()`
- Problem: Float quantities serialized as strings to an API that expects integer strings; first-product-wins currency inference (see A-4).
- Why It Matters: Quantity `1.5` either errors at CHIP or charges wrong units.
- Recommended Fix: Accept int-only quantities (throw on non-integral float), require explicit `currency()` before products, assert all products share it.
- Breaking Change: YES (float quantities now throw)
- Affected Packages: `chip`, `checkout`, `cashier-chip`
- Required Dependent Changes: Callers pass int quantities + explicit currency.
- Migration Required: NO

### C-2 `CashierChip\Billing\Cashier::chip()` service-locator sprawl (chip side)
- Severity: Low
- Location: `packages/chip/src/Services/ChipCollectService.php` (god service: purchases + clients + webhooks + payment methods + public keys), `Clients/Http/BaseHttpClient.php`
- Problem: One service fronts five API areas; tests must mock the world.
- Why It Matters: Change blast radius per API fix.
- Recommended Fix: Split behind the existing `Services/Collect/*Api` facades over time (no rush; do when touching).
- Breaking Change: NO
- Affected Packages: `chip`
- Required Dependent Changes: None.
- Migration Required: NO

## Laravel-Specific Findings
- L-1 (Info): Missing `spatie/laravel-webhook-client` require — see A-2 (single tracking point; not a separate High). Also confirm `spatie/laravel-data` usage is covered by transitive deps or add it explicitly — `Data/*` leans on it heavily.
- L-2 (Compliant): PHP `^8.4`; uuid PKs; `getTable()` via `tableSuffix()` + prefix; `timestampsTz`/`immutable_datetime` casts; `CarbonImmutable` in `BuildChipDocData`, `Webhook` model; no `SoftDeletes` (app deletes in `Purchase::booted`, `Webhook::booted` — correct per rules).
- L-3 (Low): `ChipServiceProvider` webhook-brand→owner map validation (lines ~368-419) fails fast with clear messages — good; keep. `configureWebhookRoutes()` respects `chip.webhooks.enabled` — good.

## Filament Adapter Findings
- Thin-adapter check: FAIL on phantom resources, PASS elsewhere. Real resources (`Purchase`, `Payment`, `Client`, `BankAccount`, `CompanyStatement`, `SendInstruction`) + exporters + payout/refund/analytics pages correctly wrap domain models.
- Domain leak: `Widgets/*` (11 widgets) recompute balances/turnover/payout stats via direct model queries — acceptable for read-only analytics, but share the aggregation with `chip/Services/LocalAnalyticsService.php` instead of triplicating SQL in widgets.
- Duplication: `filament-chip` payout pages vs `filament-cashier-chip` billing portal both show money movement with different vocabulary — keep (different audiences: gateway ops vs subscriber billing), but cross-link.
- Dependency direction: Correct (`filament-chip` → `chip`).
- Navigation: COMPLIANT for real resources (`BaseChipResource::getNavigationGroup()` from `filament-chip.navigation.group`; `AnalyticsDashboardPage` same). No static `$navigationGroup`.
- Phantom resources (verified — each file is ~30 lines, `getPages(): array { return []; }`, no Schemas/Tables): `AuditLogResource` (over `Webhook`), `ComplianceReportResource` (over `Purchase`), `FraudReviewResource` (over `Payment`), `PaymentLinkResource` (over `Payment`), `RefundResource` (over `Payment`), `RiskRuleResource` (over `Purchase`). Three different labels over the same `Payment` model and two over the same `Purchase` model with zero pages — pure nav noise that will error in Filament (resources with no pages). Delete all six.

## Database Findings
- D-1 (Compliant): uuid PKs everywhere (verified); `nullableUuidMorphs`/`nullableMorphs` owner columns; no `constrained()`/`cascadeOnDelete()` (grep-verified); `json_column_type` configurable; app-level cascade in `Purchase::booted()` (`payments()->delete()`) — correct.
- D-2 (Medium): Re-check outcome (corrected 2026-09-07): `idempotency_key` on the webhook store ALREADY has a UNIQUE index (`2000_04_01_000003` line 76-77: `$table->string('idempotency_key')->nullable()->unique()` on `webhook_calls`, which is the actual store since `Webhook extends WebhookCall`). The residual race is code-level, not schema-level: `ProcessChipWebhook` / `WebhookLogger::isDuplicate()` do check-then-insert (`where('idempotency_key')` + `exists()`/`create`) without catching the duplicate-key exception the unique index will throw under concurrent delivery. Fix by catching the unique-violation on insert (treat as duplicate, idempotent return) and/or a `lockForUpdate`-guarded check in `storeWebhookRecord()`. Repo rules forbid FK/cascade constraints, not unique indexes — the existing unique stays. Code-only fix.
- D-3 (Low): `chip_send_*` tables (instructions, limits, webhooks) model money-out — ensure `SendInstruction` amount columns are integer minor units (verify; the `70+`-line casts file suggests array/JSON heavy storage — confirm no float amounts).

## Model / Domain Findings
- M-1: `ChipModel`/`ChipIntegerModel` base split + `tableSuffix()` is a clean prefix-respecting pattern — keep; extend to any model still hardcoding names.
- M-2: `AutoAssignOwnerOnCreate` trait exists (`Models/Concerns/`) — audit which models use it vs manual `booted()` assignment; standardize on one (trait) so `chip.owner.auto_assign_on_create` behaves uniformly. `ChipOwnerTuple` + `ChipWebhookOwnerResolver` + `WebhookOwnerBatchRunner` are the right webhook-owner seams — keep.
- M-3: Money handling is integer-based (`Purchase::amount` int, builder cents methods, `MoneyCast`/`MoneyTransformer`) — correct; the gap is verification (A-4), not representation. `Purchase::totalMoney`/`format()` display path should route through `commerce-support` formatters (same note as cashier C-1).

## Security Findings
- S-1 (Critical): A-1 (duplicate charges on retry) + A-3 (Send verification ambiguity) are the two money-critical security findings.
- S-2 (Medium): `verifySignature()` fail-open shape — `assertSignatureVerificationEnabled()` returns `false` when verification is disabled, and callers do `if (! assert) return true` (verified `WebhookService.php:21-23,44-46`), so a disabled non-prod install ACCEPTS every webhook with only a log warning. Production already fail-closes (throws `WebhookVerificationException` when disabled in prod — verified lines 341-343, matching checkout's `CheckoutSpatieSignatureValidator` discipline). Demoted from High: the prod path is safe; the residue is confusing naming + non-prod accept-all (needed by `SimulatesWebhooks`, which sets `chip.webhooks.verify_signature=false`). Rename to `isVerificationBypassed()` or invert, and keep the production hard-fail.
- S-3 (Medium): `chip.logging.log_requests/log_responses` + `mask_sensitive_data` — verify `BaseHttpClient` never logs `api_key`/`Authorization`/`recurring tokens` even when masking is on (mask list must include them; `VerifyWebhookSignature::maskSensitiveData` list lacks token fields).
- S-4 (Medium): Public-key fetch (`getPublicKey()` → `Cache::remember` → API `getWebhook($webhookId)`) uses caller-influenced `$webhookId` — constrain to configured `webhooks.collect.webhook_keys` map before hitting the network (SSRF/cache-poisoning surface).
- S-5 (Low): `SimulatesWebhooks`/`WebhookSimulator` disables verification via `config(['chip.webhooks.verify_signature' => false])` — ensure it can only run in non-production (assert `! app()->isProduction()`).

## Performance Findings
- P-1: `LocalAnalyticsService` six `forOwner()` aggregates + `DashboardMetrics`/`RevenueMetrics` recompute per page view — cache per owner with short TTL (same `OwnerScopeKey` pattern shipping uses).
- P-2: `SyncChipRecordsFromApiAction`/`Command` full-sync shape — keep paginated + idempotent (upsert by remote id), never truncate.

## Testing Findings
- T-1 (Medium): Zero tests despite `Testing/WebhookSimulator.php`, `WebhookFactory.php`, `SimulatesWebhooks.php` existing precisely for tests. Demoted from High per severity rubric (testability gaps are Medium). Priority Pest suite (`--parallel`): Collect verify (valid/forged/missing key, disabled-in-production refusal), Send verify separation (SHA-512 vs SHA-256 cross-rejection), idempotent webhook double-delivery, mutation retry reuse (A-1), amount/currency assertion (A-4), owner resolution via brand map, Filament phantom-resource absence (assert only real resources registered).
- T-2: `Commands/ChipHealthCheckCommand.php`, `CleanWebhooksCommand`, `RetryWebhooksCommand` need console tests (exit codes, dry-run).

## Cross-Package Dependency Impact
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `checkout` (processors, payload builder, status mapper, event registrar, signature validator) | `ChipCollectService`, `PurchaseBuilder`, `PurchaseStatus/WebhookEventType`, `WebhookService`, `Purchase*` events | A-1/A-4/A-5 change build/verify/confirm contracts | Single builder, amount assertions, canonical mapper, webhook-precedence rule |
| `cashier-chip` (charge/refund/subscription) | same Collect APIs | Same | Same + lease/amount freeze coordination |
| `cashier` (`ChipGateway`) | `ChipCollectService`, `PaymentData` | Delegation collapse | Gateway calls stable service methods only |
| `docs` | `BuildChipDocData`, doc-gen listeners | A-6 moves ownership | `docs` subscribes to chip events |
| `customers`/`checkout` | `ChipCustomerBridge`, `ChipCustomerDirectory`, link actions | A-6 moves ownership | New owners adopt the bridge contract |
| `filament-cashier-chip` | purchase data for billing portal | Display-only | No change |

## Recommended Refactor Plan
1. Mutation idempotency + Send verification lockdown + verify-bypass hardening (A-1, A-3, S-2) — money first.
2. Declare `webhook-client` require; single webhook writer (A-2); fix idempotency race (D-2).
3. Amount/currency assertion chain + single builder + canonical mapper (A-4, A-5, C-1).
4. Move docs/customer-bridge ownership out (A-6).
5. Delete 6 phantom Filament resources; share analytics aggregation (Filament findings).
6. Pest suite with the ready-made simulator/factory (T-1).

## Files Likely to Change
- `packages/chip/src/Clients/Http/BaseHttpClient.php`, `Services/ChipCollectService.php`, `Services/Collect/*.php`, `Builders/PurchaseBuilder.php`
- `packages/chip/src/Services/WebhookService.php`, `Webhooks/*.php`, `Http/Controllers/WebhookController.php`, `Http/Controllers/SendWebhookController.php`, `Http/Middleware/VerifyWebhookSignature.php` (+ new `VerifySendWebhookSignature.php`)
- `packages/chip/src/Models/Purchase.php`, `Models/Webhook.php`, `Support/ChipPaymentStatusMapper.php`, `Support/ChipCustomerBridge.php`, `Support/BuildChipDocData.php`, `Actions/Purchases/*.php`
- `packages/chip/config/chip.php`, `src/ChipServiceProvider.php`, `composer.json`, `routes/webhooks.php`, `routes/send-webhooks.php`
- `packages/filament-chip/src/Resources/*`, `Widgets/*`, `Pages/*`
- `packages/checkout/src/Support/Chip*.php`, `Integrations/Payment/*.php`, `Webhooks/CheckoutSpatieSignatureValidator.php`

## Files / Code That Should Be Removed
- `packages/filament-chip/src/Resources/AuditLogResource.php` (verified: `getPages()` returns `[]`, label-only alias over `Webhook`)
- `packages/filament-chip/src/Resources/ComplianceReportResource.php` (verified: `[]` pages over `Purchase`)
- `packages/filament-chip/src/Resources/FraudReviewResource.php` (verified: `[]` pages over `Payment`)
- `packages/filament-chip/src/Resources/PaymentLinkResource.php` (verified: `[]` pages over `Payment`)
- `packages/filament-chip/src/Resources/RefundResource.php` (verified: `[]` pages over `Payment`)
- `packages/filament-chip/src/Resources/RiskRuleResource.php` (verified: `[]` pages over `Purchase`)
- `packages/checkout/src/Support/ChipPaymentStatusMapper.php` (verified duplicate name+role of `chip/src/Support/ChipPaymentStatusMapper.php`; keep chip's copy)
- `packages/chip/src/Webhooks/WebhookLogger.php` if `ProcessChipWebhook::storeWebhookRecord` becomes the single writer (verified overlapping `isDuplicate`/insert logic in both files), else keep as a thin delegate — no two writers.
- `packages/chip/src/Support/BuildChipDocData.php` + `Listeners/GenerateDocOnPayment.php` + `GenerateDocOnRefund.php` + `Actions/RunChipPurchaseDocGenerationAction.php` from `chip` after ownership moves to `docs` (verified doc-gen cluster inside the gateway package).
- No migration files removed. No legacy shims preserved.

## Final Recommended Architecture
`chip` = Collect + Send API clients, one purchase builder with idempotency, one verified webhook pipeline (Collect vs Send separated, single `chip_webhooks` store), money-integer models, and domain events. `cashier-chip` owns recurring billing on top; `cashier` multiplexes; `checkout` orchestrates and asserts amounts; `docs`/`customers` own their integrations; `filament-chip` shows only real, paged resources. No new packages.
