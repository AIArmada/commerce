---
name: filament-ticketing-development
description: Develop and integrate filament-ticketing with owner-safe Filament operations.
---

# FilamentTicketing development

Register FilamentTicketingPlugin in the panel. Use TicketTypeResource for ticket types and TicketTypesRelationManager for a fixed ticketable relationship. Validate submitted ticketable references through ValidateTicketableReference; retain owner-scoped queries and permission checks. Ticket prices are integer minor units; TicketMoney handles major-unit display and strict decimal parsing.

## Verification

Run the package architecture checks and affected monorepo Pest tests, PHPStan and Pint. Update callers directly; do not introduce compatibility aliases or backfill migrations.
