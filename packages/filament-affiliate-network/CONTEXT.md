---
title: Filament Affiliate Network Context
package: filament-affiliate-network
status: current
surface: filament
family: growth-and-incentives
keywords:
  - filament
  - marketplace-admin
  - offers-ui
---

# Filament Affiliate Network Context

## Snapshot
- Composer: `aiarmada/filament-affiliate-network`
- Role: Filament marketplace/admin for affiliate-network: sites, offers, applications.
- Triggers: filament, marketplace-admin, offers-ui
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `affiliate-network`, `filament-affiliates`
- Paired: `affiliate-network` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../affiliate-network/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `affiliate-network`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `affiliate-network` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Marketplace admin pages.
- Skip when: Domain rules — see affiliate-network.
- Owner/security: Filament adapter; defers to core scope.

## Key surfaces
- Resources: `AffiliateOfferApplicationResource`, `AffiliateOfferCategoryResource`, `AffiliateOfferResource`, `AffiliateSiteResource`
- Actions/Services: `Support/AffiliateNetworkOptionsProvider`, `Support/NetworkAdminAccess`, `Support/NetworkStatsAggregator`
- Config `filament-affiliate-network.php`: `navigation`, `group`, `sort`, `authorization`, `admin_ability`, `marketplace`, `show_commission_rates`, `show_cookie_duration`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-pages-widgets.md`, `06-customization.md`, `07-actions-reference.md`, `08-testing.md`
