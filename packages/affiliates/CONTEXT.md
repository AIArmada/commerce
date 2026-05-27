---
title: Affiliates Context
package: affiliates
status: current
surface: domain
family: growth-and-incentives
---

# Affiliates Context

## Snapshot
- Composer: `aiarmada/affiliates`
- Role: Affiliate attribution, commissions, payouts, fraud detection, and analytics.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Events`, `config`, `docs`
- Related: `filament-affiliates`, `affiliate-network`, `vouchers`, `cart`
- Conversion records prefer neutral fields like `value_minor`, `external_reference`, and `subject_identifier`; cart and order names remain compatibility aliases.
- Commission maturity promotes `Qualified` conversions into `Approved` and releases funds from `holding_minor` into `available_minor`.
- Performance leaderboards and bonuses use approved revenue with explicit owner-scoped query-builder paths.

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-affiliates/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-affiliates`.
- Update `docs/*.md` in the same pass when public behavior or config changes.
