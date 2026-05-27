---
title: Docs Context
package: docs
status: current
surface: output
family: payments-and-documents
---

# Docs Context

## Snapshot
- Composer: `aiarmada/docs`
- Role: Business document generation, numbering, PDFs, emails, approvals, and e-invoice tracking.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Support`, `config`, `docs`
- Related: `filament-docs`, `orders`, `checkout`, `chip`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-docs/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns document generation, templates, numbering, PDFs, emails, approvals, and share-link behavior.
- Audit `filament-docs`, `orders`, and `checkout` when document workflows change.
- Update `docs/*.md` in the same pass when public behavior or config changes.
