---
title: Overview
---

# Checkout Package

## Purpose

The `aiarmada/checkout` package orchestrates the checkout journey across cart, pricing, customers, shipping, payment, document generation, and order creation.

## What this package owns

- Checkout sessions and step orchestration
- Checkout state transitions and step registry execution
- Payment-gateway resolution and redirect or confirmation handoff
- The coordinated flow that validates a cart, calculates totals, creates an order, and completes checkout

## What this package does not own

- Cart persistence (`aiarmada/cart`)
- Order domain storage and lifecycle (`aiarmada/orders`)
- Shipping, pricing, customer, product, or document domain rules, even though checkout consumes them
- Gateway-specific payment implementations (`aiarmada/chip`, `aiarmada/cashier`, `aiarmada/cashier-chip`)

## Related packages

- [`aiarmada/cart`](../../cart/docs/01-overview.md) — source cart state
- [`aiarmada/orders`](../../orders/docs/01-overview.md) — order creation and lifecycle
- [`aiarmada/customers`](../../customers/docs/01-overview.md), [`aiarmada/products`](../../products/docs/01-overview.md), [`aiarmada/pricing`](../../pricing/docs/01-overview.md), [`aiarmada/shipping`](../../shipping/docs/01-overview.md), and [`aiarmada/docs`](../../docs/docs/01-overview.md) — required domain collaborators
- [`aiarmada/chip`](../../chip/docs/01-overview.md), [`aiarmada/cashier`](../../cashier/docs/01-overview.md), [`aiarmada/cashier-chip`](../../cashier-chip/docs/01-overview.md) — payment integrations
- [`aiarmada/tax`](../../tax/docs/01-overview.md), [`aiarmada/promotions`](../../promotions/docs/01-overview.md), [`aiarmada/vouchers`](../../vouchers/docs/01-overview.md), [`aiarmada/inventory`](../../inventory/docs/01-overview.md), [`aiarmada/jnt`](../../jnt/docs/01-overview.md) — optional integrations

## Main models services or surfaces

- **Core surfaces** — checkout sessions, checkout steps, payment gateway resolver, and orchestration service layer
- **Integration seams** — payment, pricing, tax, inventory, shipping, and voucher hooks invoked during checkout processing
- **Lifecycle** — start, validate, calculate, pay, create order, reserve stock, complete

## Owner scoping and security notes

- Checkout sessions are owner-aware and should follow `commerce-support` owner-boundary rules
- Payment, customer, shipping, and order identifiers must be validated inside the current owner scope rather than trusting filtered UI or client-submitted state
- Webhook or post-payment callbacks should re-enter the correct owner context before mutating checkout sessions or orders

The Checkout package provides a unified checkout flow for the AIArmada Commerce ecosystem, integrating cart management, order creation, payment processing, and fulfillment into a seamless checkout experience.

## Features

- **Unified Checkout Flow**: Orchestrates the entire checkout process from cart to completed order
- **Step-Based Architecture**: Modular, pluggable steps with dependency resolution
- **State Machine**: Robust checkout status management using Spatie Model States
- **Multiple Payment Gateways**: Supports Chip, Cashier-Chip, and Cashier payment processors
- **Multi-tenancy Support**: Full owner-scoping via the `HasOwner` trait
- **Inventory Integration**: Optional inventory reservation during checkout
- **Tax & Discount Integration**: Automatic tax calculation and discount application
- **Session Management**: Resume interrupted checkouts with session persistence
- **Event-Driven**: Comprehensive event dispatching for extensibility

## How It Works

The checkout process follows these high-level steps:

1. **Start Checkout**: Create a checkout session from a cart
2. **Validate Cart**: Ensure items are valid and in stock
3. **Calculate Pricing**: Apply pricing rules and discounts
4. **Calculate Tax**: Compute applicable taxes
5. **Process Payment**: Collect payment via configured gateway
6. **Create Order**: Generate the order record
7. **Reserve Inventory**: Decrement stock (if enabled)
8. **Complete**: Dispatch post-checkout events and notifications

## Package Dependencies

The Checkout package integrates with these ecosystem packages:

| Package | Required | Purpose |
|---------|----------|---------|
| `commerce-support` | Yes | Owner-scoping, shared utilities |
| `cart` | Yes | Cart management |
| `orders` | Yes | Order creation |
| `chip` | One of | Payment processing |
| `cashier-chip` | One of | Payment processing |
| `cashier` | One of | Payment processing |
| `inventory` | No | Stock reservation |
| `tax` | No | Tax calculation |
| `promotions` | No | Discount application |
| `vouchers` | No | Voucher redemption |

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    CheckoutService                          │
│  ┌────────────────────────────────────────────────────────┐ │
│  │              CheckoutStepRegistry                      │ │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │ │
│  │  │ Validate │→│ Pricing  │→│ Payment  │→│  Order   │  │ │
│  │  │   Cart   │ │ Calc     │ │ Process  │ │ Create   │  │ │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘  │ │
│  └────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────┐ │
│  │           PaymentGatewayResolver                       │ │
│  │  ┌─────────┐  ┌─────────────┐  ┌──────┐               │ │
│  │  │ Cashier │  │ Cashier-Chip│  │ Chip │               │ │
│  │  └─────────┘  └─────────────┘  └──────┘               │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

## Quick Example

```php
use AIArmada\Checkout\Facades\Checkout;

// Start checkout from an existing cart
$session = Checkout::startCheckout($cartId, $customerId);

// Process the entire checkout flow
$result = Checkout::processCheckout($session);

if ($result->success) {
    // Redirect to order confirmation
    return redirect()->route('orders.show', $result->orderId);
}

if ($result->requiresRedirect()) {
    // Payment gateway requires customer interaction
    return redirect($result->redirectUrl);
}

// Handle failure
return back()->withErrors($result->errors);
```

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Checkout steps](05-checkout-steps.md)
- [Payment gateways](06-payment-gateways.md)
- [Payment flow](07-payment-flow.md)
- [Integrations](08-integrations.md)
- [Troubleshooting](99-troubleshooting.md)
