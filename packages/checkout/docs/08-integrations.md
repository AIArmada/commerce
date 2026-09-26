---
title: Package Integrations
---

# Package Integrations

The checkout package integrates seamlessly with other commerce packages when they're installed.

## Inventory Integration

When the `aiarmada/inventory` package is installed, checkout automatically manages stock reservations during the checkout process.

### How It Works

1. **Stock Validation**: Before reserving, the `ReserveInventoryStep` validates that sufficient stock exists for all cart items.

2. **Reservation**: Stock is reserved using the cart ID (`$session->cart_id`) as the reference. Reservations prevent overselling while the customer completes payment.

3. **Commitment**: Checkout does not commit reservations. `aiarmada/inventory` commits the allocation itself — `CommitInventoryOnPayment` listens for `PaymentConfirmed`, and `DeductInventoryFromOrder` listens for the orders package's `InventoryDeductionRequired`. `InventoryAdapter::commit()` exists as a seam but has no checkout caller.

4. **Rollback**: If checkout fails or is cancelled, `ReserveInventoryStep::compensate()` releases the reservation group (honouring `release_on_failure`).

### Configuration

```php
// config/checkout.php
'integrations' => [
    'inventory' => [
        'enabled' => true,                    // Enable inventory integration
        'validate_stock' => true,              // Validate stock availability
        'reserve_before_payment' => true,      // True keeps reserve_inventory before payment; false moves it to the post-payment phase
        'release_on_failure' => true,          // Auto-release on failure/cancel
        'reservation_ttl' => 60 * 15,          // Reservation duration (15 minutes)
    ],
],
```

### Configuration Options

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | `true` | Enable/disable inventory integration |
| `validate_stock` | bool | `true` | Validate stock availability before reservation |
| `reserve_before_payment` | bool | `true` | Keep `reserve_inventory` before `process_payment`; when `false`, checkout runs it first in the post-payment phase before `persist_customer` and `create_order` |
| `release_on_failure` | bool | `true` | Automatically release reservations on failure |
| `reservation_ttl` | int | `900` | Reservation expiration in seconds (15 min) |

### Using Without Inventory Package

When the inventory package isn't installed:

- `InventoryAdapter::isInventoryPackageInstalled()` returns false and every call
  returns a `ReservationOutcome` with state `not_managed`
- The `ReserveInventoryStep` is never registered (see `RegisterCheckoutOptionalSteps`)
- No reservations are created
- `EnsureCheckoutOfferProduct` also skips inventory seeding unless inventory integration is enabled **and** the inventory tables are present

This allows checkout to work standalone without inventory management.

### Manual Inventory Integration

`AIArmada\Checkout\Integrations\InventoryAdapter` is `final`, so it is not
extensible. For custom inventory systems, bind your own implementation of the
inventory package's reservation contract and let the adapter resolve it:

```php
<?php

namespace App\Checkout\Integrations;

use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Data\ReservationOutcome;

class CustomReservationService implements CheckoutReservationServiceInterface
{
    /** @param  list<ReservationLine>  $lines */
    public function reserve(string $reference, array $lines, int $ttlSeconds): ReservationOutcome
    {
        // Your custom reservation logic
        return new ReservationOutcome(
            reference: $reference,
            state: 'reserved',
            expiresAt: now()->addSeconds($ttlSeconds)->toIso8601String(),
        );
    }

    public function release(string $reference): ReservationOutcome
    {
        return new ReservationOutcome(reference: $reference, state: 'released');
    }

    public function commit(string $reference, string $orderId): ReservationOutcome
    {
        return new ReservationOutcome(reference: $reference, state: 'committed');
    }

    public function extend(string $reference, int $ttlSeconds): ReservationOutcome
    {
        return new ReservationOutcome(reference: $reference, state: 'reserved');
    }

    public function find(string $reference): ReservationOutcome
    {
        return new ReservationOutcome(reference: $reference, state: 'reserved');
    }
}
```

Register your service in a service provider:

```php
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use App\Checkout\Integrations\CustomReservationService;

public function register(): void
{
    $this->app->bind(CheckoutReservationServiceInterface::class, CustomReservationService::class);
}
```

> **warning**
> `InventoryAdapter` short-circuits to `not_managed` whenever
> `CheckoutReservationServiceInterface` is not bound. Binding a stub is what
> turns the integration on.

### Events

The inventory integration is driven by the step executor, not by checkout events:

- `CheckoutFailed` / `CheckoutCancelled` / any later step failure — the executor
  calls `ReserveInventoryStep::compensate()`, which releases the whole reference
  group when `integrations.inventory.release_on_failure` is true
- `CheckoutCompleted` — checkout does not release or commit; the inventory
  package reacts to the orders package's payment events instead

## Shipping Integration

When the `aiarmada/shipping` package (or `aiarmada/jnt`) is installed, checkout calculates shipping costs.

### Configuration

```php
'integrations' => [
    'shipping' => [
        'enabled' => true,              // Enable shipping integration
        'require_selection' => true,     // Require shipping method selection
        'jnt' => [
            'enabled' => true,           // Enable J&T Express integration
            'auto_detect' => true,       // Auto-detect shipping zone
        ],
    ],
],
```

### How It Works

1. **Method Selection**: The `CalculateShippingStep` determines available shipping methods based on the destination address.

2. **Rate Calculation**: Shipping rates are calculated using the selected method and cart contents.

3. **Session Update**: The shipping cost is added to the session's pricing data.

## Tax Integration

When the `aiarmada/tax` package is installed, checkout calculates applicable taxes.

### Configuration

```php
'integrations' => [
    'tax' => [
        'enabled' => false, // Enable tax calculation
    ],
],
```

### How It Works

1. **Zone Detection**: The `CalculateTaxStep` determines the tax zone from the billing/shipping address.

2. **Rate Application**: Tax rates are applied based on product tax classes and the detected zone.

3. **Calculation**: Tax amounts are computed and added to the session's pricing data.

## Promotions Integration

When the `aiarmada/promotions` package is installed, checkout can apply promotional discounts.

### Unified discount-code input

Checkout resolves a single discount-code input from `billing_data.metadata.promo_code` first, then `cart_snapshot.metadata.promo_code`.

Resolution order is intentional:

1. validate the code as a voucher when vouchers are installed
2. if no valid voucher is found, try a code-based promotion against the promotion targeting context

This lets landing pages and billing forms submit one code field without deciding in advance whether the code represents a voucher or a promotion.

### Configuration

```php
'integrations' => [
    'promotions' => [
        'enabled' => true,    // Enable promotions
        'auto_apply' => true, // Automatically apply eligible promotions
    ],
],
```

### How It Works

1. **Evaluation**: The `ApplyDiscountsStep` evaluates promotion rules against the cart.

2. **Application**: Eligible promotions are applied in priority order.

3. **Stacking**: Multiple promotions can stack based on promotion configuration.

### Recorded Promotion Payloads

When promotions are applied during checkout, the checkout session stores `discount_data.promotions` and the created order keeps that payload in `order.metadata.discount_data.promotions`.

Each entry contains:

- `promotion_id`
- `name`
- `code`
- `type`
- `discount`

The stored `discount` is the **actual sequential discount applied at checkout time**, not a recalculation against the original subtotal. This keeps stacked-promotion analytics and downstream reporting accurate.

## Vouchers Integration

When the `aiarmada/vouchers` package is installed, checkout can redeem voucher codes.

### Configuration

```php
'integrations' => [
    'vouchers' => [
        'enabled' => true,       // Enable vouchers
        'allow_multiple' => false, // Allow multiple voucher codes per order
    ],
],
```

### How It Works

1. **Validation**: Voucher codes are validated for eligibility and usage limits using a cart-aware validation context from `CheckoutCartResolver`.

2. **Unified codes**: If the shared discount-code field resolved to a voucher, checkout prepends that code to the submitted voucher-code list automatically.

3. **Discount calculation**: Valid vouchers are priced through `VoucherDiscountCalculator`, which keeps voucher math consistent with the vouchers package.

4. **Events**: When a live cart is available, checkout dispatches `VoucherApplied` so downstream listeners can attach attribution or other side effects immediately.

5. **Recording**: Voucher usage is recorded after successful checkout.

### Recorded Voucher Usage Metadata

Checkout redemptions call the vouchers service after order creation. When the Orders package is installed, voucher usage records now carry richer order linkage:

- `redeemedBy` points at the order model
- `metadata.order_id`
- `metadata.order_number`
- `metadata.subtotal`
- `metadata.discount_total`
- `metadata.grand_total`

That metadata powers downstream voucher reporting, exports, and affiliate-source attribution in Filament.

Applied voucher payloads in checkout also include `promotion_id` when the voucher originated from promotion-issued voucher flows.

## CHIP

When `aiarmada/chip` is installed and `checkout.integrations.chip.enabled` is `true`, checkout listens to typed CHIP purchase events and forwards them into the same internal payment-callback flow used by `POST /webhooks/chip`.

This keeps checkout gateway-agnostic while letting CHIP remain the single webhook ingress. The recommended setup is to register only `config('chip.webhooks.route', '/chip/webhooks')` in the CHIP dashboard. Disable this integration if you want checkout to consume CHIP deliveries through `/webhooks/chip` instead.

`ChipIntegrationRegistrar` subscribes the checkout bridge to the concrete typed
purchase events (`PurchasePaid`, `PurchasePaymentFailure`, and
`PurchaseCancelled`). The bridge accepts the shared `PurchaseEvent` contract,
normalizes the provider reference at the boundary, and supplies the mandatory
`['chip', 'cashier-chip']` gateway set to checkout.

```php
'integrations' => [
    'chip' => [
        'enabled' => true,
    ],
],
```

## Checking Package Availability

You can check if integrations are available:

```php
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;

// Check if inventory package is installed
$hasInventory = interface_exists(CheckoutReservationServiceInterface::class);

// Check if shipping is enabled
$shippingEnabled = config('checkout.integrations.shipping.enabled', false);
```

## Disabling Integrations

Disable integrations via config or environment:

```php
// config/checkout.php
'integrations' => [
    'inventory' => ['enabled' => false],
    'shipping' => ['enabled' => false],
    'tax' => ['enabled' => false],
    'promotions' => ['enabled' => false],
    'vouchers' => ['enabled' => false],
    'chip' => ['enabled' => false],
],
```

Or disable the corresponding checkout steps:

```php
'steps' => [
    'enabled' => [
        'reserve_inventory' => false,
        'calculate_shipping' => false,
        'calculate_tax' => false,
        'apply_discounts' => false,
    ],
],
```

## Integration Registration Architecture

Optional integrations and payment processors are now registered through dedicated registrar classes instead of accumulating in `CheckoutServiceProvider`:

| Registrar | Responsibility |
|-----------|---------------|
| `RegisterBuiltInPaymentProcessors` | Registers cashier, cashier-chip, and chip payment processors |
| `RegisterCheckoutOptionalSteps` | Registers inventory, tax, and discount steps based on package availability and config |
| `ChipIntegrationRegistrar` | Listens to CHIP purchase events and forwards them to the callback flow |

### Adding a new integration via StepContributor

```php
use AIArmada\Checkout\Contracts\StepContributor;

final class MyIntegrationContributor implements StepContributor
{
    /** @return array<string, \Closure(): CheckoutStepInterface> */
    public function steps(): array
    {
        return [
            'my_custom_step' => fn (): CheckoutStepInterface => new MyCustomStep(),
        ];
    }
}
```

Tag the contributor in your service provider:

```php
// In your service provider's boot method
$this->app->tag(MyIntegrationContributor::class, 'checkout.steps');
```

### Callback flow

All callback entrypoints (redirect controller, webhook, CHIP events) converge on `HandleCheckoutPaymentCallback`, which owns session locking, completed-session short-circuiting, and delegation to `CheckoutServiceInterface::handlePaymentCallback()`.

- `PaymentCallbackController` — validates callback tokens, then calls `HandleCheckoutPaymentCallback`
- `ProcessCheckoutPaymentNotification` — extracts session from webhook payload, then calls `HandleCheckoutPaymentCallback`
- `HandleChipPurchaseEventForCheckout` — resolves callback type from CHIP event, delegates to `ProcessCheckoutPaymentNotification`

### CHIP checkout adapters

Checkout keeps a small adapter seam because its `PaymentStatus` and
`PaymentResult` contracts differ from CHIP's universal Commerce Support payment
contracts. The adapters are shared by `ChipProcessor` and
`CashierChipProcessor`:

| Class | Purpose |
|-------|---------|
| `ChipPurchasePayloadBuilder` | Builds the CHIP purchase payload from `PaymentRequest` and `CheckoutSession` |
| `ChipPaymentStatusMapper` | Translates CHIP callback/status values to checkout's `PaymentStatus` enum |
| `ChipRefundGateway` | Handles CHIP refund and void calls |
