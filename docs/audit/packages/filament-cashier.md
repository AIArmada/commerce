# Audit: `filament-cashier` (AIArmada\FilamentCashier)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Filament v5 admin UI and customer billing portal for the unified cashier system (Stripe + CHIP).

**Surface:** filament

---

## Findings

### Low
1. **`billing_portal.login_enabled` missing from config** — `BillingPanelProvider` reads the key but it's not defined in the published config file or docs.
2. **Static `$navigationSort` dead code** — Both resources define the static property despite `getNavigationSort()` reading from config and overriding it.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| Navigation — static `$navigationGroup` | ✅ 0 violations | All use config-driven |
| Config structure | ✅ Nested `navigation.group` | Compliant |
| `getEloquentQuery()` owner scoping | ✅ N/A | DTO-backed resources; Pages/Widgets use `OwnerScopedQuery::apply()` |
| Admin widgets | ✅ 5 widgets | All owner-scoped |
| Customer portal | ✅ 4 pages | Portal panel with subscription/invoice/payment management |
| Policies | ✅ Server-side | SubscriptionPolicy, PaymentMethodPolicy validate ownership |
| Tests | ✅ 19 files | Includes dedicated owner-scope regression test |
| Docs | ✅ 5 files | Full standard set |

---

## Summary

2 resources (UnifiedSubscription, UnifiedInvoice), 7 admin pages, 4 portal pages, 8 widgets, 11 Blade views. DTO-backed resources with `OwnerScopedQuery::apply()` used consistently on all query paths. Customer billing portal with BillingPanelProvider.

**Verdict:** Ready. Well-structured with thorough owner scoping.
