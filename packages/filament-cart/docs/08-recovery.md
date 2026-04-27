---
title: Recovery
---

# Recovery

Cart-local recovery campaigns, templates, attempts, and message dispatching have been removed from Filament Cart.

## Current approach

Filament Cart tracks operational abandonment state only:

- `checkout_started_at`
- `checkout_abandoned_at`
- `last_activity_at`

The `cart:mark-abandoned` command marks inactive checkout carts as abandoned and emits `CartAbandoned`.

## Optional follow-up workflows

If you need downstream workflows, listen to Signals events or application events and implement them outside Cart:

- `cart.checkout.started`
- `cart.abandoned`
- `cart.high_value.detected`

Signals can trigger generic alert rules and webhooks when those events match configured filters.
