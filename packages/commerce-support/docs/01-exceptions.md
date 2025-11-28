# Exceptions

AIArmada Commerce Support provides a standardized exception hierarchy for consistent error handling across all commerce packages.

## Exception Hierarchy

```
CommerceException (base)
├── CommerceApiException
├── PaymentGatewayException
└── WebhookVerificationException
```

## CommerceException

Base exception with rich error context.

### Usage

```php
use AIArmada\CommerceSupport\Exceptions\CommerceException;

throw new CommerceException(
    message: 'Cart operation failed',
    errorCode: 'cart_operation_failed',
    errorData: ['cart_id' => 'abc123', 'operation' => 'checkout']
);
```

### Accessing Context

```php
try {
    // operation
} catch (CommerceException $e) {
    $e->getErrorCode();  // 'cart_operation_failed'
    $e->getErrorData();  // ['cart_id' => 'abc123', ...]
    $e->getContext();    // Complete array with message, code, file, line, data
}
```

## CommerceApiException

For external API integration errors (CHIP, J&T Express, etc.).

### Factory Method

```php
use AIArmada\CommerceSupport\Exceptions\CommerceApiException;

$exception = CommerceApiException::fromResponse(
    responseData: ['error' => 'invalid_brand_id', 'message' => 'Brand not found'],
    statusCode: 404,
    endpoint: '/purchases/'
);
```

### Accessing API Context

```php
$exception->getStatusCode();  // 404
$exception->getEndpoint();    // '/purchases/'
$exception->getApiResponse(); // Original API response array
```

## PaymentGatewayException

For payment gateway operation failures.

### Factory Methods

```php
use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;

// Payment creation failed
throw PaymentGatewayException::creationFailed('chip', 'Invalid amount');

// Payment not found
throw PaymentGatewayException::notFound('chip', 'pay_123');

// Refund failed
throw PaymentGatewayException::refundFailed('chip', 'pay_123', 'Already refunded');

// Capture failed
throw PaymentGatewayException::captureFailed('chip', 'pay_123', 'Expired');

// Cancellation failed
throw PaymentGatewayException::cancellationFailed('chip', 'pay_123', 'Already paid');

// Invalid configuration
throw PaymentGatewayException::invalidConfiguration('chip', 'Missing API key');

// Unsupported operation
throw PaymentGatewayException::unsupportedOperation('chip', 'recurring');

// Currency mismatch
throw PaymentGatewayException::currencyMismatch('chip', 'MYR', 'USD');
```

### Properties

```php
$exception->gatewayName;  // 'chip'
$exception->errorCode;    // 'refund_failed'
$exception->context;      // ['payment_id' => 'pay_123']
```

## WebhookVerificationException

For webhook signature verification failures.

### Factory Methods

```php
use AIArmada\CommerceSupport\Exceptions\WebhookVerificationException;

throw WebhookVerificationException::missingSignature('chip');
throw WebhookVerificationException::invalidSignature('chip');
throw WebhookVerificationException::missingPublicKey('chip');
throw WebhookVerificationException::invalidPayload('chip', 'Missing event type');
```

### Properties

```php
$exception->gatewayName;  // 'chip'
```

## Error Handling Pattern

Recommended pattern for handling commerce exceptions:

```php
use AIArmada\CommerceSupport\Exceptions\CommerceException;
use AIArmada\CommerceSupport\Exceptions\CommerceApiException;
use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;

try {
    $gateway->createPayment($cart);
} catch (PaymentGatewayException $e) {
    // Handle payment-specific errors
    Log::error('Payment failed', [
        'gateway' => $e->gatewayName,
        'error' => $e->errorCode,
        'context' => $e->context,
    ]);
} catch (CommerceApiException $e) {
    // Handle API errors
    Log::error('API error', [
        'status' => $e->getStatusCode(),
        'endpoint' => $e->getEndpoint(),
    ]);
} catch (CommerceException $e) {
    // Handle general commerce errors
    Log::error('Commerce error', $e->getContext());
}
```
