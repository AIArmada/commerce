# Contracts, Traits, and Services Notes

The event package now relies on sibling package contracts for ticket and seating behavior.

## Current integration points

- Use `AIArmada\Ticketing\Actions\EnsureTicketTypeAction` for event-scoped ticket definitions.
- Use `AIArmada\Ticketing\Actions\IssuePassesAction` and `AIArmada\Ticketing\Support\PassIssuanceContext` for generic issuance primitives.
- Use `AIArmada\Events\Actions\IssueEventRegistrationPassesAction` for event registration orchestration on top of generic ticketing.
- Use `AIArmada\Seating\Contracts\SeatAllocatorInterface` for seat assignment.
- Use `AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface` for delivery.

## Read next

1. `../../src/Actions/IssueEventRegistrationPassesAction.php`
2. `../../../ticketing/src/Actions`
3. `../../../ticketing/src/Contracts`
4. `../../../seating/src/Contracts`
