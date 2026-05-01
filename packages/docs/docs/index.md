---
title: AIArmada Docs
---

# AIArmada Docs

A document management package for Laravel with PDF generation, email delivery, numbering, and optional owner scoping.

## Overview

AIArmada Docs includes:

- **Document creation** via `DocService`
- **PDF generation** via Spatie Laravel PDF / Browsershot
- **Email sending and tracking** via `DocEmailService`
- **Automatic numbering** via `SequenceManager`
- **Template management** via `DocTemplate`
- **Optional owner scoping** via `HasOwner`

## Table of Contents

1. [Overview](00-overview.md) - Architecture and core concepts
2. [Installation](01-installation.md) - Setup and configuration
3. [Usage](02-usage.md) - Creating and managing documents
4. [PDF Generation](03-pdf-generation.md) - Generating PDF documents
5. [Status Management](04-status-management.md) - Status transitions and history
6. [Templates](05-templates.md) - Document templates
7. [Tailwind Usage](06-tailwind-usage.md) - Styling PDF templates with Tailwind
8. [Troubleshooting](99-troubleshooting.md) - Common issues and solutions

## Quick Start

```bash
composer require aiarmada/docs
php artisan migrate
```

```php
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Services\DocService;

$doc = app(DocService::class)->create(DocData::from([
    'doc_type' => 'invoice',
    'items' => [
        ['name' => 'Consulting', 'quantity' => 10, 'price' => 150],
    ],
    'customer_data' => [
        'name' => 'Acme Corp',
        'email' => 'billing@acme.com',
    ],
    'generate_pdf' => true,
]));
```

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.4+ |
| Laravel | 12.0+ |
| aiarmada/commerce-support | Required |
| spatie/laravel-pdf | Required transitively |

## Related Packages

- **aiarmada/filament-docs** - Filament admin panel integration
- **aiarmada/commerce-support** - Multi-tenancy and shared utilities

## Support

For issues and feature requests, please use the [GitHub Issues](https://github.com/aiarmada/commerce/issues).
