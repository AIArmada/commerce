---
title: Filament Affiliates Context
package: filament-affiliates
status: current
surface: filament
family: growth-and-incentives
keywords:
  - filament
  - portal
  - payout-queue
  - fraud-review
---

# Filament Affiliates Context

## Snapshot
- Composer: `aiarmada/filament-affiliates`
- Role: Filament admin + affiliate self-service portal for affiliates.
- Triggers: filament, portal, payout-queue, fraud-review
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `affiliates`, `affiliate-network`, `filament-affiliate-network`
- Paired: `affiliates` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../affiliates/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `affiliates`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `affiliates` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Affiliate admin or portal UI.
- Skip when: Attribution math — see affiliates.
- Owner/security: Filament adapter; validate IDs server-side.

## Key surfaces
- Resources: `AffiliateCommissionTemplateResource`, `AffiliateConversionResource`, `AffiliateCreativeResource`, `AffiliateFraudSignalResource`, `AffiliateLinkResource`, `AffiliateNetworkResource`, `AffiliatePayoutResource`, `AffiliateProgramResource`, `AffiliateRankHistoryResource`, `AffiliateRankResource`
- Actions/Services: `Actions/BulkFraudReviewAction`, `Actions/BulkPayoutAction`, `Actions/ProcessAffiliatePayout`, `Actions/UpdateAffiliateFraudSignalStatus`, `Actions/ValidateAffiliateParentAssignment`, `Services/AffiliateStatsAggregator`, `Services/PayoutExportService`
- Config `filament-affiliates.php`: `navigation`, `group`, `widgets`, `currency`, `features`, `admin`, `conversions`, `payouts`, `programs`, `commission_management`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-widgets.md`, `06-portal.md`
