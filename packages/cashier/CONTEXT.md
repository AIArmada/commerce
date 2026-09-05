---
title: Cashier Context
package: cashier
status: current
surface: billing-abstraction
family: payments-and-documents
keywords:
  - billing
  - subscription
  - invoice
  - stripe
  - multi-gateway
  - billable
---

# Cashier Context

## Snapshot
- Composer: `aiarmada/cashier`
- Role: Unified multi-gateway billing abstraction over Stripe (laravel/cashier) and CHIP-style providers.
- Triggers: billing, subscription, invoice, stripe, multi-gateway, billable
- Search first: `src/Actions, src/Gateways, src/Contracts, src/Checkout, config, docs`
- Related: `filament-cashier`, `cashier-chip`, `chip`
- Paired: `filament-cashier` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-cashier/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-cashier`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: One billing API across Stripe + CHIP.
- Skip when: CHIP-only recurring — see cashier-chip; direct gateway calls — see chip.
- Owner/security: No HasOwner models; OwnerScopedQuery helper only.

## Key surfaces
- Models: `UnifiedInvoiceRecord`, `UnifiedSubscriptionRecord`
- Actions/Services: `Actions/CancelSubscription`, `Actions/CreatePayment`, `Actions/CreateSubscription`, `Actions/RefundPayment`, `Actions/SyncWebhook`, `Support/CartIntegrationRegistrar`, `Support/CartManagerWithPayment`, `Support/GatewayDetector`
- Config `cashier.php`: `models`, `billable`, `default`, `currency`, `locale`, `database`, `tables`, `unified_invoices`, `unified_subscriptions`, `gateways`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-subscriptions.md`, `06-payments.md`, `07-multi-gateway.md`, `08-webhooks.md`
