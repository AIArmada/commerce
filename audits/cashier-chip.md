# Cashier-Chip Audit

## Packages Reviewed
- `aiarmada/cashier-chip` (`packages/cashier-chip`): `src/` (Actions, Billing, Concerns×10, Console, Contracts, Enums, Events, Exceptions, Facades, Invoice, Invoices, Listeners, Payment, Subscription, Testing), `config/cashier-chip.php`, `database/migrations/` (4) + `factories/` (2), `resources/views/invoice.blade.php`, `composer.json`, `src/CashierChipServiceProvider.php`, `CONTEXT.md`/`README.md`/`docs/` (13 docs)
- `aiarmada/filament-cashier-chip` (`packages/filament-cashier-chip`): `src/` (Concerns, CustomerPortal, Resources, Support, Widgets), `config/filament-cashier-chip.php`, `resources/views + lang/en + lang/ms`, `composer.json`, `CONTEXT.md`/`README.md`/`docs/`
- No `tests/` directory (verified) — despite `src/Testing/FakeChipClient.php`, `FakeChipCollectService.php`.

## Overall Assessment
- Quality: The most complete billing implementation of the three payment units (real persistence, lease-based renewal, coupon/voucher bridge, invoice rendering, customer portal). Concerns are well-factored; `ClaimRenewalAttempt` + `RenewSubscriptionsCommand` + `OwnerBatchRunner` is the right shape for multi-tenant cron renewals.
- Health: Fair. One Critical renewal-correctness bug (monthly `period_key` blocks sub-monthly billings), one High amount-integrity gap (renewal/charge amounts recomputed from mutable items with no price lock), plus the three-way duplication with `cashier`/`chip` documented in the cashier audit.
- Risks: Double-charge or missed-charge on weekly/custom intervals; `Subscription::$with = ['items']` performance tax on every query including the renewal chunk loop; silent voucher no-op when `aiarmada/vouchers` is absent.
- Refactor size: Medium-Large. ~12–16 files. No migration required for the recommended path (one optional unique index noted).

## Migration Impact
**Migration Required: NO**
No required table/column/index/constraint changes. The recommended fixes are code-only. Optional hardening index noted below is explicitly NOT required (lease correctness is fixed by key granularity, not by a constraint).

| Table | Change | Detail |
|---|---|---|
| `cashier_chip_subscriptions`, `cashier_chip_subscription_items`, `cashier_chip_payment_methods`, `cashier_chip_renewal_attempts` | none | uuid PKs kept; plain `uuid->index()` FK-style columns kept with app-level cascades (`Subscription::booted()` deletes items); no FK constraints added (rule-compliant); no data migration |

## Package Responsibilities
- Owns: CHIP recurring billing (subscriptions, items, stored payment methods/recurring tokens, renewal attempts with leases), one-off charges (`ChargeChipCustomer`), refunds (`RefundChipPayment`), purchase-status sync (`SyncChipPurchaseStatus`), coupon/discount bridge to vouchers, invoice model + renderer, renewal cron, webhook listeners (`HandlePurchasePaid/Preauthorized/PaymentFailure`), `Billable` trait surface.
- Filament adapter owns: customer/invoice/subscription resources, billing-portal pages, MRR/churn/trial widgets. Must stay thin over Actions/models.

## Architecture Findings
### A-1 Renewal `period_key` granularity blocks sub-monthly billing (double-charge/missed-charge)
- Severity: Critical
- Location: `packages/cashier-chip/src/Actions/ClaimRenewalAttempt.php::handle()` (`$periodKey = $subscription->next_billing_at->format('Y-m')`), `packages/cashier-chip/src/Console/RenewSubscriptionsCommand.php::processRenewals()` + `::executeAttempt()`, `packages/cashier-chip/src/Subscription/RenewalAttempt.php`
- Problem: The idempotency key for a renewal is month-granular. Any `billing_interval` finer than monthly (weekly, custom days) claims at most one renewal per calendar month: the second weekly renewal in the same month finds the existing `claimed` attempt with a live lease and returns null → skipped as duplicate (missed charge). Conversely, after the lease expires mid-month, a retry can claim a second attempt for the same period with no unique guard → double charge. The `next_billing_at->isFuture()` recheck inside the lock mitigates but does not fix the key collision.
- Why It Matters: This is the recurring-revenue path — wrong granularity directly causes under- or over-billing.
- Recommended Fix: Key on the actual billing period: `period_key = next_billing_at->format('Y-m-d') . '|' . billing_interval . '|' . billing_interval_count` (or the period-start date derived from `currentPeriodStart()`), and advance `next_billing_at` inside the same transaction that creates the attempt. Keep the `lockForUpdate()` + `DB::transaction(..., 3)` shape.
- Breaking Change: NO (period_key values change going forward; old rows age out via lease expiry)
- Affected Packages: `cashier-chip`
- Required Dependent Changes: `RenewSubscriptionsCommand` reporting only; `filament-cashier-chip` renewal widgets read status, not keys.
- Migration Required: NO

### A-2 Renewal/charge amounts recomputed from mutable items with no price lock (amount integrity)
- Severity: High
- Location: `packages/cashier-chip/src/Subscription/Subscription.php::calculateSubscriptionAmount()` (`$this->items->sum(unit_amount * quantity)`), `Actions/ClaimRenewalAttempt.php` (`'amount_minor' => $subscription->calculateSubscriptionAmount()`), `Actions/ChargeChipCustomer.php::handle()` (`addProductCents($productName, $amount)` with caller-supplied `$amount`), `Concerns/PerformsCharges.php::charge()/createPayment()/checkout()`
- Problem: The renewal amount is snapshotted from the live `items` relation at claim time — a concurrent `swap()`/`addPrice()`/`updateQuantity()` between claim and `ChargeChipCustomer` changes what the customer pays, and `executeAttempt()` never re-validates `attempt->amount_minor` against the subscription total. One-off `charge($amount)` takes any int with no lower/upper bound check visible at the Action layer (negative/zero/huge amounts flow to `PurchaseBuilder::addProductCents()`).
- Why It Matters: Amount tampering / race between plan change and renewal charge; supports the checkout amount-mismatch gap (checkout trusts its own `grand_total` while the gateway charges a separately computed number).
- Recommended Fix: Freeze `amount_minor` + currency at claim time and assert equality before charging (abort + re-claim on mismatch); add amount guards in `ChargeChipCustomer` (`$amount > 0`, upper bound from config) and validate `unit_amount` presence in `calculateSubscriptionAmount()` (fail closed on null instead of treating as 0).
- Breaking Change: NO (fail-closed on previously ambiguous inputs)
- Affected Packages: `cashier-chip`, `checkout` (`Integrations/Payment/CashierChipProcessor.php`)
- Required Dependent Changes: `CashierChipProcessor` must surface amount-mismatch as retryable payment failure, not order creation.
- Migration Required: NO

### A-3 Three-way payment duplication (`cashier` ↔ `cashier-chip` ↔ `chip`)
- Severity: High
- Location: `packages/cashier-chip/src/Billing/Cashier.php` (391 lines: `chip()`, `findBillable()`, `formatAmount()`, fake/octane helpers) vs `packages/cashier/src/Cashier.php` (same method names); `Billing/Billable.php` + 10 `Concerns/*` vs `cashier` `Concerns/Billable.php` + `Gateways/Chip/*`; `Payment/Payment.php` (346 lines) vs `chip` `PaymentData`/`PurchaseData`; `Invoice/Invoice.php` vs `cashier` `Gateways/Chip/ChipInvoice.php`
- Problem: Same concepts, three implementations. `cashier/Gateways/ChipGateway.php` calls into `CashierChip\Billing\Cashier::findBillable()` and `Cashier::chip()` while also wrapping results in its own `Chip*` adapters — a change to CHIP customer/payment semantics must land in all three units (see cashier audit A-3, which directs the collapse from the other side).
- Why It Matters: Highest maintenance cost in the repo; behavior drift between the unified gateway and the native CHIP path is already visible (different 404 handling, different public-key paths, different `formatAmount` copies).
- Recommended Fix: Declare `cashier-chip` the canonical CHIP billing owner. `cashier/ChipGateway` becomes delegation-only (no `Gateways/Chip/*` logic); `chip` stays the HTTP/API owner. Delete one copy of `formatAmount` (keep `commerce-support` formatter), one copy of status mapping, one copy of `findBillable`.
- Breaking Change: YES (adapter class consolidation, same as cashier A-3)
- Affected Packages: `cashier-chip`, `cashier`, `chip`, `checkout`, `filament-cashier`, `filament-cashier-chip`
- Required Dependent Changes: Import updates across checkout processors + both Filament adapters.
- Migration Required: NO

### A-4 `Subscription::$with = ['items']` taxes every query
- Severity: Medium
- Location: `packages/cashier-chip/src/Subscription/Subscription.php:132` (`protected $with = ['items']`), `RenewSubscriptionsCommand::processRenewals()` (`select('id')` + `chunkById` then per-subscription re-query with `billable`)
- Problem: Every subscription query — including the renewal chunk loop that only needs ids — hydrates all items. The renewal loop then N+1s (`executeAttempt` loads `subscription()->with('billable')` per attempt) and `calculateSubscriptionAmount()` sums the already-loaded items again.
- Why It Matters: Renewal cron cost scales with items × subscriptions; admin lists pay the same tax.
- Recommended Fix: Remove `$with`, eager-load explicitly where needed (`with('items')` in portal/invoice paths, `with('billable')` in renewal execution via chunk-with); keep `chunkById` selecting full rows needed for the claim check or batch the claim differently.
- Breaking Change: NO (lazy loads on access; add explicit `with()` at the ~5 call sites that iterate items)
- Affected Packages: `cashier-chip`, `filament-cashier-chip`
- Required Dependent Changes: Audit `hasProduct/hasPrice/calculateSubscriptionAmount/discounts` call sites for explicit eager loads.
- Migration Required: NO

### A-5 Voucher bridge silently no-ops without the package
- Severity: Medium
- Location: `packages/cashier-chip/src/Subscription/Subscription.php::retrieveCoupon()` (`if (! class_exists(VoucherService::class)) return null`), `::recordCouponUsage()` (same guard), `Billing/Coupon.php`, `Billing/Discount.php`, `Billing/PromotionCode.php`, `Concerns/AllowsCoupons.php`
- Problem: `aiarmada/vouchers` is only `suggest`ed, so on installs without it `applyCoupon()` throws `InvalidCoupon::notFound` (correct) but `recordCouponUsage()` silently returns (usage never recorded) — and any path that calls `discount()` degrades to null without telling the operator why.
- Why It Matters: Discounts appear to apply but are never tracked, or coupon application fails with a misleading "not found" for a correctly-typed code.
- Recommended Fix: Fail fast at boot (provider `bootingPackage()` check with clear message when coupon features are used) or move the voucher bridge behind an explicit `cashier-chip.integrations.vouchers.enabled` flag defaulting to "auto-detect but loud" (log warning with the missing package name).
- Breaking Change: NO
- Affected Packages: `cashier-chip`, `vouchers` (optional)
- Required Dependent Changes: Docs (`docs/04-usage.md`, `09-subscriptions.md`) state the requirement.
- Migration Required: NO

### A-6 Renewal cron strips all global scopes then re-adds one
- Severity: Low
- Location: `packages/cashier-chip/src/Console/RenewSubscriptionsCommand.php::processRenewals()` (`Subscription::withoutGlobalScopes()` then `(new Subscription)->scopeForOwner($query)`)
- Problem: `withoutGlobalScopes()` removes everything (including future safety scopes), only to re-add the owner scope manually. Should be `withoutOwnerScope()`-style narrowing or just a plain owner-scoped query via `OwnerBatchRunner` (which already iterates owners — the manual `scopeForOwner` inside is redundant with `batchRunner()->run()`).
- Why It Matters: Fragile against new global scopes; double-scoping confusion.
- Recommended Fix: Rely on `OwnerBatchRunner` iteration; query with the runner-provided owner context only.
- Breaking Change: NO
- Affected Packages: `cashier-chip`
- Required Dependent Changes: None.
- Migration Required: NO

## Code Quality Findings
### C-1 Mutable Carbon in the coupon bridge
- Severity: Low
- Location: `packages/cashier-chip/src/Billing/Coupon.php:10` (`use Carbon\Carbon`), `Billing/Discount.php:8` (same), vs every other file using `CarbonImmutable`
- Problem: Two files import mutable `Carbon` in a codebase standardized on `CarbonImmutable` (repo rule). Any mutation of a shared instance leaks across the subscription lifecycle.
- Why It Matters: Octane + mutable dates = cross-request contamination.
- Recommended Fix: Switch both to `CarbonImmutable`.
- Breaking Change: NO
- Affected Packages: `cashier-chip`
- Required Dependent Changes: None.
- Migration Required: NO

### C-2 `swap()`/`addPrice()` id generation + mass deletion
- Severity: Medium
- Location: `Subscription.php::swap()` (`$this->items()->delete()` then `createTrustedSubscriptionItem` with `'chip_id' => 'si_'.uniqid().'_'.time()`), `::addPrice()` (same id scheme)
- Problem: `swap()` deletes all items before creating replacements — a mid-transaction failure after delete relies entirely on DB rollback (it is inside `DB::transaction`, so safe, but the code reads as destructive-first). `uniqid().time()` ids are predictable and collide under high concurrency on the same microsecond.
- Why It Matters: Item ids are referenced by renewal attempts/refunds; predictable ids aid enumeration.
- Recommended Fix: Use `Str::uuid()`/`orderedUuid()` for `chip_id`; create-then-prune ordering inside the existing transaction.
- Breaking Change: NO
- Affected Packages: `cashier-chip`
- Required Dependent Changes: None.
- Migration Required: NO

### C-3 `latestPayment()`/`upcomingInvoice()`/`latestInvoice()`/`invoices()` are null stubs
- Severity: Medium
- Location: `Subscription.php::latestPayment()` (returns null), `::upcomingInvoice()`, `::latestInvoice()`, `::invoices()` (returns `collect()`)
- Problem: Public API promises invoice/payment history but returns empty — `filament-cashier-chip` invoice pages and `upcomingInvoice` displays render blank instead of erroring, misleading operators.
- Why It Matters: Dead surface that looks alive; checkout/CashierProcessor callers may branch on null as "no payment due".
- Recommended Fix: Implement against `RenewalAttempt` + `chip` purchase history, or delete the four methods and update callers/docs.
- Breaking Change: YES if deleted
- Affected Packages: `cashier-chip`, `filament-cashier-chip`, `cashier`
- Required Dependent Changes: Filament invoice/portal pages.
- Migration Required: NO

## Laravel-Specific Findings
- L-1 (Compliant): PHP `^8.4`; uuid PKs; `HasUuids`; `getTable()` from `cashier-chip.database.*`; `timestampsTz`; `CarbonImmutable` except C-1; no `SoftDeletes`; `HasFactory` + factories present (but no tests use them).
- L-2 (Low): `CashierChipServiceProvider` — verify it registers `RenewSubscriptionsCommand` + `WebhookCommand`, publishes config, and (like `cashier`) snapshots Octane statics (`rememberOctaneDefaults` exists on `Billing/Cashier` — confirm the provider calls it + restores on `RequestReceived`).
- L-3 (Info): `resources/views/invoice.blade.php` + `Invoices/DocsInvoiceRenderer.php` duplicate invoice PDF responsibility with `chip/Support/BuildChipDocData.php` + docs package — pick one renderer (see chip audit).

## Filament Adapter Findings
- Thin-adapter check: MIXED. `SubscriptionResource`/`InvoiceResource`/`CustomerResource` correctly wrap domain models, but `Concerns/InteractsWithCashierChipData.php` + `Support/FormatsSubscriptionStatus.php` re-derive subscription status for badges instead of calling `Subscription::active()/pastDue()/onTrial()` — status drift risk (mirrors cashier-adapter leak).
- Domain leak: Widgets (`MRRWidget`, `ChurnRateWidget`, `RevenueChartWidget`, `ActiveSubscribersWidget`, `AttentionRequiredWidget`, `TrialConversionsWidget`, `SubscriptionDistributionWidget`) recompute billing aggregates in UI queries rather than calling domain services — move MRR/churn math into `cashier-chip` support classes and have both Filament adapters call them (kills the `filament-cashier` vs `filament-cashier-chip` widget duplication too).
- Duplication: `CustomerPortal/*` (BillingDashboard, Invoices, PaymentMethods, Subscriptions) overlaps `filament-cashier/CustomerPortal/*` page-for-page for CHIP customers — keep one portal per audience (see cashier audit).
- Dependency direction: Correct (`filament-cashier-chip` → `cashier-chip`).
- Navigation: COMPLIANT. Nested `navigation.group` in `config/filament-cashier-chip.php`; `BaseCashierChipResource::getNavigationGroup()` + all portal pages read config; no static `$navigationGroup`.

## Database Findings
- D-1 (Compliant): 4 migrations, uuid PKs, `nullableUuidMorphs`-style owner columns where tenant-owned (`Subscription`, `SubscriptionItem` use `HasOwner`; verify `StoredPaymentMethod`/renewal attempts match), no `constrained()`/`cascadeOnDelete()` (grep-verified), `json_column_type` configurable, `down()` absent (consistent with shipping/checkout convention — fine).
- D-2 (Low): `Subscription::booted()` app-level cascade (`items()->delete()`) is correct per rules; extend the same explicitness to any `StoredPaymentMethod` → token rows if they exist.
- D-3 (Info): `SubscriptionFactory`/`SubscriptionItemFactory` exist — wire them into the new Pest suite.

## Model / Domain Findings
- M-1: `Subscription` lifecycle columns (`trial_ends_at/started_at`, `next_billing_at`, `ends_at/canceled_at`, `paused_at`, `past_due_at`, `renewed_at`) follow the timestamp rules; `chip_status` enum + transition methods (`cancel/cancelAt/cancelNow/resume/pause/unpause/extendTrial/skipTrial`) keep state-to-timestamp mapping centralized — good.
- M-2: `billable_type/billable_id` + `owner_type/owner_id` dual morphs are intentional (customer vs tenant) — keep, and keep the `booted()` cross-tenant billable-owner validation (`validate_billable_owner`) which is the strongest multitenancy enforcement of the three payment units.
- M-3: `coupon_*` columns denormalize voucher state onto the subscription — acceptable cache, but `coupon_discount` (int minor units — correct) must be recomputed on renewal, not carried forward blindly (ties to A-2 freeze).

## Security Findings
- S-1 (Medium): Same root cause as A-2 (no amount validation at the Action boundary) — see A-2 for the fix. Demoted from High to avoid double-counting one gap in two sections. Security-specific residue: reject `unit_amount: null` items in renewal math (fail closed) and add `$amount > 0` + max guards in `ChargeChipCustomer` / `PerformsCharges::charge/createPayment`.
- S-2 (Medium): `RateLimiter::attempt('cashier-chip:charge:{chipId|key}', 30/min)` keys on chip id falling back to primary key — two billables sharing a chip id (data error) share a bucket; include billable morph in the key.
- S-3 (Medium): `#[SensitiveParameter]` on recurring tokens is good — extend the audit to ensure tokens never land in `renewal_attempts` payload columns, exception messages, or `Log::` context in `RenewSubscriptionsCommand::executeAttempt` failure paths.
- S-4 (Low): `webhooks.secret` (`CHIP_WEBHOOK_SECRET`) + `verify_signature` toggle — same shape as `chip`; document that these must be the same value in split deployments (or better: read once from `chip` config — see chip audit).

## Performance Findings
- P-1: Remove `$with=['items']` (A-4); batch renewal execution; add `whereActive()->whereNotNull('next_billing_at')` composite index if the renewal scan slows (migration only if measured — not recommended now).
- P-2: `Subscription::invoices()` stub returning `collect()` hides a future N+1 — implement with a bounded query when built.

## Testing Findings
- T-1 (Medium): Zero tests despite shipping fakes (`FakeChipClient`, `FakeChipCollectService`, `Testing/README.md`). Demoted from High per severity rubric (testability gaps are Medium). The fakes prove testability was intended — use them. Priority Pest suite (`--parallel`): claim-lease exclusivity (two workers, one wins), sub-monthly periods (A-1 regression), amount-freeze on concurrent swap (A-2), charge guards, coupon apply/remove, cross-tenant isolation (`OwnerScopingContractTests`), renewal cron dry-run vs execute.
- T-2: Factories exist — no excuse for slow tests; keep them fast and parallel.

## Cross-Package Dependency Impact
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `cashier` (`Gateways/ChipGateway.php`) | `Billing\Cashier::findBillable/findSubscriptionForWebhook/chip()`, `Subscription` model | A-1/A-2/A-3 change claim keys, amounts, class surface | Delegate-only gateway; pass owner; handle amount-mismatch errors |
| `checkout` (`Integrations/Payment/CashierChipProcessor.php`) | `Cashier::chip()`, charge/refund, payload builder | Same | Surface amount-mismatch; verify charged amount == session total |
| `chip` | `ChipCollectService` purchase/charge/refund APIs | Shared API evolution | Coordinate `addProductCents` guards |
| `vouchers` (optional) | `VoucherService::find/recordUsage` | A-5 fail-loud change | Document requirement; handle missing-package error |
| `filament-cashier` | parallel portal/widgets | Dedup decision | One portal + one widget set per audience |
| `docs` | invoice rendering | Renderer choice | Point at one `InvoiceRenderer` |

## Recommended Refactor Plan
1. Fix renewal period key + amount freeze/guards (A-1, A-2, S-1) — revenue correctness first.
2. Collapse three-way duplication toward `cashier-chip`-canonical CHIP billing (A-3) with `cashier` audit A-3.
3. Remove `$with`, fix cron scoping, fail-loud vouchers (A-4, A-6, A-5).
4. Mutable-Carbon swap, uuid item ids, stub-method decision (C-1, C-2, C-3).
5. Shared MRR/churn services for both Filament adapters; Pest suite with fakes (T-1).

## Files Likely to Change
- `packages/cashier-chip/src/Actions/ClaimRenewalAttempt.php`, `Actions/ChargeChipCustomer.php`, `Actions/CreateChipSubscription.php`, `Actions/CancelChipSubscription.php`, `Actions/RefundChipPayment.php`, `Actions/SyncChipPurchaseStatus.php`
- `packages/cashier-chip/src/Subscription/Subscription.php`, `SubscriptionItem.php`, `SubscriptionBuilder.php`, `RenewalAttempt.php`
- `packages/cashier-chip/src/Concerns/PerformsCharges.php`, `ManagesSubscriptions.php`, `AllowsCoupons.php`
- `packages/cashier-chip/src/Billing/Cashier.php`, `Billing/Coupon.php`, `Billing/Discount.php`, `Billing/Checkout.php`, `Billing/CheckoutBuilder.php`
- `packages/cashier-chip/src/Payment/Payment.php`, `Invoice/Invoice.php`, `Console/RenewSubscriptionsCommand.php`, `Listeners/*.php`
- `packages/cashier-chip/config/cashier-chip.php`
- `packages/filament-cashier-chip/src/Concerns/*`, `Support/FormatsSubscriptionStatus.php`, `Widgets/*.php`, `Resources/**/*.php`, `CustomerPortal/**/*.php`
- `packages/cashier/src/Gateways/ChipGateway.php`, `Gateways/Chip/*`
- `packages/checkout/src/Integrations/Payment/CashierChipProcessor.php`

## Files / Code That Should Be Removed
- `Subscription.php::latestPayment()`, `::upcomingInvoice()`, `::latestInvoice()`, `::invoices()` stubs if not implemented (verified all four return null/empty with "placeholder" comments) — implement or delete, no permanent stubs.
- One copy of `formatAmount`/`formatCurrencyUsing` (converge on the `commerce-support` formatter; re-check outcome 2026-09-07: the two copies are NOT near-identical — `cashier/Cashier.php::formatAmount` passes `convert=true` through `Money::__callStatic` (100x display bug, see cashier audit C-1) while `cashier-chip/Billing/Cashier.php::formatAmount` correctly uses `new Money($amount, new Currency($currency), false)` — fix `cashier`'s call first, then converge).
- `Subscription.php::$with = ['items']` (verified line 132; replace with explicit eager loads).
- `RenewSubscriptionsCommand` manual `withoutGlobalScopes()` + re-scope (verified; rely on `OwnerBatchRunner`).
- `use Carbon\Carbon` in `Billing/Coupon.php` + `Billing/Discount.php` (verified mutable imports; switch to `CarbonImmutable`).
- No migration files removed. No legacy shims preserved.

## Final Recommended Architecture
`cashier-chip` stays the canonical CHIP billing owner (subscriptions, renewals with period-granular leases + frozen amounts, charges, coupons, invoices). `cashier` delegates; `chip` serves HTTP/API + verification; checkout orchestrates and validates amounts; one shared billing-math service feeds both Filament adapters; fakes back a real Pest suite. No new packages.
