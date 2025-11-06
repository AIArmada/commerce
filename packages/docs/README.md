# Docs Package for Laravel

Modern Laravel package for generating professional documents (invoices, receipts, and more) with PDF support using Spatie Laravel PDF and Tailwind CSS.

## Features

- 📄 Generate professional PDF documents with Blade templates
- 🎨 Tailwind CSS support for beautiful, customizable designs
- 📊 Support for multiple document types: invoices, receipts (expandable)
- 🔢 Automatic document numbering with configurable formats
- 📝 Multiple templates support for each document type
- 📱 Status tracking (Draft, Pending, Sent, Paid, etc.)
- 💾 Store PDFs on any Laravel filesystem disk
- 📧 Email document capabilities
- 🔄 Status history tracking
- 🏢 Configurable company and customer data

## Supported Document Types

### Currently Supported
- **Invoices** - Full invoice generation with line items, taxes, discounts
- **Receipts** - Payment receipts (coming soon)

### Expandable Architecture
The package is designed to easily support additional document types such as quotations, purchase orders, delivery notes, and more.

## Installation

Install the package via Composer:

```bash
composer require aiarmada/docs
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=docs-config
```

Run the migrations:

```bash
php artisan migrate
```

## Configuration

The package configuration is located in `config/docs.php`. You can customize:

- Document type configurations (invoice, receipt)
- Document number formats per type
- Default templates per type
- PDF storage settings
- Company information
- Currency and tax defaults

## Usage

### Creating Documents

```php
use AIArmada\Docs\Facades\Document;
use AIArmada\Docs\DataObjects\DocumentData;
use AIArmada\Docs\Enums\DocumentStatus;

// Create an invoice
$document = Document::createDocument(DocumentData::from([
    'document_type' => 'invoice',
    'items' => [
        [
            'name' => 'Web Development Service',
            'description' => 'Custom website development',
            'quantity' => 1,
            'price' => 2500.00,
        ],
        [
            'name' => 'Hosting (Annual)',
            'quantity' => 1,
            'price' => 500.00,
        ],
    ],
    'customer_data' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'address' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'state' => 'Federal Territory',
        'postal_code' => '50000',
        'country' => 'Malaysia',
    ],
    'notes' => 'Thank you for your business!',
    'terms' => 'Payment due within 30 days.',
    'generate_pdf' => true,
]));
```

### Generating PDF

```php
// Generate and save PDF
$pdfUrl = Document::generatePdf($document, save: true);

// Generate PDF without saving
$pdfContent = Document::generatePdf($document, save: false);
```

### Download Document PDF

```php
$pdfUrl = Document::downloadPdf($document);
```

### Update Document Status

```php
use AIArmada\Docs\Enums\DocumentStatus;

Document::updateDocumentStatus($document, DocumentStatus::PAID, 'Payment received via bank transfer');
```

### Mark Document as Paid

```php
$document->markAsPaid();
```

### Mark Document as Sent

```php
$document->markAsSent();
```

### Email Document

```php
Document::emailDocument($document, 'customer@example.com');
```

### Link Document to a Model

You can link documents to any model using polymorphic relationships:

```php
$document = Document::createDocument(DocumentData::from([
    'document_type' => 'invoice',
    'documentable_type' => 'App\\Models\\Order',
    'documentable_id' => $order->id,
    // ... other data
]));

// Access the linked model
$order = $document->documentable;
```

## Creating Custom Templates

1. Create a new Blade view in `resources/views/vendor/docs/templates/`:

```blade
<!-- resources/views/vendor/docs/templates/modern.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Document {{ $document->document_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <!-- Your custom template design -->
</body>
</html>
```

2. Create a template record in the database:

```php
use AIArmada\Docs\Models\DocumentTemplate;

DocumentTemplate::create([
    'name' => 'Modern Template',
    'slug' => 'modern',
    'description' => 'A modern, sleek invoice design',
    'view_name' => 'modern',
    'document_type' => 'invoice',
    'is_default' => false,
    'settings' => [
        'show_logo' => true,
        'primary_color' => '#3b82f6',
    ],
]);
```

3. Use the template when creating a document:

```php
$document = Document::createDocument(DocumentData::from([
    'template_slug' => 'modern',
    // ... other data
]));
```

## Document Status

The package includes predefined statuses for documents:

- **Draft** - Initial state
- **Pending** - Awaiting approval or action
- **Sent** - Delivered to customer
- **Paid** - Payment received
- **Partially Paid** - Partial payment received
- **Overdue** - Past due date
- **Cancelled** - Cancelled
- **Refunded** - Payment refunded

## Requirements

- PHP 8.3+
- Laravel 12.0+
- Spatie Laravel PDF 1.5+
- Node.js 18+ and npm
- Puppeteer (for PDF generation)

### Installing Dependencies

After installing the package, you need to install Node.js dependencies for PDF generation:

```bash
# Install npm packages (includes puppeteer)
npm install

# Or if dependencies are already defined in package.json
npm install puppeteer
```

> **Note:** Puppeteer is required for PDF generation. The package uses Spatie Laravel PDF which relies on Puppeteer/Chromium to render PDFs from HTML.

## Development Tools

The package includes the following development tools as specified:

- **Spatie Laravel Package Tools** - For package scaffolding and structure
- **Larastan** - PHPStan wrapper for Laravel, level 6 static analysis
- **Laravel Rector** - Automated code refactoring and upgrades
- **Laravel Pint** - Opinionated PHP code style fixer

Run these tools with:

```bash
composer format    # Run Laravel Pint
composer analyse   # Run Larastan
vendor/bin/rector  # Run Rector
```

## License

MIT License. See [LICENSE](LICENSE.md) for details.
