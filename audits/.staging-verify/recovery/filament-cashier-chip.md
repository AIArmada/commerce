End-to-end review: packages/filament-cashier-chip (adapter-only Filament UI over cashier-chip; no migrations/routes/jobs/commands/tests in package)

FINDINGS

1. CRITICAL / bug — src/Resources/SubscriptionResource/Pages/ListSubscriptions.php:36-60 — Bulk pause/resume mass-updates `chip_status` via raw query, bypassing domain transitions and owner scoping.
Evidence: `Subscription::query()->where('chip_status', Active)->update(['chip_status' => Paused->value])`. No `paused_at`, no model events, no CHIP sync (domain `pause()/resume()` at cashier-chip Subscription.php:1242,1257 do this). OwnerScope::apply no-ops when owner disabled (the cashier-chip default, `enabled=false`), so this rewrites EVERY owner's subscriptions cross-tenant. Query-builder `update()` also skips model events/observers.
Recommendation: remove these actions or reimplement as owner-scoped iteration calling `$sub->pause()/$sub->resume()` (or a cashier-chip Action), chunked. Confidence: high.

2. HIGH / bug — src/Resources/BaseCashierChipResource.php:47-86 + src/Resources/CustomerResource.php:41-44 — CustomerResource SQL-crashes when owner scoping is enabled; InvoiceResource checks the wrong owner key.
Evidence: base `getEloquentQuery()` unconditionally constrains `owner_type/owner_id` (via `OwnerQuery`, which always references those columns, OwnerQuery.php:43-60). `Cashier::$customerModel` defaults to base `Model::class` and is typically User/Team with NO owner columns and no `ownerScopeConfig()` → `SQLSTATE unknown column owner_type` as soon as `cashier-chip.features.owner.enabled=true`. Separately, `Purchase` (via ChipModel) is governed by `chip.owner` key, but InvoiceResource's gate reads `cashier-chip.features.owner.enabled` → scoping mismatches the model's own config.
Recommendation: override `getEloquentQuery()` in CustomerResource (billable-aware or explicit opt-out with documented rationale); gate each resource on its model's own `ownerScopeConfig()->enabled`. Confidence: high.

3. HIGH / bug — src/CustomerPortal/Pages/Subscriptions.php:156-187 + resources/views/pages/subscriptions.blade.php — Canceled grace-period subscriptions are invisible; Resume is unreachable.
Evidence: main list `whereIn(chip_status, [active,trialing,past_due])` excludes canceled; `getCancelledSubscriptions()` (`onGracePeriod()`) is fetched with `items+billable` but the blade only loops `$subscriptions` — `$cancelledSubscriptions` is never rendered. A canceled-but-gracing user sees "no active subscriptions" and can never resume.
Recommendation: render a second "Ending soon / Resume" section from `cancelledSubscriptions`. Confidence: high.

4. HIGH / performance — src/Resources/CustomerResource/Tables/CustomerTable.php:62-98,212-248 — Severe per-row N+1: `subscriptions_count` runs a `count()` per row (74-89); `defaultPaymentMethod()` is invoked twice per row (label + lastFour); plus `chipId()` and `onTrial()` per row. A 25-row page fires ~75-125 queries.
Recommendation: `withCount`/select-subselect for counts, compute payment-method label+lastFour once per record (memoize per row or eager-load `storedPaymentMethods`). Confidence: high.

5. HIGH / performance — src/Widgets/MRRWidget.php:119-149, RevenueChartWidget.php:79-131 (also Churn/Trial/Distribution/Attention widgets) — Dashboard fires ~60+ queries per load with full-collection hydration and no caching.
Evidence: MRR chart loop does 6× `->withSum(...)->get()->sum(closure)`; Revenue does 24× (12 MRR + 12 new-revenue) full hydrations; TrialConversions ~14 counts; Distribution 6 counts; Attention 5 counts. Re-run on 45s table / 120s chart polling per viewer.
Recommendation: compute SUM/COUNT in SQL (single grouped query per widget), cache stats with short TTL (60-300s) keyed by owner. Confidence: high.

6. MEDIUM / bug+performance(security-adjacent: DoS/timeout) — src/Resources/CustomerResource/Pages/ListCustomers.php:34-60 — "Sync All to Chip" is unscoped, unbounded, and synchronous.
Evidence: `$model::query()->whereDoesntHave('chipCustomerLink')->get()` (no owner scope, no chunking) then one CHIP API call per customer inside a web request; `whereDoesntHave('chipCustomerLink')` throws outside the try/catch if the customer model lacks the Billable relation; per-item exceptions swallowed into a counter.
Recommendation: move to a queued chunked job with owner scope and a `method_exists`/relation guard; surface failures. Confidence: high.

7. MEDIUM / bug — src/Concerns/InteractsWithCashierChipData.php:31 + all Widgets — Under explicit-global admin context every widget shows global-only (usually empty) data instead of aggregates.
Evidence: `OwnerUiScope::apply($query, includeGlobal: false)`; with null owner `OwnerQuery` returns `whereNull(owner_type)->whereNull(owner_id)` (OwnerQuery.php:46-48), so admin dashboards (BillingDashboard is also registered on the admin panel, FilamentCashierChipPlugin.php:170-172) render misleading zeros.
Recommendation: give admin-registered widgets an aggregate path or gate them (`canView`) to owner contexts. Confidence: medium (depends on host panel's OwnerContext setup).

8. MEDIUM / bug — src/Concerns/InteractsWithCashierChipData.php:42-53 — `normalizeToMonthly()` DivisionByZeroError when `billing_interval_count=0`.
Evidence: `match` arms `30/$count`, `1/$count`, `1/(12*$count)` — int/int division by zero throws. Domain guards this (`Subscription.php:835` uses `> 0 ? : 1`); the widget copy does not.
Recommendation: `$count = max(1, $count)`; better, move normalization into cashier-chip per the adapter-only guardrail. Confidence: high.

9. MEDIUM / bug — src/Widgets/ChurnRateWidget.php:39-88,132-163 — Churn denominators inconsistent: current-month filters Active/Trialing, previous-month and chart count ALL statuses → bogus trend/chart.
Recommendation: apply the same status filter in all three paths. Confidence: high.

10. MEDIUM / bug — src/Widgets/MRRWidget.php:41-76 — MRR discount handling inconsistent + business logic in adapter.
Evidence: current MRR subtracts full `coupon_discount` from a monthly-normalized amount (wrong for yearly plans); previous MRR ignores discounts entirely. Guardrail says calculations belong in cashier-chip.
Recommendation: move MRR computation to cashier-chip with interval-aware discount handling. Confidence: high (inconsistency), medium (materiality).

11. MEDIUM / security — CustomerPortal/Pages/Subscriptions.php:96-102,137-143; PaymentMethods.php:125-131,163-169 — Raw `$e->getMessage()` shown to end users (CHIP/API internals leak).
Recommendation: generic user message + `report($e)`/log. Confidence: high.

12. MEDIUM / bug — CustomerResource/RelationManagers/SubscriptionsRelationManager.php:21 vs CustomerResource.php:94-103 — hardcoded `$relationship='subscriptions'` but resource admits chipSubscriptions-only models → Filament "relationship not found" crash.
Recommendation: make the relationship name dynamic or only register when `subscriptions` exists. Confidence: high.

13. MEDIUM / bug — SubscriptionResource/Schemas/SubscriptionInfolist.php:91 — `method_exists($record->customer, 'chipId')` TypeErrors when `customer` is null (`method_exists()` requires object|string).
Recommendation: null-check first. Confidence: high.

14. MEDIUM / bug — CustomerPortal/Pages/PaymentMethods.php:65-94 — `getAddPaymentMethodUrl()` creates a CHIP setup purchase during page render (side-effect GET; `setupPaymentMethodUrl→createSetupPurchase` calls `createPurchase`), and the catch-all silently returns `'#'` with no feedback.
Recommendation: create the setup purchase lazily in a redirect action on click; notify on failure. Confidence: medium-high.

15. MEDIUM / performance — CustomerPortal/Pages/Invoices.php:105-114 — Unbounded invoice list: loads all invoices (domain also `loadMissing('subscriptions.items'...)`) with in-memory sort and renders every row, no pagination.
Recommendation: paginate or cap + lazy-load. Confidence: high.

16. LOW-MEDIUM / performance+bug — BaseCashierChipResource.php:32-37 — `getNavigationBadge()` runs a scoped `count()` per resource on every request AND asserts owner context (throws where no owner/global context exists).
Recommendation: guard with try/catch or `OwnerUiScope::canCreate`-style check; cache briefly. Confidence: high.

17. LOW / security — Widgets/RevenueChartWidget.php:69 — config `currency` interpolated unescaped into a JS callback string (`'{$currency} '`).
Recommendation: escape/allowlist (config-controlled, so low). Confidence: high.

18. LOW / bug — Dead buttons: InvoiceTable.php:160-168 `download_pdf` action body is empty; ListInvoices `export_csv` is a no-op. Both render clickable actions.
Recommendation: remove or implement. Confidence: high.

19. LOW / bug — BaseCashierChipResource.php:51 default `true` vs cashier-chip.php:43 default `false` for `owner.enabled` — divergent fail-open/fail-closed defaults if key is missing.
Recommendation: align default to `false` (match domain). Confidence: high.

20. LOW / bug — SubscriptionsRelationManager.php:112 `getStatusColor(SubscriptionStatus $status)` non-nullable while FormatsSubscriptionStatus accepts null; null `chip_status` → TypeError. Confidence: medium (DB likely non-null).

21. LOW / bug — CustomerTable.php:27,279-295 static `$genericTrialQuerySupport` schema cache persists across Octane requests/tests.
Recommendation: key already by connection|table; acceptable — note for Octane reset/test isolation. Confidence: medium.

22. LOW / bug — SubscriptionItemsRelationManager.php:99-148 quantity/price forms: `quantity`/`unit_amount` have min but no max; `price` is free text with no length/format check (admin-only).
Recommendation: add `maxValue`/`maxLength` + domain-side validation. Confidence: high.

23. LOW / bug — Invoices.php:59-77 `abort(404)` inside a Livewire action; prefer a Notification + early return. Confidence: medium.

POSITIVES (verified)
- Widgets scope via `OwnerUiScope::apply(..., includeGlobal:false)` (fail-closed); base resource asserts owner-or-explicit-global before querying.
- Portal cancel/resume resolve via `$billable->subscriptions()->find($id)` — billable-scoped, no IDOR.
- Domain `findInvoice()` verifies purchase client-id matches billable chip id; `PaymentMethodStore::setDefault/deleteForBillable` scope by billable + owner with `AuthorizationException` — portal-submitted IDs are revalidated server-side.
- Money consistently int minor units via `MoneyFormatter`; no migrations/FKs (correct for adapter); no SoftDeletes; navigation via `config(navigation.group)` + `getNavigationGroup()` per repo rules.
- Blades use escaped `{{ }}`; driver-specific raw SQL in InvoiceTable high-value filter uses bound parameters; setup purchases require `idempotency_key`.
- No mass-assignment surface (no models/forms binding), no `unserialize`/file-path input/deserialization, no SSRF (no user-controlled URLs fetched), no Octane-unsafe mutable static state beyond the schema boolean cache.

OUT-OF-SCOPE NOTES
- Missing-index check: package owns no tables; widget predicates (`chip_status`, `created_at`, `ends_at`, `trial_ends_at` on cashier-chip subscriptions) should be indexed in cashier-chip — recommend verifying there.
- `role:...` middleware string in BillingPanelProvider.php:88 assumes spatie/laravel-permission semantics; verify against the installed version in host app (not verified here).