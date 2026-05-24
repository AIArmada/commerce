---
title: Troubleshooting
---

# Troubleshooting

## No gateway can be resolved

**Likely cause:** `CASHIER_GATEWAY` points to a gateway package that is not installed, or the
billable model is missing the matching gateway trait.

**Fix:** install the underlying gateway package, set the correct default gateway, and add the
gateway-specific billable trait to your model.

**Verify:** confirm `Cashier::gateway()` resolves successfully and that the billable model exposes
the expected helper methods.

## Customer IDs or subscription tables are missing

**Likely cause:** the underlying gateway migrations were never run.

**Fix:** run the migrations owned by the installed gateway package. Stripe tables and columns come
from `laravel/cashier`; CHIP billing tables and columns come from `aiarmada/cashier-chip`.

**Verify:** confirm the billable model has the expected gateway ID columns and the gateway-owned
subscription tables exist.

## CHIP subscriptions never renew

**Likely cause:** the renewal command is not scheduled.

**Fix:** add `cashier-chip:renew-subscriptions` to your scheduler when you use CHIP subscriptions.

**Verify:** run the command manually or wait for the next scheduled cycle and confirm overdue
subscriptions advance.

## Webhooks are received but your app logic does not react

**Likely cause:** the gateway package is receiving the webhook, but your application is not
listening to the unified `aiarmada/cashier` events.

**Fix:** attach listeners to the unified payment or subscription events and keep gateway-specific
logic in the underlying gateway packages.

**Verify:** trigger a webhook event and confirm your listener runs against the unified event.