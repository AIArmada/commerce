---
title: Usage
---

# Usage Guide

## Payment Gateway (Recommended)

The `ChipGateway` implements the universal `PaymentGatewayInterface`, allowing CHIP to work with any `CheckoutableInterface`:

```php
use AIArmada\Chip\Gateways\ChipGateway;

$gateway = app(ChipGateway::class);
$payment = $gateway->createPayment($cart, $customer, [
    'success_url' => route('checkout.success'),
    'failure_url' => route('checkout.failed'),
]);

return redirect($payment->getCheckoutUrl());
```

## CHIP Collect Facade

### Create a Purchase

```php
use AIArmada\Chip\Facades\Chip;

// Using array syntax
$purchase = Chip::createPurchase([
    'client' => ['email' => 'customer@example.com'],
    'purchase' => [
        'currency' => 'MYR',
        'products' => [['name' => 'Product', 'price' => 9900]],
    ],
]);

// Using fluent builder
$purchase = Chip::purchase()
    ->customer('customer@example.com', 'John Doe')
    ->addProductCents('Product', 9900)
    ->successUrl(route('success'))
    ->create();
```

### Refund a Purchase

```php
// Full refund
$purchase = Chip::refundPurchase($purchaseId);

// Partial refund (amount in cents)
$purchase = Chip::refundPurchase($purchaseId, 5000);
```

### Cancel a Purchase

```php
$purchase = Chip::cancelPurchase($purchaseId);
```

### Get Available Payment Methods

```php
$methods = Chip::getPaymentMethods([
    'currency' => 'MYR',
]);
```

## CHIP Send Facade

### Create a Payout

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

## Webhooks

### Listen to Events

```php
// In EventServiceProvider
use AIArmada\Chip\Events\PurchasePaid;

protected $listen = [
    PurchasePaid::class => [
        HandlePaidPurchase::class,
    ],
];

// Listener
class HandlePaidPurchase
{
    public function handle(PurchasePaid $event): void
    {
        $purchase = $event->purchase;
        $payload = $event->payload;
        
        // Process the payment...
    }
}
```

### Available Events

| Event | Description |
|-------|-------------|
| `PurchaseCreated` | Purchase was created |
| `PurchasePaid` | Payment was successful |
| `PurchasePaymentFailure` | Payment failed |
| `PurchaseCancelled` | Purchase was cancelled |
| `PurchaseRefunded` | Payment was refunded |
| `PaymentRefunded` | Refund completed |
| `PayoutPending` | Payout is pending |
| `PayoutSuccess` | Payout completed |
| `PayoutFailed` | Payout failed |

## Recurring Payments

The package provides app-layer recurring payments using CHIP's token + charge APIs:

```php
use AIArmada\Chip\Services\RecurringService;

$recurring = app(RecurringService::class);

// Create a schedule from a paid purchase with recurring token
$schedule = $recurring->createScheduleFromPurchase(
    purchase: $paidPurchase,
    interval: RecurringInterval::Monthly,
    intervalCount: 1,
);

// Process due charges (typically via scheduler)
$results = $recurring->processAllDue();
```

### Scheduled Processing

Add to your scheduler:

```php
// app/Console/Kernel.php
$schedule->command('chip:process-recurring')->hourly();
$schedule->command('chip:aggregate-metrics')->daily();
$schedule->command('chip:clean-webhooks --days=30')->weekly();
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `chip:health` | Check CHIP API connectivity |
| `chip:process-recurring` | Process due recurring payments |
| `chip:aggregate-metrics` | Aggregate purchase data into daily metrics |
| `chip:retry-webhooks` | Retry failed webhooks |
| `chip:clean-webhooks` | Clean old webhook records |
