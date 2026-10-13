---
title: Filament Rewards Context
package: filament-rewards
status: current
surface: adapter
family: growth-and-incentives
keywords:
  - filament
  - rewards-ui
  - awards-ui
  - payout-queue
---

# Filament Rewards Context

## Snapshot
- Composer: `aiarmada/filament-rewards`
- Role: Filament v5 admin adapter for `aiarmada/rewards` — programs, awards, appeals, funding, payees, payouts, benefit items/grants, recoveries, and the fraud queue.
- Triggers: filament, rewards-ui, awards-ui, payout-queue
- Search first: `src/Resources`, `src/Support`, `config`, `docs`
- Related: `rewards`, `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `docs/02-installation.md` when setup or publishing changes are involved
6. `../rewards/CONTEXT.md` when domain behavior changes are involved

## Guardrails
- Adapter only: resources, tables, forms, infolists, and the admin presenter. Domain logic belongs in `rewards`.
- Navigation reads `filament-rewards.navigation.*` via `getNavigationGroup()`/`getNavigationSort()`; never use static `$navigationGroup`.
- Table queries scope through the program boundary via `RewardFilamentScope`, except the payee queue (recipient-keyed, staff-gated) and the program list (OwnerScope applies automatically).
- Action handlers call guarded core actions; never write ledger rows directly.
- Update `docs/*.md` in the same pass when public behavior or config changes.
