# Audit: `promotions` (AIArmada\Promotions)

**Status:** Ready with minor improvements

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Automatic and code-based discount campaigns with targeting.

**Surface:** domain

---

## Findings

### Medium
1. **BUG: `MarkPromotionAsUsedOnOrderPlaced` references `times_used` column** — Model has `usage_count`, not `times_used`. Will cause SQL error on execution. Also uses unscoped `Promotion::query()->find()` bypassing owner scoping.
2. **`ReevaluatePromotionsOnCartUpdated` unscoped query** — `Promotion::query()->where('is_active', true)->get()` without `->forOwner()`. Leaks cross-owner promotions.

### Low
3. **Console commands unscoped** — `DeactivateExpiredPromotionsCommand` and `RecomputePromotionEligibilityCommand` query without `->forOwner()`. Acceptable for system commands but should be documented.
4. **No exception hierarchy** — 0 custom exceptions across 5 actions, 4 events, 2 commands.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ `$fillable` | Explicit 16-field whitelist on Promotion model |
| Money storage | ✅ Integer minor units | `discount_value` as integer, `min_purchase_amount` as integer |
| Owner scoping | ⚠️ Partial | Model ✅; 2 listeners ❌; 2 commands ❌ |
| Migrations | ✅ Clean | No `constrained()`, no `cascadeOnDelete()` |
| Actions | ✅ 5 classes | CreatePromotion, ApplyPromotionToCart, EvaluatePromotionForCart, DeactivatePromotion, IssueVouchersFromPromotion |
| Events | ✅ 4 events | Created, Applied, Removed, Deactivated |
| Strategies | ✅ 3 strategies | PercentageStrategy, FixedStrategy, BuyXGetYStrategy |
| Commands | ✅ 2 commands | DeactivateExpiredPromotions, RecomputePromotionEligibility |
| Tests | ✅ 4 files (1117 lines) | Monorepo tests — model, enum, voucher issuance well tested |
| Docs | ✅ 8 files | Full standard set + dedicated targeting and multitenancy docs |

---

## Summary

Focused package: 1 model (Promotion), 1 enum (PromotionType), 5 actions, 4 events, 3 discount strategies (Percentage, Fixed, BXGY), 2 commands, 2 contracts. Strategy pattern for discount types. Stacking coordination registrar for promotion stacking rules.

Promotion model has explicit `$fillable` (16 fields), `booted()` cascades, full owner scoping. Money as integer minor units. Config-driven table names.

**Issues:** One real bug — `MarkPromotionAsUsedOnOrderPlaced` listener references non-existent `times_used` column (should be `usage_count`) and uses unscoped query. `ReevaluatePromotionsOnCartUpdated` also lacks owner scoping.

**Verdict:** Ready with minor improvements. Fix the `times_used` bug and add scoping to listeners.
