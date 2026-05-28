---
title: Package Integrations
---

# Package Integrations

The checkout package integrates seamlessly with other commerce packages when they're installed.

## Inventory Integration

When the `aiarmada/inventory` package is installed, checkout automatically manages stock reservations during the checkout process.

### How It Works

1. **Stock Validation**: Before reserving, the `ReserveInventoryStep` validates that sufficient stock exists for all cart items.

2. **Reservation**: Stock is reserved using the checkout session ID as the reference. Reservations prevent overselling while the customer completes payment.

3. **Commitment**: After successful payment, reservations are committed (converted to actual stock deductions).

4. **Rollback**: If checkout fails or is cancelled, reservations are automatically released.

### Configuration

```php
// config/checkout.php
'integrations' => [
    'inventory' => [
        'enabled' => true,                    // Enable inventory integration
        'validate_stock' => true,              // Validate stock availability
        'reserve_before_payment' => true,      // Reserve before or after payment
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
| `reserve_before_payment` | bool | `true` | Reserve stock before payment (vs after) |
| `release_on_failure` | bool | `true` | Automatically release reservations on failure |
| `reservation_ttl` | int | `900` | Reservation expiration in seconds (15 min) |

### Using Without Inventory Package

When the inventory package isn't installed:

- The `ReserveInventoryStep` automatically skips
- All stock checks return unlimited availability
- No reservations are created
- `EnsureCheckoutOfferProduct` also skips inventory seeding unless inventory integration is enabled **and** the inventory tables are present

This allows checkout to work standalone without inventory management.

### Manual Inventory Integration

For custom inventory systems, you can replace the `InventoryAdapter`:

```php
<?php

namespace App\Checkout\Integrations;

use AIArmada\Checkout\Integrations\InventoryAdapter;

class CustomInventoryAdapter extends InventoryAdapter
{
    public function getAvailableStock(string $productId, ?string $variantId = null): int
    {
        // Your custom inventory lookup
        return YourInventorySystem::getStock($productId, $variantId);
    }

    public function reserve(
        string $productId,
        ?string $variantId,
        int $quantity,
        string $reference,
        int $ttl = 900,
    ): array {
        // Your custom reservation logic
        $reservation = YourInventorySystem::reserve($productId, $quantity, $reference);

        return [
            'id' => $reservation->id,
            'expires_at' => $reservation->expires_at->toIso8601String(),
        ];
    }

    // Implement other methods as needed...
}
```

Register your adapter in a service provider:

```php
use App\Checkout\Integrations\CustomInventoryAdapter;
use AIArmada\Checkout\Integrations\InventoryAdapter;

public function register(): void
{
    $this->app->bind(InventoryAdapter::class, CustomInventoryAdapter::class);
}
```

### Events

The inventory integration respects checkout events:

- **CheckoutCompleted**: Reservations are committed
- **CheckoutCancelled**: Reservations are released
- **CheckoutFailed**: Reservations are released (if `release_on_failure` is true)

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
        'enabled' => true,  // Enable tax calculation
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

When `aiarmada/chip` is installed and `checkout.integrations.chip.enabled` is `true`, checkout listens to CHIP purchase events and forwards them into the same internal payment-callback flow used by `POST /webhooks/checkout`.

This keeps checkout gateway-agnostic while letting CHIP remain the single webhook ingress. The recommended setup is to register only `config('chip.webhooks.route', '/chip/webhooks')` in the CHIP dashboard. Disable this integration if you want checkout to rely on `/webhooks/checkout` for CHIP callbacks instead.

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
use AIArmada\Checkout\Integrations\InventoryAdapter;
use AIArmada\Inventory\Contracts\CheckoutInventoryServiceInterface;

// Check if inventory package is installed
$hasInventory = interface_exists(CheckoutInventoryServiceInterface::class);

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
