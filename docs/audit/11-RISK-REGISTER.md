# Risk Register & Remediation Roadmap

**Generated:** 2026-06-27
**Scope:** All 57 packages

---

## Legend

- **R** = Risk index (priority order)
- **Impact:** CV = Critical, HI = High, ME = Medium, LO = Low
- **Effort:** S = Small (<1 day), M = Medium (1-3 days), L = Large (3-10 days), XL = >10 days
- **Detection:** E = Easy to detect/fail, M = Moderate, H = Hard (no test coverage)

---

## Critical Risks

| R | Risk | Impact | Effort | Detection | Description | Remediation | Packages |
|---|------|--------|--------|-----------|-------------|-------------|----------|
| 1 | `$guarded = []` on payment models | CV | S | M | 7 models across 3 packages have mass-assignment protection fully disabled | Replace `$guarded = []` with `$fillable` on all models | chip, cashier-chip, cashier |
| 2 | Cross-tenant data leak (affiliates) | CV | M | H | 6/14 Filament resources don't scope to owner | Add `->forOwner()` to `getEloquentQuery()` on 6 resources | filament-affiliates |
| 3 | Cross-tenant data leak (docs) | HI | S | H | 3/4 resources + 4 widgets + 2 pages unscoped in document engine | Add explicit owner scoping | filament-docs |
| 4 | Payment operations silently fail | HI | M | H | Cashier error swallowing in 4 gateway methods (catch Throwable → null) | Add logging, typed exceptions, or at minimum distinguish "not found" from errors | cashier |
| 5 | Zero tests on financial packages | HI | M | E | cashier, cashier-chip, docs have zero tests despite handling real money / documents | Add minimum smoke+regression test suite | cashier, cashier-chip, docs |

---

## High Risks

| R | Risk | Impact | Effort | Detection | Description | Remediation | Packages |
|---|------|--------|--------|-----------|-------------|-------------|----------|
| 6 | Promotions bug: wrong column name | ME | S | E | `MarkPromotionAsUsedOnOrderPlaced` references `times_used` (doesn't exist) — will error on execution | Change to `usage_count` | promotions |
| 7 | Affiliate-network command never works | ME | S | E | `ArchiveExpiredOffersCommand` never matches any record (checks for `'active'` status that doesn't exist in enum) | Fix status check or remove command | affiliate-network |
| 8 | Webhook default misinterprets test mode | ME | S | E | `WebhookReceived::isTest()` returns `true` when key missing — could classify production events as test | Default `is_test` to `false` | chip |
| 9 | Unscoped listeners in promotions | ME | S | H | 2 event listeners query promotions without owner scoping | Add `->forOwner()` | promotions |
| 10 | Dashboard bypasses all scoping | HI | M | H | 7 cashier-chip widgets bypass owner scoping entirely | Add scoping or document intent | filament-cashier-chip |
| 11 | 11 filament packages untested | ME | XL | E | ~39 resources, ~71 pages, ~44 widgets have zero test coverage | Add minimum test suite per package | 11 filament packages |
| 12 | Schema introspection on every request | ME | S | H | `Schema::hasColumn()` called on every owner-scoped query — performance degrades as column count grows | Cache column check | cashier |

---

## Medium Risks

| R | Risk | Impact | Effort | Detection | Description | Remediation | Packages |
|---|------|--------|--------|-----------|-------------|-------------|----------|
| 13 | No exception hierarchy (18 packages) | ME | L | M | Catch-all `\Exception` or no typed exceptions — can't distinguish business errors from system errors | Add base exception per package, extend for domain errors | 18 domain packages |
| 14 | Stale config/docs mislead developers | LO | M | M | 8 packages with `navigation_group` vs `navigation.group` mismatch; phantom keys in docs | Update docs + config to match | 8 filament + others |
| 15 | No CHANGELOG in any package | LO | M | M | 0/57 packages have changelog — breaking changes invisible to consumers | Add CHANGELOG.md per package | All 57 |
| 16 | Navigation violations block runtime override | LO | S | E | 7 filament packages use static navigation properties | Convert to config-driven | 7 filament packages |
| 17 | Duplicate Billable trait | LO | S | E | Identical `Billable.php` in src root and src/Concerns/ | Remove one copy | cashier |
| 18 | No down() in most migrations | LO | S | E | Convention says none, but chip and customers have them | Remove or standardize | chip, customers |
| 19 | Static analysis: unguarded IDs bypassed | LO | S | M | products and customers use `$guarded = ['id']` instead of `$fillable` | Standardize to `$fillable` | products, customers |

---

## Low / Cosmetic Risks

| R | Risk | Impact | Effort | Detection | Description | Remediation | Packages |
|---|------|--------|--------|-----------|-------------|-------------|----------|
| 20 | Missing composer deps (tax) | LO | S | E | Uses packages transitively but doesn't declare them | Add to require | tax |
| 21 | Orphaned resources (filament-events) | LO | S | M | Resource implemented but never registered in plugin | Register or remove | filament-events |
| 22 | Orphaned schema class | LO | S | M | `AddressCountryFormSchema` never used | Remove | filament-addressing |
| 23 | Dead code in navigation | LO | S | E | No-op loop in `buildSidebarForForm()` | Remove | filament-commerce-support |
| 24 | Enum inconsistency | LO | M | M | Some packages use PHP enums, some use class constants | Standardize to PHP enums | engagement, signals, cashier-chip |
| 25 | Duplicated policy helpers (products) | LO | S | E | `belongsToOwner()` and `isGlobalModel()` duplicated across 6 policy files | Extract to base | products |

---

## Remediation Roadmap

### Phase 1 — Critical (Week 1)
| Order | Task | R# | Effort |
|-------|------|----|--------|
| 1 | Fix `$guarded = []` on chip, cashier-chip, cashier | 1 | Small |
| 2 | Add `->forOwner()` to 6 unscoped filament-affiliates resources | 2 | Small |
| 3 | Fix promotions `times_used` → `usage_count` bug | 6 | Tiny |
| 4 | Fix affiliate-network `ArchiveExpiredOffersCommand` status check | 7 | Tiny |
| 5 | Fix chip `WebhookReceived::isTest()` default | 8 | Tiny |
| 6 | Add explicit owner scoping to filament-docs resources/widgets/pages | 3 | Medium |

### Phase 2 — High (Week 2-3)
| Order | Task | R# | Effort |
|-------|------|----|--------|
| 7 | Add test suite to cashier | 5 | Medium |
| 8 | Add test suite to docs | 5 | Medium |
| 9 | Fix cashier error swallowing with logging+typed exceptions | 4 | Medium |
| 10 | Scope promotions listeners | 9 | Small |
| 11 | Scope cashier-chip dashboard widgets | 10 | Small |
| 12 | Cache cashier Schema::hasColumn() | 12 | Small |
| 13 | Add test suite to filament-inventory, filament-products | 11 | Large |

### Phase 3 — Medium (Week 3-4)
| Order | Task | R# | Effort |
|-------|------|----|--------|
| 14 | Add base exception hierarchies to 18 domain packages | 13 | Large |
| 15 | Fix docs/config mismatches across 8 filament packages | 14 | Medium |
| 16 | Add CHANGELOG.md to all packages | 15 | Medium |
| 17 | Fix navigation violations in 7 filament packages | 16 | Small |
| 18 | Remove duplicate Billable trait | 17 | Tiny |
| 19 | Standardize `$fillable` on products, customers | 19 | Small |

### Phase 4 — Low (Ongoing)
| Order | Task | R# | Effort |
|-------|------|----|--------|
| 20 | Add missing composer deps to tax | 20 | Small |
| 21 | Clean up orphaned resources, dead code | 21-23 | Small |
| 22 | Remove stale implementation spec from contacting | 14 | Tiny |
| 23 | Standardize PHP enums | 24 | Medium |
| 24 | Extract duplicated policy helpers in products | 25 | Small |
| 25 | Add tests to remaining untested filament packages | 11 | XL |
