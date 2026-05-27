---
title: Usage
---

# Usage

## DocResource

`DocResource` is the main document-management surface.

The form is template-aware:

- `doc_type` selection resets the chosen template,
- the template dropdown is filtered to the current document type,
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
- `Generate PDFs` in bulk
- `Mark as Sent` in bulk
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

### Relation Managers

- `StatusHistoriesRelationManager`
- `PaymentsRelationManager`
- `EmailsRelationManager`
- `VersionsRelationManager`
- `ApprovalsRelationManager`

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
