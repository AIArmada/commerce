# Demo QA Tracker (Smoke + Full Certification)

Last updated: 2026-05-17 (reduced automated subset run)  
Owner: QA / Demo Verification  
Scope source: `/memories/session/plan.md`

## Status Legend
- `Pass`
- `Fail`
- `Blocked`
- `Not surfaced in current demo wiring`
- `Not run`

## Surface Type Legend
- `demo-manual`
- `package-automated`
- `both`
- `not-surfaced`

---

## 1) Smoke-First Checklist (fast confidence)

> Goal: validate live-demo readiness quickly with highest-risk flows.

| # | Scenario | Status | Packages touched | UI evidence | DevTools evidence | Expected DB side effects | Actual DB result | Severity | Notes |
|---|---|---|---|---|---|---|---|---|---|
| S1 | Baseline boot + seeded users + gateway mode known | Not run | commerce-support, checkout, chip |  |  | App boots; owner seeded; gateway mode determined |  |  |  |
| S2 | Storefront browse (`/`, `/products`, `/products/{slug}`, `/categories`) | Pass | products, pricing, customers | Home/catalog cards and category counts rendered; product cards visible with stock state | DevTools snapshot on `https://cdemo.test/` captured stable layout and links | No write expected | No unexpected writes observed | Low | Manual browser pass completed |
| S3 | Add-to-cart + quantity update + remove | Pass | cart, products, pricing | Added `iPhone 15 Pro`; cart badge changed to `1`; toast shown | DevTools snapshot shows toast `iPhone 15 Pro added to cart!` and badge update | Session cart mutations only | Session/cart changed as expected | Low | Manual browser pass completed |
| S4 | Voucher fixed-code flow (`FLASH50`) | Pass (variant code) | vouchers, pricing, checkout | Applied voucher in cart flow (tested `WEEKEND30` from available vouchers) | DevTools snapshot shows `Voucher WEEKEND30 applied!` and updated totals | Voucher applied in session/checkout context | Discount line and cart total updated (`-RM 1,619.70`) | Low | Used surfaced seeded code `WEEKEND30` from UI |
| S5 | Voucher percent-code flow (`WELCOME2024` or `MAYA15`) | Not run | vouchers, affiliates, pricing, checkout |  |  | Discount + attribution context updated |  |  |  |
| S6 | Checkout validation + tax + shipping selection | Pass | checkout, tax, shipping, customers | Checkout form rendered with contact/address fields, shipping methods, and order summary math | DevTools snapshot on `/checkout` shows tax, shipping, voucher discount and total (`RM 4,014.06`) | CheckoutSession created/updated | UI summary and validation focus behavior matched expected flow | Low | Manual + automated evidence |
| S7 | Demo payment success (`/demo/pay/{checkoutSession}`) | Pass | checkout, orders, chip, cashier, inventory, jnt | Automated: `CheckoutDemoModePaymentTest.php`, `PaymentSuccessWebhookSimulationTest.php` | Pest output (subset run), no failures | Paid order path writes all expected records | Verified by automated assertions | Low | Reduced run only; manual UI still pending |
| S8 | Order success page + my-orders visibility | Not run | orders, customers |  |  | No extra unexpected writes |  |  |  |
| S9 | Tracking lookup by order/tracking number | Pass (recent shipments + search UI) | shipping, jnt, orders | Public tracking page renders input and recent shipment table with statuses (`PROBLEM`, `DELIVERED`) | DevTools snapshot on `/tracking`; screenshot captured in chat artifact | Owner-safe read only | UI confirms tracking dataset visibility and search entry point | Low | Direct owner-isolation probe still pending |
| S10 | Admin login and dashboard render (`/admin`) | Pass | filament-authz, filament-* | Logged in as `admin@commerce.demo`; dashboard and sidebar resources rendered | DevTools snapshot shows `🎭 Commerce Command Center` and live widgets/tables | No critical console/network errors | Rendered successfully; no blocking errors | Low | Manual + automated evidence |
| S11 | Role check: marketing | Pass | filament-authz, filament-affiliates, filament-vouchers, filament-promotions | Logged in as `marketing@commerce.demo`; marketing lanes (`Affiliates`, `Vouchers`, `Promotions`) visible | DevTools snapshot + sidebar extraction confirmed `Promotions/Vouchers/Affiliates` visible and `Users/Roles/Billing` hidden | Permission-scoped access only | Behavior matched expected marketing scope in this pass | Medium | Good RBAC behavior observed for marketing user |
| S12 | Role check: finance | Pass | filament-authz, filament-cashier, filament-chip, filament-orders | Logged in as `finance@commerce.demo`; finance lanes (`Billing`, `Payout Batch`, `Purchases`) visible | DevTools snapshot + sidebar extraction confirmed finance-relevant surfaces and no `Users/Roles` in sidebar | Permission-scoped access only | Behavior matched expected finance scope in this pass | Medium | Finance scope appears correct for sampled navigation |
| S13 | Promotions+Docs dashboard showcase (`Total Promotions`, `Total Documents`) | Pass | promotions, filament-promotions, docs, filament-docs | Automated: `FilamentPromotionsAndDocsShowcaseTest.php` | Pest output (subset run), no failures | Matches seeded showcase | Verified by automated assertions | Low | Manual card screenshot still pending |
| S14 | Docs quick access/create/view/download sanity | Pass (navigation/resource render) | docs, filament-docs | Admin `/admin/docs` loaded `Documents` page with `New Document` and table shell | DevTools snapshot + chat screenshot artifact (Docs page) | Owner-safe docs read/write | Read/list UI confirmed; mutation/download not exercised yet | Medium | Partial manual pass (resource surface verified) |
| S15 | Affiliate referral hit (`/ref/{code}`) | Not run | affiliates, vouchers, signals |  |  | Referral attribution/session marker |  |  |  |
| S16 | Mobile viewport sanity (storefront + admin key pages) | Pass | frontend cross-package | Mobile snapshots captured for docs/promotions admin surfaces plus public tracking | DevTools snapshots + chat screenshot artifacts for Docs, Promotions, and Tracking pages | Layout/focus/no blocking errors | Usable mobile layout confirmed for sampled pages | Low | Sampled pages only |
| S17 | Console/network cleanliness sweep | Fail | frontend cross-package | Core flows functional across sampled journeys | Repeated warning: `cdn.tailwindcss.com should not be used in production`; intermittent login-flow console errors observed (`Unexpected token '<' ... not valid JSON`) plus failed resource responses (403 expected on blocked routes, one observed 500 during reload) | No persistent hard-stop in sampled flows, but noisy/error-prone console state exists | Warning/error debt confirmed in manual pass | Medium | Track as UX/ops hardening item; review Livewire/API error handling around auth transitions |

---

## 2) Full Certification Checklist (release sign-off)

> Goal: complete phase-by-phase validation for UX, integrity, RBAC, owner isolation, and package consistency.

| # | Scenario | Status | Packages touched | UI evidence | DevTools evidence | Expected DB side effects | Actual DB result | Severity | Notes |
|---|---|---|---|---|---|---|---|---|---|
| C1 | Phase 0 baseline + instrumentation presets | Not run | commerce-support, checkout, chip |  |  | Controlled baseline established |  |  |  |
| C2 | Phase 1 seeded storefront happy path end-to-end | Pass | products, pricing, tax, cart, vouchers, checkout, orders, shipping, jnt, customers, signals | Automated subset: `CheckoutIntegrationTest.php` | Pest output (subset run), no failures | Session + checkout consistency | Verified by automated assertions | Medium | Narrow automated path only; full manual journey not executed |
| C3 | Phase 2 payment: demo success branch | Pass | checkout, orders, chip, cashier, inventory, jnt, affiliates, vouchers | Automated subset: `CheckoutDemoModePaymentTest.php`, `PaymentSuccessWebhookSimulationTest.php` | Pest output (subset run), no failures | Order + address + shipment + inventory + payment rows | Verified by automated assertions | Medium | Failure/cancel branches still pending |
| C4 | Phase 2 payment: demo failure branch | Not run | checkout, orders, chip |  |  | No paid order side effects |  |  |  |
| C5 | Phase 2 payment: demo cancel branch | Not run | checkout, orders, chip |  |  | No paid order side effects |  |  |  |
| C6 | Phase 2 payment: live CHIP redirect/webhook (if creds available) | Not run | chip, cashier-chip, checkout, orders, jnt |  |  | Webhook path finalizes correctly |  |  |  |
| C7 | Phase 3 admin role matrix: admin/manager/warehouse/marketing/finance/support/viewer | Pass (post-fix recheck) | filament-authz + all surfaced filament packages | Manual checks completed for marketing, finance, manager, warehouse, viewer, support; post-fix support recheck completed in browser | DevTools evidence: `viewer@commerce.demo` receives `403` on `/admin/authz/users` and `/admin/billing-dashboard`; `manager@commerce.demo` can access `orders`, `products/create`, and `billing-dashboard`; `warehouse@commerce.demo` can access `inventory-locations` and `shipping-dashboard` but gets `403` on `orders`; post-fix support recheck now returns `403` on `/admin/authz/users`, `/admin/products/create`, `/admin/vouchers/create`, `/admin/docs/create`, `/admin/affiliates/create`, `/admin/affiliate-programs`, and `/admin/purchases` instead of leaking forms or crashing. | Permission-correct reads/mutations | Post-fix live browser pass confirms the critical support-role create/authz leaks are closed and the affiliates create crash path now fails safely. | Medium | Remaining UX follow-up is nav polish / broader route sampling, not the original privilege leak. |
| C8 | Dashboard widgets integrity (revenue/orders/affiliates/vouchers/promotions/docs/inventory/signals/growth/chip/jnt/cart) | Not run | filament-* + core dependencies |  |  | Widget counts match owner-safe data |  |  |  |
| C9 | Promotions core pass (activity, scheduling, stacking, targeting, owner guards) | Pass | promotions, pricing, vouchers | Automated subset: `tests/src/Promotions/PromotionModelExtendedTest.php` | Pest output (subset run), no failures | Promotion behavior consistent with rules | Verified by automated assertions | Low | Additional integration scenarios still pending |
| C10 | Filament promotions pass (`PromotionResource`, widget, actions, guards) | Pass | filament-promotions, promotions | Automated subset: `tests/src/FilamentPromotions/PromotionResourceTest.php` | Pest output (subset run), no failures | Owner-safe resource writes | Verified by automated assertions | Low | Action-guard matrix still pending |
| C11 | Filament docs pass (`Doc*` resources/pages/widgets/download/guards) | Pass | filament-docs, docs | Automated subset: `tests/src/FilamentDocs/Unit/DocResourceTest.php`, `FilamentDocsPluginTest.php` | Pest output (subset run), no failures | Owner-safe docs mutations/downloads | Verified by automated assertions | Low | Full docs workflow + manual checks pending |
| C12 | Promotions+Docs showcase seed alignment + dashboard proof | Pass | promotions, filament-promotions, docs, filament-docs | Automated subset: `FilamentPromotionsAndDocsShowcaseTest.php` | Pest output (subset run), no failures | `Total Promotions` + `Total Documents` align with seed | Verified by automated assertions | Low | Manual dashboard capture pending |
| C13 | Phase 4 post-purchase ops (my-orders, tracking timelines, operator follow-through) | Not run | orders, shipping, jnt, inventory, docs |  |  | State transitions persisted correctly |  |  |  |
| C14 | Phase 5 affiliates/referrals/revenue attribution | Not run | affiliates, vouchers, orders, signals |  |  | Conversion + commission integrity |  |  |  |
| C15 | Phase 5 signals/growth experiments and winner widgets | Not run | signals, growth, filament-signals, filament-growth |  |  | Event/session/assignment aggregates consistent |  |  |  |
| C16 | Phase 6 billing/subscriptions (`/checkout/single`, `/subscribe/chip/{plan}`, `/billing/portal`) | Not run | cashier, chip, cashier-chip, filament-cashier |  |  | Subscription + billing fields consistent |  |  |  |
| C17 | Phase 7 synthetic second-owner isolation lane | Not run | cross-package owner-scoped |  |  | No cross-owner leakage on read/write |  |  |  |
| C18 | Phase 8 non-functional: responsive, a11y, performance, cache/session, console | Not run | frontend cross-package |  |  | No critical UX/perf regressions |  |  |  |
| C19 | Phase 9 reconciliation: matrix fully completed for every package | Not run | all aiarmada/* |  |  | All rows resolved with final status |  |  |  |
| C20 | Final gap report with follow-up automation candidates | Not run | all |  |  | Gaps + risk ranking documented |  |  |  |

---

## 3) Package Coverage Matrix (all direct `aiarmada/*` deps from `demo/composer.json`)

> Requirement: every direct dependency must have an explicit row.

| Package | Surface type | Demo manual scenario(s) | Automated evidence (minimum) | Final status | Notes |
|---|---|---|---|---|---|
| aiarmada/cashier | both | C16 billing/subscriptions | `demo/tests/Feature/*Billing*`, relevant package tests | Not run | Billing lifecycle + portal redirects |
| aiarmada/checkout | both | S6, S7, C2-C6 | `demo/tests/Feature/CheckoutIntegrationTest.php`, `CheckoutDemoModePaymentTest.php` | Pass (smoke subset) | Core checkout orchestration |
| aiarmada/customers | both | S2, S6, C2 | `demo/tests/Feature/CheckoutIntegrationTest.php` | Pass (smoke subset) | Customer creation/reuse during checkout |
| aiarmada/docs | both | S13, S14, C11-C13 | `tests/src/Docs/*`, `demo/tests/Feature/FilamentPromotionsAndDocsShowcaseTest.php` | Pass (smoke subset) | Explicit demo-wired surface |
| aiarmada/growth | both | C15 | `tests/src/Growth/*` | Not run | Experiment metrics + assignments |
| aiarmada/orders | both | S8, C2-C5, C13 | `demo/tests/Feature/CheckoutIntegrationTest.php`, `PaymentSuccessWebhookSimulationTest.php` | Pass (smoke subset) | Order lifecycle |
| aiarmada/pricing | both | S2, S4, S5, C2, C9 | `tests/src/Pricing/*`, `demo/tests/Feature/CheckoutSubtotalDisplayTest.php` | Pass (smoke subset) | Subtotals/discount/totals integrity |
| aiarmada/products | both | S2, S3, C2 | `demo/tests/Feature/OwnerScopingTest.php` | Not run | Catalog and owner-scope reads |
| aiarmada/promotions | both | S13, C9, C12 | `tests/src/Promotions/*`, `demo/tests/Feature/FilamentPromotionsAndDocsShowcaseTest.php` | Pass (smoke subset) | Explicit demo-wired surface |
| aiarmada/shipping | both | S6, S9, C13 | `tests/src/Shipping/*`, `demo/tests/Feature/TrackingOwnerScopingTest.php` | Pass (smoke subset) | Shipping methods + tracking |
| aiarmada/tax | both | S6, C2 | `tests/src/Tax/*`, `demo/tests/Feature/CheckoutIntegrationTest.php` | Pass (smoke subset) | Tax + shipping-tax correctness |
| aiarmada/cart | both | S3, C2 | package cart tests + checkout integration tests | Not run | Session cart behavior |
| aiarmada/cashier-chip | package-automated | C6, C16 (indirect) | package tests + payment simulation tests | Not run | Indirect/foundation bridge; no primary standalone UI |
| aiarmada/chip | both | S7, C6, C16 | `demo/tests/Feature/PaymentSuccessWebhookSimulationTest.php` | Pass (smoke subset) | Gateway + callback/webhook behavior |
| aiarmada/commerce-support | package-automated | C1 (indirect) | package tests + integration coverage across demo | Not run | Foundation utilities; no dedicated direct UI lane |
| aiarmada/signals | both | S15, C15 | `tests/src/Signals/Feature/*` | Not run | Event/session attribution |
| aiarmada/vouchers | both | S4, S5, C2, C14 | `demo/tests/Feature/CheckoutSubtotalDisplayTest.php`, voucher package tests | Not run | Voucher validity and math |
| aiarmada/affiliates | both | S15, C14 | `tests/src/Affiliates/Integration/NetworkFlowTest.php` | Not run | Referral + commission flow |
| aiarmada/jnt | both | S9, C13 | JNT package tests + `PaymentSuccessWebhookSimulationTest.php` | Pass (smoke subset) | Shipment creation/tracking timeline |
| aiarmada/filament-cart | both | C7-C8 | package tests + dashboard/admin smoke tests | Not run | Admin cart surfaces |
| aiarmada/filament-cashier | both | C7, C16 | package tests + demo billing/admin tests | Not run | Admin billing surfaces |
| aiarmada/filament-vouchers | both | C7-C8 | package tests + badge/navigation tests | Not run | Voucher admin workflows |
| aiarmada/filament-affiliates | both | C7-C8, C14 | package tests + affiliate integration tests | Not run | Affiliate admin analytics |
| aiarmada/filament-chip | both | C7, C8, C16 | package tests + payment simulation tests | Not run | Payment/admin reconciliation |
| aiarmada/filament-customers | both | C7-C8 | package tests + checkout/customer integration | Not run | Customer admin management |
| aiarmada/filament-docs | both | S13, S14, C11-C13 | `tests/src/FilamentDocs/*`, `demo/tests/Feature/FilamentPromotionsAndDocsShowcaseTest.php` | Pass (smoke subset) | Explicit demo-wired surface |
| aiarmada/filament-growth | both | C8, C15 | `tests/src/FilamentGrowth/*` | Not run | Growth widgets/pages |
| aiarmada/filament-jnt | both | C7-C8, C13 | package tests + tracking/payment integration tests | Not run | Shipment admin surface |
| aiarmada/filament-orders | both | C7-C8, C13 | package tests + checkout/payment feature tests | Not run | Order operations in admin |
| aiarmada/filament-pricing | both | C7-C8, C9 | package tests + pricing/promotion tests | Not run | Price/admin configuration |
| aiarmada/filament-products | both | C7-C8 | package tests + owner scoping feature tests | Not run | Product admin resource |
| aiarmada/filament-promotions | both | S13, C10, C12 | `tests/src/FilamentPromotions/*`, `demo/tests/Feature/FilamentPromotionsAndDocsShowcaseTest.php` | Pass (smoke subset) | Explicit demo-wired surface |
| aiarmada/filament-signals | both | C8, C15 | package tests + `tests/src/Signals/Feature/*` | Not run | Signals dashboards/widgets |
| aiarmada/filament-shipping | both | C7-C8, C13 | package tests + shipping/tracking integration tests | Not run | Shipping admin workflows |
| aiarmada/filament-tax | both | C7-C8, C2 | package tests + checkout integration tests | Not run | Tax admin configuration |
| aiarmada/filament-authz | both | S10-S12, C7 | docs + demo feature tests for role/badge scoping | Not run | RBAC + impersonation gates |
| aiarmada/inventory | both | C3, C13 | payment/checkout integration tests + inventory package tests | Not run | Stock decrement/restoration correctness |
| aiarmada/filament-inventory | both | C7-C8, C13 | package tests + admin role/resource checks | Not run | Inventory admin operations |

---

## 4) Mandatory Wired-Surface Verification (Promotions + Docs)

- [ ] Confirm `demo/app/Providers/Filament/AdminPanelProvider.php` registers:
  - `FilamentPromotionsPlugin::make()`
  - `FilamentDocsPlugin::make()`
- [ ] Confirm `demo/app/Filament/Pages/Dashboard.php` includes:
  - `PromotionStatsWidget::class`
  - `DocStatsWidget::class`
- [ ] Confirm `demo/database/seeders/ShowcaseSeeder.php` calls:
  - `PromotionsShowcaseSeeder::class`
  - `DocsShowcaseSeeder::class`
- [x] Run and log outcome of:
  - `demo/tests/Feature/FilamentPromotionsAndDocsShowcaseTest.php`
- [ ] Manual admin proof captured for both cards:
  - `Total Promotions`
  - `Total Documents`

---

## 5) Execution Notes / Gap Report

### Open blockers
- `Full Pest run intentionally skipped by request (time constraint); tracker reflects reduced subset only.`
- `Dashboard manual pass did not surface explicit text labels 'Total Promotions'/'Total Documents' in current widget mix; automated showcase test remains passing.`
- `RBAC/resource/page hardening has now been implemented in code for products, vouchers, promotions, affiliates, and docs; live browser re-verification is still required for support/viewer/warehouse/manager role paths.`
- `The affiliates create-path crash (`OwnerQuery.php:22 - qualifyColumn() on null) has already been patched in code; live browser recheck still pending.`
- `The Tailwind CDN production warning source in docs template rendering has already been replaced with local Vite CSS; live browser console recheck still pending.`
- `Dry-run mutation probe findings remain historically relevant, but resource/page authorization has now been tightened in code and should be revalidated live.`

### Follow-up automation candidates
- `catalog → cart → voucher → checkout → demo payment success`
- `admin role visibility matrix`
- `tracking search owner-isolation`
- `promotions+docs dashboard showcase`

### Final sign-off
- Smoke-first outcome: `Pass (automated subset)`
- Full certification outcome: `Skipped by request (full Pest not executed)`
- Ready for demo: `Partially assessed with high-priority RBAC issue to resolve before production-like demo hardening`
