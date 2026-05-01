---
title: Overview
---

# Docs Package Overview

A Laravel package for generating business documents with PDF output, numbering, email delivery, and optional tenant-aware owner scoping.

## Features

### Core Document Management

- **Multiple Document Types** - Invoice, Receipt, Quotation, Credit Note, Delivery Note, Proforma Invoice
- **PDF Generation** - Powered by Spatie Laravel PDF with Blade templates
- **Status Lifecycle** - Draft, pending, sent, paid, partially paid, overdue, cancelled, refunded
- **Automatic Calculations** - Subtotals, taxes, discounts, totals
- **Polymorphic Linking** - Link documents to any model (Orders, Tickets, etc.)

### Templates & Customization

- **Blade Templates** - Full control over document appearance
- **Tailwind CSS** - Works well for PDF templates when rendered through Browsershot
- **Per-Document Overrides** - PDF format, margins, orientation
- **Custom Fields** - Metadata support for unlimited extensibility

### Numbering & Sequences

- **Flexible Numbering** - Configurable prefixes, formats, separators
- **Reset Frequencies** - Never, Daily, Monthly, Yearly
- **Custom Strategies** - Implement your own numbering logic
- **Multi-tenant Sequences** - Owner-scoped sequence management

### Email Integration

- **Email Templates** - Customizable per document type and trigger
- **Variable Substitution** - Dynamic content with `{{doc_number}}`, `{{customer_name}}`, etc.
- **Tracking** - Open and click tracking routes with encrypted tokens
- **Automated Reminders** - Due-soon and overdue reminder flows

### Approval Workflows

- **Approval Records** - Persist approvers, requester, status, and timestamps
- **Status Integration** - Filament pages can surface pending approvals for the current user

### E-Invoice Support (Malaysia)

- **MyInvois Integration** - Ready for Malaysian e-invoicing compliance
- **Submission Tracking** - Track validation status and errors
- **QR Code URLs** - Portal links for submitted documents

### Multi-Tenancy

- **Owner Scoping** - Full tenant isolation with `HasOwner` trait
- **Global Records** - Optional global templates/sequences
- **Configurable** - Enable/disable via config

## Architecture

```
packages/docs/
├── src/
│   ├── DataObjects/       # DocData DTO
│   ├── Enums/             # DocStatus, DocType, ResetFrequency
│   ├── Facades/           # Doc facade
│   ├── Jobs/              # SendDocReminderJob
│   ├── Models/            # All Eloquent models
│   ├── Numbering/         # Strategy pattern for doc numbers
│   └── Services/          # DocService, DocEmailService, SequenceManager
├── database/
│   ├── factories/         # Model factories
│   ├── migrations/        # Database migrations
│   └── seeders/           # Template seeders
├── resources/
│   └── views/templates/   # Blade templates
└── config/
    └── docs.php           # Package configuration
```

## Models Overview

| Model | Purpose |
|-------|---------|
| `Doc` | Core document (invoice, receipt, etc.) |
| `DocTemplate` | Blade template configuration |
| `DocStatusHistory` | Status change audit log |
| `DocPayment` | Payment records against documents |
| `DocEmail` | Sent email tracking |
| `DocEmailTemplate` | Email content templates |
| `DocSequence` | Number sequence configuration |
| `SequenceNumber` | Period-based sequence counters |
| `DocVersion` | Document version snapshots |
| `DocApproval` | Approval workflow requests |
| `DocWorkflow` | Workflow definitions |
| `DocWorkflowStep` | Individual workflow steps |
| `DocEInvoiceSubmission` | E-invoice submission tracking |

## Quick Start

```php
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\DataObjects\DocData;

$docService = app(DocService::class);

$invoice = $docService->create(DocData::from([
    'doc_type' => 'invoice',
    'items' => [
        ['name' => 'Consulting', 'quantity' => 5, 'price' => 200],
    ],
    'customer_data' => [
        'name' => 'Acme Corp',
        'email' => 'billing@acme.com',
    ],
    'generate_pdf' => true,
]));
```

## Related Packages

- **aiarmada/filament-docs** - Filament admin panel integration
- **aiarmada/commerce-support** - Shared utilities (HasOwner, OwnerContext)

## Next Steps

1. [Installation](01-installation.md) - Set up the package
2. [Usage](02-usage.md) - Create your first document
3. [PDF Generation](03-pdf-generation.md) - Generate and store PDFs
4. [Status Management](04-status-management.md) - Handle document lifecycle
5. [Templates](05-templates.md) - Customize document appearance
6. [Tailwind Usage](06-tailwind-usage.md) - Style with Tailwind CSS
