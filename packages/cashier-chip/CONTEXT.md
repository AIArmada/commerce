---
title: Cashier Chip Context
package: cashier-chip
status: current
surface: gateway-billing
family: payments-and-documents
keywords:
  - chip-billing
  - recurring
  - subscription
  - renewal
  - billable
  - webhook
---

# Cashier Chip Context

## Snapshot
- Composer: `aiarmada/cashier-chip`
- Role: Cashier-style recurring billing on CHIP with app-managed renewals, billables, invoices.
- Triggers: chip-billing, recurring, subscription, renewal, billable, webhook
- Search first: `src/Subscription, src/Billing, src/Actions, config, docs`
- Related: `filament-cashier-chip`, `cashier`, `chip`
- Paired: `filament-cashier-chip` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-cashier-chip/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-cashier-chip`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Subscriptions billed through CHIP.
- Skip when: Stripe billing — see cashier; one-off CHIP collect — see chip.
- Owner/security: Owner-scoped (Subscription, SubscriptionItem, StoredPaymentMethod).

## Key surfaces
- Actions/Services: `Actions/CancelChipSubscription`, `Actions/ChargeChipCustomer`, `Actions/ClaimRenewalAttempt`, `Actions/CreateChipSubscription`, `Actions/RefundChipPayment`, `Actions/SyncChipPurchaseStatus`
- Config `cashier-chip.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `subscriptions`, `subscription_items`, `payment_methods`, `renewal_attempts`, `currency`, `currency_locale`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-customers.md`, `06-charges.md`, `07-checkout.md`, `08-payment-methods.md`, `09-subscriptions.md`, `10-webhooks.md`, `11-testing.md`, `12-api-reference.md`
