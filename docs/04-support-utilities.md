# Support Utilities Reference

The **aiarmada/commerce-support** package provides shared utilities, exceptions, HTTP client, and helpers used across all Commerce packages.

## Overview

Support package contains:
- **Exception Hierarchy**: Structured exceptions for consistent error handling
- **BaseApiClient**: Abstract HTTP client with retry logic and logging
- **MoneyHelper**: Currency utilities for safe money operations
- **Enum Concerns**: Traits for enhanced Laravel enums

Tip: Configure database JSON type via `php artisan commerce:configure-database` to set `COMMERCE_JSON_COLUMN_TYPE` or per-package overrides before running migrations.

## Exception Hierarchy

### CommerceException

Base exception for all Commerce packages.

```php
use AIArmada\Support\Exceptions\CommerceException;

throw new CommerceException(
    message: 'Invalid cart operation',
    code: 'CART_INVALID',
    errorCode: 'cart.invalid',
    errorData: ['cart_id' => $cartId]
);
```

**Methods:**
- `getErrorCode()`: string - Machine-readable error code
- `getErrorData()`: array - Additional error context
- `getContext()`: array - Full error context for logging

### CommerceApiException

For external API failures.

```php
use AIArmada\Support\Exceptions\CommerceApiException;

throw CommerceApiException::fromResponse(
    response: $response,
    message: 'CHIP API request failed',
    errorCode: 'chip.api_error'
);
```

**Factory Methods:**
- `fromResponse(Response $response, string $message, string $errorCode)`: Create from HTTP response

**Properties:**
- `statusCode`: int - HTTP status code
- `responseBody`: array - Decoded response body

### CommerceValidationException

For validation failures.

```php
use AIArmada\Support\Exceptions\CommerceValidationException;

throw CommerceValidationException::forField(
    field: 'quantity',
    message: 'Quantity must be at least 1',
    value: $quantity
);
```

**Factory Methods:**
- `forField(string $field, string $message, mixed $value)`: Single field error

**Properties:**
- `field`: string - Field name
- `value`: mixed - Invalid value

### CommerceConfigurationException

For configuration issues.

```php
use AIArmada\Support\Exceptions\CommerceConfigurationException;

throw CommerceConfigurationException::missing(
    key: 'chip.collect.api_key',
    context: ['environment' => app()->environment()]
);

throw CommerceConfigurationException::invalid(
    key: 'cart.storage_driver',
    value: $driver,
    expectedValues: ['session', 'cache', 'database']
);
```

**Factory Methods:**
- `missing(string $key, array $context)`: Missing configuration
- `invalid(string $key, mixed $value, array $expectedValues)`: Invalid value

## HTTP Client (BaseApiClient)

Abstract HTTP client for external APIs with retry logic, logging, and error handling.

### Creating API Client

```php
namespace App\Services;

use AIArmada\Support\Http\BaseApiClient;
use Illuminate\Http\Client\Response;

class MyApiClient extends BaseApiClient
{
    protected function getBaseUrl(): string
    {
        return config('myapi.base_url');
    }
    
    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . config('myapi.api_key'),
            'Accept' => 'application/json',
        ];
    }
    
    protected function shouldRetry(Response $response): bool
    {
        // Retry on 5xx errors or connection issues
        return $response->status() >= 500;
    }
    
    protected function handleError(Response $response): void
    {
        throw CommerceApiException::fromResponse(
            $response,
            'My API request failed',
            'myapi.error'
        );
    }
}
```

### Using API Client

```php
$client = new MyApiClient();

// GET request
$response = $client->get('/users');

// POST request
$response = $client->post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// PUT request
$response = $client->put('/users/123', [
    'name' => 'Jane Doe',
]);

// DELETE request
$response = $client->delete('/users/123');
```

### Configuration

```php
// Constructor options
$client = new MyApiClient(
    timeout: 30,           // Request timeout (seconds)
    retryTimes: 3,         // Retry attempts
    retryDelay: 100,       // Initial delay (ms)
    retryMultiplier: 2.0,  // Exponential backoff
    logRequests: true,     // Log all requests
    maskSensitiveData: true // Mask API keys in logs
);
```

### Logging

Requests are automatically logged when `logRequests` is enabled:

```php
// Log format
[2025-11-01 10:30:45] production.INFO: API Request {
    "method": "POST",
    "url": "https://api.example.com/users",
    "headers": {
        "Authorization": "Bearer ***MASKED***",
        "Content-Type": "application/json"
    },
    "body": { "name": "John Doe" },
    "response_status": 200,
    "response_time_ms": 245
}
```

Sensitive fields are automatically masked: `api_key`, `secret`, `token`, `password`, `authorization`.

## MoneyHelper

Utilities for safe money operations with Akaunting Money.

### Creating Money Instances

```php
use AIArmada\Support\Utilities\MoneyHelper;

// From cents
$money = MoneyHelper::make(2999, 'MYR'); // RM 29.99

// From formatted string
$money = MoneyHelper::sanitizePrice('RM 29.99', 'MYR');
$money = MoneyHelper::sanitizePrice('29.99', 'MYR');
$money = MoneyHelper::sanitizePrice('2,999.00', 'MYR');

// Zero amount
$zero = MoneyHelper::zero('MYR');
```

### Converting Amounts

```php
// To cents
$cents = MoneyHelper::toCents($money); // 2999

// From cents
$money = MoneyHelper::fromCents(2999, 'MYR');

// Parse string amount
$amount = MoneyHelper::parseAmount('29.99'); // 2999
$amount = MoneyHelper::parseAmount('29,99'); // 2999
```

### Formatting for Display

```php
$money = MoneyHelper::make(2999, 'MYR');

// Format with symbol
echo MoneyHelper::formatForDisplay($money); // "RM29.99"

// Format without symbol
echo MoneyHelper::formatForDisplay($money, includeSymbol: false); // "29.99"
```

### Currency Operations

```php
// Get default currency
$currency = MoneyHelper::getDefaultCurrency(); // "MYR"

// Validate currency code
MoneyHelper::validateCurrency('MYR'); // true
MoneyHelper::validateCurrency('INVALID'); // throws exception

// Get currency symbol
$symbol = MoneyHelper::getCurrencySymbol('MYR'); // "RM"
$symbol = MoneyHelper::getCurrencySymbol('USD'); // "$"
```

### Money Calculations

```php
$price1 = MoneyHelper::make(2999, 'MYR');
$price2 = MoneyHelper::make(1999, 'MYR');

// Check equality
MoneyHelper::equals($price1, $price2); // false

// Sum multiple amounts
$total = MoneyHelper::sum([$price1, $price2]); // RM 49.98

// Calculate percentage
$discount = MoneyHelper::percentage($price1, 10); // RM 2.999 (10%)

// Currency conversion (requires exchange rate)
$usd = MoneyHelper::convertCurrency(
    $price1,
    'USD',
    exchangeRate: 4.5
); // Converts RM to USD
```

## Enum Concerns

Traits for enhanced Laravel 8.1+ enums.

### HasLabels

Add human-readable labels to enums.

```php
use AIArmada\Support\Concerns\HasLabels;

enum OrderStatus: string
{
    use HasLabels;
    
    case PENDING = 'pending';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    
    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'Pending Payment',
            self::PAID => 'Paid',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
        };
    }
}

// Usage
echo OrderStatus::PAID->getLabel(); // "Paid"

// Get all labels
$labels = OrderStatus::labels();
// ['pending' => 'Pending Payment', 'paid' => 'Paid', ...]

// Select options for forms
$options = OrderStatus::toSelectOptions();
// ['pending' => 'Pending Payment', 'paid' => 'Paid', ...]

// Get label by value
$label = OrderStatus::getLabelByValue('paid'); // "Paid"

// Get enum from label
$status = OrderStatus::fromLabel('Paid'); // OrderStatus::PAID
```

### HasColors

Add colors for UI display.

```php
use AIArmada\Support\Concerns\HasColors;

enum OrderStatus: string
{
    use HasColors;
    
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    
    public function getColor(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::PAID => 'success',
            self::CANCELLED => 'danger',
        };
    }
}

// Usage
echo OrderStatus::PAID->getColor(); // "success"

// Get all colors
$colors = OrderStatus::colors();
// ['pending' => 'warning', 'paid' => 'success', ...]

// Get color by value
$color = OrderStatus::getColorByValue('paid'); // "success"

// Get Filament badge color
$badgeColor = OrderStatus::PAID->getBadgeColor(); // "success"
```

### HasIcons

Add icons for UI display.

```php
use AIArmada\Support\Concerns\HasIcons;

enum OrderStatus: string
{
    use HasIcons;
    
    case PENDING = 'pending';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
    
    public function getIcon(): string
    {
        return match($this) {
            self::PENDING => 'heroicon-o-clock',
            self::PAID => 'heroicon-o-check-circle',
            self::SHIPPED => 'heroicon-o-truck',
        };
    }
}

// Usage
echo OrderStatus::PAID->getIcon(); // "heroicon-o-check-circle"

// Get all icons
$icons = OrderStatus::icons();

// Get icon by value
$icon = OrderStatus::getIconByValue('paid');

// Get Filament icon
$filamentIcon = OrderStatus::PAID->getFilamentIcon();
```

### HasDescriptions

Add descriptions for enums.

```php
use AIArmada\Support\Concerns\HasDescriptions;

enum PaymentMethod: string
{
    use HasDescriptions;
    
    case FPX = 'fpx';
    case CARD = 'card';
    
    public function getDescription(): string
    {
        return match($this) {
            self::FPX => 'Online banking via FPX',
            self::CARD => 'Credit/debit card payment',
        };
    }
}

// Usage
echo PaymentMethod::FPX->getDescription();
// "Online banking via FPX"

// Get all descriptions
$descriptions = PaymentMethod::descriptions();

// Get description by value
$desc = PaymentMethod::getDescriptionByValue('fpx');

// Check if has description
PaymentMethod::FPX->hasDescription(); // true
```

### Combining Multiple Concerns

```php
enum VoucherType: string
{
    use HasLabels, HasColors, HasIcons, HasDescriptions;
    
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';
    case FREE_SHIPPING = 'free_shipping';
    
    public function getLabel(): string
    {
        return match($this) {
            self::FIXED => 'Fixed Amount',
            self::PERCENTAGE => 'Percentage',
            self::FREE_SHIPPING => 'Free Shipping',
        };
    }
    
    public function getColor(): string
    {
        return match($this) {
            self::FIXED => 'success',
            self::PERCENTAGE => 'primary',
            self::FREE_SHIPPING => 'warning',
        };
    }
    
    public function getIcon(): string
    {
        return match($this) {
            self::FIXED => 'heroicon-o-currency-dollar',
            self::PERCENTAGE => 'heroicon-o-percent-badge',
            self::FREE_SHIPPING => 'heroicon-o-truck',
        };
    }
    
    public function getDescription(): string
    {
        return match($this) {
            self::FIXED => 'Discount by fixed amount',
            self::PERCENTAGE => 'Discount by percentage',
            self::FREE_SHIPPING => 'Free shipping on order',
        };
    }
}
```

## Best Practices

### Exception Handling

Always use specific Commerce exceptions:

```php
// ❌ Bad
throw new \Exception('Cart not found');

// ✅ Good
throw new CommerceException(
    'Cart not found',
    'CART_NOT_FOUND',
    errorCode: 'cart.not_found',
    errorData: ['cart_id' => $cartId]
);
```

### Money Operations

Always use MoneyHelper for currency:

```php
// ❌ Bad
$total = $price * $quantity; // Loses precision

// ✅ Good
$price = MoneyHelper::make(2999, 'MYR');
$total = $price->multiply($quantity);
```

### API Clients

Extend BaseApiClient for external APIs:

```php
// ❌ Bad
Http::get('https://api.example.com/users');

// ✅ Good
class MyApiClient extends BaseApiClient {
    // Structured, logged, retried, error-handled
}
```

## Next Steps

- **[Cart Package](03-packages/01-cart.md)**: Uses MoneyHelper and exceptions
- **[CHIP Package](03-packages/02-chip.md)**: Uses BaseApiClient
- **[Vouchers Package](03-packages/03-vouchers.md)**: Uses enum concerns
