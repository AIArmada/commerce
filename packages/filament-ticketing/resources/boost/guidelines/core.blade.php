# FilamentTicketing

Register FilamentTicketingPlugin in the panel. Use TicketTypeResource for ticket types and TicketTypesRelationManager for a fixed ticketable relationship. Validate submitted ticketable references through ValidateTicketableReference; retain owner-scoped queries and permission checks. Ticket prices are integer minor units; TicketMoney handles major-unit display and strict decimal parsing.
