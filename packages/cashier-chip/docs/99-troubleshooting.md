---
title: Troubleshooting
---

# Troubleshooting

## Subscriptions are not renewing

**Likely cause:** the renewal command is not scheduled or is failing in the current environment.

**Fix:** ensure your scheduler runs `cashier-chip:renew-subscriptions` on the expected cadence and that the app has the required CHIP credentials configured.

**Verify:** confirm the scheduler executes successfully and overdue renewals move forward after the next run.

## Payment methods are missing for billable models

**Likely cause:** the model is missing the `AIArmada\CashierChip\Billable` trait or the required billable columns were not migrated.

**Fix:** add the trait to the billable model and run the package migrations.

**Verify:** create or fetch a customer, then confirm payment method and subscription helpers are available on the model.

## Webhooks are received but subscriptions are not updated

**Likely cause:** webhook configuration or owner context is incomplete.

**Fix:** verify the webhook secret, route, and owner-scoping configuration, then ensure webhook jobs run inside the correct owner context when multitenancy is enabled.

**Verify:** trigger a webhook event and confirm the related subscription, invoice, or customer record updates as expected.