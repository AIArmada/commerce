# Config Guidelines

All configuration options must be actively used or implemented in the codebase.

## Standard Config Order

Config files MUST follow this section order:

### Core Package Configs
1. **Database** - Tables, prefixes, JSON column types
2. **Credentials/API** - Keys, secrets, environment
3. **Defaults** - Currency, tax rates, default values
4. **Features/Behavior** - Core feature toggles
5. **Integrations** - Other package integrations
6. **HTTP** - Timeouts, retries
7. **Webhooks** - Webhook configuration
8. **Cache** - Caching settings
9. **Logging** - Logging configuration

### Filament Package Configs
1. **Navigation** - Group, sort order
2. **Tables** - Polling, formats
3. **Features** - Feature toggles
4. **Resources** - Resource-specific settings

## Rules

- If a config key is defined but not referenced anywhere, remove it.
- Publish only necessary configs via `php artisan vendor:publish`.
- Keep `config/*.php` files minimal and purposeful.
- Packages with JSON columns in migrations MUST have `json_column_type` config.
- Use compact section headers (single line description only).
- Group related settings under nested arrays.
- Prefer opinionated defaults over excessive configuration.
- Remove redundant env() wrappers for non-sensitive hardcoded values.

## Comment Style

Use compact Laravel-style section headers:
```php
/*
|--------------------------------------------------------------------------
| Section Name
|--------------------------------------------------------------------------
*/
```

Inline comments only for non-obvious values:
```php
'ttl' => env('CACHE_TTL', 3600), // Seconds
'max_per_cart' => env('MAX_PER_CART', 1), // 0=disabled, -1=unlimited
```

## Verification

Search codebase for config key usage:
```bash
grep -r "config('package.key')" src/ packages/*/src/
```
If no matches, remove the config.
