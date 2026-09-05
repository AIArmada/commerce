---
title: Ticketing Context
package: ticketing
status: current
surface: domain
family: catalog-and-identity
keywords:
  - ticket
  - pass
  - transfer
  - bundle
---

# Ticketing Context

## Snapshot
- Composer: `aiarmada/ticketing`
- Role: Polymorphic ticket types, passes, transfers, bundle products for any ticketable.
- Triggers: ticket, pass, transfer, bundle
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-ticketing`, `inventory`, `cart`, `orders`, `checkout`
- Paired: `filament-ticketing` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-ticketing/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-ticketing`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Ticket issuance or transfers.
- Skip when: Seat maps — see seating; event schedule — see events.
- Owner/security: Owner-scoped (all 7; enabled by default).

## Key surfaces
- Models: `Pass`, `PassHolder`, `PassTransfer`, `TicketType`, `TicketTypeComponent`, `TicketTypeProduct`, `TicketTypeSeatingOption`
- Actions/Services: `Actions/AddTicketTypeToCartAction`, `Actions/AutoAddRequiredTicketBundlesAction`, `Actions/BulkTransferPassesAction`, `Actions/EnsureTicketTypeAction`, `Actions/ExpandTicketTypeComponentsAction`, `Actions/IssuePassesAction`, `Actions/RevokePassAction`, `Actions/TransferPassToHolderAction`
- Config `ticketing.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `ticket_types`, `ticket_type_components`, `ticket_type_products`, `ticket_type_seating_options`, `passes`, `pass_holders`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
