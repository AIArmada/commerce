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

**Fix:** inspect the occurrence status plus `registration_opens_at` and `registration_closes_at`.

**Verify:** reload the occurrence and confirm it is scheduled/live and the current time falls inside the configured registration window.

## Registration creation fails with “This event date is sold out.” or “Only N seat(s) remain...”

**Likely cause:** the occurrence `capacity` has already been consumed by capacity-blocking registrations.

**Fix:** review `pending`, `confirmed`, `checked_in`, and `no_show` registrations for that occurrence, or increase the capacity if appropriate.

**Verify:** compare the remaining capacity against the number of attendee payloads you are trying to create.

## Check-in fails with “This event date is not currently open for check-in.”

**Likely cause:** the occurrence is outside its check-in window.

**Fix:** inspect `check_in_opens_at` and `check_in_closes_at` on the occurrence.

**Verify:** retry while the current time falls inside the configured check-in window.

## Check-in or cancellation actions fail unexpectedly

**Likely cause:** the target registration is not in a state that permits the lifecycle transition.

**Fix:** inspect the current registration status and run the correct lifecycle transition for that state.

**Verify:** confirm the registration status changes and the occurrence attendee counts remain consistent afterward.

## Mutating a global occurrence or registration throws an explicit-global error

**Likely cause:** owner scoping is enabled and the target record is global, but the current flow did not enter explicit global context.

**Fix:** wrap the mutation in `OwnerContext::withOwner(null, ...)`.

**Verify:** rerun the same write inside explicit global context and confirm the mutation succeeds.