# Model Relationship Notes

This archived document has been collapsed to the relationships that still matter after the extraction.

## Current cross-package links

- `Event`, `EventOccurrence`, and `EventSession` expose `ticketTypes()`, `passes()`, and `seatMaps()` relations to sibling package models.
- `EventRegistrationItem` stores `ticket_type_id` and resolves a generic `TicketType`.
- `EventAttendance` stores `pass_id` and resolves a generic `Pass`.
- Event-scoped seat usage is expressed through `SeatMap::seatable` and `SeatAllocation::allocated_to`.

## Read next

1. `../../src/Models/Event.php`
2. `../../src/Models/EventOccurrence.php`
3. `../../src/Models/EventSession.php`
4. `../../src/Models/EventRegistrationItem.php`
5. `../../src/Models/EventAttendance.php`
