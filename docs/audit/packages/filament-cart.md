# Audit: `filament-cart` (AIArmada\FilamentCart)

**Status:** Ready with minor improvements

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Filament v5 admin UI for cart snapshots, conditions, monitoring, and abandonment workflows.

**Surface:** filament

---

## Findings

### Low
1. **4 static `$navigationSort` violations** — CartItemResource, ConditionResource, CartDashboard, LiveDashboardPage use static property instead of config-driven `getNavigationSort()`. Blocks `CommerceNavigation` runtime overrides.
2. **Docs stale** — `docs/03-configuration.md` and `README.md` show flat `navigation_group` key, but config correctly uses nested `navigation.group`.
3. **`.bak` file in tests** — `AlertEvaluatorTest.php.bak` should be cleaned up.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| Navigation — static `$navigationGroup` | ✅ 0 violations | All use config-driven |
| Navigation — static `$navigationSort` | ⚠️ **4 violations** | CartItemResource, ConditionResource, CartDashboard, LiveDashboardPage |
| Config structure | ✅ Nested `navigation.group` | Compliant |
| `getEloquentQuery()` owner scoping | ✅ All 3 resources | Cart, CartItem, Condition all scoped |
| Owner scoping in widgets | ✅ All 3 widgets | Scoped |
| Owner scoping in relation managers | ✅ Both | Via parent record |
| Tests | ✅ 30 files | Good coverage |
| Docs | ✅ 11 files | Full set |

---

## Summary

3 resources (Cart, CartItem, Condition), 9 pages, 3 widgets, 4 actions, 2 relation managers. Owner scoping thorough on all query paths. Navigation structure clean except for 4 `$navigationSort` static properties.

**Verdict:** Ready with minor improvements. Fix 4 navigation sort violations and stale doc examples.
