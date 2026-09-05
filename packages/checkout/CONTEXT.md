---
title: Checkout Context
package: checkout
status: current
surface: orchestrator
family: checkout-flow
keywords:
  - checkout
  - session
  - steps
  - payment-resolver
  - orchestration
---

# Checkout Context

## Snapshot
- Composer: `aiarmada/checkout`
- Role: Checkout orchestration: session → validate → price/tax → pay → create order → reserve stock → complete.
- Triggers: checkout, session, steps, payment-resolver, orchestration
- Search first: `src/Actions, src/Services, src/Support, config, docs`
- Related: `cart`, `orders`, `shipping`, `chip`, `cashier`, `docs`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. related package contexts when the change crosses boundaries
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: End-to-end purchase flow across cart/pricing/shipping/payments/orders.
- Skip when: Single-domain rules — edit the owning package, not checkout.
- Owner/security: Sessions are owner-aware; re-enter owner context in webhooks.

## Key surfaces
- Models: `CheckoutSession`
- Actions/Services: `Actions/BuildCheckoutSessionViewData`, `Actions/CheckoutFinalizer`, `Actions/EnsureCheckoutOfferProduct`, `Actions/HandleCheckoutPaymentCallback`, `Actions/ProcessCheckoutPaymentNotification`, `Actions/ValidatePromoCodeAction`, `Services/CheckoutService`, `Services/CheckoutStepRegistry`
- Config `checkout.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `checkout_sessions`, `defaults`, `currency`, `session_ttl`, `session_query_param`, `shipping_rate`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-checkout-steps.md`, `06-payment-gateways.md`, `07-payment-flow.md`, `08-integrations.md`
