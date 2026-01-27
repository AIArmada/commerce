---
title: Overview
---

# Checkout Package

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
