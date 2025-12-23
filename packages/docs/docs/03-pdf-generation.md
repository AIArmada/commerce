---
title: PDF Generation
---

# PDF Generation

## Generating PDFs

```php
$docService = app(DocService::class);

// Generate and save to disk
$pdfPath = $docService->generatePdf($document, save: true);
// Returns: "docs/invoices/inv25-abc123.pdf"

// Generate without saving (returns PDF content)
$pdfContent = $docService->generatePdf($document, save: false);
```

## Stream to Browser

```php
$pdfContent = $docService->generatePdf($document, save: false);

return response($pdfContent)
    ->header('Content-Type', 'application/pdf')
    ->header('Content-Disposition', 'inline; filename="'.$document->doc_number.'.pdf"');
```

## Download PDF

```php
// Generates PDF if not exists, or returns existing path
$pdfPath = $docService->downloadPdf($document);
```

## PDF Configuration

Configure in `config/docs.php`:

```php
'pdf' => [
    'format' => 'a4',              // a4, letter, legal, a3, a5
    'orientation' => 'portrait',   // portrait or landscape
    'margin' => [
        'top' => 10,               // Margin in mm
        'right' => 10,
        'bottom' => 10,
        'left' => 10,
    ],
],
```

## Per-Document Options

Override PDF settings when creating documents:

```php
$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'pdf_options' => [
        'format' => 'letter',
        'orientation' => 'landscape',
        'margin' => [
            'top' => 5,
            'right' => 5,
            'bottom' => 5,
            'left' => 5,
        ],
    ],
    // ... other data
]));
```

## Storage Configuration

Configure storage per document type:

```php
'storage' => [
    'disk' => 'local',     // Default disk
    'path' => 'docs',      // Default path
    'paths' => [
        'invoice' => 'docs/invoices',
        'receipt' => 'docs/receipts',
    ],
],
```

Access stored PDFs:

```php
use Illuminate\Support\Facades\Storage;

$disk = config('docs.storage.disk');
$path = $document->pdf_path;

// Get URL (if disk supports it)
$url = Storage::disk($disk)->url($path);

// Download
return Storage::disk($disk)->download($path);
```
