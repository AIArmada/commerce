---
title: Troubleshooting
---

# Troubleshooting

## Resources or pages are missing

**Likely cause:** the plugin is running alongside `filament-cashier`, or the relevant feature flags are disabled.

**Fix:** check your panel registration and `filament-cashier-chip` feature toggles. Remember that overlapping resources and pages are intentionally suppressed when unified Filament Cashier is installed on the same panel.

**Verify:** reload the panel after clearing cached config and confirm the intended resources/pages appear on the correct panel.

## Billing portal pages are not available

**Likely cause:** the plugin is not registered on the billing panel, or `billingPortal(true)` is disabled.

**Fix:** register the billing panel provider or plugin on the billing panel and enable the billing portal features.

**Verify:** visit the configured billing panel and confirm subscriptions, payment methods, and invoices pages load.

## Dashboard widgets are missing

**Likely cause:** widget features are disabled in config or the panel is delegating to `filament-cashier`.

**Fix:** enable the dashboard widget feature flags and confirm you are looking at the intended admin panel.

**Verify:** reload the dashboard and confirm CHIP billing widgets render with current subscription data.