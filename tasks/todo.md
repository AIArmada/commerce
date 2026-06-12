## Events package upgrade

- [x] Add event hierarchy support with `parent_event_id` and `structure`.
- [x] Replace legacy speaker compatibility with the generic people model.
- [x] Extend moderation/search payloads to understand the upgraded event graph.
- [x] Update tests for hierarchy creation, people roles, and search payloads.
- [x] Run package-scoped verification and fix any regressions.

## Registration / participant scoping

- [x] Add `event_occurrence_id` and `event_session_id` columns to `event_registration_participants`.
- [x] Add `registration()`, `occurrence()`, `session()` BelongsTo relationships on `EventRegistrationParticipant`.
- [x] Add `registrations()` and `participants()` HasMany on `EventSession`.
- [x] Add `participants()` HasMany on `EventOccurrence`.

## Review

- Generic `EventPerson` now owns people-role modeling; no backward-compatibility shim remains.
- `Event` keeps the `Event` + `Occurrence` split while adding parent/child hierarchy support.
- Verification completed on the affected Events test scope and package source.
- Participants can now be scoped directly to an occurrence/session, not just inherited from parent registration.