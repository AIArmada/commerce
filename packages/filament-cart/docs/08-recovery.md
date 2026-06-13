---
title: Recovery
---

# Recovery

Cart-local recovery campaigns, templates, and retry attempts have been removed from Filament Cart.

## Current approach

Filament Cart tracks operational abandonment state only:

- `checkout_started_at`
- `checkout_abandoned_at`
- `last_activity_at`

The `cart:mark-abandoned` command marks inactive checkout carts as abandoned and emits `CartAbandoned`.

## Optional notifications

The package now ships an optional abandoned-cart email listener for `CartAbandoned`.

- Enable or disable it with `filament-cart.notifications.abandoned_cart.enabled`
- The listener sends mail to the purchaser email found on the latest checkout session for the cart
- If no recovery URL is available, the notification falls back to the application URL

## Optional follow-up workflows

If you need downstream workflows, listen to Signals events or application events and implement them outside Cart:

- `cart.checkout.started`
- `cart.abandoned`
- `cart.high_value.detected`

Signals can trigger generic alert rules and webhooks when those events match configured filters.
