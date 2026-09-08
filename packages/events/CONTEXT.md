---
title: Events Context
package: events
status: current
surface: domain
family: analytics-and-events
keywords:
  - event
  - venue
  - occurrence
  - registration
  - check-in
  - waitlist
---

# Events Context

## Snapshot
- Composer: `aiarmada/events`
- Role: Events domain: series/venues/occurrences/sessions, registrations, check-in, change workflows (60+ models).
- Triggers: event, venue, occurrence, registration, check-in, waitlist
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-events`, `products`, `customers`, `orders`
- Paired: `filament-events` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-events/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- `EventOrganizer` is an event-scoped organizer role/profile, not a tenant organization or a replacement for shared `persons.Person`. Involvements may point at a person or another `CanBeInvolvedInEvents` model through the event-owned polymorphic role.
- If admin UI changes too, audit `filament-events`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Event scheduling, venues, registrations, attendance.
- Skip when: Ticket inventory/pricing — see ticketing; seat maps — see seating; shared human identity — see persons.
- Owner/security: direct owner models use commerce-support `HasOwner`; event
  children use the documented relation-via-event owner boundary.

## Key surfaces
- Models: `Event`, `EventAccessPolicy`, `EventApprovalRequest`, `EventAttendance`, `EventAttendanceLog`, `EventAttribute`, `EventAudience`, `EventAudienceProfile`, `EventAvailabilityBlock`, `EventChangeLog`
- Actions/Services: `Actions/AddEventTicketTypeToCartAction`, `Actions/AllocateEventSeatsOnPassIssued`, `Actions/ApproveAssignmentRequestAction`, `Actions/ArchiveEventRegistrationQuestionAction`, `Actions/CreateEventComponentRegistrationsAction`, `Actions/BatchCreateOccurrencesAction`, `Actions/CancelAssignmentRequestAction`, `Actions/SynchronizeEventContent`
- Config `events.php`: `enabled`, `include_global`, `auto_assign_on_create`, `models`, `event`, `registration`, `attendance`, `submission`, `registration_question`, `database`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-taxonomy-hierarchy.md`
