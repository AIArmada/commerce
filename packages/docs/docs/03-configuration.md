---
title: Configuration
---

# Configuration

Configuration lives in `config/docs.php`.

## Database

```php
'database' => [
    'table_prefix' => env('DOCS_TABLE_PREFIX', 'docs_'),
    'json_column_type' => env('DOCS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    'tables' => [
        'docs' => 'docs_docs',
        'doc_templates' => 'docs_doc_templates',
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
    'tax_rate' => env('DOCS_TAX_RATE', 0),
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
    'invoice' => ['default_template' => 'doc-default', 'numbering' => ['prefix' => 'INV']],
    'quotation' => ['default_template' => 'doc-default', 'numbering' => ['prefix' => 'QUO']],
    'receipt' => ['default_template' => 'doc-default', 'numbering' => ['prefix' => 'RCP']],
    'credit_note' => ['default_template' => 'doc-default', 'numbering' => ['prefix' => 'CN']],
    'delivery_note' => ['default_template' => 'doc-default', 'numbering' => ['prefix' => 'DN']],
    'proforma_invoice' => ['default_template' => 'doc-default', 'numbering' => ['prefix' => 'PI']],
],
```

Each type can choose its own template and numbering prefix while still using the shared numbering strategy.

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
],
```

Global storage defaults can be overridden again per document type.

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