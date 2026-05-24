---
title: Troubleshooting
---

# Troubleshooting

## Registrations are not visible in admin or service queries

**Likely cause:** owner scoping is enabled but the current owner context is missing or incorrect.

**Fix:** ensure the request, job, or command is running with the intended owner context before querying event, occurrence, or registration records.

**Verify:** load the same occurrence or registration again after setting the owner context and confirm the expected records are returned.

## Check-in or cancellation actions fail unexpectedly

**Likely cause:** the target registration is not in a state that permits the lifecycle transition.

**Fix:** inspect the current registration status and run the correct lifecycle transition for that state.

**Verify:** confirm the registration status changes and the occurrence attendee counts remain consistent afterward.