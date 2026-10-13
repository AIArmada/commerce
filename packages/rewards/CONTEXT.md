---
title: Rewards Context
package: rewards
status: current
surface: domain
family: growth-and-incentives
keywords:
  - reward
  - rewards
  - points
  - badge
  - cash
  - benefit
  - award
  - payout
  - payee
  - program
---

# Rewards Context

## Snapshot
- Composer: `aiarmada/rewards`
- Role: Configurable reward programs (recognition, points, badges, cash, benefits) with audited entitlements, immutable ledgers, and host-bound payout/coupon rails.
- Triggers: reward, points, badge, cash, benefit, award, payout, payee, program
- Search first: `src/Actions`, `src/Models`, `src/Support`, `src/Contracts`, `config`, `docs`
- Related: `filament-rewards`, `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `docs/02-installation.md` when setup or publishing changes are involved
6. `../filament-rewards/CONTEXT.md` when admin UI changes are involved

## Guardrails
- Owns programs, versions, enrollments, awards, points/badges/milestones/caps/appeals/adjustments ledgers, funding, payees, payout requests, benefit catalog/grants, and recoveries.
- Host rails stay contracts, never implementations: `RewardSourceFactReader`, `RewardPayoutExecutor`, `RewardPayoutMethodCatalog`, `RewardBenefitCouponFulfillment`, `RewardRecipientResolver`, `RewardCorrectionKeySource`, `RewardGratitudePlanner`.
- The program is the tenant boundary (`HasOwner` on `RewardProgram`); ledger tables carry no owner columns. Resolve programs/awards/requests/grants through `RewardProgramGuard` on write paths.
- Update `docs/*.md` in the same pass when public behavior or config changes.
- Filament UI belongs in `filament-rewards`, never here.
