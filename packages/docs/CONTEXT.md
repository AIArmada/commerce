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
- Owner/security: Persisted document models use `HasOwner`; write logic belongs to core Actions, not UI adapters.

## Key surfaces
- Versioning feature: `Features/Versioning` owns document version writes; use `Contracts/DocumentVersions` for capture and restore.
- Numbering feature: `Features/Numbering` owns sequence configuration and counters; document creation uses `Contracts/DocumentSequence`.
- Models: `Doc`, `Features/Approvals/Models/DocApproval`, `Features/Lifecycle/Models/DocEInvoiceSubmission`, `Features/Delivery/Models/DocEmail`, `Features/Delivery/Models/DocEmailTemplate`, `Features/Payments/Models/DocPayment`, `DocSequence`, `Features/Delivery/Models/DocShareLink`, `Features/Lifecycle/Models/DocStatusHistory`, `Features/Templates/Models/DocTemplate`, `Features/Versioning/Models/DocVersion`, `Features/Approvals/Models/DocWorkflow`, `Features/Approvals/Models/DocWorkflowStep`, `SequenceNumber`
- Actions/Services: `Features/Delivery/Actions/DocEmailService`, `Features/Payments/Actions/RecordDocPayment`, `Services/DocRenderService`, `Services/DocumentRecords`, `Services/DocTotals`, `Features/Delivery/Queries/DueDocReminders`, `Services/SequenceManager`, `Numbering/DocumentNumberRegistry`, `Support/DocRichContentStorage`, `Support/TemplateBlockRegistry`
- Config `docs.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `docs`, `doc_templates`, `doc_share_links`, `doc_status_histories`, `doc_payments`, `doc_email_templates`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-pdf-generation.md`, `06-status-management.md`, `07-templates.md`, `08-tailwind-usage.md`
