---
title: Installation
---

import Aside from "@components/Aside.astro"

# Installation

## Requirements

- PHP 8.4+
- Laravel 12.0+
- Spatie Laravel PDF 1.5+
- Node.js 18+ and npm
- Puppeteer (for PDF generation)

## Install via Composer

```bash
composer require aiarmada/docs
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=docs-config
```

## Publish Views (Optional)

To customize templates:

```bash
php artisan vendor:publish --tag=docs-views
```

## Run Migrations

```bash
php artisan migrate
```

## Install PDF Dependencies

Puppeteer is required for PDF generation:

```bash
npm install puppeteer
```

## Environment Configuration

Add to your `.env` file:

```env
# Company Information
DOCS_COMPANY_NAME="Your Company Name"
DOCS_COMPANY_ADDRESS="123 Business Street"
DOCS_COMPANY_CITY="Kuala Lumpur"
DOCS_COMPANY_STATE="Federal Territory"
DOCS_COMPANY_POSTCODE="50000"
DOCS_COMPANY_COUNTRY="Malaysia"
DOCS_COMPANY_PHONE="+60 3-1234-5678"
DOCS_COMPANY_EMAIL="billing@yourcompany.com"
DOCS_COMPANY_WEBSITE="https://yourcompany.com"
DOCS_COMPANY_TAX_ID="123456789"

# Storage
DOCS_STORAGE_DISK=local
DOCS_STORAGE_PATH=docs

# Defaults
DOCS_CURRENCY=MYR
DOCS_TAX_RATE=0.06
DOCS_DUE_DAYS=30

# PDF Generation
DOCS_GENERATE_PDF=true
```


## Multi-Tenant Setup

<Aside variant="warning">
  Owner scoping is **disabled by default** (`DOCS_OWNER_ENABLED=false`). In a multi-tenant deployment every tenant will see all invoices and documents unless you enable it. Set `DOCS_OWNER_ENABLED=true` and bind `OwnerResolverInterface` before going live.
</Aside>

```env
DOCS_OWNER_ENABLED=true
```

Bind the resolver in `AppServiceProvider::register()`:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

$this->app->bind(OwnerResolverInterface::class, CurrentTenantResolver::class);
```
