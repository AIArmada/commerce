# AIArmada Checkout Package

Unified checkout flow for the AIArmada Commerce ecosystem, integrating cart management, order creation, payment processing, and fulfillment.

## Installation

```bash
composer require aiarmada/checkout
```

## Quick Start

```php
use AIArmada\Checkout\Facades\Checkout;

// Start checkout from a cart
$session = Checkout::startCheckout($cartId);

// Process the entire flow
$result = Checkout::processCheckout($session);

if ($result->success) {
    return redirect()->route('orders.show', $result->orderId);
}

if ($result->requiresRedirect()) {
    return redirect($result->redirectUrl);
}

return back()->withErrors($result->errors);
```

### Publishing Views

If you opt to use the built-in views (`response_mode` => `'view'`), you can publish them for customization:

```bash
php artisan vendor:publish --tag=checkout-views
```

## Features

- **Unified Checkout Flow**: Orchestrates cart → order → payment → fulfillment
- **Step-Based Architecture**: Modular, pluggable steps with dependency resolution
- **Flexible Response Modes**: Choose between 'redirect' or built-in 'view' response modes for payment callbacks.
- **Built-in Views**: Optional, customizable Tailwind CSS Blade views for success, failure, and cancellation pages.
- **Multiple Payment Gateways**: Chip, Cashier-Chip, Cashier processors
- **Multi-tenancy Support**: Full owner-scoping via `HasOwner` trait
- **Inventory Integration**: Optional stock reservation during checkout
- **Tax & Discount Integration**: Automatic calculations
- **Session Management**: Resume interrupted checkouts
- **Event-Driven**: Comprehensive event dispatching

## Requirements

- PHP 8.4+
- Laravel 11.0+
- At least one payment gateway package (`chip`, `cashier-chip`, or `cashier`)

## Documentation

See the [docs/](docs/) directory for comprehensive documentation:

- [Overview](docs/01-overview.md)
- [Installation](docs/02-installation.md)
- [Configuration](docs/03-configuration.md)
- [Usage](docs/04-usage.md)
- [Checkout Steps](docs/05-checkout-steps.md)
- [Payment Gateways](docs/06-payment-gateways.md)
- [Troubleshooting](docs/99-troubleshooting.md)

## License

MIT License. See [LICENSE](LICENSE) for details.
