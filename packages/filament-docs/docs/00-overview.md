---
title: Overview
---

# Filament Docs Overview

A Filament admin panel integration for the AIArmada Docs package. It exposes resources, report pages, widgets, and secure PDF downloads for the underlying docs domain.

## Features

### Document Management

- **Full CRUD** - Create, view, edit, delete documents
- **Advanced Filtering** - By type, status, overdue, paid, PDF presence, and current month
- **Bulk Actions** - Generate PDFs, mark as sent, and delete selected documents
- **PDF Actions** - Generate and download PDFs
- **Status Transitions** - Mark as sent, paid, cancelled with audit trail

### Templates

- **Template Management** - Create and configure document templates
- **PDF Settings** - Paper format, orientation, margins per template
- **Default Templates** - Set per document type with one click
- **Usage Tracking** - See how many documents use each template

### Sequences

- **Number Sequences** - Configure automatic document numbering
- **Format Tokens** - `{PREFIX}`, `{NUMBER}`, `{YYYY}`, `{YYMM}`, etc.
- **Reset Frequencies** - Never, Daily, Monthly, Yearly
- **Preview** - See the next number after saving a sequence

### Email Templates

- **Trigger-Based** - Different templates for send, due-soon reminder, overdue, paid, and created events
- **Variable Substitution** - `{{doc_number}}`, `{{customer_name}}`, etc.
- **Rich Content** - WYSIWYG editor for email body

### Reports & Dashboards

- **Aging Report** - Accounts receivable aging by bucket
- **Pending Approvals** - Approval queue for current user
- **Stats Widget** - Total, draft, pending, paid, overdue counts
- **Revenue Chart** - Revenue over time
- **Status Breakdown** - Visual status distribution

### Multi-Tenancy

- **Owner Scoping** - Full tenant isolation on all resources
- **Filament Tenancy** - Compatible with Filament's multi-panel setup
- **Defense in Depth** - Server-side validation beyond UI filtering

## Resources

| Resource | Purpose |
|----------|---------|
| `DocResource` | Core document management |
| `DocTemplateResource` | Template configuration |
| `DocSequenceResource` | Number sequence setup |
| `DocEmailTemplateResource` | Email template management |

## Pages

| Page | Purpose |
|------|---------|
| `AgingReportPage` | Accounts receivable aging report |
| `PendingApprovalsPage` | User's pending approval queue |

## Widgets

| Widget | Purpose |
|--------|---------|
| `DocStatsWidget` | Overview stats (total, draft, pending, paid, overdue) |
| `QuickActionsWidget` | New invoice, quotation, credit note, receipt, delivery note, proforma invoice, and aging report shortcuts |
| `RecentDocumentsWidget` | Latest document list |
| `RevenueChartWidget` | Paid revenue over the last 30 days |
| `StatusBreakdownWidget` | Status distribution doughnut chart |

## Relation Managers

| Manager | Purpose |
|---------|---------|
| `StatusHistoriesRelationManager` | Audit trail for status changes |
| `PaymentsRelationManager` | Payments against document |
| `EmailsRelationManager` | Sent emails for document |
| `VersionsRelationManager` | Document version history |
| `ApprovalsRelationManager` | Approval requests |

## Actions

| Action | Purpose |
|--------|---------|
| `RecordPaymentAction` | Record a payment against a document |
| `SendEmailAction` | Send document via email |

## Quick Start

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentDocsPlugin::make()
                ->navigationGroup('Billing')
                ->agingReportEnabled(true)
                ->docStatsWidgetEnabled(true)
        ]);
}
```

All pages and widgets are enabled by default. Use the fluent API to customize which components are registered.

## Architecture

```
packages/filament-docs/
├── src/
│   ├── Actions/           # RecordPaymentAction, SendEmailAction
│   ├── Exports/           # DocExporter
│   ├── Http/Controllers/  # DocDownloadController
│   ├── Pages/             # AgingReportPage, PendingApprovalsPage
│   ├── Resources/         # All Filament resources
│   │   ├── DocResource/
│   │   │   ├── Pages/     # ListDocs, CreateDoc, EditDoc, ViewDoc
│   │   │   ├── RelationManagers/
│   │   │   ├── Schemas/   # DocForm, DocInfolist
│   │   │   └── Tables/    # DocsTable
│   │   ├── DocTemplateResource/
│   │   ├── DocSequenceResource/
│   │   └── DocEmailTemplateResource/
│   ├── Support/           # DocsOwnerScope helper
│   └── Widgets/           # Dashboard widgets
├── resources/
│   └── views/
│       ├── pages/         # Blade views for pages
│       ├── partials/      # Reusable components
│       └── widgets/       # Widget views
├── routes/
│   └── filament-docs.php  # Download route
└── config/
    └── filament-docs.php  # Package configuration
```

## Next Steps

1. [Installation](01-installation.md) - Set up and register the plugin
2. [Resources](02-resources.md) - Learn about DocResource and DocTemplateResource
3. [Configuration](03-configuration.md) - Customize navigation and features
