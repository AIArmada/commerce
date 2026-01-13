---
title: Configuration
---

# Configuration

The configuration file is published to `config/chip.php`.

## Database

```php
'database' => [
    'table_prefix' => env('CHIP_TABLE_PREFIX', 'chip_'),
    'json_column_type' => env('CHIP_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
],
```

| Option | Description |
|--------|-------------|
| `table_prefix` | Prefix for all CHIP tables (default: `chip_`) |
| `json_column_type` | JSON column type: `json` or `jsonb` for PostgreSQL |

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

## Multi-Tenancy (Owner Scoping)

```php
'owner' => [
    'enabled' => env('CHIP_OWNER_ENABLED', false),
    'include_global' => env('CHIP_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('CHIP_OWNER_AUTO_ASSIGN', true),
    'webhook_brand_id_map' => [],
],
```

| Option | Description |
|--------|-------------|
| `enabled` | Enable owner scoping for all models |
| `include_global` | Include global (null owner) rows in queries |
| `auto_assign_on_create` | Auto-assign current owner on model creation |
| `webhook_brand_id_map` | Map brand IDs to owner models for webhook routing |

### Webhook Brand ID Mapping

For multi-tenant setups, map CHIP brand IDs to your tenant models:

```php
'webhook_brand_id_map' => [
    'brand-uuid-1' => ['type' => App\Models\Tenant::class, 'id' => 1],
    'brand-uuid-2' => ['type' => App\Models\Tenant::class, 'id' => 2],
],
```

## HTTP

```php
'http' => [
    'timeout' => env('CHIP_HTTP_TIMEOUT', 30),
    'retry' => [
        'attempts' => env('CHIP_HTTP_RETRY_ATTEMPTS', 3),
        'delay' => env('CHIP_HTTP_RETRY_DELAY', 1000),
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
    'webhook_keys' => $webhookKeys,
    'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
    'log_payloads' => env('CHIP_WEBHOOK_LOG_PAYLOADS', false),
    'store_data' => env('CHIP_WEBHOOK_STORE_DATA', true),
],
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
],
```
