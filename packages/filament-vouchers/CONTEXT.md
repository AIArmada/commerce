---
title: Filament Vouchers Context
package: filament-vouchers
status: current
surface: filament
family: growth-and-incentives
keywords:
  - filament
  - vouchers-ui
  - wallets
  - stacking
---

# Filament Vouchers Context

## Snapshot
- Composer: `aiarmada/filament-vouchers`
- Role: Filament admin for vouchers/usage/wallets + stacking/targeting pages.
- Triggers: filament, vouchers-ui, wallets, stacking
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `vouchers`, `filament-cart`
- Paired: `vouchers` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../vouchers/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `vouchers`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `vouchers` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Voucher admin UI.
- Skip when: Redemption math — see vouchers.
- Owner/security: Filament adapter.

## Key surfaces
- Resources: `VoucherResource`, `VoucherUsageResource`, `VoucherWalletResource`
- Actions/Services: `Actions/ActivateVoucherAction`, `Actions/AddToMyWalletAction`, `Actions/ApplyVoucherToCartAction`, `Actions/BulkGenerateVouchersAction`, `Actions/ManualRedeemVoucherAction`, `Actions/PauseVoucherAction`, `Support/ConditionTargetFormData`, `Support/ConditionTargetPreset`
- Config `filament-vouchers.php`: `navigation`, `group`, `resources`, `navigation_sort`, `vouchers`, `voucher_usage`, `voucher_wallets`, `pages`, `navigation_sort`, `stacking_configuration`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-widgets.md`, `06-cart-integration.md`
