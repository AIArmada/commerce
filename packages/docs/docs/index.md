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

1. [Overview](01-overview.md) - Architecture and core concepts
2. [Installation](02-installation.md) - Setup and configuration
3. [Configuration](03-configuration.md) - Runtime defaults and document behavior
4. [Usage](04-usage.md) - Creating and managing documents
5. [PDF Generation](05-pdf-generation.md) - Generating PDF documents
6. [Status Management](06-status-management.md) - Status transitions and history
7. [Templates](07-templates.md) - Document templates
8. [Tailwind Usage](08-tailwind-usage.md) - Styling PDF templates with Tailwind
9. [Troubleshooting](99-troubleshooting.md) - Common issues and solutions

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
