---
title: Troubleshooting
---

# Troubleshooting

## Registrations are not visible in admin or service queries

**Likely cause:** owner scoping is enabled but the current owner context is missing or incorrect.

**Fix:** ensure the request, job, or command is running with the intended owner context before querying event, occurrence, or registration records.

**Verify:** load the same occurrence or registration again after setting the owner context and confirm the expected records are returned.

## Registration creation fails with “This event date is not accepting registrations.”

**Likely cause:** the occurrence status or registration window does not currently allow new attendees.

**Fix:** inspect the occurrence status, `events.lifecycle.occurrence.registration_accepting_statuses`, plus `registration_opens_at` and `registration_closes_at`.

**Verify:** reload the occurrence and confirm its status is allowed by lifecycle config and the current time falls inside the configured registration window.

## Registration creation fails with “This event date is sold out.” or “Only N seat(s) remain...”

**Likely cause:** the occurrence `capacity` has already been consumed by capacity-blocking registrations.

**Fix:** review registrations whose statuses are listed in `events.lifecycle.registration.capacity_blocking_statuses`, or increase the capacity if appropriate.

**Verify:** compare the remaining capacity against the number of attendee payloads you are trying to create.

## Check-in fails with “This event date is not currently open for check-in.”

**Likely cause:** the occurrence is outside its check-in window.

**Fix:** inspect `check_in_opens_at` and `check_in_closes_at` on the occurrence.

**Verify:** retry while the current time falls inside the configured check-in window.

Also confirm the occurrence status is listed in `events.lifecycle.occurrence.check_in_accepting_statuses`.

## Walk-in recording fails with “This event date is not accepting walk-ins.”

**Likely cause:** the occurrence is not in `walk_in_only` or `hybrid` mode, the occurrence status is not allowed for walk-ins, or the check-in window is closed.

**Fix:** inspect `participation_mode`, `events.lifecycle.occurrence.walk_in_accepting_statuses`, `check_in_opens_at`, and `check_in_closes_at`.

**Verify:** reload the occurrence and confirm `acceptsWalkIns()` returns true before recording the walk-in.

## Check-in or cancellation actions fail unexpectedly

**Likely cause:** the target registration is not in a state that permits the lifecycle transition.

**Fix:** inspect the current registration status and run the correct lifecycle transition for that state.

**Verify:** confirm the registration status changes and the occurrence attendee counts remain consistent afterward.

## Mutating a global occurrence or registration throws an explicit-global error

**Likely cause:** owner scoping is enabled and the target record is global, but the current flow did not enter explicit global context.

**Fix:** wrap the mutation in `OwnerContext::withOwner(null, ...)`.

**Verify:** rerun the same write inside explicit global context and confirm the mutation succeeds.

## Commerce-specific relationships throw an integration unavailable error

**Likely cause:** the app is using the core events package without the matching optional first-party commerce package installed or configured.

**Fix:** install the needed package, for example `aiarmada/orders`, `aiarmada/customers`, or `aiarmada/products`, then refresh Composer autoload and clear/rebuild Laravel config. If you use custom models, configure the matching `events.integrations.*_model` key.

**Verify:** confirm the relevant integration config key resolves to a concrete Eloquent model class and retry the relationship or order-fulfillment flow.

## Order fulfillment creates no registrations

**Likely cause:** `aiarmada/orders` is installed, but `events.integrations.order_item_fulfillment_resolver` is not configured.

**Fix:** configure a class that implements `AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver`.

**Verify:** resolve `EventOrderItemFulfillmentResolver` from the container and confirm it is either your application resolver or the package default resolver, depending on your intended integration.

## Package migrations try to create unexpected table names

**Likely cause:** the app upgraded from older defaults but did not pin the existing package table names in `events.database.tables.*`.

**Fix:** publish `events.php` and set the table names to the names already installed in your database before running migrations.

**Verify:** compare the configured table names with the actual database tables and confirm the package models return the expected names from `getTable()`.
