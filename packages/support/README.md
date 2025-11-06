# AIArmada Commerce Support

Core utilities, exceptions, HTTP clients, and foundation code for all AIArmada Commerce packages.

[![Packagist](https://img.shields.io/packagist/v/aiarmada/commerce-support.svg?style=flat-square)](https://packagist.org/packages/aiarmada/commerce-support)
[![Tests](https://img.shields.io/github/actions/workflow/status/aiarmada/commerce-support/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/aiarmada/commerce-support/actions)

## Purpose

This package provides shared utilities, traits, base classes, and standardized patterns used across all AIArmada Commerce packages. It eliminates code duplication and ensures consistency across the ecosystem.

---

## Installation

This package is automatically required by all AIArmada Commerce packages. You don't need to install it directly unless building custom extensions:

```bash
composer require aiarmada/commerce-support
```

---

## What's Included

### 🚨 Exception Hierarchy

A standardized exception hierarchy for consistent error handling across all commerce packages.

#### `CommerceException` (Base)

Base exception with rich error context:

```php
use AIArmada\CommerceSupport\Exceptions\CommerceException;

throw new CommerceException(
    message: 'Cart operation failed',
    errorCode: 'cart_operation_failed',
    errorData: ['cart_id' => 'abc123', 'operation' => 'checkout']
);

// Access context
$exception->getErrorCode(); // 'cart_operation_failed'
$exception->getErrorData(); // ['cart_id' => 'abc123', ...]
$exception->getContext();   // Complete array with message, code, file, line, data
```

#### `CommerceApiException`

For external API integration errors (CHIP, J&T Express):

```php
use AIArmada\CommerceSupport\Exceptions\CommerceApiException;

// Automatically extract error details from API response
$exception = CommerceApiException::fromResponse(
    responseData: ['error' => 'invalid_brand_id', 'message' => 'Brand not found'],
    statusCode: 404,
    endpoint: '/purchases/'
);

$exception->getStatusCode(); // 404
$exception->getEndpoint();   // '/purchases/'
$exception->getApiResponse(); // Original API response
```

#### `CommerceValidationException`

For validation failures with field-level error tracking:

```php
use AIArmada\CommerceSupport\Exceptions\CommerceValidationException;

// Field-specific errors
throw CommerceValidationException::forField(
    field: 'email',
    error: 'Invalid email format'
);

// Multiple errors
throw new CommerceValidationException(
    message: 'Validation failed',
    errors: [
        'email' => ['Invalid email format'],
        'code' => ['Code already used']
    ]
);

// Check for specific field errors
$exception->hasError('email');          // true
$exception->getFieldErrors('email');    // ['Invalid email format']
```

#### `CommerceConfigurationException`

For configuration errors with helpful factory methods:

```php
use AIArmada\CommerceSupport\Exceptions\CommerceConfigurationException;

// Missing config
throw CommerceConfigurationException::missing('chip.api_key');

// Invalid config value
throw CommerceConfigurationException::invalid(
    configKey: 'chip.environment',
    value: 'invalid',
    reason: 'Must be "sandbox" or "production"'
);

// Config validation failed
throw CommerceConfigurationException::validationFailed([
    'api_key' => 'API key is required',
    'brand_id' => 'Brand ID must be a valid UUID'
]);
```

---

### 🌐 HTTP Client

#### `BaseApiClient`

Standardized HTTP client for external API integrations using Laravel's HTTP client:

```php
use AIArmada\CommerceSupport\Http\BaseApiClient;

class MyApiClient extends BaseApiClient
{
    protected function resolveBaseUrl(): string
    {
        return config('mypackage.api_url');
    }
    
    protected function authenticateRequest(PendingRequest $request): PendingRequest
    {
        return $request->withToken(config('mypackage.api_key'));
    }
    
    protected function handleFailedResponse(Response $response): void
    {
        throw CommerceApiException::fromResponse(
            responseData: $response->json(),
            statusCode: $response->status(),
            endpoint: $response->effectiveUri()->getPath()
        );
    }
}

// Usage
$client = new MyApiClient();
$data = $client->request('POST', '/endpoint', ['key' => 'value']);

// Configure retry/timeout/logging
$client->withRetry(maxRetries: 5, retryDelay: 2000)
       ->withTimeout(timeout: 60)
       ->withLogging(enabled: true, channel: 'api');
```

**Features:**
- Automatic retry with exponential backoff (default: 3 retries, 1s base delay)
- Configurable timeouts (default: 30s request, 10s connect)
- Request/response logging with sensitive data masking
- Handles connection failures and 5xx errors automatically
- Clean abstraction for authentication and error handling

---

### 💰 Money Utilities

#### `MoneyHelper`

Comprehensive money manipulation and formatting utilities:

```php
use AIArmada\CommerceSupport\Utilities\MoneyHelper;

// Create Money instances
$price = MoneyHelper::make(99.99, 'MYR');        // From float
$price = MoneyHelper::make('99.99', 'MYR');      // From string
$price = MoneyHelper::make('RM 99.99', 'MYR');   // Auto-sanitize

// Sanitize user input
$clean = MoneyHelper::sanitizePrice('RM 1,234.50'); // '1234.50'

// Format for display
MoneyHelper::formatForDisplay($price);                    // 'RM 1,234.50'
MoneyHelper::formatForDisplay($price, includeSymbol: false); // '1,234.50'

// Cents conversion
$cents = MoneyHelper::toCents($price);           // 9999
$price = MoneyHelper::fromCents(9999, 'MYR');    // Money instance

// Parse user input
$money = MoneyHelper::parseAmount('1234.50');    // Uses default currency

// Currency operations
MoneyHelper::getDefaultCurrency();               // 'MYR' (from config)
MoneyHelper::validateCurrency('USD');            // true/false
MoneyHelper::getCurrencySymbol('MYR');           // 'RM'

// Math operations
$zero = MoneyHelper::zero('MYR');
$equal = MoneyHelper::equals($price1, $price2);
$total = MoneyHelper::sum($price1, $price2, $price3);
$discount = MoneyHelper::percentage($price, 10);          // 10% of price
$withDiscount = MoneyHelper::percentage($price, 10, true); // price + 10%
```

**Default Currency Fallback Chain:**
1. `config('cart.money.default_currency')`
2. `config('app.currency')`
3. `'MYR'`

---

### 🎨 Enum Enhancement Concerns

Traits to enhance PHP enums with Filament-friendly features.

#### `HasLabels`

Add human-readable labels to enums:

```php
use AIArmada\CommerceSupport\Concerns\HasLabels;

enum OrderStatus: string
{
    use HasLabels;
    
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    
    public function getLabel(): string
    {
        return match($this) {
            self::Pending => 'Pending Payment',
            self::Paid => 'Paid',
            self::Shipped => 'Shipped to Customer',
        };
    }
}

// Usage
OrderStatus::Pending->getLabel();              // 'Pending Payment'
OrderStatus::labels();                         // ['pending' => 'Pending Payment', ...]
OrderStatus::toSelectOptions();                // For Filament Select components
OrderStatus::getLabelByValue('paid');          // 'Paid'
OrderStatus::fromLabel('Paid');                // OrderStatus::Paid
```

#### `HasColors`

Add Filament badge colors to enums:

```php
use AIArmada\CommerceSupport\Concerns\HasColors;

enum OrderStatus: string
{
    use HasColors;
    
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    
    public function getColor(): string
    {
        return match($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed => 'danger',
        };
    }
}

// Usage
OrderStatus::Paid->getColor();                 // 'success'
OrderStatus::Paid->getBadgeColor();            // Alias for Filament
OrderStatus::colors();                         // ['pending' => 'warning', ...]
```

#### `HasIcons`

Add Heroicon icons to enums:

```php
use AIArmada\CommerceSupport\Concerns\HasIcons;

enum PaymentMethod: string
{
    use HasIcons;
    
    case Card = 'card';
    case Bank = 'bank';
    case Wallet = 'wallet';
    
    public function getIcon(): string
    {
        return match($this) {
            self::Card => 'heroicon-o-credit-card',
            self::Bank => 'heroicon-o-building-library',
            self::Wallet => 'heroicon-o-wallet',
        };
    }
}

// Usage
PaymentMethod::Card->getIcon();                // 'heroicon-o-credit-card'
PaymentMethod::Card->getFilamentIcon();        // Alias for Filament
PaymentMethod::icons();                        // ['card' => 'heroicon-o-credit-card', ...]
```

#### `HasDescriptions`

Add optional descriptions/help text to enums:

```php
use AIArmada\CommerceSupport\Concerns\HasDescriptions;

enum ShippingMethod: string
{
    use HasDescriptions;
    
    case Standard = 'standard';
    case Express = 'express';
    
    public function getDescription(): ?string
    {
        return match($this) {
            self::Standard => 'Delivery in 3-5 business days',
            self::Express => 'Next day delivery before 5 PM',
        };
    }
}

// Usage
ShippingMethod::Express->getDescription();     // 'Next day delivery before 5 PM'
ShippingMethod::Express->hasDescription();     // true
ShippingMethod::descriptions();                // ['standard' => 'Delivery in 3-5...', ...]
```

---

### 🔧 Service Provider Helpers

#### `RegistersSingletonAliases` Trait

Simplifies registering services as singletons with optional aliases:

```php
use AIArmada\CommerceSupport\Concerns\RegistersSingletonAliases;

class MyServiceProvider extends PackageServiceProvider
{
    use RegistersSingletonAliases;
    
    public function packageRegistered(): void
    {
        $this->registerSingletonAlias(
            CartService::class,
            'cart'
        );
    }
}
```

#### `ValidatesConfiguration` Trait

Runtime configuration validation helpers:

```php
use AIArmada\CommerceSupport\Concerns\ValidatesConfiguration;

class MyServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;
    
    public function boot(): void
    {
        $this->requireConfigKeys('chip', ['api_key', 'brand_id']);
        $this->validateConfigEnum('chip.environment', ['sandbox', 'production']);
    }
}
```

---

### 📦 Helper Functions

#### `commerce_config()`

Convenient config accessor with dot notation:

```php
$storageDriver = commerce_config('cart.storage', 'session');
$apiKey = commerce_config('chip.collect.api_key');
```

---

## Package Structure

```
packages/support/
├── composer.json
├── CHANGELOG.md
├── README.md
├── src/
│   ├── SupportServiceProvider.php
│   ├── helpers.php
│   ├── Concerns/
│   │   ├── RegistersSingletonAliases.php
│   │   ├── ValidatesConfiguration.php
│   │   ├── HasLabels.php
│   │   ├── HasColors.php
│   │   ├── HasIcons.php
│   │   └── HasDescriptions.php
│   ├── Exceptions/
│   │   ├── CommerceException.php
│   │   ├── CommerceApiException.php
│   │   ├── CommerceValidationException.php
│   │   └── CommerceConfigurationException.php
│   ├── Http/
│   │   └── BaseApiClient.php
│   ├── Utilities/
│   │   └── MoneyHelper.php
│   └── Contracts/
│       └── (shared interfaces)
└── tests/
    ├── Unit/
    └── Feature/
```

---

## Dependencies

- PHP ^8.4
- Laravel ^12.0
- spatie/laravel-package-tools ^1.92

### Optional Dependencies

- akaunting/laravel-money ^6.0 (for MoneyHelper)
- guzzlehttp/guzzle ^7.9 (for HTTP client utilities)

---

## Usage in Packages

All AIArmada Commerce packages depend on this support package:

```json
{
    "require": {
        "aiarmada/commerce-support": "self.version"
    }
}
```

---

## Migration Guide

### From Legacy BaseHttpClient

If you're using the old Guzzle-based `BaseHttpClient`, migrate to the new Laravel HTTP-based `BaseApiClient`:

```php
// Old (Guzzle-based)
use AIArmada\CommerceSupport\Http\BaseHttpClient;

class MyClient extends BaseHttpClient
{
    protected function shouldLog(): bool { ... }
}

// New (Laravel HTTP-based)
use AIArmada\CommerceSupport\Http\BaseApiClient;

class MyClient extends BaseApiClient
{
    protected function resolveBaseUrl(): string { ... }
    protected function authenticateRequest(PendingRequest $request): PendingRequest { ... }
    protected function handleFailedResponse(Response $response): void { ... }
}
```

**Key Changes:**
- Uses Laravel's HTTP client instead of Guzzle directly
- Cleaner authentication hook with `authenticateRequest()`
- Explicit error handling with `handleFailedResponse()`
- Fluent configuration with `withRetry()`, `withTimeout()`, `withLogging()`

---

## Testing

```bash
composer test
```

Run specific tests:

```bash
composer test -- --filter=MoneyHelper
```

Run with coverage:

```bash
composer test-coverage
```

---

## Design Philosophy

1. **Foundation Only** - No business logic, only shared utilities
2. **Minimal Dependencies** - Only Laravel core and essential packages
3. **Zero UI Dependencies** - Can be used independently of Filament
4. **Clean Separation** - Each feature in its own namespace
5. **Well Documented** - Clear examples and comprehensive PHPDoc
6. **Type Safe** - PHP 8.4 types everywhere
7. **Test Driven** - Comprehensive Pest test suite

---

## Contributing

This package is part of the AIArmada Commerce monorepo.

### When to Add Code Here

✅ **DO** - Add truly shared code used by multiple packages  
✅ **DO** - Keep dependencies minimal and optional  
✅ **DO** - Document with comprehensive examples  
✅ **DO** - Add tests for all new utilities  

❌ **DON'T** - Add business logic (belongs in domain packages like cart, chip, vouchers)  
❌ **DON'T** - Add UI components (belongs in filament-* packages)  
❌ **DON'T** - Add heavy dependencies (keep support lightweight)  

### Development Workflow

1. Fork and clone the monorepo
2. Install dependencies: `composer install`
3. Run tests: `vendor/bin/pest`
4. Apply formatting: `vendor/bin/pint --dirty`
5. Submit PR with clear description and tests

---

## Security

If you discover security vulnerabilities, please email security@aiarmada.com instead of using the issue tracker.

---

## Credits

- [AIArmada Team](https://aiarmada.com)
- [All Contributors](https://github.com/aiarmada/commerce/contributors)

---

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
