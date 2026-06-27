---
title: Usage
---

# Usage

## Creating Documents

Use the `DocService` to create documents:

```php
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\DataObjects\DocData;

$docService = app(DocService::class);

$document = $docService->create(DocData::from([
    'doc_type' => 'invoice',
    'items' => [
        [
            'name' => 'Web Development Service',
            'description' => 'Custom website development',
            'quantity' => 1,
            'price' => 2500.00,
        ],
    ],
    'customer_data' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'address' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country' => 'Malaysia',
    ],
    'notes' => 'Thank you for your business!',
    'generate_pdf' => true,
]));
```

## Document Types

The package supports multiple document types:

- **invoice** - Full invoice with line items, taxes, discounts
- **receipt** - Payment receipts

Configure types in `config/docs.php`:

```php
'types' => [
    'invoice' => [
        'numbering' => [
            'strategy' => DefaultNumberStrategy::class,
            'prefix' => 'INV',
        ],
    ],
    'receipt' => [
        'numbering' => [
            'strategy' => DefaultNumberStrategy::class,
            'prefix' => 'RCP',
        ],
    ],
],
```

Default template selection no longer lives in config. `DocService` resolves the default template from `DocTemplate` records for the current `doc_type`, or you can pass `doc_template_id` / `template_slug` when you want a specific layout.

## Selecting a Template Explicitly

```php
$document = $docService->create(DocData::from([
    'doc_type' => 'invoice',
    'template_slug' => 'modern-invoice',
    'items' => [
        ['name' => 'Web Development Service', 'quantity' => 1, 'price' => 2500.00],
    ],
    'customer_data' => [
        'name' => 'John Doe',
    ],
]));
```

If the selected template contains a `rich_body` block, you can also pass Tiptap JSON in `body`. `DocRenderService::validateDocPayload()` rejects bodies when the template has no rich-body block, and rejects templates with a `line_items` block when you submit an empty `items` array.

## Automatic Calculations

The package automatically calculates totals:

```php
$document = $docService->create(DocData::from([
    'doc_type' => 'invoice',
    'items' => [
        ['name' => 'Item 1', 'quantity' => 2, 'price' => 100],  // $200
        ['name' => 'Item 2', 'quantity' => 1, 'price' => 150],  // $150
    ],
    'tax_rate' => 0.06,           // 6% tax
    'discount_amount' => 25,      // $25 discount
]));

// Automatically calculated:
// Subtotal: $350
// Tax: $21 (6% of $350)
// Discount: -$25
// Total: $346
```

## Linking to Models

Link documents to orders, tickets, or any model:

```php
use App\Models\Order;

$order = Order::find($orderId);

$document = $docService->create(DocData::from([
    'doc_type' => 'invoice',
    'docable_type' => Order::class,
    'docable_id' => $order->id,
    'items' => [...],
    'customer_data' => [...],
]));

// Access linked model
$order = $document->docable;
```

## Querying Documents

```php
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Paid;

// Get paid invoices
$paidInvoices = Doc::where('doc_type', 'invoice')
    ->where('status', Paid::class)
    ->get();

// Get docs with a generated PDF
$docsWithPdf = Doc::whereNotNull('pdf_path')->get();

// Eager load relationships
$docs = Doc::with(['template', 'statusHistories', 'docable'])
    ->get();
```

When owner mode is enabled, the package models use `HasOwner` and follow the configured owner-scoping rules from `commerce-support`.

## Recording Payments

Use `DocService::recordPayment()` for document payments. Payment statuses are backed by `DocPaymentStatus`; supported values are `paid`, `refunded`, `failed`, and `voided`.

```php
use AIArmada\Docs\Enums\DocPaymentStatus;
use AIArmada\Docs\Services\DocService;

$payment = app(DocService::class)->recordPayment($document, [
    'status' => DocPaymentStatus::Paid,
    'amount' => 100.00,
    'currency' => 'MYR',
    'payment_method' => 'bank_transfer',
    'reference' => 'PAY-123',
    'paid_at' => now(),
]);
```

## Rendering and Share Links

`DocService` delegates HTML/PDF rendering and share-link generation to `DocRenderService`.

```php
use AIArmada\Docs\DataObjects\ShareLinkData;
use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Enums\ShareLinkAction;
use AIArmada\Docs\Services\DocRenderService;

$renderer = app(DocRenderService::class);

$html = $renderer->renderHtml($document, RenderAudience::CustomerView);
$pdf = $renderer->renderPdf($document);

$shareLink = $renderer->createShareLink($document, new ShareLinkData(
    allowedActions: [ShareLinkAction::View, ShareLinkAction::Pdf],
));

$plainToken = $shareLink->plainToken();
```

Share links resolve the document back inside its owner or explicit-global context, then serve either the HTML customer view or inline PDF with hardened response headers.
