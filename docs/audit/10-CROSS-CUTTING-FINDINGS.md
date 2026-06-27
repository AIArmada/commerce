# Cross-Cutting Audit Findings — Commerce Monorepo

**Generated:** 2026-06-27
**Scope:** All 57 packages (25 domain + 32 filament)
**Commit:** `7d1dc95fa`

---

## 1. Navigation Violations

| Severity | Packages | Issue | Remediation |
|----------|----------|-------|-------------|
| High | filament-orders | 2 pages use `static $navigationGroup = 'Sales'` | Convert to `getNavigationGroup()` reading config |
| Medium | filament-engagement | All 7 resources use static `$navigationSort` | Convert to `getNavigationSort()` reading config |
| Medium | filament-cart | 4 surfaces use static `$navigationSort` | Convert to config-driven |
| Low | filament-shipping | 2 pages hardcode `'Shipping'` group string | Read from config |
| Low | filament-cashier | Static `$navigationSort` dead code on 2 resources (overridden but property still defined) | Remove unused static properties |

**Pattern:** `$navigationSort` is the most common violation. Only 2 packages use static `$navigationGroup`. All violations are fixable with 1-3 line changes.

---

## 2. Owner Scoping Gaps

| Severity | Packages | Issue | Remediation |
|----------|----------|-------|-------------|
| Critical | filament-affiliates | 6 of 14 resources lack `->forOwner()` — AffiliateConversion, AffiliateLink, AffiliateRankHistory, AffiliateCreative, AffiliateSupportTicket, AffiliateTaxDocument | Add `->forOwner()` to all 6 |
| High | filament-docs | 3/4 resources (DocTemplate, DocSequence, DocEmailTemplate) lack explicit scoping; 4 widgets + 2 pages use bare `Doc::query()` | Add explicit scoping |
| High | filament-cashier-chip | 7 dashboard widgets bypass owner scoping via `$this->subscriptionModel()::query()` | Add scoping or document |
| Medium | promotions | `MarkPromotionAsUsedOnOrderPlaced` and `ReevaluatePromotionsOnCartUpdated` query without `->forOwner()` | Add owner scoping |
| Medium | filament-signals | `SavedSignalReportResource` missing `->forOwner()` in `getEloquentQuery()` | Add explicit scoping |
| Low | filament-growth | 11 query sites rely on automatic global `OwnerScope` | Add explicit scoping |

**Pattern:** Most dangerous gaps are in filament-affiliates and filament-docs. Domain-package gaps limited to promotions.

---

## 3. Zero Test Coverage

| Severity | Packages | Uncovered Surface |
|----------|----------|-------------------|
| Critical | cashier | Entire package — no test directory despite `tests/` in composer.json |
| Critical | cashier-chip | Entire package — Pest.php exists, zero test files |
| Critical | docs | 14 migrations, 13 models, 11 enums, state machine — zero tests |
| High | filament-products | 6 resources, 19 pages, 4 widgets |
| High | filament-cashier-chip | 3 resources, 6 admin pages, 4 portal pages, 7 widgets |
| High | filament-growth | 2 resources, 6 pages, 3 standalone pages, 2 widgets |
| High | filament-signals | 7 resources, 11 report pages, 3 widgets |
| High | filament-inventory | 6 resources, 9 widgets, 7 actions (largest Filament package) |
| High | filament-vouchers | 3 resources, 8 pages, 8 widgets |
| High | filament-promotions | 1 resource, 4 pages, 2 widgets |
| High | filament-pricing | 2 resources, 2 standalone pages |

**Total uncovered surface:** ~39 resources, ~71 pages, ~44 widgets across 11 packages.

---

## 4. Missing Exception Hierarchies

| Severity | Packages | Notes |
|----------|----------|-------|
| High | docs | 2 standard exceptions only — no base for financial document engine |
| Medium | feedback, engagement, signals, growth, orders, promotions, moderation, communications, cashier-chip | Zero or inadequate custom exceptions |
| Low | products, contacting, customers, inventory, membership, pricing, tax, references | Zero custom exceptions |

**Healthy:** events (14 classes), vouchers (9), checkout (7), shipping (4), jnt (4).

---

## 5. Stale Documentation

| Severity | Packages | Issue |
|----------|----------|-------|
| Medium | filament-orders | 3 phantom config keys in docs not present in config |
| Medium | filament-shipping | 5 phantom config keys in docs |
| Medium | filament-events | Missing 2 resources from docs; non-existent env var documented |
| Low | 8 filament packages | Docs show flat `navigation_group`; actual config uses nested `navigation.group` |
| Low | contacting | Stale 2104-line implementation spec at package root |

---

## 6. Missing Composer Dependencies

| Severity | Packages | Issue |
|----------|----------|-------|
| Medium | tax | Imports 5 packages not declared in require (spatie/laravel-data, spatie/laravel-settings, spatie/laravel-model-states, owen-it/laravel-auditing, akaunting/money) |
| Low | cashier | Hard `use` of `Laravel\Cashier\Cashier` without declaring as hard dependency |

---

## 7. Config Staleness

| Severity | Packages | Issue |
|----------|----------|-------|
| Medium | filament-customers | 3 config keys consumed in Plugin but absent from config file |
| Medium | filament-cashier | `billing_portal.login_enabled` read in code but not defined in config |
| Low | filament-chip | `tables.amount_precision` defined but never read |

---

## 8. `$guarded = []` (Security)

| Severity | Packages | Models affected |
|----------|----------|-----------------|
| Critical | chip | 2 base models (ChipModel, ChipIntegerModel) |
| Critical | cashier-chip | 3 write models (Subscription, SubscriptionItem, StoredPaymentMethod) |
| High | cashier | 2 models (read-only intent but no enforcement) |

All are payment/financial packages. `$guarded = []` disables mass-assignment protection entirely.

---

## 9. Known Bugs

| Severity | Packages | Bug |
|----------|----------|-----|
| Medium | promotions | `MarkPromotionAsUsedOnOrderPlaced` references `times_used` column; model has `usage_count` |
| Medium | affiliate-network | `ArchiveExpiredOffersCommand` checks for `'active'` status; enum has `draft`/`published`/`archived` |
| Medium | chip | `WebhookReceived::isTest()` defaults `is_test` to `true` when key missing |
| Medium | cashier | Schema introspection (`Schema::hasColumn()`) on every owner-scoped query — no caching |

---

## 10. Cross-Package Inconsistencies

| Pattern | Issue | Packages |
|---------|-------|----------|
| `$guarded` / `$fillable` | 3 different approaches across packages | chip, cashier, cashier-chip, products, customers vs all others |
| Exception hierarchies | 6 good, 18 inadequate, rest in between | All packages |
| Navigation pattern | Static vs config-driven | 7 filament packages |
| Owner scoping approach | 4 different patterns in Filament layer | All filament packages |
| Enum usage | PHP enums vs class constants | engagement, signals, cashier-chip |
| `down()` in migrations | Convention says none, some have them | chip, customers |
| CHANGELOG.md | 0 of 57 packages have one | Universal gap |
