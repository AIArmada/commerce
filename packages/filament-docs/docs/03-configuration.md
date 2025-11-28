# Configuration

## Publishing Configuration

```bash
php artisan vendor:publish --tag=filament-docs-config
```

Creates `config/filament-docs.php`.

## Configuration File

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    |
    | The navigation group where the docs resources will be displayed.
    |
    */
    'navigation_group' => 'Documents',

    /*
    |--------------------------------------------------------------------------
    | Resource Configuration
    |--------------------------------------------------------------------------
    |
    | Configure navigation sort order for Filament resources.
    |
    */
    'resources' => [
        'navigation_sort' => [
            'docs' => 10,
            'doc_templates' => 20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Preview
    |--------------------------------------------------------------------------
    |
    | Enable PDF preview in the document view page.
    |
    */
    'enable_pdf_preview' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto Generate PDF
    |--------------------------------------------------------------------------
    |
    | Automatically generate PDF when a document is created from Filament.
    |
    */
    'auto_generate_pdf' => true,
];
```

## Configuration Options

### Navigation Group

Change where resources appear in the sidebar:

```php
'navigation_group' => 'Billing',
```

Set to `null` to remove grouping:

```php
'navigation_group' => null,
```

### Navigation Sort Order

Control the order of resources within the group:

```php
'resources' => [
    'navigation_sort' => [
        'docs' => 5,           // Appears first
        'doc_templates' => 100, // Appears later
    ],
],
```

Lower numbers appear first in the navigation.

### PDF Preview

Enable or disable PDF preview in the view page:

```php
'enable_pdf_preview' => true,  // Show PDF preview
'enable_pdf_preview' => false, // Hide PDF preview
```

### Auto Generate PDF

Control automatic PDF generation on document creation:

```php
'auto_generate_pdf' => true,  // Generate PDF automatically
'auto_generate_pdf' => false, // Manual generation only
```

When disabled, use the "Generate PDF" action to create PDFs.

## Runtime Configuration

Access configuration values in your code:

```php
// Get navigation group
$group = config('filament-docs.navigation_group');

// Get resource sort order
$sortOrder = config('filament-docs.resources.navigation_sort.docs');

// Check if auto-generate is enabled
if (config('filament-docs.auto_generate_pdf', true)) {
    // Generate PDF logic
}

// Check if preview is enabled
$showPreview = config('filament-docs.enable_pdf_preview', true);
```

## Environment Variables

Override configuration via `.env`:

```env
# Not built-in, but you can add support in config file:
FILAMENT_DOCS_NAVIGATION_GROUP=Billing
FILAMENT_DOCS_AUTO_PDF=false
```

Then update your config:

```php
'navigation_group' => env('FILAMENT_DOCS_NAVIGATION_GROUP', 'Documents'),
'auto_generate_pdf' => env('FILAMENT_DOCS_AUTO_PDF', true),
```

## Related Configuration

### Docs Package Configuration

The underlying docs package has its own configuration:

```bash
php artisan vendor:publish --tag=docs-config
```

Key settings in `config/docs.php`:

- **company** - Default company information
- **numbering** - Document number generation strategies
- **storage** - PDF storage disk and path
- **pdf** - Default PDF settings

### Storage Configuration

Configure where PDFs are stored in `config/docs.php`:

```php
'storage' => [
    'disk' => 'local',
    'path' => 'docs',
],
```

For S3 storage:

```php
'storage' => [
    'disk' => 's3',
    'path' => 'documents/pdfs',
],
```
