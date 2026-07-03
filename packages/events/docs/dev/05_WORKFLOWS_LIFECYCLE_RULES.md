# Workflow Notes

The event workflow remains the source of truth for registration, confirmation, check-in, and attendance. Ticket and seating execution now flows through sibling packages.

## Current workflow shape

- Free registrations can confirm without line items; the event layer ensures a hidden zero-priced ticket definition before issuing a pass.
- Paid registrations resolve generic ticket types from order-backed registration items.
- Pass issuance happens through generic ticketing actions.
- Seat allocation happens through the seating allocator after pass issuance.
- Check-in resolves and validates generic passes against the current event scope.

## Read next

1. `../../docs/04-usage.md`
2. `../../src/Actions/CreateRegistrationsFromOrderAction.php`
3. `../../src/Actions/IssueEventRegistrationPassesAction.php`
4. `../../src/Services/DefaultEventCheckInService.php`
