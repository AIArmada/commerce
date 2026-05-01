---
title: Resources
---

# Resources

## DocResource

The primary resource for managing documents.

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

### Filters

- Type
- Status
- Overdue
- Paid
- Has PDF
- This Month

### Toolbar Actions

- Export via the built-in Filament exporter integration
- Generate PDFs in bulk
- Mark selected documents as sent
- Delete selected documents

### View Page Actions

- Edit
- Generate PDF
- Download PDF
- Mark as Sent
- Mark as Paid
- Record Payment
- Send Email
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

Manages document templates and PDF defaults.

### Highlights

- edit `view_name`, `doc_type`, and `is_default`
- configure PDF settings such as format, orientation, margins, and background printing
- use **Set as Default** to call `DocTemplate::setAsDefault()`
- delete templates with owner checks applied

---

## DocSequenceResource

Configures document numbering sequences.

### Highlights

- name, document type, prefix, and reset frequency
- format tokens such as `{PREFIX}`, `{NUMBER}`, `{YYYY}`, `{YYMM}`
- start number, increment, padding, and active flag
- preview text for persisted records

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
