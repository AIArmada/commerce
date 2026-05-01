---
title: Tailwind CSS Usage
---

# Tailwind CSS Usage

The docs package uses Spatie Laravel PDF and Browsershot, so standard Tailwind-friendly Blade templates work well for generated PDFs.

## Basic Setup

The default template includes Tailwind CSS via CDN:

```html
<script src="https://cdn.tailwindcss.com"></script>
```

## Advanced Setup: Build Process

For production, use a proper Tailwind CSS build:

### Install Tailwind

```bash
npm install -D tailwindcss
```

### Create Config

```js
// tailwind.docs.config.js
module.exports = {
  content: [
    './resources/views/vendor/docs/**/*.blade.php',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          primary: '#1f2937',
          secondary: '#3b82f6',
        },
      },
    },
  },
}
```

### Build Script

```json
{
  "scripts": {
    "build:docs-css": "tailwindcss -c tailwind.docs.config.js -o public/css/docs.css --minify"
  }
}
```

### Use in Template

```blade
<head>
    <link rel="stylesheet" href="{{ public_path('css/docs.css') }}">
</head>
```

## Custom Fonts

### Google Fonts

```html
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
```

### Self-Hosted

```html
<style>
    @font-face {
        font-family: 'CustomFont';
        src: url('{{ public_path('fonts/CustomFont.woff2') }}') format('woff2');
    }
    body { font-family: 'CustomFont', sans-serif; }
</style>
```

## Common Patterns

### Status Badge

```blade
<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
  @if(\Commerce\Docs\States\DocStatus::normalize($doc->status) === 'paid') bg-green-100 text-green-800
  @elseif(\Commerce\Docs\States\DocStatus::normalize($doc->status) === 'pending') bg-yellow-100 text-yellow-800
  @elseif(\Commerce\Docs\States\DocStatus::normalize($doc->status) === 'overdue') bg-red-100 text-red-800
    @else bg-gray-100 text-gray-800
    @endif">
    {{ $doc->status->label() }}
</span>
```

### Items Table

```blade
<table class="w-full">
    <thead>
        <tr class="border-b-2 border-gray-900">
            <th class="pb-3 text-left text-sm font-semibold">Item</th>
            <th class="pb-3 text-right text-sm font-semibold">Qty</th>
            <th class="pb-3 text-right text-sm font-semibold">Price</th>
            <th class="pb-3 text-right text-sm font-semibold">Total</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
        @foreach($doc->items as $item)
        <tr>
            <td class="py-4 text-sm">{{ $item['name'] }}</td>
            <td class="py-4 text-right text-sm">{{ $item['quantity'] }}</td>
            <td class="py-4 text-right text-sm">{{ number_format($item['price'], 2) }}</td>
            <td class="py-4 text-right text-sm font-medium">
                {{ number_format($item['quantity'] * $item['price'], 2) }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
```

## Best Practices

1. **Keep it Simple** - PDFs have limitations. Stick to basic Tailwind utilities.
2. **Test PDF Output** - Always test in PDF format. Some CSS may not render correctly.
3. **Use Absolute Units** - Prefer fixed dimensions for consistent PDF rendering.
4. **Optimize Images** - Keep PDF file size reasonable.
5. **Print Consideration** - Use appropriate colors and contrast.
6. **Page Breaks** - For multi-page documents:
   ```blade
   <div class="page-break-after:always"></div>
   ```

## Practical advice

- Keep layouts simple and print-friendly.
- Prefer explicit spacing and fixed widths for predictable PDF output.
- If you build a dedicated CSS file, make sure the generated path is readable by the PDF process.
