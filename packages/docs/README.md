# Docs Package for Laravel

`aiarmada/docs` provides document creation, numbering, PDF generation, email delivery, tracking, and owner-aware document storage for Laravel applications.

## What ships today

- `AIArmada\Docs\Services\DocService` for document creation, updates, conversion, payment recording, and PDF generation
- `AIArmada\Docs\Services\DocEmailService` for send/send-reminder flows with tracking records
- Config-backed numbering through `AIArmada\Docs\Services\SequenceManager`
- Models for documents, templates, payments, versions, emails, approvals, workflows, and e-invoice submissions
- Optional owner scoping through `aiarmada/commerce-support`

## Installation

```bash
composer require aiarmada/docs
php artisan vendor:publish --tag=docs-config
php artisan migrate
```

If you want to customize the bundled Blade templates, you can also publish the package views:

```bash
php artisan vendor:publish --tag=docs-views
```

## Basic usage

```php
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Services\DocService;

$doc = app(DocService::class)->create(DocData::from([
    'doc_type' => 'invoice',
    'items' => [
        [
            'name' => 'Consulting',
            'quantity' => 2,
            'price' => 150.00,
        ],
    ],
    'customer_data' => [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ],
    'generate_pdf' => true,
]));
```

## Core APIs

### Create a document

Use `DocService::create()` with `DocData::from([...])`.

### Update or transition status

- `app(DocService::class)->updateStatus($doc, $statusClass, $notes)`
- `$doc->markAsSent()`
- `$doc->markAsPaid()`
- `$doc->cancel()`

### Generate or locate PDFs

- `app(DocService::class)->generatePdf($doc, save: true)`
- `app(DocService::class)->downloadPdf($doc)`

### Record payments

Use `app(DocService::class)->recordPayment($doc, $paymentData)`.

### Send emails

- `app(DocEmailService::class)->send($doc, $recipientEmail, $recipientName)`
- `app(DocEmailService::class)->sendReminder($doc, $recipientEmail)`

## Supported document types

The default config ships these types:

- `invoice`
- `quotation`
- `receipt`
- `credit_note`
- `delivery_note`
- `proforma_invoice`

## Configuration highlights

Important keys in `config/docs.php`:

- `database`
- `defaults`
- `payment_methods`
- `owner`
- `email`
- `einvoice`
- `types`
- `numbering`
- `storage`
- `pdf`
- `company`

## Notes

- Owner scoping is controlled by `docs.owner.*`.
- Template lookup is owner-aware when owner mode is enabled.
- Email tracking routes are registered under `/docs/track/*`.
- Per-type storage overrides live under `docs.types.{type}.storage`.

## Documentation

See the package docs in `packages/docs/docs/`:

- `00-overview.md`
- `01-installation.md`
- `02-usage.md`
- `03-pdf-generation.md`
- `04-status-management.md`
- `05-templates.md`
- `06-tailwind-usage.md`
- `99-troubleshooting.md`
