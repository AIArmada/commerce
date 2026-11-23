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
  - merchant-statements
  - creator-postbacks
---

# Filament Affiliate Network Context

## Snapshot
- Composer: `aiarmada/filament-affiliate-network`
- Role: Filament v5 admin/marketplace adapter for `aiarmada/affiliate-network`. UI only — no domain rules, models, or money math.
- Search first: `src/Resources, src/Pages, src/Widgets, src/Support, config, docs`
- Related: `affiliate-network` (core domain owner), `filament-affiliates`
- Paired: `affiliate-network` (owns all Actions, Models, Data, and scoping)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../affiliate-network/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes

## Guardrails
- Adapter only: never add domain models, actions, calculations, or money math here. Call `aiarmada/affiliate-network` `Features\<Feature>\Actions\*` / `Queries\*` / `Api\*`.
- Cross-package boundary: import from `AIArmada\AffiliateNetwork\Features\<Feature>\{Models,Api,Actions}` and `Data`/`Platform` only. Never reach into another package's `EntryPoints` or `Drivers`.
- Owner scoping: resources return owner-safe queries; every action handler revalidates the submitted ID server-side. Admin surfaces (`MerchantStatementResource`, network widgets) intentionally use `withoutOwnerScope()` behind `NetworkAdminAccess::allows()`.
- If behaviour or calculations change, move them into `affiliate-network` and keep this package presentational.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Filament v5 admin/marketplace surfaces for sites, offers, enrollment, coupons, deals, legs, statements, and network reporting.
- Skip when: Domain rules, persistence, money math — see `affiliate-network`.
- Owner/security: Filament adapter; defers to core scope, but revalidates IDs in action handlers.

## Key surfaces
- Resources: `AffiliateSiteResource`, `AffiliateOfferResource`, `AffiliateOfferCategoryResource`, `AffiliateOfferApplicationResource`, `MerchantStatementResource`
- Relation managers (offer edit): `CreativesRelationManager`, `LinksRelationManager`, `LegsRelationManager` (Adjust, Lock Matured, Reverse), `InvitationsRelationManager`, `DealsRelationManager`, `CouponsRelationManager`
- Pages: `MerchantDashboardPage`
- Widgets: `NetworkStatsWidget`, `TopOffersWidget`
- Support: `NetworkAdminAccess`, `NetworkStatsAggregator`, `Actions\SyncSiteCatalog`
- Policies: `AffiliateSitePolicy`, `AffiliateOfferPolicy`, `AffiliateOfferCategoryPolicy`, `AffiliateOfferApplicationPolicy`, `MerchantStatementPolicy`
- Config `filament-affiliate-network.php`: `navigation.group`, `navigation.sort`, `authorization.admin_ability`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-pages-widgets.md`, `06-customization.md`, `07-actions-reference.md`, `08-testing.md`
