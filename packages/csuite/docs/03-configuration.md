---
title: Configuration
---

# Configuration

Environment and configuration options for AIArmada Commerce.

## Environment Variables

### CHIP Payment Gateway

```env
CHIP_ENVIRONMENT=sandbox
CHIP_COLLECT_API_KEY=your-collect-api-key
CHIP_COLLECT_BRAND_ID=your-brand-id
CHIP_COLLECT_PUBLIC_KEY=your-collect-public-key
CHIP_SEND_API_KEY=your-send-api-key
CHIP_SEND_API_SECRET=your-send-api-secret
```

| Variable | Description | Default |
|----------|-------------|---------|
| `CHIP_ENVIRONMENT` | `sandbox` or `production` | `sandbox` |
| `CHIP_COLLECT_API_KEY` | CHIP Collect secret key | - |
| `CHIP_COLLECT_BRAND_ID` | Your CHIP brand ID | - |
| `CHIP_COLLECT_PUBLIC_KEY` | Collect public key for webhook verification | - |
| `CHIP_SEND_API_KEY` | CHIP Send API key | - |
| `CHIP_SEND_API_SECRET` | CHIP Send API secret | - |

### J&T Express

```env
JNT_ENVIRONMENT=testing
JNT_API_ACCOUNT=your-api-account
JNT_PRIVATE_KEY=your-private-key
JNT_CUSTOMER_CODE=your-customer-code
JNT_PASSWORD=your-password
```

| Variable | Description | Default |
|----------|-------------|---------|
| `JNT_ENVIRONMENT` | `testing` or `production` | `testing` |
| `JNT_API_ACCOUNT` | Your J&T API account | - |
| `JNT_PRIVATE_KEY` | Your J&T private key | - |
| `JNT_CUSTOMER_CODE` | Your customer code | - |
| `JNT_PASSWORD` | Your J&T API password | - |
| `JNT_BASE_URL_TESTING` | Testing API endpoint override | Demo endpoint |
| `JNT_BASE_URL_PRODUCTION` | Production API endpoint override | Production endpoint |

### Database

```env
COMMERCE_JSON_COLUMN_TYPE=json
```

| Variable | Description | Default |
|----------|-------------|---------|
| `COMMERCE_JSON_COLUMN_TYPE` | `json` or `jsonb` (PostgreSQL) | `json` |

Per-package overrides:

```env
CART_JSON_COLUMN_TYPE=jsonb
VOUCHERS_JSON_COLUMN_TYPE=jsonb
DOCS_JSON_COLUMN_TYPE=jsonb
```

## Configuration Files

### Cart

```php
// config/cart.php
return [
    'database' => [
        'json_column_type' => env('CART_JSON_COLUMN_TYPE', 'jsonb'),
        'table' => env('CART_DB_TABLE', 'carts'),
        'conditions_table' => env('CART_CONDITIONS_TABLE', 'conditions'),
        'tables' => [
            'snapshots' => env('CART_SNAPSHOTS_TABLE', 'cart_snapshots'),
            'snapshot_items' => env('CART_SNAPSHOT_ITEMS_TABLE', 'cart_snapshot_items'),
            'snapshot_conditions' => env('CART_SNAPSHOT_CONDITIONS_TABLE', 'cart_snapshot_conditions'),
        ],
    ],
    'money' => [
        'default_currency' => env('CART_DEFAULT_CURRENCY', 'MYR'),
        'rounding_mode' => env('CART_ROUNDING_MODE', 'half_up'),
    ],
    'owner' => [
        'enabled' => env('CART_OWNER_ENABLED', false),
        'include_global' => env('CART_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('CART_OWNER_AUTO_ASSIGN_ON_CREATE', true),
    ],
    'limits' => [
        'max_items' => env('CART_MAX_ITEMS', 1000),
        'max_item_quantity' => env('CART_MAX_QUANTITY', 10000),
    ],
];
```

### Vouchers

```php
// config/vouchers.php
return [
    'database' => [
        'table_prefix' => env('VOUCHERS_TABLE_PREFIX', env('COMMERCE_TABLE_PREFIX', '')),
        'tables' => [
            'vouchers' => 'vouchers',
            'voucher_usage' => 'voucher_usage',
            'voucher_wallets' => 'voucher_wallets',
        ],
        'json_column_type' => env('VOUCHERS_JSON_COLUMN_TYPE', 'jsonb'),
    ],
    'code' => [
        'prefix' => env('VOUCHERS_CODE_PREFIX', ''),
        'length' => (int) env('VOUCHERS_CODE_LENGTH', 8),
        'auto_uppercase' => true,
    ],
];
```

### CHIP

```php
// config/chip.php
return [
    'environment' => env('CHIP_ENVIRONMENT', 'sandbox'),
    'collect' => [
        'base_url' => env('CHIP_COLLECT_BASE_URL', 'https://gate.chip-in.asia/api/v1/'),
        'api_key' => env('CHIP_COLLECT_API_KEY'),
        'brand_id' => env('CHIP_COLLECT_BRAND_ID'),
        'public_key' => env('CHIP_COLLECT_PUBLIC_KEY'),
    ],
    'send' => [
        'base_url' => [
            'sandbox' => env('CHIP_SEND_SANDBOX_URL', 'https://staging-api.chip-in.asia/api'),
            'production' => env('CHIP_SEND_PRODUCTION_URL', 'https://api.chip-in.asia/api'),
        ],
        'api_key' => env('CHIP_SEND_API_KEY'),
        'api_secret' => env('CHIP_SEND_API_SECRET'),
    ],
    'owner' => [
        'enabled' => env('CHIP_OWNER_ENABLED', false),
        'include_global' => env('CHIP_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('CHIP_OWNER_AUTO_ASSIGN', true),
    ],
    'http' => [
        'timeout' => env('CHIP_HTTP_TIMEOUT', 30),
    ],
    'webhooks' => [
        'enabled' => env('CHIP_WEBHOOKS_ENABLED', true),
        'route' => env('CHIP_WEBHOOK_ROUTE', '/chip/webhooks'),
    ],
];
```

### Docs

```php
// config/docs.php
return [
    'database' => [
        'table_prefix' => env('DOCS_TABLE_PREFIX', 'docs_'),
        'json_column_type' => env('DOCS_JSON_COLUMN_TYPE', 'jsonb'),
        'tables' => [
            'docs' => env('DOCS_TABLE', 'docs_docs'),
            'doc_templates' => env('DOC_TEMPLATES_TABLE', 'docs_doc_templates'),
            'doc_share_links' => env('DOC_SHARE_LINKS_TABLE', 'docs_doc_share_links'),
            'doc_status_histories' => env('DOC_STATUS_HISTORIES_TABLE', 'docs_doc_status_histories'),
            'doc_payments' => env('DOC_PAYMENTS_TABLE', 'docs_payments'),
            'doc_email_templates' => env('DOC_EMAIL_TEMPLATES_TABLE', 'docs_email_templates'),
            'doc_emails' => env('DOC_EMAILS_TABLE', 'docs_emails'),
            'doc_versions' => env('DOC_VERSIONS_TABLE', 'docs_versions'),
            'doc_approvals' => env('DOC_APPROVALS_TABLE', 'docs_approvals'),
            'doc_einvoice_submissions' => env('DOC_EINVOICE_SUBMISSIONS_TABLE', 'docs_einvoice_submissions'),
            'doc_sequences' => env('DOC_SEQUENCES_TABLE', 'docs_sequences'),
            'sequence_numbers' => env('DOC_SEQUENCE_NUMBERS_TABLE', 'docs_sequence_numbers'),
            'workflows' => env('DOC_WORKFLOWS_TABLE', 'docs_workflows'),
            'workflow_steps' => env('DOC_WORKFLOW_STEPS_TABLE', 'docs_workflow_steps'),
        ],
    ],
    'defaults' => [
        'currency' => env('DOCS_CURRENCY', 'MYR'),
        'tax_rate' => env('DOCS_TAX_RATE', 0),
        'due_days' => env('DOCS_DUE_DAYS', 30),
    ],
    'payment_methods' => [
        'bank_transfer' => 'Bank Transfer',
        'cash' => 'Cash',
        'credit_card' => 'Credit Card',
        'check' => 'Check',
        'e_wallet' => 'E-Wallet',
        'other' => 'Other',
    ],
    'owner' => [
        'enabled' => env('DOCS_OWNER_ENABLED', false),
        'include_global' => env('DOCS_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('DOCS_OWNER_AUTO_ASSIGN_ON_CREATE', true),
    ],
    'email' => [
        'queue_enabled' => env('DOCS_EMAIL_QUEUE_ENABLED', true),
        'queue' => env('DOCS_EMAIL_QUEUE', 'default'),
        'attach_pdf' => env('DOCS_EMAIL_ATTACH_PDF', true),
    ],
    'einvoice' => [
        'sandbox' => env('DOCS_EINVOICE_SANDBOX', true),
    ],
    'types' => [
        'invoice' => ['numbering' => ['prefix' => 'INV']],
        'quotation' => ['numbering' => ['prefix' => 'QUO']],
        'receipt' => ['numbering' => ['prefix' => 'RCP']],
        'credit_note' => ['numbering' => ['prefix' => 'CN']],
        'delivery_note' => ['numbering' => ['prefix' => 'DN']],
        'proforma_invoice' => ['numbering' => ['prefix' => 'PI']],
    ],
];
```

## Filament Configuration

For applications that install many `filament-*` packages, prefer the shared Commerce navigation plugin so menu grouping/hiding lives in one application-level config file.

Register it once before the package plugins:

```php
use AIArmada\CommerceSupport\Support\Filament\CommerceNavigationPlugin;

return $panel
    ->plugins([
        CommerceNavigationPlugin::make(),
        // Other Commerce Filament plugins...
    ]);
```

Then configure `config/commerce-support.php`:

```php
'filament' => [
    'navigation' => [
        'groups' => [
            'Catalog' => ['label' => 'Catalog', 'sort' => 10],
            'Sales' => ['label' => 'Sales', 'sort' => 20],
            'Operations' => ['label' => 'Operations', 'sort' => 30, 'collapsed' => true],
        ],
        'packages' => [
            'filament-cart' => ['group' => 'Sales'],
            'filament-vouchers' => ['group' => 'Sales'],
            'filament-docs' => ['group' => 'Operations'],
        ],
        'items' => [
            AIArmada\FilamentCart\Resources\CartResource::class => [
                'visible' => false,
            ],
        ],
    ],
],
```

Hiding a navigation item only removes it from the menu. Policies, owner scopes, and server-side validation remain responsible for access control.

Each Filament package has its own configuration:

### filament-cart

```php
// config/filament-cart.php
return [
    'navigation' => [
        'group' => 'E-Commerce',
        'sort' => 30,
    ],
    'resources' => [
        'navigation_sort' => [
            'carts' => 30,
            'cart_items' => 31,
            'conditions' => 33,
        ],
    ],
    'features' => [
        'dashboard' => true,
        'monitoring' => true,
    ],
];
```

### filament-vouchers

```php
// config/filament-vouchers.php
return [
    'navigation' => [
        'group' => 'Vouchers & Discounts',
    ],
    'resources' => [
        'navigation_sort' => [
            'vouchers' => 10,
            'voucher_usage' => 20,
            'voucher_wallets' => 30,
        ],
    ],
];
```

### filament-docs

```php
// config/filament-docs.php
return [
    'navigation' => [
        'group' => 'Documents',
    ],
    'features' => [
        'auto_generate_pdf' => false,
    ],
    'resources' => [
        'navigation_sort' => [
            'docs' => 10,
            'doc_templates' => 20,
            'sequences' => 90,
            'email_templates' => 91,
        ],
    ],
];
```

## Publishing Configurations

Publish individual configs:

```bash
php artisan vendor:publish --tag=cart-config
php artisan vendor:publish --tag=vouchers-config
php artisan vendor:publish --tag=chip-config
php artisan vendor:publish --tag=docs-config
php artisan vendor:publish --tag=filament-cart-config
php artisan vendor:publish --tag=filament-docs-config
```

## Interactive Setup

Use the setup wizard for guided configuration:

```bash
php artisan commerce:setup
```

This will:
1. Prompt for CHIP credentials
2. Prompt for J&T Express credentials
3. Configure database JSON column type
4. Write to `.env` file

Use `--force` to overwrite existing values:

```bash
php artisan commerce:setup --force
```
