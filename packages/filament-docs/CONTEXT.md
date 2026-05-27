---
title: Filament Docs Context
package: filament-docs
status: current
surface: filament
family: payments-and-documents
---

# Filament Docs Context

## Snapshot
- Composer: `aiarmada/filament-docs`
- Role: Filament admin UI for documents, templates, sequences, reports, and secure downloads.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `docs`, `orders`, `checkout`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../docs/CONTEXT.md` when document behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep document generation, persistence, and numbering rules in `docs`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.
