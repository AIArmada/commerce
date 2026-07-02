---
title: Ticketing Context
package: ticketing
status: current
surface: domain
family: catalog-and-identity
---

# Ticketing Context

## Snapshot
- Composer: `aiarmada/ticketing`
- Role: Ticket types, passes, pass issuance, transfers, bundle products, and pricing for any ticketable model.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Contracts`, `src/Listeners`, `config`, `docs`
- Related: `filament-ticketing`, `inventory`, `cart`, `orders`, `checkout`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-ticketing/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns ticketing-domain models, actions, contracts, services, and persistence rules.
- Keep Filament resources, pages, widgets, and admin-only workflow actions in `filament-ticketing`.
- Preserve polymorphic `ticketable` morph relationship — any model can implement `TicketableInterface`.
- Prefer actions for orchestration; keep models and listeners thin.
- Update `docs/*.md` in the same pass when public behavior or config changes.
