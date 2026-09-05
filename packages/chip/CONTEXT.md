---
title: Chip Context
package: chip
status: current
surface: gateway
family: payments-and-documents
keywords:
  - chip
  - collect
  - send
  - payout
  - purchase
  - webhook
  - malaysia
---

# Chip Context

## Snapshot
- Composer: `aiarmada/chip`
- Role: Direct CHIP gateway: Collect payments + Send payouts, webhooks, local payment data.
- Triggers: chip, collect, send, payout, purchase, webhook, malaysia
- Search first: `src/Models, src/Services, src/Actions, config, docs`
- Related: `filament-chip`, `cashier-chip`, `checkout`, `docs`
- Paired: `filament-chip` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-chip/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-chip`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: CHIP Collect/Send integration or webhook handling.
- Skip when: Multi-gateway abstraction — see cashier; recurring — see cashier-chip.
- Owner/security: Owner-scoped base models.

## Key surfaces
- Models: `BankAccount`, `ChipCustomerLink`, `ChipIntegerModel`, `ChipModel`, `Client`, `CompanyStatement`, `Payment`, `Purchase`, `SendInstruction`, `SendLimit`
- Actions/Services: `Actions/DispatchChipWebhookAction`, `Actions/LinkChipCustomerFromCheckout`, `Actions/Purchases/CancelPurchase`, `Actions/Purchases/CapturePurchase`, `Actions/Purchases/ChargePurchase`, `Actions/Purchases/CreatePurchase`, `Actions/Purchases/RefundPurchase`, `Actions/Purchases/SyncPurchaseRefundState`
- Config `chip.php`: `database`, `table_prefix`, `json_column_type`, `environment`, `collect`, `base_url`, `api_key`, `brand_id`, `public_key`, `send`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-multitenancy.md`, `api-reference.md`, `chip-collect.md`, `chip-send.md`, `index.md`, `payment-gateway.md`, `webhooks.md`
