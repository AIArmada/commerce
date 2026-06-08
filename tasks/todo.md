## Events package upgrade

- [x] Add event hierarchy support with `parent_event_id` and `structure`.
- [x] Replace legacy speaker compatibility with the generic people model.
- [x] Extend moderation/search payloads to understand the upgraded event graph.
- [x] Update tests for hierarchy creation, people roles, and search payloads.
- [x] Run package-scoped verification and fix any regressions.

## Review

- Generic `EventPerson` now owns people-role modeling; no backward-compatibility shim remains.
- `Event` keeps the `Event` + `Occurrence` split while adding parent/child hierarchy support.
- Verification completed on the affected Events test scope and package source.