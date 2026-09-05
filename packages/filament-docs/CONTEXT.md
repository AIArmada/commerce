---
title: Filament Docs Context
package: filament-docs
status: current
surface: filament
family: payments-and-documents
keywords:
  - filament
  - documents-ui
  - approvals-ui
---

# Filament Docs Context

## Snapshot
- Composer: `aiarmada/filament-docs`
- Role: Filament admin for documents, templates, sequences, reports, secure downloads.
- Triggers: filament, documents-ui, approvals-ui
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `docs`, `orders`, `checkout`
- Paired: `docs` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../docs/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `docs`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `docs` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Document admin UI.
- Skip when: Render/numbering — see docs.
- Owner/security: DocsOwnerScope helper.

## Key surfaces
- Resources: `DocEmailTemplateResource`, `DocResource`, `DocSequenceResource`, `DocTemplateResource`
- Actions/Services: `Actions/RecordPaymentAction`, `Actions/SendEmailAction`, `Support/DocsOwnerScope`
- Config `filament-docs.php`: `navigation`, `group`, `features`, `auto_generate_pdf`, `resources`, `navigation_sort`, `docs`, `doc_templates`, `sequences`, `email_templates`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-pages-widgets.md`, `06-customization.md`, `index.md`
