# Templates

Templates are Blade views that define document appearance.

## Template Path Convention

Templates are resolved using:

```
docs::templates.<template-slug>
```

**Examples:**
- `docs::templates.doc-default` → Default template
- `docs::templates.modern` → Custom modern template

## Template Resolution Priority

1. **By UUID** - if `doc_template_id` provided
2. **By Slug** - if `template_slug` provided
3. **Database Default** - `is_default = true` for doc type
4. **Config Fallback** - `docs.types.{type}.default_template`

## Creating Templates

### 1. Create the Blade View

Publish package views:

```bash
php artisan vendor:publish --tag=docs-views
```

Create template in `resources/views/vendor/docs/templates/`:

```blade
<!-- modern.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $doc->doc_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="mx-auto max-w-4xl bg-white p-8 shadow">
        <!-- Header -->
        <h1 class="text-4xl font-bold text-indigo-600">
            {{ strtoupper($doc->doc_type) }}
        </h1>
        <p class="text-gray-600">{{ $doc->doc_number }}</p>

        <!-- Items Table -->
        <table class="mt-8 w-full">
            <thead class="bg-indigo-600 text-white">
                <tr>
                    <th class="p-3 text-left">Item</th>
                    <th class="p-3 text-right">Qty</th>
                    <th class="p-3 text-right">Price</th>
                    <th class="p-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($doc->items as $item)
                <tr class="border-b">
                    <td class="p-3">{{ $item['name'] }}</td>
                    <td class="p-3 text-right">{{ $item['quantity'] ?? 1 }}</td>
                    <td class="p-3 text-right">{{ $doc->currency }} {{ number_format($item['price'], 2) }}</td>
                    <td class="p-3 text-right">{{ $doc->currency }} {{ number_format(($item['quantity'] ?? 1) * $item['price'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Total -->
        <div class="mt-4 text-right text-xl font-bold">
            Total: {{ $doc->currency }} {{ number_format($doc->total, 2) }}
        </div>
    </div>
</body>
</html>
```

### 2. Register Template in Database

```php
use AIArmada\Docs\Models\DocTemplate;

DocTemplate::create([
    'name' => 'Modern Template',
    'slug' => 'modern',
    'description' => 'A modern design',
    'view_name' => 'modern',
    'doc_type' => 'invoice',
    'is_default' => false,
    'settings' => [
        'pdf' => [
            'format' => 'a4',
            'print_background' => true,
        ],
    ],
]);
```

### 3. Use the Template

```php
$document = $docService->createDoc(DocData::from([
    'template_slug' => 'modern',
    'doc_type' => 'invoice',
    // ... other data
]));
```

## Available Template Variables

```php
$doc->doc_number          // Document number
$doc->doc_type            // Type (invoice, receipt)
$doc->status              // DocStatus enum
$doc->issue_date          // Carbon instance
$doc->due_date            // Carbon instance (nullable)
$doc->subtotal            // Subtotal amount
$doc->tax_amount          // Tax amount
$doc->discount_amount     // Discount amount
$doc->total               // Total amount
$doc->currency            // Currency code
$doc->notes               // Notes
$doc->terms               // Terms and conditions
$doc->customer_data       // Customer array
$doc->company_data        // Company array
$doc->items               // Line items array
$doc->metadata            // Additional metadata
```

## Setting Default Template

```php
$template->setAsDefault();
```

This automatically unsets other defaults for the same doc type.

## Using Tailwind CSS

The recommended approach uses Tailwind CDN:

```html
<script src="https://cdn.tailwindcss.com"></script>
```

For production, consider building a dedicated CSS file. See [Tailwind Usage Guide](./05-tailwind-usage.md).
