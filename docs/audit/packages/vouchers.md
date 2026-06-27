# Audit: `vouchers` (AIArmada\Vouchers)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Voucher issuance, redemption, wallets, assignments, and usage tracking.

**Surface:** domain

---

## Findings

### Low
1. **`VoucherAssignment` not auditable** — No `HasCommerceAudit`/`LogsCommerceActivity`; assignment revocations untracked.
2. **`VoucherAssignment` stores status as string** — Not an enum or state class unlike parent `Voucher` model.
3. **Duplicated constants** — `VOUCHER_METADATA_KEY` string duplicated in `VoucherConditionProvider` and `ValidateVoucherOnCheckout`.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ `$fillable` | All 5 models use explicit `$fillable` |
| Money storage | ✅ Integer minor units | Fixed values in cents, percentages in basis points |
| Owner scoping | ✅ Full | Voucher + VoucherWallet have `HasOwner`; children scoped via parent |
| Migrations | ✅ Clean | No `constrained()`, no `cascadeOnDelete()` across 5 migrations |
| **Exception hierarchy** | ✅ **9 classes** | Best in repo: `VoucherException` base + 8 subclasses |
| State machine | ✅ 5 states | Active, Paused, Expired, Depleted via spatie/laravel-model-states |
| Actions | ✅ 8 classes | Create, Update, Expire, ApplyToCart, RemoveFromCart, RecordUsage, AddToWallet, ValidateCode |
| Events | ✅ 6 events | Created, Applied, Removed, Expired, UsageRecorded, Refilled |
| Stacking engine | ✅ 8 rules | Campaign exclusion, category, max discount, mutual exclusion, type restriction, value threshold, etc. |
| Tests | ✅ **52 files** | Strongest test coverage in repo — models, actions, services, matchers, stacking, conditions |
| Docs | ✅ **11 files** | Most comprehensive docs — API reference, cart integration, wallet, multi-tenancy, manual redemption, usage tracking |

---

## Summary

Well-engineered vouchers package: 5 models (Voucher, VoucherUsage, VoucherWallet, VoucherAssignment, VoucherTransaction), 6 enums, 8 actions, 6 events, 4 contracts, 9-exception hierarchy (best in repo), 5-state state machine, 8 stacking rules, 6 product matchers (SKU, category, price, attribute, composite, all/any), compound condition types (BOGO, bundle, cashback, tiered). Full cartridge with apply/remove/validate operations integrated with cart condition system. Wallet system with transactions. Builder-pattern `VoucherRulesFactory` for runtime rule configuration.

Money as integer minor units throughout. Owner scoping on Voucher and VoucherWallet. 52 test files — strongest in the repo. 11 docs files — most comprehensive.

**Exception hierarchy score:** 9 classes. Gold standard for this repo. Only events (14) has more.

**Verdict:** Ready. Best exception hierarchy and doc coverage in the repo.
