# Using Tailwind CSS with Invoice PDFs

The invoice package uses [Spatie Laravel PDF](https://spatie.be/docs/laravel-pdf/v1/introduction) which supports Tailwind CSS for styling your invoice templates.

## Why Tailwind CSS?

- 🎨 Familiar utility-first CSS framework
- 🚀 Fast development with pre-built utility classes
- 📱 Responsive design out of the box
- 🎯 No need to write custom CSS for most designs

## Basic Setup

The default invoice template already includes Tailwind CSS via CDN:

```html
<script src="https://cdn.tailwindcss.com"></script>
```

This is the simplest way to get started and works great for most use cases.

## Advanced Setup: Using Tailwind Build Process

For production applications, you may want to use a proper Tailwind CSS build process to:
- Reduce file size with purged unused styles
- Use custom Tailwind configuration
- Add custom fonts and colors
- Use Tailwind plugins

### Step 1: Install Tailwind CSS

```bash
npm install -D tailwindcss
```

### Step 2: Create Tailwind Config for Invoice Templates

Create a separate Tailwind config file for invoice templates:

```js
// tailwind.invoice.config.js
module.exports = {
  content: [
    './packages/aiarmada/invoice/resources/views/**/*.blade.php',
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
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
```

### Step 3: Build Tailwind CSS

Add a build script to your `package.json`:

```json
{
  "scripts": {
    "build:invoice-css": "tailwindcss -c tailwind.invoice.config.js -o public/css/invoice.css --minify"
  }
}
```

Run the build:

```bash
npm run build:invoice-css
```

### Step 4: Use Built CSS in Templates

Update your template to use the built CSS file:

```blade
<!-- resources/views/vendor/docs/templates/custom.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="{{ public_path('css/invoice.css') }}">
</head>
<body>
    <!-- Your invoice content -->
</body>
</html>
```

## Custom Fonts

To use custom fonts in your invoices:

### Option 1: Google Fonts

```html
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
```

### Option 2: Self-Hosted Fonts

1. Place font files in `public/fonts/`
2. Reference them in your template:

```html
<head>
    <style>
        @font-face {
            font-family: 'CustomFont';
            src: url('{{ public_path('fonts/CustomFont.woff2') }}') format('woff2');
            font-weight: 400;
            font-style: normal;
        }
        body { font-family: 'CustomFont', sans-serif; }
    </style>
</head>
```

## Common Tailwind Patterns for Invoices

### Professional Header

```blade
<div class="mb-8 border-b-2 border-gray-900 pb-8">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-4xl font-bold text-gray-900">INVOICE</h1>
            <p class="mt-2 text-sm text-gray-600">{{ $invoice->invoice_number }}</p>
        </div>
        <div class="text-right">
            <p class="text-2xl font-bold text-gray-900">{{ $invoice->company_data['name'] }}</p>
            <p class="text-sm text-gray-600">{{ $invoice->company_data['address'] }}</p>
        </div>
    </div>
</div>
```

### Status Badge

```blade
<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
    @if($invoice->status->value === 'paid') bg-green-100 text-green-800
    @elseif($invoice->status->value === 'pending') bg-yellow-100 text-yellow-800
    @elseif($invoice->status->value === 'overdue') bg-red-100 text-red-800
    @else bg-gray-100 text-gray-800
    @endif">
    {{ $invoice->status->label() }}
</span>
```

### Items Table

```blade
<table class="w-full">
    <thead>
        <tr class="border-b-2 border-gray-900">
            <th class="pb-3 text-left text-sm font-semibold text-gray-900">Item</th>
            <th class="pb-3 text-right text-sm font-semibold text-gray-900">Qty</th>
            <th class="pb-3 text-right text-sm font-semibold text-gray-900">Price</th>
            <th class="pb-3 text-right text-sm font-semibold text-gray-900">Total</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
        @foreach($invoice->items as $item)
        <tr>
            <td class="py-4 text-sm text-gray-900">{{ $item['name'] }}</td>
            <td class="py-4 text-right text-sm text-gray-900">{{ $item['quantity'] }}</td>
            <td class="py-4 text-right text-sm text-gray-900">{{ number_format($item['price'], 2) }}</td>
            <td class="py-4 text-right text-sm font-medium text-gray-900">
                {{ number_format($item['quantity'] * $item['price'], 2) }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
```

### Total Section

```blade
<div class="flex justify-end">
    <div class="w-80 space-y-2">
        <div class="flex justify-between border-t-2 border-gray-900 pt-4 text-lg font-bold">
            <span class="text-gray-900">Total:</span>
            <span class="text-gray-900">{{ $invoice->currency }} {{ number_format($invoice->total, 2) }}</span>
        </div>
    </div>
</div>
```

## Tips and Best Practices

1. **Keep it Simple**: PDFs have limitations compared to web pages. Stick to basic Tailwind utilities.

2. **Test Thoroughly**: Always test your invoices in PDF format. Some CSS features may not render correctly.

3. **Use Absolute Units**: Prefer fixed heights/widths over responsive units for consistent PDF rendering.

4. **Optimize Images**: Use optimized images to keep PDF file size reasonable.

5. **Consider Print**: Use appropriate colors and contrast for printed invoices.

6. **Page Breaks**: Use CSS page-break properties for multi-page invoices:
   ```blade
   <div class="page-break-after:always"></div>
   ```

## References

- [Spatie Laravel PDF Documentation](https://spatie.be/docs/laravel-pdf/v1/introduction)
- [Using Tailwind with Spatie Laravel PDF](https://spatie.be/docs/laravel-pdf/v1/advanced-usage/using-tailwind)
- [Laravel Daily Article on Spatie PDF](https://laraveldaily.com/post/laravel-spatie-pdf-package-invoice-images-css)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
