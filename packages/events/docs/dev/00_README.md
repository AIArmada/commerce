# Generic Events Package — Developer Handoff Pack

This handoff pack is the source-of-truth specification for building a reusable Laravel Events package with a powerful Filament administration layer.

The package must be generic enough to support many domains:

- ilmu360: masjids, speakers, kitab/books, kuliah, prayer-time labels, AJK approval.
- Training/events: speakers, tickets, workshops, registrations, attendance, recordings.
- Conferences: sessions, panels, moderators, sponsors, venues, seating, itineraries.
- Community events: public submissions, walk-ins, updates.
- Online/hybrid events: live links, recordings, restricted URLs, online meeting links.

The Events package must not become domain-specific. Domain packages extend it by implementing contracts and using polymorphic capabilities.

## Files in this pack

1. `01_ARCHITECTURE_PRINCIPLES.md` — philosophy, scope, generic vs domain-specific split.
2. `02_DATABASE_SCHEMA.md` — full table-by-table database structure with UUID primary keys, timestampTz usage, status/lifecycle rules, and no soft deletes / no database foreign keys.
3. `03_MODELS_RELATIONSHIPS.md` — model list, relationships, scoping rules, and usage examples.
4. `04_CONTRACTS_TRAITS_SERVICES.md` — required PHP contracts, traits, service contracts, and extension points.
5. `05_WORKFLOWS_LIFECYCLE_RULES.md` — lifecycle, changes, postponement, cancellation, registrations, attendance, walk-ins, access, notifications, submissions, and approval workflows.
6. `06_FILAMENT_ADMIN_SPEC.md` — Filament resources, pages, relation managers, widgets, actions, navigation groups, and admin features.
7. `07_IMPLEMENTATION_PHASES.md` — phased build plan from foundation to full admin.
8. `08_PARALLEL_AGENT_CHECKLISTS.md` — parallel agent workstreams with non-colliding task boundaries.
9. `09_LARAVEL_MIGRATION_BLUEPRINTS.md` — migration style rules and representative Laravel migration snippets.
10. `10_TESTING_ACCEPTANCE.md` — tests, acceptance criteria, and quality gates.

## Non-negotiable technical rules

- Use UUID primary keys for every package table.
- Use `timestampTz()` for single timestamp columns.
- Use `timestampsTz()` for `created_at` and `updated_at` on normal mutable records.
- Do not use soft deletes. No `deleted_at` columns.
- Do not use database foreign key constraints.
- Do not use cascading deletes.
- Use application-side policies, services, validators, and model guards to enforce integrity.
- Use indexed UUID reference columns such as `event_id`, `event_occurrence_id`, `event_session_id`, but do not attach database FK constraints.
- Use lifecycle timestamp columns instead of booleans where they represent state transition, for example `published_at`, `cancelled_at`, `postponed_at`, `approved_at`, `rejected_at`, `revoked_at`, `voided_at`, `archived_at`, `checked_in_at`.
- Use booleans only for durable properties or feature flags, for example `is_primary`, `is_featured`, `is_required`, `is_child_friendly`, `is_active`.
- Use code columns for stable classification values: `status`, `visibility`, `delivery_mode`, `role_code`, `usage_type`, `link_type`, `media_type`, `facility_code`, etc.
- Avoid database enums unless the host application explicitly wants them. Prefer strings with application-side constants/classes.
- Use JSONB for flexible metadata, but do not hide first-class searchable concepts in metadata.
- Package tables must be migration-safe and production-deployment-safe.

## Core mental model

```text
events              = what the event/program is
event_occurrences   = when/where the event actually happens
event_sessions      = agenda items inside an occurrence
event_involvements  = speakers, organizers, sponsors, moderators, volunteers, etc.
event_roles         = reusable role codes used by involvements
event_locations     = actual event/occurrence/session location assignment
event_registrations = registration header / booking / signup
event_registration_participants = people included in the registration
event_ticket_types  = access/admission definitions, not issued tickets
event_passes        = actual issued access/QR/pass for a participant
event_attendances   = actual check-in / attendance truth
event_materials     = resources used/delivered/taught/shared
event_references    = resources cited/credited/linked as supporting context
event_updates       = public-facing updates users must clearly see
event_change_logs   = internal audit trail of important changes
```

## Generic vs domain-specific

The Events package owns event mechanics.

Domain packages own real-world meaning.

Example for ilmu360:

```text
Generic Events package:
- event_locations
- event_involvements
- event_materials
- event_references
- event_time_expressions
- event_submissions
- event_approval_requests

ilmu360 domain package:
- masjids
- masjid memberships / AJK roles
- speakers / ustaz / ustazah
- books / kitab
- prayer-time resolver
- Islamic taxonomies such as bidang ilmu, tema, isu
```

The Events package must integrate with application-specific models through contracts such as `HasEventAddress`, `CanOrganizeEvents`, `CanManageEventsFor`, `CanBeInvolvedInEvents`, `CanBeEventMaterial`, and `CanBeEventReference`.

## High-level package modules

```text
Core Events
Scheduling & Occurrences
Sessions & Itineraries
Locations & Facilities
Involvements & Roles
Registration & Participants
Access, Ticket Types, Passes & Seating
Attendance & Check-in
Materials & References
Links, Media & Languages
Audience, Eligibility & Taxonomy
Changes, Updates & Notifications
Submissions & Approval
Series & Discovery
Filament Administration
```

## Instruction to developer AI agents

Do not redesign the package from scratch. Implement this specification unless a concrete contradiction or blocking technical issue is discovered. If a change is necessary, document:

1. What part of the spec cannot be implemented as-is.
2. Why it is technically unsafe or impossible.
3. The smallest proposed adjustment.
4. Migration/data impact.
5. Backward compatibility impact.

Do not introduce database foreign keys, cascading deletes, soft deletes, domain-specific columns, or payment/order logic into this package.
