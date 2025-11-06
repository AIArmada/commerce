# ⚙️ Configuration Reference

Complete guide to all configuration options for the Cart package.

---

## Table of Contents

- [Overview](#overview)
- [Publishing Configuration](#publishing-configuration)
- [Storage Configuration](#storage-configuration)
- [Cache Configuration](#cache-configuration)
- [Database Configuration](#database-configuration)
- [Session Configuration](#session-configuration)
- [Identifiers Configuration](#identifiers-configuration)
- [Migration Configuration](#migration-configuration)
- [Events Configuration](#events-configuration)
- [Limits Configuration](#limits-configuration)
- [Money Configuration](#money-configuration)
- [Conditions Configuration](#conditions-configuration)
- [Logging Configuration](#logging-configuration)
- [Environment-Specific Configurations](#environment-specific-configurations)
- [Security Considerations](#security-considerations)
- [Performance Impact](#performance-impact)
- [Configuration Testing](#configuration-testing)
- [Troubleshooting](#troubleshooting)

---

## Overview

The cart package is configured via `config/cart.php`. All options support environment variables for easy deployment.

### Quick Start

```bash
# Publish config file
php artisan vendor:publish --tag=cart-config

# Clear config cache after changes
php artisan config:clear
```

---

## Publishing Configuration

### Publish All Cart Files

```bash
# Publish config, migrations, and translations
php artisan vendor:publish --provider="AIArmada\Cart\CartServiceProvider"
```

### Publish Individual Components

```bash
# Config only
php artisan vendor:publish --tag=cart-config

# Migrations only
php artisan vendor:publish --tag=cart-migrations

# Translations only
php artisan vendor:publish --tag=cart-translations
```

---

## Storage Configuration

### Driver Selection

```php
'driver' => env('CART_DRIVER', 'session'),
```

**Available Drivers:**

| Driver | Description | Use Case | Persistence |
|--------|-------------|----------|-------------|
| `session` | PHP sessions | Single-device carts | Until session expires |
| `cache` | Cache store (Redis, Memcached) | Multi-device carts | TTL-based |
| `database` | Database table | Permanent carts | Until manually deleted |

**Environment Variable:**

```env
CART_DRIVER=cache
```

**When to Use Each Driver:**

```php
// Development (simple, no dependencies)
CART_DRIVER=session

// Staging (multi-device testing)
CART_DRIVER=cache

// Production (persistent, reliable)
CART_DRIVER=database
```

---

## Cache Configuration

Used when `driver` is set to `cache`.

```php
'cache' => [
    'store' => env('CART_CACHE_STORE', 'redis'),
    'connection' => env('CART_CACHE_CONNECTION', 'default'),
    'ttl' => env('CART_CACHE_TTL', 43200), // 12 hours
    'prefix' => env('CART_CACHE_PREFIX', 'cart'),
],
```

### Options Explained

#### `store`

Which cache store to use.

```env
CART_CACHE_STORE=redis  # Recommended for production
# CART_CACHE_STORE=memcached
# CART_CACHE_STORE=file
# CART_CACHE_STORE=dynamodb
```

**Recommendations:**

- **Redis**: Best performance, persistent, supports TTL
- **Memcached**: Good performance, volatile
- **File**: Development only, slow
- **DynamoDB**: AWS environments, scalable

#### `connection`

Redis/Memcached connection name from `config/database.php`.

```env
CART_CACHE_CONNECTION=default
# CART_CACHE_CONNECTION=cache
```

#### `ttl`

Time-to-live in seconds.

```env
CART_CACHE_TTL=43200  # 12 hours
# CART_CACHE_TTL=86400  # 24 hours
# CART_CACHE_TTL=604800 # 7 days
```

**Guidelines:**

| Cart Type | Recommended TTL | Reasoning |
|-----------|-----------------|-----------|
| Guest carts | 12 hours | Short browsing sessions |
| User carts | 7 days | Return users |
| Wishlist | 30 days | Long-term storage |
| Compare | 24 hours | Temporary comparison |

#### `prefix`

Cache key prefix to avoid collisions.

```env
CART_CACHE_PREFIX=cart
# CART_CACHE_PREFIX=myapp_cart
```

**Example Keys:**

```
cart_session_abc123
cart_user_456
myapp_cart_wishlist_user_789
```

---

## Database Configuration

Used when `driver` is set to `database`.

```php
'database' => [
    'table' => env('CART_DATABASE_TABLE', 'carts'),
    'connection' => env('CART_DATABASE_CONNECTION', null),
    'locking' => env('CART_DATABASE_LOCKING', 'optimistic'),
],
```

### Options Explained

#### `table`

Database table name.

```env
CART_DATABASE_TABLE=carts
# CART_DATABASE_TABLE=shopping_carts
```

**Table Structure:**

```sql
CREATE TABLE carts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL UNIQUE,
    instance VARCHAR(255) NOT NULL DEFAULT 'default',
    content JSON NOT NULL,
    version INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_identifier (identifier),
    INDEX idx_instance (instance)
);
```

#### `connection`

Database connection name from `config/database.php`. Uses default if null.

```env
CART_DATABASE_CONNECTION=mysql
# CART_DATABASE_CONNECTION=pgsql
# CART_DATABASE_CONNECTION=sqlsrv
```

#### `locking`

Concurrency control strategy.

```env
CART_DATABASE_LOCKING=optimistic  # Recommended (default)
# CART_DATABASE_LOCKING=pessimistic  # High contention scenarios
```

**Comparison:**

| Strategy | When to Use | Pros | Cons |
|----------|-------------|------|------|
| **Optimistic** | Normal traffic | Better performance, scales well | May retry on conflicts |
| **Pessimistic** | High contention | Prevents conflicts | Slower, potential deadlocks |

**Examples:**

```php
// Optimistic locking (default)
try {
    Cart::add(1, 'Product', 1000);
} catch (CartConflictException $e) {
    // Retry on conflict
    retry(3, fn() => Cart::add(1, 'Product', 1000), 100);
}

// Pessimistic locking (config: locking => pessimistic)
Cart::add(1, 'Product', 1000); // Blocks other requests until complete
```

---

## Session Configuration

Used when `driver` is set to `session`.

```php
'session' => [
    'key' => env('CART_SESSION_KEY', 'cart'),
],
```

### Options Explained

#### `key`

Session key to store cart data.

```env
CART_SESSION_KEY=cart
# CART_SESSION_KEY=shopping_cart
```

**Session Structure:**

```php
session()->get('cart'); // Returns cart array
/*
[
    'items' => [...],
    'conditions' => [...],
    'metadata' => [...],
]
*/
```

**Security Note:** Ensure session driver is secure:

```env
SESSION_DRIVER=redis  # Recommended
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

---

## Identifiers Configuration

```php
'identifiers' => [
    'prefix' => env('CART_IDENTIFIER_PREFIX', 'cart'),
    'separator' => env('CART_IDENTIFIER_SEPARATOR', '_'),
    'format' => env('CART_IDENTIFIER_FORMAT', '{prefix}{separator}{type}{separator}{id}'),
],
```

### Options Explained

#### `prefix`

Identifier prefix for all carts.

```env
CART_IDENTIFIER_PREFIX=cart
# CART_IDENTIFIER_PREFIX=myapp
```

#### `separator`

Separator between identifier parts.

```env
CART_IDENTIFIER_SEPARATOR=_
# CART_IDENTIFIER_SEPARATOR=-
# CART_IDENTIFIER_SEPARATOR=:
```

#### `format`

Identifier format template.

**Default Format:**
```
{prefix}{separator}{type}{separator}{id}
```

**Examples:**

```
cart_session_abc123
cart_user_456
myapp-guest-xyz789
```

**Custom Format:**

```env
CART_IDENTIFIER_FORMAT={type}:{id}@{prefix}
# Result: session:abc123@cart
```

---

## Migration Configuration

```php
'migration' => [
    'enabled' => env('CART_MIGRATION_ENABLED', true),
    'strategy' => env('CART_MIGRATION_STRATEGY', 'add_quantities'),
    'clear_guest_after' => env('CART_MIGRATION_CLEAR_GUEST', true),
    'timeout' => env('CART_MIGRATION_TIMEOUT', 60),
],
```

### Options Explained

#### `enabled`

Enable automatic migration on login.

```env
CART_MIGRATION_ENABLED=true
# CART_MIGRATION_ENABLED=false  # Disable for manual migration
```

#### `strategy`

Guest cart merge strategy.

```env
CART_MIGRATION_STRATEGY=add_quantities  # Default (recommended)
# CART_MIGRATION_STRATEGY=keep_highest_quantity
# CART_MIGRATION_STRATEGY=keep_user_cart
# CART_MIGRATION_STRATEGY=replace_with_guest
```

**Strategy Comparison:**

| Strategy | When to Use | Example |
|----------|-------------|---------|
| `add_quantities` | E-commerce (default) | Guest: 2, User: 3 → Result: 5 |
| `keep_highest_quantity` | Inventory limits | Guest: 2, User: 3 → Result: 3 |
| `keep_user_cart` | B2B (saved carts) | Guest: 2, User: 3 → Result: 3 (user wins) |
| `replace_with_guest` | Temporary guest sessions | Guest: 2, User: 3 → Result: 2 (guest wins) |

#### `clear_guest_after`

Delete guest cart after migration.

```env
CART_MIGRATION_CLEAR_GUEST=true  # Recommended (cleanup)
# CART_MIGRATION_CLEAR_GUEST=false  # Keep guest cart
```

#### `timeout`

Migration timeout in seconds.

```env
CART_MIGRATION_TIMEOUT=60  # 1 minute
# CART_MIGRATION_TIMEOUT=120  # 2 minutes (large carts)
```

---

## Events Configuration

```php
'events' => [
    'enabled' => env('CART_EVENTS_ENABLED', true),
    'dispatch' => [
        'item_added' => env('CART_EVENT_ITEM_ADDED', true),
        'item_updated' => env('CART_EVENT_ITEM_UPDATED', true),
        'item_removed' => env('CART_EVENT_ITEM_REMOVED', true),
        'cart_cleared' => env('CART_EVENT_CART_CLEARED', true),
        'condition_applied' => env('CART_EVENT_CONDITION_APPLIED', true),
        'cart_migrated' => env('CART_EVENT_CART_MIGRATED', true),
    ],
],
```

### Options Explained

#### `enabled`

Master switch for all events.

```env
CART_EVENTS_ENABLED=true
# CART_EVENTS_ENABLED=false  # Disable all events globally
```

#### `dispatch.*`

Enable/disable specific events.

```env
# Enable all (default)
CART_EVENT_ITEM_ADDED=true
CART_EVENT_ITEM_UPDATED=true
CART_EVENT_ITEM_REMOVED=true
CART_EVENT_CART_CLEARED=true
CART_EVENT_CONDITION_APPLIED=true
CART_EVENT_CART_MIGRATED=true

# Disable specific events
CART_EVENT_ITEM_UPDATED=false  # Too noisy
CART_EVENT_CONDITION_APPLIED=false  # Not needed
```

**Performance Note:** Disabling events improves performance:

```
With events: ~8ms per operation
Without events: ~5ms per operation (37% faster)
```

---

## Limits Configuration

```php
'limits' => [
    'max_items' => env('CART_MAX_ITEMS', 100),
    'max_quantity' => env('CART_MAX_QUANTITY', 999),
    'max_value' => env('CART_MAX_VALUE', null),
    'enforce' => env('CART_ENFORCE_LIMITS', true),
],
```

### Options Explained

#### `max_items`

Maximum number of unique items in cart.

```env
CART_MAX_ITEMS=100  # Default
# CART_MAX_ITEMS=50  # Small carts
# CART_MAX_ITEMS=500  # Wholesale
```

#### `max_quantity`

Maximum quantity per item.

```env
CART_MAX_QUANTITY=999  # Default
# CART_MAX_QUANTITY=10  # Limit bulk purchases
# CART_MAX_QUANTITY=1  # One per customer
```

#### `max_value`

Maximum cart total (in cents). Null = no limit.

```env
CART_MAX_VALUE=10000000  # $100,000 limit
# CART_MAX_VALUE=500000  # $5,000 limit
# CART_MAX_VALUE=null  # No limit (default)
```

#### `enforce`

Enforce limits by throwing exceptions.

```env
CART_ENFORCE_LIMITS=true  # Throw exceptions (recommended)
# CART_ENFORCE_LIMITS=false  # Log warnings only
```

**Example Enforcement:**

```php
try {
    Cart::add(1, 'Product', 1000, 1000); // Exceeds max_quantity
} catch (InvalidCartItemException $e) {
    return response()->json([
        'error' => 'Quantity exceeds maximum allowed (999)',
    ], 422);
}
```

---

## Money Configuration

```php
'money' => [
    'currency' => env('CART_CURRENCY', 'USD'),
    'locale' => env('CART_LOCALE', 'en_US'),
    'formatter' => [
        'decimals' => env('CART_DECIMALS', 2),
        'thousands_separator' => env('CART_THOUSANDS_SEP', ','),
        'decimal_separator' => env('CART_DECIMAL_SEP', '.'),
        'symbol' => env('CART_SYMBOL', '$'),
        'symbol_first' => env('CART_SYMBOL_FIRST', true),
    ],
],
```

### Options Explained

#### `currency`

Default currency code (ISO 4217).

```env
CART_CURRENCY=USD  # Default
# CART_CURRENCY=EUR
# CART_CURRENCY=GBP
# CART_CURRENCY=JPY
# CART_CURRENCY=MYR
```

**Supported Currencies:** 150+ currencies via akaunting/laravel-money.

#### `locale`

Locale for number formatting.

```env
CART_LOCALE=en_US  # Default
# CART_LOCALE=en_GB  # British English
# CART_LOCALE=fr_FR  # French
# CART_LOCALE=de_DE  # German
# CART_LOCALE=ja_JP  # Japanese
```

#### `formatter.*`

Manual formatting options (overrides locale).

```env
# US format: $1,234.56
CART_DECIMALS=2
CART_THOUSANDS_SEP=,
CART_DECIMAL_SEP=.
CART_SYMBOL=$
CART_SYMBOL_FIRST=true

# EU format: 1.234,56 €
CART_DECIMALS=2
CART_THOUSANDS_SEP=.
CART_DECIMAL_SEP=,
CART_SYMBOL=€
CART_SYMBOL_FIRST=false
```

**Examples:**

```php
// USD: $1,234.56
Money::USD(123456)->format();

// EUR: 1.234,56 €
Money::EUR(123456)->format();

// JPY: ¥1,235 (no decimals)
Money::JPY(1235)->format();
```

---

## Conditions Configuration

```php
'conditions' => [
    'order' => env('CART_CONDITIONS_ORDER', 'item,subtotal,total'),
    'allow_negative' => env('CART_CONDITIONS_ALLOW_NEGATIVE', false),
],
```

### Options Explained

#### `order`

Calculation order for conditions.

```env
CART_CONDITIONS_ORDER=item,subtotal,total  # Default
# CART_CONDITIONS_ORDER=total,subtotal,item  # Reverse order
```

**Order Matters:**

```
1. Item-level conditions (e.g., item discount)
   ↓
2. Subtotal conditions (e.g., coupon code)
   ↓
3. Total conditions (e.g., tax, shipping)
```

**Example Impact:**

```
Scenario: 10% item discount, then 20% tax

Order 1 (item,subtotal,total):
$100 → -$10 (discount) → $90 → +$18 (tax on $90) = $108

Order 2 (total,subtotal,item):
$100 → +$20 (tax on $100) → $120 → -$12 (discount on $120) = $108
```

#### `allow_negative`

Allow negative cart totals.

```env
CART_CONDITIONS_ALLOW_NEGATIVE=false  # Recommended (prevents abuse)
# CART_CONDITIONS_ALLOW_NEGATIVE=true  # Allow refunds/credits
```

**Example:**

```php
// allow_negative = false
Cart::add(1, 'Product', 1000); // $10
Cart::addCondition(['name' => 'Discount', 'value' => '-15.00']); // -$15
Cart::total(); // $0.00 (floored to zero)

// allow_negative = true
Cart::total(); // -$5.00 (negative allowed)
```

---

## Logging Configuration

```php
'logging' => [
    'enabled' => env('CART_LOGGING_ENABLED', false),
    'channel' => env('CART_LOGGING_CHANNEL', 'stack'),
    'level' => env('CART_LOGGING_LEVEL', 'info'),
    'operations' => [
        'add' => env('CART_LOG_ADD', true),
        'update' => env('CART_LOG_UPDATE', true),
        'remove' => env('CART_LOG_REMOVE', true),
        'clear' => env('CART_LOG_CLEAR', true),
        'migrate' => env('CART_LOG_MIGRATE', true),
    ],
],
```

### Options Explained

#### `enabled`

Enable cart operation logging.

```env
CART_LOGGING_ENABLED=false  # Default (disabled for performance)
# CART_LOGGING_ENABLED=true  # Enable for debugging
```

#### `channel`

Log channel from `config/logging.php`.

```env
CART_LOGGING_CHANNEL=stack
# CART_LOGGING_CHANNEL=daily
# CART_LOGGING_CHANNEL=slack
```

#### `level`

Minimum log level.

```env
CART_LOGGING_LEVEL=info  # Default
# CART_LOGGING_LEVEL=debug  # Verbose
# CART_LOGGING_LEVEL=warning  # Errors only
```

#### `operations.*`

Log specific operations.

```env
# Enable all (when logging enabled)
CART_LOG_ADD=true
CART_LOG_UPDATE=true
CART_LOG_REMOVE=true
CART_LOG_CLEAR=true
CART_LOG_MIGRATE=true

# Disable noisy operations
CART_LOG_UPDATE=false
CART_LOG_ADD=false
```

**Log Example:**

```
[2024-10-08 10:30:45] local.INFO: Cart item added {"item_id":1,"name":"Product","price":1000,"quantity":2,"user_id":123}
[2024-10-08 10:30:50] local.INFO: Cart migrated {"from":"cart_session_abc","to":"cart_user_123","strategy":"add_quantities","items_merged":3}
```

---

## Environment-Specific Configurations

### Development (`.env`)

```env
APP_ENV=local
CART_DRIVER=session
CART_EVENTS_ENABLED=true
CART_LOGGING_ENABLED=true
CART_LOGGING_LEVEL=debug
CART_MIGRATION_ENABLED=true
CART_ENFORCE_LIMITS=true
```

**Rationale:**
- Session driver: Simple, no dependencies
- Events enabled: Test event listeners
- Logging enabled: Debug issues
- Enforce limits: Catch validation errors

### Staging (`.env.staging`)

```env
APP_ENV=staging
CART_DRIVER=cache
CART_CACHE_STORE=redis
CART_CACHE_TTL=86400
CART_EVENTS_ENABLED=true
CART_LOGGING_ENABLED=false
CART_MIGRATION_ENABLED=true
CART_MIGRATION_STRATEGY=add_quantities
CART_ENFORCE_LIMITS=true
```

**Rationale:**
- Cache driver: Test multi-device carts
- Redis: Production-like environment
- Logging disabled: Performance testing
- Events enabled: Integration testing

### Production (`.env.production`)

```env
APP_ENV=production
CART_DRIVER=database
CART_DATABASE_CONNECTION=mysql
CART_DATABASE_LOCKING=optimistic
CART_CACHE_STORE=redis
CART_CACHE_TTL=604800
CART_EVENTS_ENABLED=true
CART_LOGGING_ENABLED=false
CART_MIGRATION_ENABLED=true
CART_MIGRATION_STRATEGY=add_quantities
CART_MIGRATION_CLEAR_GUEST=true
CART_ENFORCE_LIMITS=true
CART_MAX_ITEMS=100
CART_MAX_QUANTITY=999
CART_CONDITIONS_ALLOW_NEGATIVE=false

# Performance
REDIS_CLIENT=phpredis
```

**Rationale:**
- Database driver: Persistent, reliable
- Optimistic locking: Better performance
- Logging disabled: Reduce overhead
- Events enabled: Analytics, notifications
- Strict limits: Prevent abuse

---

---

## Troubleshooting

### Config Cache Issues

**Problem:** Changes not reflected after editing `config/cart.php`.

**Solution:**

```bash
# Clear config cache
php artisan config:clear

# Re-cache config
php artisan config:cache
```

### Missing Environment Variables

**Problem:** `env('CART_DRIVER')` returns null.

**Solution:**

```bash
# Check .env file exists
ls -la .env

# Verify variable is set
grep CART_DRIVER .env

# Clear config cache (cached values override .env)
php artisan config:clear
```

### Type Mismatch Errors

**Problem:** `Expected boolean, got string "true"`.

**Solution:**

```env
# ❌ Wrong (string)
CART_EVENTS_ENABLED="true"

# ✅ Correct (boolean)
CART_EVENTS_ENABLED=true
```

### Driver Not Found

**Problem:** `Driver [xyz] not supported.`

**Solution:**

```bash
# Check available drivers
grep "'driver'" config/cart.php

# Valid options: session, cache, database
CART_DRIVER=cache
```

### Performance Degradation

**Problem:** Cart operations slow after config changes.

**Solution:**

```bash
# Check current config
php artisan config:show cart

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Configuration Examples

### E-commerce Store

```env
# Multi-device persistent carts
CART_DRIVER=database
CART_DATABASE_LOCKING=optimistic
CART_MIGRATION_STRATEGY=add_quantities
CART_MIGRATION_CLEAR_GUEST=true
CART_MAX_ITEMS=50
CART_MAX_QUANTITY=10
CART_CONDITIONS_ALLOW_NEGATIVE=false
CART_EVENTS_ENABLED=true
CART_LOGGING_ENABLED=false
```

### Wholesale B2B Platform

```env
# Large carts, pessimistic locking
CART_DRIVER=database
CART_DATABASE_LOCKING=pessimistic
CART_MIGRATION_STRATEGY=keep_user_cart
CART_MIGRATION_CLEAR_GUEST=false
CART_MAX_ITEMS=500
CART_MAX_QUANTITY=10000
CART_CONDITIONS_ALLOW_NEGATIVE=true
CART_EVENTS_ENABLED=true
CART_LOGGING_ENABLED=true
```

### High-Traffic API

```env
# Cache driver, minimal overhead
CART_DRIVER=cache
CART_CACHE_STORE=redis
CART_CACHE_TTL=86400
CART_EVENTS_ENABLED=false
CART_LOGGING_ENABLED=false
CART_MAX_ITEMS=100
```

### Marketplace with Multi-Currency

```env
# Multi-currency support
CART_DRIVER=database
CART_CURRENCY=USD
CART_MIGRATION_STRATEGY=add_quantities
CART_CONDITIONS_ORDER=item,subtotal,total
CART_CONDITIONS_ALLOW_NEGATIVE=false
CART_EVENTS_ENABLED=true
```

---

## Additional Resources

- [Getting Started Guide](getting-started.md)
- [Storage Drivers](storage.md)
- [Money & Currency](money-and-currency.md)
- [Events System](events.md)

---

**Need help?** Check the [Troubleshooting Guide](troubleshooting.md) or [open an issue](https://github.com/aiarmada/cart/issues).
