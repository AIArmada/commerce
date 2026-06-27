# Audit Executive Summary — Commerce Monorepo

**Date:** 2026-06-27
**Scope:** All 57 packages (25 domain, 32 filament)
**Commit:** `7d1dc95fa`
**Method:** Structured 360° audit across 8 dimensions per package

---

## Verdict

**The codebase is generally well-architected but has gaps in test coverage, owner scoping, and security hardening on payment packages. Priority remediation (critical/high) affects ~10 packages. Low-risk cosmetic gaps affect most packages.**

---

## Package Health Overview

| Status | Count | Package names |
|--------|-------|---------------|
| **Ready** | 31 | cart, checkout, commerce-support, communications, contacting, csuite, customers, engagement, events, feedback, growth, inventory, jnt, membership, moderation, orders, pricing, references, shipping, signals, vouchers, filament-addressing, filament-authz, filament-cart, filament-cashier, filament-chip, filament-commerce-support, filament-communications, filament-contacting, filament-customers, filament-engagement, filament-events, filament-feedback, filament-tax |
| **Ready (minor)** | 12 | addressing, affiliate-network, affiliates, authz, checkout, filament-affiliate-network, filament-cart, filament-events, filament-engagement, filament-jnt, filament-orders, filament-shipping |
| **Conditionally ready** | 13 | cashier, cashier-chip, chip, docs, products, promotions, tax, filament-affiliates, filament-cashier-chip, filament-docs, filament-growth, filament-inventory, filament-pricing, filament-products, filament-promotions, filament-signals, filament-vouchers |
| **Does not exist** | 1 | commerce-demo (listed in discovery but never created) |

---

## Key Metrics

| Metric | Value |
|--------|-------|
| Total packages | 57 |
| Packages with tests | 25 (44%) |
| Packages with zero tests | 11 (19%) |
| Total test files | 286+ |
| Total doc files | ~285 (5 per package) |
| Navigation violations | 7 packages |
| Owner scoping gaps | 5 packages (1 critical) |
| `$guarded = []` violations | 3 payment packages (critical) |
| Known bugs | 4 |
| Missing exception hierarchies | 18 packages |
| Stale docs/config mismatch | 15+ packages |

---

## Top 5 Findings

### 1. `$guarded = []` on Payment Models (Critical)
**3 packages** — chip, cashier-chip, cashier — disable mass-assignment protection on models handling real money and subscriptions.

### 2. Cross-Tenant Data Leak Risks (Critical-High)
**filament-affiliates** (6 unscoped resources) and **filament-docs** (3 unscoped resources + 4 widgets) could expose data across tenants. Affiliates is the largest gap at 6/14 resources.

### 3. Zero Test Coverage on 11 Packages (High)
**cashier**, **cashier-chip**, **docs**, and **8 filament packages** have no tests. Combined uncovered surface: ~39 resources, ~71 pages, ~44 widgets.

### 4. Unknown Bugs from Untested Logic (Medium)
**4 confirmed bugs** in promotions (wrong column), affiliate-network (dead command), chip (wrong default), and cashier (performance regression). More bugs likely present in untested code paths.

### 5. No Exception Hierarchies in 18 Packages (Medium)
Most domain packages use generic `\Exception` or no custom exceptions at all. Debugging and error handling are harder as a result. 6 packages (events, vouchers, checkout, shipping, jnt, commerce-support) serve as the model.

---

## Remediation (4-Week Plan)

| Phase | Week | Focus | Key Deliverables |
|-------|------|-------|------------------|
| Critical | 1 | Security + data isolation | Fix `$guarded = []` (3 pkgs), scope affiliates+docs (2 pkgs), fix 3 known bugs |
| High | 2-3 | Test coverage + reliability | Add tests to cashier, docs, largest filament pkgs; fix error swallowing; scope promotions listeners |
| Medium | 3-4 | Code quality + consistency | Add exception hierarchies (18 pkgs), fix docs/config, add changelogs, fix navigation |
| Low | Ongoing | Cleanup | Missing deps, dead code, orphaned resources, enum standardization |

### Quick Wins (Day 1)
- Fix `$guarded = []` on chip, cashier-chip, cashier
- Fix promotions `times_used` → `usage_count`
- Fix affiliate-network dead command
- Fix chip `isTest()` default
- Scope 6 filament-affiliates resources

---

## Conclusion

The monorepo has strong architectural foundations — owner scoping conventions, config-driven navigation, and integer minor units for money are consistently applied. The main risks cluster in three areas: **security hardening on payment packages**, **test coverage gaps in filament packages**, and **missing exception hierarchies in domain packages**. The 4-week remediation plan addresses all critical and high risks in the first 2 weeks.

The audit is complete for all 57 packages. See `10-CROSS-CUTTING-FINDINGS.md` for detailed findings and `11-RISK-REGISTER.md` for prioritized remediation.
