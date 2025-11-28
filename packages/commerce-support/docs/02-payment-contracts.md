# Payment Contracts

Standardized interfaces for payment gateway integrations. These contracts allow easy swapping between providers (Stripe, CHIP, PayPal, etc.) without changing application code.

## Overview

| Contract | Purpose |
|----------|---------|
| `PaymentGatewayInterface` | Universal gateway interface |
| `CheckoutableInterface` | Objects that can be checked out |
| `CustomerInterface` | Customer information |
| `LineItemInterface` | Individual line items |
| `PaymentIntentInterface` | Payment response |
| `WebhookHandlerInterface` | Webhook processing |
| `PaymentStatus` | Universal status enum |
| `WebhookPayload` | Standardized webhook data |

## PaymentGatewayInterface

Universal interface for all payment gateways.

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;

interface PaymentGatewayInterface
{
    public function getName(): string;
    public function getDisplayName(): string;
    public function isTestMode(): bool;

    public function createPayment(
        CheckoutableInterface $checkoutable,
        ?CustomerInterface $customer = null,
        array $options = []
    ): PaymentIntentInterface;

    public function getPayment(string $paymentId): PaymentIntentInterface;
    public function cancelPayment(string $paymentId): PaymentIntentInterface;
    public function refundPayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface;
    public function capturePayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface;

    public function getPaymentMethods(array $filters = []): array;
    public function supports(string $feature): bool;
    public function getWebhookHandler(): WebhookHandlerInterface;
}
```

### Supported Features

Check gateway capabilities:

```php
$gateway->supports('refunds');           // Supports refunds
$gateway->supports('partial_refunds');   // Supports partial refunds
$gateway->supports('pre_authorization'); // Supports auth + capture
$gateway->supports('recurring');         // Supports recurring payments
$gateway->supports('webhooks');          // Supports webhooks
$gateway->supports('hosted_checkout');   // Supports redirect checkout
$gateway->supports('embedded_checkout'); // Supports embedded forms
```

## CheckoutableInterface

For objects that can be checked out (Cart, Order, Invoice).

```php
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;

interface CheckoutableInterface
{
    public function getCheckoutLineItems(): iterable;
    public function getCheckoutSubtotal(): Money;
    public function getCheckoutDiscount(): Money;
    public function getCheckoutTax(): Money;
    public function getCheckoutTotal(): Money;
    public function getCheckoutCurrency(): string;
    public function getCheckoutReference(): string;
    public function getCheckoutNotes(): ?string;
    public function getCheckoutMetadata(): array;
}
```

### Implementation Example

```php
class Cart implements CheckoutableInterface
{
    public function getCheckoutLineItems(): iterable
    {
        return $this->items->map(fn($item) => new CartLineItem($item));
    }

    public function getCheckoutTotal(): Money
    {
        return Money::MYR($this->total * 100);
    }

    public function getCheckoutReference(): string
    {
        return $this->uuid;
    }
}
```

## CustomerInterface

Customer information for payment processing.

```php
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;

interface CustomerInterface
{
    // Basic info
    public function getCustomerEmail(): string;
    public function getCustomerName(): ?string;
    public function getCustomerPhone(): ?string;
    public function getCustomerCountry(): ?string;

    // Billing address
    public function getBillingStreetAddress(): ?string;
    public function getBillingCity(): ?string;
    public function getBillingState(): ?string;
    public function getBillingPostalCode(): ?string;
    public function getBillingCountry(): ?string;

    // Shipping address
    public function hasShippingAddress(): bool;
    public function getShippingStreetAddress(): ?string;
    public function getShippingCity(): ?string;
    public function getShippingState(): ?string;
    public function getShippingPostalCode(): ?string;
    public function getShippingCountry(): ?string;

    // Gateway-specific
    public function getGatewayCustomerId(): ?string;
    public function getCustomerMetadata(): array;
}
```

## LineItemInterface

Individual purchasable items.

```php
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;

interface LineItemInterface
{
    public function getLineItemId(): string;
    public function getLineItemName(): string;
    public function getLineItemPrice(): Money;
    public function getLineItemQuantity(): int|float;
    public function getLineItemDiscount(): Money;
    public function getLineItemTaxPercent(): float;
    public function getLineItemSubtotal(): Money;
    public function getLineItemCategory(): ?string;
    public function getLineItemMetadata(): array;
}
```

## PaymentIntentInterface

Represents a payment intent/purchase response.

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;

interface PaymentIntentInterface
{
    public function getPaymentId(): string;
    public function getReference(): ?string;
    public function getAmount(): Money;
    public function getStatus(): PaymentStatus;
    public function getCheckoutUrl(): ?string;
    public function getSuccessUrl(): ?string;
    public function getFailureUrl(): ?string;

    public function isPaid(): bool;
    public function isPending(): bool;
    public function isFailed(): bool;
    public function isCancelled(): bool;
    public function isRefunded(): bool;
    public function isTest(): bool;

    public function getRefundableAmount(): Money;
    public function getGatewayName(): string;
    public function getCreatedAt(): DateTimeInterface;
    public function getUpdatedAt(): DateTimeInterface;
    public function getPaidAt(): ?DateTimeInterface;
    public function getMetadata(): array;
    public function getRawResponse(): array;
}
```

## PaymentStatus Enum

Universal payment status with helper methods.

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

enum PaymentStatus: string
{
    case CREATED = 'created';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case REQUIRES_ACTION = 'requires_action';
    case AUTHORIZED = 'authorized';
    case PAID = 'paid';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case DISPUTED = 'disputed';

    public function isSuccessful(): bool;
    public function isPending(): bool;
    public function isTerminal(): bool;
    public function isRefundable(): bool;
    public function isCancellable(): bool;
    public function label(): string;
    public function color(): string;
}
```

### Usage

```php
$status = PaymentStatus::PAID;

$status->isSuccessful();  // true
$status->isPending();     // false
$status->isTerminal();    // true (final state)
$status->isRefundable();  // true
$status->label();         // 'Paid'
$status->color();         // 'success' (for Filament badges)
```

## WebhookHandlerInterface

For processing payment webhooks.

```php
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;

interface WebhookHandlerInterface
{
    public function verifyWebhook(Request $request): bool;
    public function parseWebhook(Request $request): WebhookPayload;
    public function getEventType(Request $request): string;
    public function isPaymentEvent(Request $request): bool;
    public function getPaymentFromWebhook(Request $request): ?PaymentIntentInterface;
}
```

## WebhookPayload

Standardized webhook data.

```php
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;

readonly class WebhookPayload
{
    public function __construct(
        public string $eventType,
        public string $paymentId,
        public PaymentStatus $status,
        public ?string $reference,
        public string $gatewayName,
        public DateTimeInterface $occurredAt,
        public array $rawData = [],
    );

    public function isPaymentSuccess(): bool;
    public function isPaymentFailed(): bool;
    public function isRefund(): bool;
    public function isCancellation(): bool;
    public function get(string $key, mixed $default = null): mixed;
}
```

### Usage Example

```php
$payload = $webhookHandler->parseWebhook($request);

if ($payload->isPaymentSuccess()) {
    $order = Order::findByReference($payload->reference);
    $order->markAsPaid();
}

if ($payload->isRefund()) {
    // Handle refund
}
```
