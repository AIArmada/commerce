# Audit: `filament-cashier-chip` (AIArmada\FilamentCashierChip)

**Status:** Conditionally ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Filament v5 admin UI and customer billing portal for CHIP payment gateway integration.

**Surface:** filament

---

## Findings

### Medium
1. **Zero tests** — No test files exist. Only Filament package in the repo without any test coverage.
2. **7 dashboard widgets bypass owner scoping** — Widgets use `$this->subscriptionModel()::query()` directly, bypassing the `BaseCashierChipResource::getEloquentQuery()` owner scoping. Acceptable for global admin but should be documented.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| Navigation — static `$navigationGroup` | ✅ 0 violations | `BaseCashierChipResource` reads from config |
| Config structure | ✅ Nested `navigation.group` | Compliant |
| `getEloquentQuery()` owner scoping | ✅ In base resource | Properly applies `OwnerQuery::applyToEloquentBuilder()` |
| Dashboard widgets | ⚠️ Unscoped | 7 widgets bypass owner scope |
| Customer portal | ✅ 4 pages | BillingDashboard, Subscriptions, PaymentMethods, Invoices |
| Tests | ❌ **0** | No test files in-package or in monorepo |
| Docs | ✅ 7 files | Full standard set + billing portal + widgets |

---

## Summary

3 resources (Customer, Subscription, Invoice), 6 admin pages, 4 portal pages, 7 dashboard widgets. Navigation compliance clean. `BaseCashierChipResource::getEloquentQuery()` properly applies owner scoping with `OwnerQuery::applyToEloquentBuilder()`.

**Critical gap:** Zero test coverage. Widgets query unscoped. Portal pages and admin pages should be tested.

**Verdict:** Conditionally ready. Need minimum test coverage for resources and portal pages. Document widget scoping bypass.
