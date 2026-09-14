---
title: Usage
---

# Usage

## Creating Documents

Use the `DocService` to create documents:

```php
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\CommerceSupport\Support\OwnerContext;

$docService = app(DocService::class);

$document = $docService->create(DocData::from([
    'doc_type' => 'invoice',
    'items' => [
        [
            'name' => 'Web Development Service',
            'description' => 'Custom website development',
            'quantity' => 1,
            'unit_price_minor' => 250_000,
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
        ['name' => 'Web Development Service', 'quantity' => 1, 'unit_price_minor' => 250_000],
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
        ['name' => 'Item 1', 'quantity' => 2, 'unit_price_minor' => 10_000],  // $200
        ['name' => 'Item 2', 'quantity' => 1, 'unit_price_minor' => 15_000],  // $150
    ],
    'tax_rate_basis_points' => 600,           // 6% tax
    'discount_amount_minor' => 2_500,      // $25 discount
]));

// Automatically calculated:
// Subtotal: $350
// Tax: $21 (6% of $350)
// Discount: -$25
// Total: $346
```

When items are present, any explicitly supplied `subtotal_minor`, `tax_amount_minor`, or `total_minor` must match the items-derived values; mismatches throw `InvalidArgumentException` instead of persisting contradictory totals. Document types must be members of the `DocType` enum, and unknown status strings throw instead of silently becoming drafts.

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

Use `OwnerContext::withOwner($owner, ...)` around background or explicit tenant work. Do not assign `owner_type` or `owner_id` yourself; new rows inherit the current owner and inbound document/template IDs are checked against the same owner scope.

## Updating Documents

`DocService::update()` persists an explicit allowlist only: template selection, docable link, dates, body, notes, terms, customer/company data, items, metadata, currency, discount, and pdf options. `doc_number`, `doc_type`, `pdf_path`, lifecycle timestamps, and derived totals are immutable here and silently ignored when passed.

```php
$document = app(DocService::class)->update($document, [
    'notes' => 'Updated payment terms',
    'items' => [
        ['name' => 'Item 1', 'quantity' => 2, 'unit_price_minor' => 10_000],
    ],
    'status' => Sent::class,
]);
```

Totals are always re-derived from items (or from the stored subtotal/tax when only the discount changes). A `status` key goes through the state machine: unchanged values are a no-op, unreachable transitions throw, and every update records a new version snapshot.

Restore an earlier snapshot through the same path; immutable snapshot keys are ignored and the restore itself is versioned:

```php
$version->restore(); // or $version->restore('Rollback to signed copy');
```

## Recording Payments

Use `DocService::recordPayment()` for document payments. Recorded payments are always stored with status `paid` and a server-set `paid_at`; the method must exist in `docs.payment_methods`.

```php
use AIArmada\Docs\Services\DocService;

$payment = app(DocService::class)->recordPayment($document, [
    'amount_minor' => 10_000,
    'currency' => 'MYR',
    'payment_method' => 'bank_transfer',
    'reference' => 'PAY-123',
]);
```

Payment recording is transactional, locks the document inside its owner scope, rejects non-positive amounts and overpayments, and transitions the document to `partially_paid` or `paid` through the canonical document state machine. Outstanding balances count `paid` payments only, so refunded, failed, or voided rows never reduce what is owed.

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

Email open and click tracking are approximate engagement signals: they are GET endpoints and may be triggered by mail clients, prefetchers, or crawlers. Tracking destinations are encrypted in the token and redirects accept only relative paths or `http`/`https` URLs. Tracking tokens expire after `docs.email.tracking.ttl_days` days, and the public tracking/share routes are throttled. Mailed PDFs reuse the stored file when it is fresh instead of re-rendering on every send.
