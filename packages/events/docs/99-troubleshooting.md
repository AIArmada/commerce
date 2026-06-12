---
title: Troubleshooting
---

## Common Issues

### Migrations fail

Ensure the `events` database config key exists and the connection is valid. Check `EVENTS_JSON_COLUMN_TYPE` — use `jsonb` for PostgreSQL and `json` for MySQL if your MySQL version does not support native JSONB.

### Owner scoping not working

Enable owner mode in config:

```
EVENTS_OWNER_ENABLED=true
```

Ensure your owner model implements the required `OwnerResolverInterface` contract from `commerce-support`. The resolver must be bound in your service provider.

### Registrations not creating passes

Verify the registration has associated `registration_items` with valid `event_ticket_type_id` references. Passes are created through explicit action, not automatically on registration creation.

### Registration refuses creation

Check:

- The occurrence's `status` is in `lifecycle.occurrence.registration_accepting_statuses`
- The occurrence has not reached capacity
- The registration window is open
- `event_occurrence_id` references a valid occurrence

### Participant not appearing in occurrence/session queries

Ensure the participant was created with `event_occurrence_id` / `event_session_id` set explicitly. Participants inherit scope from the parent registration only when those columns are left null.

### Check-in fails

The registration must have status `confirmed` and the occurrence must be in a check-in-accepting status (configured via `lifecycle.occurrence.check_in_accepting_statuses`).

### Model not found in Filament admin

The Filament resources apply `OwnerUiScope::apply(..., includeGlobal: false)` by default. Global records are intentionally hidden. If you need to see global records, check your `EVENTS_OWNER_INCLUDE_GLOBAL` setting and ensure the resource allows it.
