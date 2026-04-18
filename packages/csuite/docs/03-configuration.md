# Configuration

Environment and configuration options for AIArmada Commerce.

## Environment Variables

### CHIP Payment Gateway

```env
CHIP_BRAND_ID=your-brand-id
CHIP_SECRET_KEY=your-secret-key
CHIP_MODE=sandbox
CHIP_WEBHOOK_URL=https://your-domain.com/webhooks/chip
```

| Variable | Description | Default |
|----------|-------------|---------|
| `CHIP_BRAND_ID` | Your CHIP brand ID | - |
| `CHIP_SECRET_KEY` | Your CHIP secret key | - |
| `CHIP_MODE` | `sandbox` or `production` | `sandbox` |
| `CHIP_WEBHOOK_URL` | Webhook callback URL | - |

### J&T Express

```env
JNT_API_KEY=your-api-key
JNT_API_URL=https://api.jtexpress.com.my
JNT_CUSTOMER_CODE=your-customer-code
```

| Variable | Description | Default |
|----------|-------------|---------|
| `JNT_API_KEY` | Your J&T API key | - |
| `JNT_API_URL` | API endpoint URL | Production URL |
| `JNT_CUSTOMER_CODE` | Your customer code | - |

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
        'tables' => [
            'alert_rules' => null,
            'alert_logs' => null,
            'daily_metrics' => null,
            'recovery_campaigns' => null,
            'recovery_templates' => null,
            'recovery_attempts' => null,
        ],
        'table_prefix' => 'cart_',
        'conditions_table' => 'conditions',
        'table' => 'carts',
    ],
    'money' => [
        'default_currency' => 'MYR',
    ],
];
```

### Vouchers

```php
// config/vouchers.php
return [
    'database' => [
        'tables' => [
            'vouchers' => 'vouchers',
            'voucher_usages' => 'voucher_usages',
        ],
    ],
    'code' => [
        'length' => 8,
        'prefix' => '',
    ],
];
```

### CHIP

```php
// config/chip.php
return [
    'brand_id' => env('CHIP_BRAND_ID'),
    'secret_key' => env('CHIP_SECRET_KEY'),
    'mode' => env('CHIP_MODE', 'sandbox'),
    'webhook_url' => env('CHIP_WEBHOOK_URL'),
];
```

### Docs

```php
// config/docs.php
return [
    'database' => [
        'tables' => [
            'docs' => 'docs',
            'doc_templates' => 'doc_templates',
            'doc_status_histories' => 'doc_status_histories',
        ],
    ],
    'company' => [
        'name' => env('COMPANY_NAME'),
        'address' => env('COMPANY_ADDRESS'),
        'phone' => env('COMPANY_PHONE'),
        'email' => env('COMPANY_EMAIL'),
    ],
    'numbering' => [
        'strategy' => 'sequential',
        'prefix' => 'INV-',
    ],
    'storage' => [
        'disk' => 'local',
        'path' => 'docs',
    ],
];
```

## Filament Configuration

Each Filament package has its own configuration:

### filament-cart

```php
// config/filament-cart.php
return [
    'navigation_group' => 'Commerce',
    'resources' => [
        'navigation_sort' => [
            'carts' => 10,
            'conditions' => 20,
        ],
    ],
];
```

### filament-vouchers

```php
// config/filament-vouchers.php
return [
    'navigation_group' => 'Commerce',
    'resources' => [
        'navigation_sort' => [
            'vouchers' => 30,
        ],
    ],
];
```

### filament-docs

```php
// config/filament-docs.php
return [
    'navigation_group' => 'Documents',
    'resources' => [
        'navigation_sort' => [
            'docs' => 10,
            'doc_templates' => 20,
        ],
    ],
    'auto_generate_pdf' => true,
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
