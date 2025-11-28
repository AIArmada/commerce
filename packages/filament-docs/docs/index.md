# Filament Docs Documentation

Filament admin panel integration for the AIArmada Docs package.

## Overview

This package provides Filament resources for managing documents and templates created by the `aiarmada/docs` package. It includes:

- **DocResource** - Manage invoices, receipts, and other documents
- **DocTemplateResource** - Create and configure document templates
- **Status History** - Track all status changes for audit purposes
- **PDF Actions** - Generate and download PDFs directly from the panel

## Table of Contents

1. [Installation](01-installation.md) - Setup and panel registration
2. [Resources](02-resources.md) - DocResource and DocTemplateResource details
3. [Configuration](03-configuration.md) - Customization options

## Quick Start

```bash
composer require aiarmada/filament-docs
```

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentDocsPlugin::make(),
        ]);
}
```

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.4+ |
| Laravel | 12.0+ |
| Filament | 5.0+ |
| aiarmada/docs | Required |

## Support

For issues and feature requests, please use the [GitHub Issues](https://github.com/aiarmada/commerce/issues).
