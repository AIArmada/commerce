# CHIP for Laravel

Laravel 12 integration for [CHIP](https://docs.chip-in.asia/) payment platform – **CHIP Collect** (payments) and **CHIP Send** (disbursements).

## Features

- **Fully independent** – works standalone without requiring other commerce packages
- **Seamless integration** – auto-integrates with Cart when installed together
- **Universal Gateway** – implements `PaymentGatewayInterface` for provider switching
- **Complete API coverage** – purchases, refunds, subscriptions, payouts, webhooks
- **Laravel DX** – facades, fluent builders, typed data objects, events
- **Production ready** – PHP 8.4, PHPStan level 6, Pest test suite
- **Secure** – webhook signature verification, sensitive data masking

## Installation

```bash
composer require aiarmada/chip
```

Publish config and migrations:

```bash
php artisan vendor:publish --tag="chip-config"
php artisan vendor:publish --tag="chip-migrations"
php artisan migrate
```

## Configuration

```env
# CHIP Collect
CHIP_COLLECT_API_KEY=your-api-key
CHIP_COLLECT_BRAND_ID=your-brand-id

# CHIP Send
CHIP_SEND_API_KEY=your-send-api-key
CHIP_SEND_API_SECRET=your-send-api-secret

# Environment
CHIP_ENVIRONMENT=sandbox

# Webhooks
CHIP_COMPANY_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
CHIP_WEBHOOK_VERIFY_SIGNATURE=true
```

## Quick Start

### Payment Gateway (Recommended)

Works with any `CheckoutableInterface` – Cart, Order, or custom implementations:

```php
use AIArmada\Chip\Gateways\ChipGateway;

$gateway = app(ChipGateway::class);
$payment = $gateway->createPayment($checkoutable, $customer, [
    'success_url' => route('checkout.success'),
    'failure_url' => route('checkout.failed'),
]);

return redirect($payment->getCheckoutUrl());
```

When `aiarmada/cart` is installed, Cart automatically implements `CheckoutableInterface`:

```php
$cart = app(\AIArmada\Cart\Cart::class);
$payment = $gateway->createPayment($cart, $customer, $options);
```

### CHIP Collect

```php
use AIArmada\Chip\Facades\Chip;

// Create purchase
$purchase = Chip::createPurchase([
    'client' => ['email' => 'customer@example.com'],
    'purchase' => [
        'currency' => 'MYR',
        'products' => [['name' => 'Product', 'price' => 9900]],
    ],
]);

// Fluent builder
$purchase = Chip::purchase()
    ->customer('customer@example.com', 'John Doe')
    ->addProduct('Product', 9900)
    ->successUrl(route('success'))
    ->create();
```

### CHIP Send

```php
use AIArmada\Chip\Facades\ChipSend;

$instruction = ChipSend::createSendInstruction(
    amountInCents: 10000,
    currency: 'MYR',
    recipientBankAccountId: 'bank_123',
    description: 'Payout',
    reference: 'PAY-001',
    email: 'recipient@example.com',
);
```

### Webhooks

```php
Route::post('/chip/webhook', function (Request $request) {
    $handler = app(ChipGateway::class)->getWebhookHandler();
    $payload = $handler->verify($request);
    
    if ($payload->event === 'purchase.paid') {
        // Handle payment
    }
    
    return response('OK');
});
```

## Health Check

```bash
php artisan chip:health
```

## Documentation

- [Payment Gateway](docs/payment-gateway.md) – Universal gateway interface
- [CHIP Collect](docs/chip-collect.md) – Payments and purchases
- [CHIP Send](docs/chip-send.md) – Disbursements and payouts
- [Webhooks](docs/webhooks.md) – Event handling
- [API Reference](docs/api-reference.md) – Complete method reference

## License

MIT License. See [LICENSE](LICENSE) for details.
