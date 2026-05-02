---
title: Configuration
---

# Configuration

The `config/chip.php` file contains all package settings organized by concern.

## Database

```php
'database' => [
    'table_prefix' => env('CHIP_TABLE_PREFIX', 'chip_'),
    'json_column_type' => env('CHIP_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
],
```

| Key | Description | Default |
|-----|-------------|---------|
| `table_prefix` | Prefix for all CHIP tables | `chip_` |
| `json_column_type` | JSON column type (`json` or `jsonb`) | `json` |

## Credentials / API

```php
'environment' => env('CHIP_ENVIRONMENT', 'sandbox'),

'collect' => [
    'base_url' => env('CHIP_COLLECT_BASE_URL', 'https://gate.chip-in.asia/api/v1/'),
    'api_key' => env('CHIP_COLLECT_API_KEY'),
    'brand_id' => env('CHIP_COLLECT_BRAND_ID'),
],

'send' => [
    'base_url' => [
        'sandbox' => env('CHIP_SEND_SANDBOX_URL', 'https://staging-api.chip-in.asia/api'),
        'production' => env('CHIP_SEND_PRODUCTION_URL', 'https://api.chip-in.asia/api'),
    ],
    'api_key' => env('CHIP_SEND_API_KEY'),
    'api_secret' => env('CHIP_SEND_API_SECRET'),
],
```

| Key | Description |
|-----|-------------|
| `environment` | `sandbox` for testing, `production` for live |
| `collect.api_key` | Secret key from CHIP Dashboard |
| `collect.brand_id` | Your brand UUID from CHIP |
| `send.api_key` | Send API key (different from Collect) |
| `send.api_secret` | HMAC secret for Send API authentication |

## Defaults

```php
'defaults' => [
    'currency' => env('CHIP_DEFAULT_CURRENCY', 'MYR'),
    'creator_agent' => env('CHIP_CREATOR_AGENT', 'AIArmada/Chip'),
    'platform' => env('CHIP_PLATFORM', 'api'),
    'payment_method_whitelist' => env('CHIP_PAYMENT_METHOD_WHITELIST', ''),
    'success_redirect' => env('CHIP_SUCCESS_REDIRECT'),
    'failure_redirect' => env('CHIP_FAILURE_REDIRECT'),
    'send_receipt' => env('CHIP_SEND_RECEIPT', false),
],
```

### Payment Method Whitelist

Restrict available payment methods (comma-separated):
```env
CHIP_PAYMENT_METHOD_WHITELIST=fpx,visa,mastercard
```

Available methods: `fpx`, `visa`, `mastercard`, `maestro`, `duitnow`, `grabpay`, `tng`, `shopeepay`

## Multi-Tenancy (Owner Scoping)

```php
'owner' => [
    'enabled' => env('CHIP_OWNER_ENABLED', false),
    'include_global' => env('CHIP_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('CHIP_OWNER_AUTO_ASSIGN', true),
    'webhook_brand_id_map' => [],
],
```

| Key | Description |
|-----|-------------|
| `enabled` | Enable owner-scoped queries |
| `include_global` | Include global records where `owner_type` and `owner_id` are `null` |
| `auto_assign_on_create` | Automatically set owner on new records |
| `webhook_brand_id_map` | Map brand IDs to owner models for incoming webhooks |

### Webhook Brand ID Mapping

When owner scoping is enabled, incoming CHIP webhooks must be mapped to the correct tenant (owner). The `webhook_brand_id_map` configuration associates CHIP brand IDs with your tenant models.

**Why it's needed:**
- CHIP webhooks arrive without tenant/request context
- The brand ID in the webhook payload is the only way to identify which tenant owns the payment
- Webhooks are fail-closed: if a brand ID cannot be mapped to an owner, the webhook is rejected

**Configuration:**
```php
'webhook_brand_id_map' => [
    'brand-uuid-1' => [
        'owner_type' => \App\Models\Tenant::class,
        'owner_id' => 'tenant-uuid-1',
    ],
    'brand-uuid-2' => [
        'owner_type' => \App\Models\Organization::class,
        'owner_id' => 'org-uuid-2',
    ],
],
```

**Fields (preferred):**
- `owner_type`: Full class name or morph alias of the owner model (e.g., `\App\Models\Tenant::class` or `'tenant'` if using `morphMap`)
- `owner_id`: ID of the owner record in your database (string or integer)

**Legacy aliases (also accepted):**
- `type` (alias for `owner_type`)
- `id` (alias for `owner_id`)

For forward compatibility, prefer `owner_type`/`owner_id`.

**Environment-based mapping:**
```php
'webhook_brand_id_map' => array_filter([
    env('CHIP_BRAND_ID_STAGING') => [
        'owner_type' => \App\Models\Tenant::class,
        'owner_id' => env('TENANT_ID_STAGING'),
    ],
    env('CHIP_BRAND_ID_PRODUCTION') => [
        'owner_type' => \App\Models\Tenant::class,
        'owner_id' => env('TENANT_ID_PRODUCTION'),
    ],
]),
```

**Validation:**
The configuration is validated at boot time when owner scoping is enabled. Invalid mappings will raise `InvalidArgumentException`:
- Missing `owner_type` or `owner_id`
- Non-string `owner_type`
- Non-string/non-integer `owner_id`

**Troubleshooting:**
If webhooks are failing with "Owner resolution failed", check:
1. Does your CHIP brand ID appear in the map?
2. Does the mapped `owner_id` exist in your database?
3. Is `owner_type` the correct morph alias or class name?

See [Webhooks](webhooks.md) for detailed webhook handling.

## Integrations

```php
'integrations' => [
    'docs' => [
        'enabled' => env('CHIP_DOCS_INTEGRATION_ENABLED', true),
        'auto_generate_invoice' => env('CHIP_DOCS_AUTO_INVOICE', true),
        'auto_generate_credit_note' => env('CHIP_DOCS_AUTO_CREDIT_NOTE', true),
        'paid_doc_type' => env('CHIP_DOCS_PAID_TYPE', 'invoice'),
        'refund_doc_type' => env('CHIP_DOCS_REFUND_TYPE', 'credit_note'),
        'generate_pdf' => env('CHIP_DOCS_GENERATE_PDF', true),
    ],
],
```

When the `aiarmada/docs` package is installed, CHIP can automatically generate:
- Invoices when payments are completed
- Credit notes when refunds are processed

## HTTP Settings

```php
'http' => [
    'timeout' => env('CHIP_HTTP_TIMEOUT', 30),
    'retry' => [
        'attempts' => env('CHIP_HTTP_RETRY_ATTEMPTS', 3),
        'delay' => env('CHIP_HTTP_RETRY_DELAY', 1000),
    ],
    'rate_limit' => [
        'enabled' => env('CHIP_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('CHIP_RATE_LIMIT_MAX', 60),
        'decay_seconds' => env('CHIP_RATE_LIMIT_DECAY', 60),
    ],
],
```

## Webhooks

```php
'webhooks' => [
    'enabled' => env('CHIP_WEBHOOKS_ENABLED', true),
    'route' => env('CHIP_WEBHOOK_ROUTE', '/chip/webhook'),
    'middleware' => ['api'],
    'company_public_key' => env('CHIP_COMPANY_PUBLIC_KEY'),
    'webhook_keys' => $webhookKeys, // Parsed from CHIP_WEBHOOK_PUBLIC_KEYS JSON
    'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
    'log_payloads' => env('CHIP_WEBHOOK_LOG_PAYLOADS', false),
    'store_webhooks' => env('CHIP_WEBHOOK_STORE', true),
    'deduplication' => env('CHIP_WEBHOOK_DEDUPLICATION', true),
],
```

### Multiple Webhook Keys

For multiple brands with different keys, set as JSON:
```env
CHIP_WEBHOOK_PUBLIC_KEYS='{"webhook-id-1":"-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----","webhook-id-2":"..."}'
```

## Cache

```php
'cache' => [
    'prefix' => env('CHIP_CACHE_PREFIX', 'chip:'),
    'default_ttl' => env('CHIP_CACHE_TTL', 3600),
    'ttl' => [
        'public_key' => env('CHIP_CACHE_PUBLIC_KEY_TTL', 86400),
        'payment_methods' => env('CHIP_CACHE_PAYMENT_METHODS_TTL', 3600),
    ],
],
```

## Logging

```php
'logging' => [
    'enabled' => env('CHIP_LOGGING_ENABLED', env('APP_DEBUG', false)),
    'channel' => env('CHIP_LOGGING_CHANNEL', 'stack'),
    'mask_sensitive_data' => env('CHIP_LOGGING_MASK_SENSITIVE', true),
    'log_requests' => env('CHIP_LOG_REQUESTS', true),
    'log_responses' => env('CHIP_LOG_RESPONSES', true),
    'sensitive_fields' => [],
],
```

Sensitive data (emails, phone numbers, card numbers) is automatically masked in logs when `mask_sensitive_data` is enabled.

## Environment-Specific Configuration

### Development
```env
CHIP_ENVIRONMENT=sandbox
CHIP_LOGGING_ENABLED=true
CHIP_WEBHOOK_VERIFY_SIGNATURE=false
```

### Production
```env
CHIP_ENVIRONMENT=production
CHIP_LOGGING_ENABLED=false
CHIP_WEBHOOK_VERIFY_SIGNATURE=true
CHIP_WEBHOOK_LOG_PAYLOADS=false
```
