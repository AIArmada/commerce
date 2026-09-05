---
title: Affiliates Context
package: affiliates
status: current
surface: domain
family: growth-and-incentives
keywords:
  - affiliate
  - commission
  - payout
  - attribution
  - referral
  - fraud
  - tracking-link
---

# Affiliates Context

## Snapshot
- Composer: `aiarmada/affiliates`
- Role: Affiliate attribution, programs/tiers, commissions, payouts, fraud signals, and analytics.
- Triggers: affiliate, commission, payout, attribution, referral, fraud, tracking-link
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-affiliates`, `affiliate-network`, `vouchers`, `cart`
- Paired: `filament-affiliates` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-affiliates/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-affiliates`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Referrals, commissions, payouts, or affiliate fraud review.
- Skip when: Multi-merchant marketplace offers — see affiliate-network.
- Owner/security: Owner-scoped broadly; validate affiliate/cart IDs in scope.

## Key surfaces
- Models: `Affiliate`, `AffiliateAttribution`, `AffiliateBalance`, `AffiliateCommissionPromotion`, `AffiliateCommissionRule`, `AffiliateCommissionTemplate`, `AffiliateConversion`, `AffiliateDailyStat`, `AffiliateFraudSignal`, `AffiliateLink`
- Actions/Services: `Actions/Affiliates/ApproveAffiliate`, `Actions/Affiliates/AttachAffiliateFromCookie`, `Actions/Affiliates/AttachAffiliateToCart`, `Actions/Affiliates/CapturePublicAffiliateReferral`, `Actions/Affiliates/CreateAffiliate`, `Actions/Affiliates/CreateTrackingLink`, `Actions/Affiliates/DetachAffiliateFromCart`, `Actions/Affiliates/DisableAffiliate`
- Config `affiliates.php`: `affiliates`, `attributions`, `conversions`, `payouts`, `payout_events`, `support_tickets`, `support_messages`, `training_modules`, `training_progress`, `tax_documents`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-models.md`, `06-services.md`, `07-programs.md`, `08-payouts.md`, `09-fraud-detection.md`, `10-multi-tenancy.md`, `11-commands.md`, `12-events.md`, `13-api.md`
