---
title: Docs Context
package: docs
status: current
surface: output
family: payments-and-documents
keywords:
  - document
  - invoice-pdf
  - numbering
  - sequence
  - approval
  - e-invoice
---

# Docs Context

## Snapshot
- Composer: `aiarmada/docs`
- Role: Business documents: numbering, PDF render, email, approvals, versions, e-invoice tracking.
- Triggers: document, invoice-pdf, numbering, sequence, approval, e-invoice
- Search first: `src/Models, src/Services, config, docs`
- Related: `filament-docs`, `orders`, `checkout`, `chip`
- Paired: `filament-docs` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-docs/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-docs`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Generating/sending/approving business documents.
- Skip when: Order state itself — see orders.
- Owner/security: Owner-scoped (all 12 models). Logic in Services, not Actions.

## Key surfaces
- Models: `Doc`, `DocApproval`, `DocEInvoiceSubmission`, `DocEmail`, `DocEmailTemplate`, `DocPayment`, `DocSequence`, `DocShareLink`, `DocStatusHistory`, `DocTemplate`
- Actions/Services: `Services/DocEmailService`, `Services/DocRenderService`, `Services/DocService`, `Services/SequenceManager`, `Support/DocRichContentStorage`, `Support/TemplateBlockRegistry`
- Config `docs.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `docs`, `doc_templates`, `doc_share_links`, `doc_status_histories`, `doc_payments`, `doc_email_templates`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-pdf-generation.md`, `06-status-management.md`, `07-templates.md`, `08-tailwind-usage.md`, `index.md`
