# Checkout Audit

## Packages Reviewed
- `aiarmada/checkout` (`packages/checkout`, standalone — no Filament adapter): `src/` (Actions×6, Contracts×10, Data×10, Enums, Events×10, Exceptions, Facades, Http/Controllers×2, Integrations + Payment×3, Jobs, Listeners, Models/CheckoutSession, Services×5, States×9, Steps×12, Support×11, Transformers×2, Webhooks×4), `config/checkout.php`, `database/migrations/` (2 files), `routes/checkout.php`, `resources/views/` (success/failure/cancel), `composer.json`, `src/CheckoutServiceProvider.php`, `CONTEXT.md`/`README.md`/`docs/` (8 docs)
- No `tests/` directory (verified). No Filament package (`filament-checkout` does not exist — N/A section below applies).

## Overall Assessment
- Quality: Ambitious and mostly well-shaped orchestrator (registry + executor + 12 single-purpose steps, tagged processor contributions, session-state machine, owner-aware service with `OwnerContextJob` queue support). The step/contract seam design is the best extension model of the six units.
- Health: Fair. The pipeline lacks atomicity (no transaction or compensation audit across steps), the payment-confirmation path trusts amounts from three unreconciled sources, the Stripe webhook secret is read from another package's config, and the callback token never expires. Zero tests for a 12-step money pipeline.
- Risks: Order created for the wrong amount (Critical); paid-but-unconfirmed or confirmed-but-unpaid sessions on partial failure (High); cross-gateway notification spoofing via `reference` fallback (Medium).
- Refactor size: Medium-Large. ~14–18 files. One additive migration is optional (callback-token expiry column already exists as data — no schema change needed); recommended verdict stays NO.

## Migration Impact
**Migration Required: NO**
No table/column/index/constraint changes. `finalization_phase`/`finalization_error` already added by `2026_07_13_000001_add_checkout_finalization_phase.php`; `callback_token_created_at` already stored inside `payment_data` JSON. All fixes are code-only.

| Table | Change | Detail |
|---|---|---|
| `checkout_sessions` | none | uuid PK kept; `foreignUuid`/`nullableUuidMorphs` without constraints kept (rule-compliant); all totals stay `unsignedBigInteger` minor units; JSON columns stay configurable type; no data migration |

## Package Responsibilities
- Owns: checkout orchestration end to end (session → validate_cart → resolve_customer → calculate_pricing → apply_discounts → calculate_shipping → calculate_tax → reserve_inventory → process_payment → persist_customer → create_order → event registrations/passes → dispatch_documents), step registry/executor, payment-gateway resolution, shipping/tax/promotion/voucher/inventory adapters, callback + webhook ingress, finalization.
- Must NOT own: pricing math (pricing/cart), rate tables (shipping/tax), charge execution (cashier/cashier-chip/chip), order persistence rules (orders), stock ledger (inventory). Current code respects this except the duplicated CHIP mapping cluster (see A-5).

## Architecture Findings
### A-1 No atomicity or audited compensation across the 12-step pipeline
- Severity: High
- Location: `packages/checkout/src/Services/StepExecutor.php::run()` + `::processStep()` (no `DB::transaction`, per-step `setStepState` + `update`), `Services/CheckoutService.php`, `Steps/ReserveInventoryStep.php`, `Steps/ProcessPaymentStep.php`, `Steps/CreateOrderStep.php`, `Actions/CheckoutFinalizer.php`, `Enums/CheckoutFinalizationPhase.php`, `Data/FinalizationPhaseResult.php`
- Problem: Each step commits independently. `reserve_before_payment=true` (default) reserves stock, then `process_payment` may fail or redirect (AwaitingPayment) — nothing in the executor releases the reservation on the failure path except each adapter's best-effort `release_on_failure` flag, with no central record of which compensations ran. `CreateOrderStep` + `CheckoutFinalizer` add a second phased commit (`finalization_phase`) without a documented recovery job for sessions stuck mid-finalization.
- Why It Matters: Stock stranded reserved, orders created without payment confirmation (or payments captured without orders) on crash between steps; operators cannot tell "failed clean" from "failed dirty".
- Recommended Fix: Add a compensating-action registry (each step declares `compensate(CheckoutSession)`; executor runs compensations in reverse on failure and records them in `step_states`), plus a `checkout:recover-stuck-sessions` command sweeping `PaymentProcessing`/`AwaitingPayment`/mid-`finalization_phase` sessions past TTL (session TTL already 24h in `defaults.session_ttl`).
- Breaking Change: NO (additive contract method with default no-op)
- Affected Packages: `checkout`, `inventory`, `orders`
- Required Dependent Changes: `ReserveInventoryStep`, `ProcessPaymentStep`, `CreateOrderStep` implement `compensate()`; docs `05-checkout-steps.md` updated.
- Migration Required: NO (record in existing `step_states` JSON)

### A-2 Order confirmation trusts unreconciled amounts (amount-tampering gap)
- Severity: Critical
- Location: `packages/checkout/src/Steps/CreateOrderStep.php::confirmPayment()` (`$amount = $paymentData['amount'] ?? $session->grand_total`), `::handle()` (`'grand_total' => $session->grand_total`), `Steps/ProcessPaymentStep.php::handle()` (`'amount' => $result->amount ?? $session->grand_total`), `Steps/CalculatePricingStep.php`, `CalculateShippingStep.php`, `CalculateTaxStep.php`, `Integrations/Payment/ChipProcessor.php`, `CashierChipProcessor.php`, `CashierProcessor.php`
- Problem: Three money sources — session `grand_total` (fillable, DB-stored), gateway `PaymentResult::amount`, and recomputed step totals — are merged with `??` fallbacks and never asserted equal before `confirmPayment()` fires `PaymentConfirmed` → `OrderPaid`. A partial capture, currency-mismatched purchase, or stale session total still creates/confirms the full order. `grand_total <= 0` short-circuits to `free_order` without asserting that discounts actually zeroed the total.
- Why It Matters: Under-payment confirms full orders; over-payment is captured without refund.
- Recommended Fix: Recompute the expected total from the pricing/shipping/tax step outputs at confirmation time and assert `payment.amount == session.grand_total == recomputed && currency == session.currency`; on mismatch, transition to `PaymentFailed`, do NOT create/confirm the order, and emit `CheckoutFailed` with the delta. Same assertion in `HandleCheckoutPaymentCallback` before completing.
- Breaking Change: NO (fail-closed on previously corrupt confirmations)
- Affected Packages: `checkout`, `orders`
- Required Dependent Changes: `CreateOrderStep`, `ProcessPaymentStep`, `HandleCheckoutPaymentCallback`, `ProcessCheckoutPaymentNotification` share one `AssertCheckoutAmount` service.
- Migration Required: NO

### A-3 Stripe secret read from another package's config (cross-package coupling)
- Severity: High
- Location: `packages/checkout/src/Webhooks/CheckoutSpatieSignatureValidator.php::verifyStripeSignature()` (`config('cashier.gateways.stripe.webhook_secret')`), `config/checkout.php::payment` (no `webhooks.secret` of its own), `src/CheckoutServiceProvider.php:257` (registers this validator for the checkout webhook route)
- Problem: Checkout's webhook security depends on `cashier` being installed with that exact key shape. Without `cashier`, `$secret` is null → fail-closed (all Stripe events rejected — safe but silently dead). Worse, `detectGateway()` sniffs `Stripe-Signature` header / `data.object` + `type` shape, so any future Stripe-shaped event sent to the checkout endpoint is verified against a secret owned elsewhere.
- Why It Matters: Security configuration with an undocumented cross-package dependency; rotation in `cashier` breaks `checkout` with no signal.
- Recommended Fix: Add `checkout.webhooks.stripe.secret` (env `CHECKOUT_STRIPE_WEBHOOK_SECRET`) and read only the checkout key. No fallback to `cashier` config (no backward-compat shim — cut over in one deploy: set the new env to the current Stripe secret value, deploy, then remove any `cashier`-owned reliance from checkout docs).
- Breaking Change: NO (new key; set the env before deploy — no fallback preserved)
- Affected Packages: `checkout`, `cashier`
- Required Dependent Changes: Deploy env docs; `CheckoutServiceProvider` validation asserts secret presence in production.
- Migration Required: NO

### A-4 Gateway detection by header/shape sniffing
- Severity: Medium
- Location: `CheckoutSpatieSignatureValidator.php::detectGateway()` (`X-Signature` → chip; `Stripe-Signature` → stripe; `reference`+`status` → chip; `data.object`+`type` → stripe; default → reject)
- Problem: Routing to the verifier is attacker-influenced (headers/body shape). Today both branches still require a valid signature, so this is defense-correct but fragile: any new gateway (or CHIP payload variant without `reference`) falls to `default => false` and is silently dropped, and a future "lenient" branch would inherit spoofable routing.
- Why It Matters: Silent webhook drops = paid-but-unconfirmed sessions (feeds A-1/A-2 fallout).
- Recommended Fix: Route by URL (per-gateway webhook paths already exist via `routes.webhook_path` + spatie config names) instead of sniffing; keep sniffing only as a logged fallback; emit a metric/log on `null` detection.
- Breaking Change: YES (webhook URL contract per gateway)
- Affected Packages: `checkout`, `chip`, `cashier-chip`
- Required Dependent Changes: `routes/checkout.php`, provider webhook configs, gateway dashboards' webhook URLs.
- Migration Required: NO

### A-5 Duplicated CHIP support cluster (own the seam, not the copy)
- Severity: Medium
- Location: `packages/checkout/src/Support/ChipPurchasePayloadBuilder.php`, `ChipPaymentStatusMapper.php` (duplicate of `chip/Support/ChipPaymentStatusMapper.php` — verified same class name/role), `ChipRefundGateway.php`, `ChipIntegrationRegistrar.php`, `HandleChipPurchaseEventForCheckout.php`, `CheckoutNotificationCallbackResolver.php`, vs `chip` equivalents
- Problem: Checkout reimplements payload building, status mapping, refund gateway, and event subscription for CHIP instead of calling `chip`/`cashier-chip` services. Three payment processors (`ChipProcessor` 123 lines, `CashierChipProcessor` 164, `CashierProcessor` 359) each wire the same three helpers.
- Why It Matters: Same drift hazard as the cashier/cashier-chip/chip triangle, now from the consumer side.
- Recommended Fix: Delete checkout's mapper copy (use chip's), collapse payload building into `chip` `PurchaseBuilder` (checkout passes session-derived DTOs), collapse refund into the gateway processors' `refund()`; keep `ChipIntegrationRegistrar` as the single event↔checkout bridge with webhook-precedence documented (see chip audit A-5).
- Breaking Change: YES (support class imports change)
- Affected Packages: `checkout`, `chip`, `cashier-chip`
- Required Dependent Changes: The three processors + registrar.
- Migration Required: NO

### A-6 Three processors, no recorded precedence with the default/priority split
- Severity: Medium
- Location: `packages/checkout/src/Services/PaymentGatewayResolver.php` (ctor default priority `['cashier','cashier-chip','chip']`), `config/checkout.php::payment` (`default_gateway='chip'`, `gateway_priority=['chip','cashier-chip','cashier']`), `Support/RegisterBuiltInPaymentProcessors.php`, `Support/RegisterTaggedPaymentProcessors.php`, `Integrations/Payment/*.php`
- Problem: Config and code disagree on the default order; `resolve(null)` uses the ctor default while operators tune `payment.gateway_priority` expecting effect. `CashierProcessor` (359 lines, multi-gateway inside a processor!) overlaps `CashierChipProcessor` + `ChipProcessor` rather than composing them.
- Why It Matters: "Set default to cashier-chip" does nothing in some boot orders; payment goes to the wrong gateway.
- Recommended Fix: Resolver reads `payment.default_gateway` + `payment.gateway_priority` from config at resolve time (no ctor-cached priority); shrink `CashierProcessor` to Stripe-only and let the resolver pick among three single-gateway processors.
- Breaking Change: YES (gateway selection behavior changes to match documented config)
- Affected Packages: `checkout`
- Required Dependent Changes: `CheckoutServiceProvider` wiring + `06-payment-gateways.md` docs.
- Migration Required: NO

### A-7 Callback token never expires; session lookup unauthenticated pre-token
- Severity: Medium
- Location: `Steps/ProcessPaymentStep.php::ensureCallbackToken()` (`Str::random(40)`, stores `callback_token_created_at` but nothing reads it), `Http/Controllers/PaymentCallbackController.php:112-123` (`withoutOwnerScope()->whereKey(sessionId)` then token compare), `Actions/HandleCheckoutPaymentCallback.php`, `Support/CheckoutCallbackStatePolicy.php`
- Problem: Token has a birth timestamp that is never enforced — a leaked success URL stays valid for the session's lifetime. The controller loads the session by UUID before checking the token (correct order would be constant-time compare immediately, and the current code is fine on that point, but the UUID is enumerable-adjacent and the token is the sole secret).
- Why It Matters: Replayable success callbacks; stale redirect links completable days later.
- Recommended Fix: Enforce expiry (`callback_token_created_at` + `checkout.payment.callback_token_ttl`, default ≤ 24h matching session TTL) and single-use consume on success; rate-limit callback attempts per session.
- Breaking Change: NO (old tokens past TTL now rejected — intended)
- Affected Packages: `checkout`
- Required Dependent Changes: `PaymentCallbackController` + `HandleCheckoutPaymentCallback` + success/failure/cancel views copy.
- Migration Required: NO

## Code Quality Findings
### C-1 `ProcessPaymentStep` payment-attempt increment is not concurrency-safe
- Severity: Medium
- Location: `Steps/ProcessPaymentStep.php::handle()` (`$session->update(['payment_attempts' => $session->payment_attempts + 1])`), config `payment.retry_limit=3` (never enforced in this step — grep shows no read of `retry_limit` here)
- Problem: Read-modify-write on `payment_attempts` races under double-submit; the configured retry limit is not checked, so `retryPayment()` can exceed it.
- Why It Matters: Unlimited payment attempts → duplicate purchases (compounds chip A-1).
- Recommended Fix: Atomic `increment('payment_attempts')` + enforce `retry_limit` before creating a new purchase (reuse payable purchase while attempts remain).
- Breaking Change: NO
- Affected Packages: `checkout`
- Required Dependent Changes: None.
- Migration Required: NO

### C-2 `extractSessionId()` trusts multiple unverified reference paths
- Severity: Medium
- Location: `Actions/ProcessCheckoutPaymentNotification.php::extractSessionId()` (`reference`, `metadata.checkout_session_id`, `data.object.metadata.checkout_session_id`, `data.object.client_reference_id`)
- Problem: Four fallback lookup keys widen the spoof surface; a forged Stripe `client_reference_id` pointing at a victim session is only safe because the Spatie validator runs first on the webhook route — but `handle()` is also reachable via internal dispatch where that guarantee is less visible.
- Why It Matters: Session-confusion across gateways (chip `reference` vs Stripe `client_reference_id` namespaces collide).
- Recommended Fix: Namespace references per gateway at create time (`chk_{uuid}` with gateway prefix recorded in `payment_data`), accept only the namespaced form, and re-assert `selected_payment_gateway` matches the notifying gateway (the `expectedGateways` check exists — make it mandatory, not optional).
- Breaking Change: YES (reference format)
- Affected Packages: `checkout`, `chip`, `cashier-chip`
- Required Dependent Changes: All three processors' payload builders + `gatewayMatches()` call sites.
- Migration Required: NO (in-flight sessions drain; old unprefixed references rejected after deploy — announce window)

### C-3 Dead/optional steps registered by default
- Severity: Low
- Location: `config/checkout.php::steps.enabled` (`create_event_registrations`, `issue_event_passes` default true), `Support/RegisterCheckoutOptionalSteps.php`, `Steps/DispatchDocumentGenerationStep.php`
- Problem: Event-ticketing steps run for every checkout even on installs without the events/ticketing packages (presumably no-op via guards, but every order pays the registry/lookup cost and operators wonder why ticketing steps appear in `step_states`).
- Why It Matters: Noisy step state; hidden coupling to optional packages.
- Recommended Fix: Default optional steps to `class_exists`-gated auto-enable (pattern already used elsewhere: `cashier` suggests + `class_exists` checks) instead of hard `true`.
- Breaking Change: NO
- Affected Packages: `checkout`, `events`/`ticketing` (optional)
- Required Dependent Changes: Docs `05-checkout-steps.md`.
- Migration Required: NO

## Laravel-Specific Findings
- L-1 (Compliant): PHP `^8.4`; uuid PK; `getTable()` from config with prefix; `json_column_type` configurable; `timestampsTz`; `CarbonImmutable`; no `SoftDeletes`; `HasStates` with deliberate `initializeHasStates()` override (documented inline — keep, but cover with a regression test since it fights the trait).
- L-2 (Low): Hard `require` on `aiarmada/cart|customers|docs|orders|pricing|products|shipping` makes checkout the heaviest install in the repo — correct for an orchestrator, but confirm each is actually referenced (docs: only `GenerateCheckoutDocumentsJob`? products: only `EnsureCheckoutOfferProduct`?). Demote any single-use integration to `suggest` + `class_exists` guard.
- L-3 (Low): `Transformers/NullSessionDataTransformer.php` as default billing/shipping transformer is a silent no-op — log when the null transformer runs in production so missing-transformer misconfig is visible.

## Filament Adapter Findings
- N/A — standalone package. No `filament-checkout` exists and none is recommended: checkout is correctly headless (session API + redirect + webhook), and admin visibility belongs to `orders` (`filament-orders`) reading created orders, not to a checkout-session admin. Do NOT create a Filament adapter; if session observability is needed, add a read-only section to the existing orders admin rather than a new package.

## Database Findings
- D-1 (Compliant): uuid PK; `foreignUuid('customer_id')->nullable()->index()`, `nullableUuidMorphs('billable'|'owner')`, `foreignUuid('order_id')->nullable()` with zero constraints (grep-verified); `unsignedBigInteger` totals + `unsignedSmallInteger payment_attempts`; `timestampTz` lifecycle columns (`expires_at/completed_at/cancelled_at/payment_failed_at`) follow the naming rules; indexes on `(status, created_at)`, `(customer_id, status)`, `expires_at`.
- D-2 (Low): `cart_id`/`payment_id` are plain strings (external ids) — correct, but add app-level format validation (uuid for sessions, gateway-prefixed for payments per C-2).
- D-3 (Info): `payment_data`/`pricing_data`/`discount_data`/`tax_data` JSON snapshots are the audit trail for A-2 assertions — never prune them; document retention alongside the 24h session TTL.

## Model / Domain Findings
- M-1: `CheckoutSession` status machine (`Pending → PaymentProcessing → AwaitingPayment → Processing → Completed`, plus `PaymentFailed/Expired/Cancelled`) + `finalization_phase` two-phase tail is the right shape — but the executor (A-1) and amount assertion (A-2) must be fixed before the machine can be trusted.
- M-2: `CheckoutService::withSessionOwnerContext()` + `ResolveCustomerStep`/`PersistCustomerStep` owner handling + `GenerateCheckoutDocumentsJob implements OwnerScopedJob` is the correct multitenancy pattern — extend the same discipline to `ProcessCheckoutPaymentNotification::gatewayMatches()` (currently `withoutOwnerScope()` then manual compare; re-enter session owner context before acting).
- M-3: Money representation is correct everywhere (minor-unit ints, `MYR` default, `MoneyNormalizer` at boundaries) — the finding is verification, not representation.

## Security Findings
- S-1 (Critical): A-2 amount confirmation — fix first.
- S-2 (High): A-3 secret coupling + A-4 sniffing + A-7 token TTL — fix as one webhook/callback hardening pass.
- S-3 (Medium): `C-2` reference namespacing; `PaymentCallbackController` GET handlers must be idempotent (double-click/refresh replays success) — verify `CheckoutCallbackStatePolicy` rejects already-`Completed` sessions instead of re-confirming payment.
- S-4 (Low): `EnsureCheckoutOfferProduct` runs `OwnerContext::withOwner(null, ...)` global write to publish a product — privileged global path; confirm it is gated to admin-initiated flows only, per global-write rules.

## Performance Findings
- P-1: Step-history writes (`setStepState` + `update(['current_step'])` per step) are chatty but fine; do not add per-step transactions (would worsen lock time) — compensations (A-1) over transactions.
- P-2: `ValidatePromoCodeAction` + `DiscountCompositionService` + `VouchersAdapter` (352 lines) run before pricing — cache voucher lookups per session to avoid re-validating on every retry.

## Testing Findings
- T-1 (Medium): Zero tests for the money pipeline. Demoted from Critical per severity rubric (test absence is a testability gap — Medium — however severe the untested path; the path bugs themselves already carry A-2 Critical / A-1 High). Minimum Pest suite (`--parallel`): happy-path session→order, free-order path, amount-mismatch blocks confirmation, retry-limit enforcement, callback token expiry + replay, webhook per-gateway routing, compensation on payment failure (inventory released, no order), owner isolation (cross-tenant session read denied), Stripe-secret-missing fail-closed.
- T-2: Fixtures needed: fake cart, fake processors (tagged `checkout.payment_processors`), spatie webhook fakes per gateway.

## Cross-Package Dependency Impact
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `orders` | `CreateOrderStep` payload, `confirmPayment` → `PaymentConfirmed` → `OrderPaid` | A-2 changes confirmation contract | Accept amount-assertion failures as first-class (no order, `PaymentFailed`) |
| `cart` | `CheckoutCartResolver`, cart snapshot | C-3/reference format | Stable snapshot schema; namespaced references |
| `shipping` | `ShippingAdapter`, `CalculateShippingStep` | Resolver priority/config change (A-6) | None (adapter interface stable) |
| `tax` | `TaxAdapter`, `CalculateTaxStep` | Tax owner-context fix (tax audit A-1) flows through | Pass session owner into tax context |
| `inventory` | `InventoryAdapter`, reserve/release | A-1 compensation contract | Implement `compensate()`; recovery sweep coordination |
| `cashier` / `cashier-chip` / `chip` | three processors + validators + events | A-3/A-4/A-5/A-6/C-2 reshape wiring | Single-gateway processors, namespaced refs, canonical mapper |
| `customers` | `ResolveCustomerStep`, `PersistCustomerStep` | Owner-context discipline | None (already correct pattern) |
| `vouchers` / `promotions` | `VouchersAdapter`, `PromotionsAdapter`, `DiscountCodeResolver` | None structural | Cache + docs |

## Recommended Refactor Plan
1. Amount assertion before confirm (A-2) + atomic attempts/retry-limit (C-1) — money first.
2. Atomicity: compensations + stuck-session recovery (A-1).
3. Webhook/callback hardening: checkout-owned Stripe secret, URL routing, token TTL + single-use, namespaced refs, mandatory gateway match (A-3, A-4, A-7, C-2).
4. Processor collapse: single-gateway processors, config-driven priority, shared CHIP seams (A-5, A-6).
5. Demote unused hard requires; null-transformer logging (L-2, L-3).
6. Pest suite for the pipeline (T-1).

## Files Likely to Change
- `packages/checkout/src/Services/StepExecutor.php`, `CheckoutService.php`, `PaymentGatewayResolver.php`, `CheckoutStepRegistry.php`
- `packages/checkout/src/Steps/ProcessPaymentStep.php`, `CreateOrderStep.php`, `CalculatePricingStep.php`, `CalculateShippingStep.php`, `CalculateTaxStep.php`, `ReserveInventoryStep.php`, `ApplyDiscountsStep.php`
- `packages/checkout/src/Actions/HandleCheckoutPaymentCallback.php`, `ProcessCheckoutPaymentNotification.php`, `CheckoutFinalizer.php`
- `packages/checkout/src/Integrations/Payment/ChipProcessor.php`, `CashierChipProcessor.php`, `CashierProcessor.php`, `ShippingAdapter.php`, `TaxAdapter.php`, `VouchersAdapter.php`
- `packages/checkout/src/Support/ChipPurchasePayloadBuilder.php`, `ChipPaymentStatusMapper.php`, `ChipRefundGateway.php`, `RegisterBuiltInPaymentProcessors.php`, `CheckoutCallbackStatePolicy.php`, `CheckoutNotificationCallbackResolver.php`
- `packages/checkout/src/Webhooks/CheckoutSpatieSignatureValidator.php`, `ProcessCheckoutWebhook.php`, `Http/Controllers/PaymentCallbackController.php`, `Http/Controllers/CheckoutWebhookController.php`
- `packages/checkout/config/checkout.php`, `routes/checkout.php`, `src/CheckoutServiceProvider.php`

## Files / Code That Should Be Removed
- `packages/checkout/src/Support/ChipPaymentStatusMapper.php` (verified duplicate of `chip/src/Support/ChipPaymentStatusMapper.php`; keep chip's).
- `packages/checkout/src/Support/ChipPurchasePayloadBuilder.php` + `ChipRefundGateway.php` after folding into `chip` builder / gateway `refund()` (verified thin wrappers used only by the two CHIP processors).
- `config/checkout.php::payment.gateway_priority` vs ctor-default divergence — delete one source (keep config; verified `PaymentGatewayResolver` ctor defaults `['cashier','cashier-chip','chip']` contradicting config `['chip','cashier-chip','cashier']`).
- `Steps/CreateOrderStep.php` `?? $session->grand_total` fallbacks (verified) — replace with assertion (A-2), no silent fallback.
- No migration files removed. No legacy shims preserved. No Filament adapter created.

## Final Recommended Architecture
Checkout stays a headless orchestrator: registry + executor with compensations, single-gateway processors resolved from config, one amount-assertion service gating order creation/confirmation, URL-routed webhooks with package-owned secrets, expiring single-use callback tokens, and namespaced references. Domain math stays in `cart`/`pricing`/`shipping`/`tax`; charges stay in `cashier`/`cashier-chip`/`chip`; persistence stays in `orders`/`inventory`. No new packages, no Filament adapter.
