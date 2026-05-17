# AIArmada Commerce Demo

This demo application is the working showcase for the packages required by `demo/composer.json`. It combines a storefront, admin panel, seeded showcase data, and owner-scoped feature tests so the ecosystem can be exercised as a real app instead of just being installed.

## What the demo showcases

### Storefront flows

- catalog browsing and product detail pages
- cart management and voucher application
- checkout with demo-mode payments and CHIP-backed flows
- order success, payment callbacks, and “My Orders”
- affiliate referral tracking and shipment tracking

### Admin surfaces

- products, pricing, promotions, inventory, orders, customers, affiliates, shipping, tax, authz, and billing integrations
- signals and growth dashboards
- billing documents and approvals via `aiarmada/docs` + `aiarmada/filament-docs`

### Promotions and docs showcase

The demo now explicitly surfaces promotions and docs as first-class showcase features:

- `demo/app/Providers/Filament/AdminPanelProvider.php` registers both `FilamentPromotionsPlugin` and `FilamentDocsPlugin`
- `demo/app/Filament/Pages/Dashboard.php` includes `PromotionStatsWidget` and `DocStatsWidget`
- `demo/database/seeders/PromotionsShowcaseSeeder.php` seeds real promotions
- `demo/database/seeders/DocsShowcaseSeeder.php` seeds templates, sequences, documents, status history, and approvals
- `demo/tests/Feature/FilamentPromotionsAndDocsShowcaseTest.php` verifies both admin resources load with seeded records

## Bootstrapping the demo

From the `demo/` directory:

```bash
composer setup
```

That script installs dependencies, prepares `.env`, runs migrations, seeds the demo data, installs front-end assets, and builds the demo UI.

For local iteration, the demo also provides:

```bash
composer dev
```

## Seeded data

`DatabaseSeeder` builds the demo in this order:

1. permissions and demo users
2. categories and products
3. inventory and orders
4. showcase data (pricing, vouchers, affiliates, promotions, docs)
5. J&T shipping data
6. billing/subscription examples
7. analytics, signals, and growth experiments

The main showcase seeders to know about are:

- `ShowcaseSeeder`
- `PromotionsShowcaseSeeder`
- `DocsShowcaseSeeder`
- `BillingShowcaseSeeder`
- `AnalyticsShowcaseSeeder`

## Demo accounts

All seeded accounts use the password `password`:

- `admin@commerce.demo` — super admin
- `manager@commerce.demo` — operations manager
- `warehouse@commerce.demo` — inventory manager
- `marketing@commerce.demo` — marketing manager
- `finance@commerce.demo` — finance manager
- `support@commerce.demo` — customer support
- `viewer@commerce.demo` — analyst (read-only)

## Useful routes

- `/` — storefront home
- `/products` — product catalog
- `/cart` — cart flow
- `/checkout` — checkout flow
- `/tracking` — shipment tracking
- `/my-orders` — order history
- `/admin` — Filament admin panel
- `/demo/owner/{user}` — owner switcher for owner-scoping demos

## Verification

The showcase is covered by targeted feature tests, including:

- `tests/Feature/FilamentAdminDashboardSmokeTest.php`
- `tests/Feature/FilamentPromotionsAndDocsShowcaseTest.php`
- `tests/Feature/CheckoutIntegrationTest.php`
- `tests/Feature/PaymentSuccessWebhookSimulationTest.php`

If you change demo package wiring, seeders, or dashboard widgets, update those tests at the same time.
