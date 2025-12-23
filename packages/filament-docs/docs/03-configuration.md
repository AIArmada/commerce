---
title: Configuration
---

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
    // Navigation
    'navigation' => [
        'group' => 'Documents',
    ],

    // Features
    'features' => [
        'auto_generate_pdf' => true,
    ],

    // Resources
    'resources' => [
        'navigation_sort' => [
            'docs' => 10,
            'doc_templates' => 20,
            'sequences' => 90,
            'email_templates' => 91,
            'pending_approvals' => 15,
            'aging_report' => 100,
        ],
    ],
];
```

## Configuration Options

### Navigation Group

Change where resources appear in the sidebar:

```php
'navigation' => [
    'group' => 'Billing',
],
```

Set to `null` to remove grouping:

```php
'navigation' => [
    'group' => null,
],
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

### Auto Generate PDF

Control automatic PDF generation on document creation:

```php
'features' => [
    'auto_generate_pdf' => true,  // Generate PDF automatically
    'auto_generate_pdf' => false, // Manual generation only
],
```

When disabled, use the "Generate PDF" action to create PDFs.

## Runtime Configuration

Access configuration values in your code:

```php
// Get navigation group
$group = config('filament-docs.navigation.group');

// Get resource sort order
$sortOrder = config('filament-docs.resources.navigation_sort.docs');

// Check if auto-generate is enabled
if (config('filament-docs.features.auto_generate_pdf', true)) {
    // Generate PDF logic
}
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
