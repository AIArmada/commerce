# Configuration Reference

Complete guide to `config/cart.php` options.

## Publishing Configuration

```bash
php artisan vendor:publish --tag=cart-config
php artisan config:clear
```

## Storage

```php
'driver' => env('CART_DRIVER', 'session'),
```

Options: `session`, `cache`, `database`

## Cache Configuration

```php
'cache' => [
    'store' => env('CART_CACHE_STORE', 'redis'),
    'connection' => env('CART_CACHE_CONNECTION', 'default'),
    'ttl' => env('CART_CACHE_TTL', 43200), // 12 hours
    'prefix' => env('CART_CACHE_PREFIX', 'cart'),
],
```

## Database Configuration

```php
'database' => [
    'table' => env('CART_DATABASE_TABLE', 'carts'),
    'connection' => env('CART_DATABASE_CONNECTION', null),
    'locking' => env('CART_DATABASE_LOCKING', 'optimistic'),
],
```

Locking options: `optimistic`, `pessimistic`

## Session Configuration

```php
'session' => [
    'key' => env('CART_SESSION_KEY', 'cart'),
],
```

## Migration Configuration

```php
'migration' => [
    'enabled' => env('CART_MIGRATION_ENABLED', true),
    'strategy' => env('CART_MIGRATION_STRATEGY', 'add_quantities'),
    'clear_guest_after' => env('CART_MIGRATION_CLEAR_GUEST', true),
    'timeout' => env('CART_MIGRATION_TIMEOUT', 60),
],
```

### Merge Strategies

| Strategy | Behavior |
|----------|----------|
| `add_quantities` | Sum quantities when same item exists |
| `keep_highest_quantity` | Keep the higher quantity |
| `keep_user_cart` | Discard guest cart |
| `replace_with_guest` | Replace user cart with guest |

## Events Configuration

```php
'events' => [
    'enabled' => env('CART_EVENTS_ENABLED', true),
    'dispatch' => [
        'item_added' => true,
        'item_updated' => true,
        'item_removed' => true,
        'cart_cleared' => true,
        'condition_applied' => true,
        'cart_migrated' => true,
    ],
],
```

## Limits Configuration

```php
'limits' => [
    'max_items' => env('CART_MAX_ITEMS', 100),
    'max_quantity' => env('CART_MAX_QUANTITY', 999),
    'max_value' => env('CART_MAX_VALUE', null),
    'enforce' => env('CART_ENFORCE_LIMITS', true),
],
```

## Money Configuration

```php
'money' => [
    'currency' => env('CART_CURRENCY', 'USD'),
    'locale' => env('CART_LOCALE', 'en_US'),
],
```

## Conditions Configuration

```php
'conditions' => [
    'order' => env('CART_CONDITIONS_ORDER', 'item,subtotal,total'),
    'allow_negative' => env('CART_CONDITIONS_ALLOW_NEGATIVE', false),
],
```

## Logging Configuration

```php
'logging' => [
    'enabled' => env('CART_LOGGING_ENABLED', false),
    'channel' => env('CART_LOGGING_CHANNEL', 'stack'),
    'level' => env('CART_LOGGING_LEVEL', 'info'),
],
```

## Environment Examples

### Development

```env
CART_DRIVER=session
CART_EVENTS_ENABLED=true
CART_LOGGING_ENABLED=true
CART_LOGGING_LEVEL=debug
```

### Production

```env
CART_DRIVER=database
CART_DATABASE_LOCKING=optimistic
CART_EVENTS_ENABLED=true
CART_LOGGING_ENABLED=false
CART_MAX_ITEMS=100
CART_CONDITIONS_ALLOW_NEGATIVE=false
```

## Configuration Troubleshooting

### Changes Not Reflected

```bash
php artisan config:clear
php artisan config:cache
```

### Missing Environment Variables

```bash
grep CART_DRIVER .env
```

### Type Mismatch

```env
# Correct
CART_EVENTS_ENABLED=true

# Wrong (string)
CART_EVENTS_ENABLED="true"
```

## Next Steps

- [Storage Drivers](storage.md) – Driver details
- [Events](events.md) – Event configuration
- [Troubleshooting](troubleshooting.md) – Common issues
