---
title: Configuration
---

# Configuration

Configuration lives in `config/docs.php`.

## Database

```php
'database' => [
    'table_prefix' => env('DOCS_TABLE_PREFIX', 'docs_'),
    'tables' => [
        'docs' => 'docs_docs',
        'doc_templates' => 'docs_doc_templates',
        'doc_share_links' => 'docs_doc_share_links',
        'doc_status_histories' => 'docs_doc_status_histories',
        'doc_payments' => 'docs_payments',
        'doc_email_templates' => 'docs_email_templates',
        'doc_emails' => 'docs_emails',
        'doc_versions' => 'docs_versions',
        'doc_approvals' => 'docs_approvals',
        'doc_einvoice_submissions' => 'docs_einvoice_submissions',
        'doc_sequences' => 'docs_sequences',
        'sequence_numbers' => 'docs_sequence_numbers',
        'workflows' => 'docs_workflows',
        'workflow_steps' => 'docs_workflow_steps',
    ],
],
```

The package uses a dedicated `docs_` prefix by default. Table names can still be overridden individually when integrating into an existing schema.

## Defaults

```php
'defaults' => [
    'currency' => env('DOCS_CURRENCY', 'MYR'),
    'tax_rate_basis_points' => env('DOCS_TAX_RATE_BASIS_POINTS', 0),
    'due_days' => env('DOCS_DUE_DAYS', 30),
],
```

These values seed new documents when the caller does not pass explicit currency, tax, or due-date settings.

## Payment Methods

```php
'payment_methods' => [
    'bank_transfer' => 'Bank Transfer',
    'cash' => 'Cash',
    'credit_card' => 'Credit Card',
    'check' => 'Check',
    'e_wallet' => 'E-Wallet',
    'other' => 'Other',
],
```

This list is the default label map used in document payment-related UI and storage.

## Owner Scoping

```php
'owner' => [
    'enabled' => env('DOCS_OWNER_ENABLED', false),
    'include_global' => env('DOCS_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('DOCS_OWNER_AUTO_ASSIGN_ON_CREATE', true),
],
```

When owner mode is on, document reads and writes should follow the same owner-boundary rules as the rest of Commerce.

## Email

```php
'email' => [
    'queue_enabled' => env('DOCS_EMAIL_QUEUE_ENABLED', true),
    'queue' => env('DOCS_EMAIL_QUEUE', 'default'),
    'attach_pdf' => env('DOCS_EMAIL_ATTACH_PDF', true),
    'from_address' => env('DOCS_EMAIL_FROM_ADDRESS'),
    'from_name' => env('DOCS_EMAIL_FROM_NAME'),
    'tracking' => [
        'enabled' => env('DOCS_EMAIL_TRACKING_ENABLED', true),
    ],
],
```

This block controls queueing, sender identity, PDF attachment behavior, and email tracking.

## Integrations

```php
'einvoice' => [
    'sandbox' => env('DOCS_EINVOICE_SANDBOX', true),
],
```

The e-invoice integration is sandboxed by default.

## Document Types

```php
'types' => [
    'invoice' => ['numbering' => ['prefix' => 'INV']],
    'quotation' => ['numbering' => ['prefix' => 'QUO']],
    'receipt' => ['numbering' => ['prefix' => 'RCP']],
    'credit_note' => ['numbering' => ['prefix' => 'CN']],
    'delivery_note' => ['numbering' => ['prefix' => 'DN']],
    'proforma_invoice' => ['numbering' => ['prefix' => 'PI']],
],
```

Each type configures numbering only. Default templates are now resolved from `DocTemplate` records using `doc_type` + `is_default`, not from config keys such as `default_template`.

## Numbering

```php
'numbering' => [
    'format' => [
        'default' => env('DOCS_NUMBER_DEFAULT_FORMAT', '{PREFIX}-{YYMM}-{NUMBER}'),
        'year_format' => env('DOCS_NUMBER_YEAR_FORMAT', 'y'),
        'separator' => env('DOCS_NUMBER_SEPARATOR', '-'),
        'suffix_length' => (int) env('DOCS_NUMBER_SUFFIX_LENGTH', 6),
    ],
],
```

Use this block when you need to align document numbers with external finance or ERP expectations.

## Storage

```php
'storage' => [
    'disk' => env('DOCS_STORAGE_DISK', 'local'),
    'path' => env('DOCS_STORAGE_PATH', 'docs'),
    'rich_content_path' => env('DOCS_RICH_CONTENT_PATH', 'docs/rich-content'),
    'rich_content_visibility' => env('DOCS_RICH_CONTENT_VISIBILITY', 'private'),
],
```

Global storage defaults can be overridden again per document type. Rich-content attachments are stored separately from generated PDFs so editors and renderers can keep uploaded assets private by default.

## Sharing

```php
'sharing' => [
    'default_expiry_days' => 30,
],
```

Share links are persisted in `doc_share_links` and default to a 30-day expiry unless you pass a different `expiresAt` value to `DocRenderService::createShareLink()`.

## PDF

```php
'pdf' => [
    'format' => 'a4',
    'orientation' => 'portrait',
    'margin' => [
        'top' => 10,
        'right' => 10,
        'bottom' => 10,
        'left' => 10,
    ],
    'full_bleed' => false,
    'print_background' => true,
],
```

These are the default PDF rendering options when a document or template does not override them.

PDF options resolve in this order: package config defaults → template `settings['pdf']` → per-document `metadata['pdf']`.

## Company

```php
'company' => [
    'name' => env('DOCS_COMPANY_NAME', config('app.name')),
    'address' => env('DOCS_COMPANY_ADDRESS'),
    'city' => env('DOCS_COMPANY_CITY'),
    'state' => env('DOCS_COMPANY_STATE'),
    'postcode' => env('DOCS_COMPANY_POSTCODE'),
    'country' => env('DOCS_COMPANY_COUNTRY'),
    'phone' => env('DOCS_COMPANY_PHONE'),
    'email' => env('DOCS_COMPANY_EMAIL'),
    'website' => env('DOCS_COMPANY_WEBSITE'),
    'tax_id' => env('DOCS_COMPANY_TAX_ID'),
],
```

Company fields are used to hydrate document headers and company metadata in generated documents.