---
title: Usage
---

# Usage

## DocResource

`DocResource` is the main document-management surface.

The form is template-aware:

- `doc_type` selection resets the chosen template,
- the template dropdown is filtered to the current document type and searched server-side (50 results max),
- on edit, the status dropdown lists only the current status and its valid state-machine transitions,
- rich body, line items, totals, and notes/terms sections only appear when the selected template layout includes those blocks,
- rich-editor attachments use the package storage configuration (`docs.storage.disk`, rich-content path, and visibility).

### List View

| Column | Description |
|--------|-------------|
| Number | Document number (searchable, copyable) |
| Type | Document type badge |
| Status | Color-coded status badge |
| Customer | Customer name |
| Total | Formatted amount with currency |
| Issue Date | Document issue date |
| Due Date | Due date, highlighted when overdue |
| Template | Hidden-by-default template column |
| Created | Hidden-by-default created timestamp |

### Filters

- Type
- Status
- Overdue
- Paid
- Has PDF
- This Month

### Toolbar Actions

- `Export` using the built-in Filament exporter
- `Generate PDFs` in bulk (queued via `GenerateDocPdfsJob` in chunks of 25; selections over 500 are refused)
- `Mark as Sent` in bulk (only eligible documents transition; the notification reports marked/skipped counts)
- `Delete Selected`

### Record Actions From the List

- View
- Preview online
- Edit
- More actions:
    - Generate PDF
    - Record payment
    - Mark as sent
    - Mark as paid
    - Delete

### View Page Actions

- Edit
- Preview Online
- Generate PDF
- Share
- Revoke Links
- Download PDF
- Mark as Sent
- Mark as Paid
- Cancel
- Delete

The Filament actions keep form presentation and notifications in the adapter. Payment balance checks, owner validation, locking, and document status transitions live in `aiarmada/docs`; email template selection and delivery likewise stay in `DocEmailService`.

### Relation Managers

- `StatusHistoriesRelationManager`
- `PaymentsRelationManager` (creates route through `DocPaymentRecorder`, so balance caps, locks, and status transitions apply; recorded amounts are immutable and payments cannot be deleted from the UI)
- `EmailsRelationManager`
- `VersionsRelationManager`
- `ApprovalsRelationManager` (unassigned approvals additionally require the `document_approval.approve` ability)

## Authorization

Each resource gates every action on its own dedicated ability via `DocPermissions` (see `src/Support/DocPermissions.php`):

| Surface | Abilities |
|---------|-----------|
| Documents | `document.viewAny`, `document.view`, `document.create`, `document.update`, `document.delete` |
| Templates | `document_template.viewAny`, `document_template.view`, `document_template.create`, `document_template.update`, `document_template.delete` |
| Sequences | `document_sequence.viewAny`, `document_sequence.view`, `document_sequence.create`, `document_sequence.update`, `document_sequence.delete` |
| Email templates | `document_email_template.viewAny`, `document_email_template.view`, `document_email_template.create`, `document_email_template.update`, `document_email_template.delete` |
| Aging report | `document.viewAny` |
| Pending approvals | `document_approval.viewAny`, plus `document_approval.approve` to act on unassigned approvals |

> [!WARNING]
> Breaking change: these replace the former shared `purchase.*` abilities. Hosts must grant the new `document*` abilities; legacy `purchase.*` grants no longer unlock any documents UI.

---

## DocTemplateResource

Manages reusable document templates, block layouts, and PDF defaults.

### Highlights

- create and view templates with owner-safe queries applied
- compose layouts with the Filament Builder using these blocks:
    - `document_header`
    - `parties`
    - `document_metadata`
    - `rich_body`
    - `static_rich_text`
    - `line_items`
    - `totals`
    - `notes_terms`
    - `signature_payment`
    - `page_break`
    - `footer`
- configure `settings.pdf.*` defaults such as format, orientation, margins, and background printing
- inspect block counts and linked document counts from the list table
- use **Set as Default** from the table action group to call `DocTemplate::setAsDefault()`

---

## DocSequenceResource

Configures document numbering sequences.

### Highlights

- name, document type, prefix, and reset frequency
- format tokens such as `{PREFIX}`, `{NUMBER}`, `{YYYY}`, `{YYMM}`
- start number, increment, padding, and active flag
- preview text for persisted records
- filters for document type and active state
- edit-only record action plus bulk delete for selected rows

---

## DocEmailTemplateResource

Configures reusable email templates for document communications.

### Supported Triggers

- `send`
- `due_soon`
- `reminder`
- `overdue`
- `paid`
- `created`

### Highlights

- name and slug
- document type and trigger
- active toggle
- subject and rich-text body
- reference list of supported template variables
- owner-scoped unique slug validation when docs owner mode is enabled
- record duplication from the list view

---

## Extending Resources

The shipped resource classes are `final`, so the safest customization path is to register your own resource classes with the plugin and reuse package schemas/tables where helpful.

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

FilamentDocsPlugin::make()
    ->docResource(\App\Filament\Resources\DocResource::class)
    ->docTemplateResource(\App\Filament\Resources\DocTemplateResource::class)
    ->docSequenceResource(\App\Filament\Resources\DocSequenceResource::class)
    ->docEmailTemplateResource(\App\Filament\Resources\DocEmailTemplateResource::class);
```
