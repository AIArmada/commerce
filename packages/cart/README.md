# AIArmada Cart

A modern, production-grade shopping cart engine for Laravel 12 applications.

## Features

- **Database Storage** – Built-in persistence with optimistic locking and owner scoping
- **Multi-Instance Carts** – Support multiple cart buckets per user (cart, wishlist, compare)
- **Precision Calculations** – Money objects via `akaunting/money` for accurate financial math
- **Flexible Conditions** – Discounts, taxes, fees, and shipping with targeted application
- **Payment Gateway Ready** – Implements `CheckoutableInterface` for any payment provider
- **Concurrency Safe** – Optimistic locking prevents race conditions
- **Event-Driven** – Hooks for logging, analytics, and business logic

## Requirements

- PHP 8.4+
- Laravel 12.x

## Installation

```bash
composer require aiarmada/cart
```

Laravel auto-discovers the service provider. To publish configuration:

```bash
php artisan vendor:publish --tag=cart-config
```

For database storage, publish and run migrations:

```bash
php artisan vendor:publish --tag=cart-migrations
php artisan migrate
```

## Quick Start

```php
use AIArmada\Cart\Facades\Cart;

// Add items
Cart::add('laptop-001', 'MacBook Pro 16"', 2499.00, 1, [
    'sku' => 'MBP16-2024',
    'color' => 'Space Gray',
]);

// Get totals
echo Cart::count();           // Total quantity
echo Cart::total()->format(); // "$2,499.00"

// Apply conditions
Cart::addDiscount('promo', '10%');
Cart::addTax('vat', '8%');
Cart::addShipping('standard', '15.00');

// Work with items
$item = Cart::get('laptop-001');
Cart::update('laptop-001', ['quantity' => 2]);
Cart::remove('laptop-001');

// Multiple instances
Cart::instance('wishlist')->add('monitor-001', 'Display', 599.00);
```

## Storage

The package ships with `DatabaseStorage` only. If you need a different backend,
bind your own `StorageInterface` implementation in the service container.

```php
use AIArmada\Cart\Storage\StorageInterface;

$this->app->bind(StorageInterface::class, function ($app): StorageInterface {
    return new App\Cart\Storage\CustomStorage(...);
});
```

## Conditions

Apply discounts, taxes, and fees at different calculation phases:

```php
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\TargetPresets;

// Percentage discount
Cart::addDiscount('summer-sale', '20%');

// Fixed amount
Cart::addDiscount('welcome', '-10.00');

// Custom condition
$condition = new CartCondition(
    name: 'vip-discount',
    type: 'discount',
    target: TargetPresets::cartSubtotal(),
    value: '-15%',
    rules: [fn($cart) => auth()->user()?->isVip()],
);
Cart::addCondition($condition);
```

## Payment Integration

Cart implements `CheckoutableInterface` for seamless payment gateway integration:

```php
use AIArmada\Chip\Gateways\ChipGateway;

$gateway = app(ChipGateway::class);
$cart = app(\AIArmada\Cart\Cart::class);

$payment = $gateway->createPayment($cart, $customer, [
    'success_url' => route('checkout.success'),
    'failure_url' => route('checkout.failed'),
]);

return redirect($payment->getCheckoutUrl());
```

## Guest to User Migration

Automatically migrate guest carts when users log in:

```php
// config/cart.php
'migration' => [
    'auto_migrate_on_login' => true,
    'merge_strategy' => 'add_quantities', // or keep_highest_quantity, keep_user_cart
],
```

## Events

Listen to cart lifecycle events:

- `CartCreated`, `CartCleared`, `CartDestroyed`, `CartMerged`
- `ItemAdded`, `ItemUpdated`, `ItemRemoved`
- `CartConditionAdded`, `CartConditionRemoved`
- `ItemConditionAdded`, `ItemConditionRemoved`
- `MetadataAdded`, `MetadataRemoved`, `MetadataBatchAdded`, `MetadataCleared`

```php
use AIArmada\Cart\Events\ItemAdded;

Event::listen(ItemAdded::class, function ($event) {
    Log::info('Item added', ['item' => $event->cartItem->id]);
});
```

## JSON Column Type

Migrations default to `json` columns. For PostgreSQL with `jsonb`:

```env
COMMERCE_JSON_COLUMN_TYPE=jsonb
# or per-package
CART_JSON_COLUMN_TYPE=jsonb
```

## Documentation

Full documentation is available in the [`docs/`](docs/) directory:

- [Overview](docs/01-overview.md)
- [Installation](docs/02-installation.md)
- [Configuration](docs/03-configuration.md)
- [Usage](docs/04-usage.md)
- [Storage](docs/08-storage.md)

## Development

```bash
composer install
vendor/bin/pest
vendor/bin/pint --dirty
```

## Testing

```bash
vendor/bin/pest tests/src/Cart --parallel
```

## License

MIT License. See [LICENSE](../../LICENSE).
