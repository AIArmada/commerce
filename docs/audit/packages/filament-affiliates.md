# Audit: `filament-affiliates` (AIArmada\FilamentAffiliates)

**Status:** Conditionally ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Filament v5 admin UI and affiliate portal for the affiliates domain.

**Surface:** filament

---

## Findings

### HIGH
1. **6 of 14 resources lack owner scoping in `getEloquentQuery()`** — AffiliateConversionResource, AffiliateLinkResource, AffiliateRankHistoryResource, AffiliateCreativeResource, AffiliateSupportTicketResource, AffiliateTaxDocumentResource return unscoped queries. Will leak cross-tenant data when owner mode is enabled.

### Low
2. **Docs stale** — `docs/03-configuration.md` shows flat `navigation_group` key in example, but actual config correctly uses nested `navigation.group`.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| Navigation — static `$navigationGroup` | ✅ 0 violations | All resources use config-driven |
| Navigation — config structure | ✅ Nested `navigation.group` | Compliant |
| Resources | ✅ 14 resources | Full domain coverage |
| Pages | ✅ 15 pages | 4 admin + 11 portal |
| Widgets | ✅ 6 widgets | Performance, stats, payouts |
| Policies | ✅ 13 policy files | Per-resource authorization |
| Tests | ✅ 38 files | Includes owner-scoping regression tests |
| Docs | ✅ 7 files | Full standard set |
| Owner scoping gaps | ⚠️ **6/14 resources** | Conversion, Link, RankHistory, Creative, SupportTicket, TaxDocument unscoped |

---

## Summary

Large Filament adapter: 14 resources, 15 pages (4 admin + 11 portal), 6 widgets, 13 policies, 38 tests, 7 docs. Navigation compliance is clean.

**Critical issue:** 6 of 14 resources return `parent::getEloquentQuery()` without `->forOwner()` when owner mode is enabled. AffiliateConversionResource is the highest risk (commission data). Write paths use `OwnerWriteGuard` correctly.

**Verdict:** Conditionally ready. Must add `->forOwner()` to the 6 unscoped resources' `getEloquentQuery()` methods. Fix docs stale config example.
