---
title: Filament Ticketing Context
package: filament-ticketing
status: current
surface: filament
family: catalog-and-identity
keywords:
  - filament
  - tickets-ui
  - passes
---

# Filament Ticketing Context

## Snapshot
- Composer: `aiarmada/filament-ticketing`
- Role: Filament ticketing admin: types/passes/holders/transfers + ticketable registry.
- Triggers: filament, tickets-ui, passes
- Search first: `src/Resources, config, docs`
- Related: `ticketing`, `commerce-support`
- Paired: `ticketing` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../ticketing/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `ticketing`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `ticketing` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Ticket operations UI.
- Skip when: Issuance/transfer logic — see ticketing.
- Owner/security: Strict/passthrough OwnerUiScope mix.

## Key surfaces
- Resources: `PassHolderResource`, `PassResource`, `PassTransferResource`, `TicketTypeResource`
- Actions/Services: `Support/TicketableTypeRegistry`
- Config `filament-ticketing.php`: `navigation`, `group`, `resources`, `enabled`, `ticket_type`, `pass`, `pass_holder`, `pass_transfer`, `navigation_sort`, `ticket_type`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
