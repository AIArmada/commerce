# Getting Started

Get AIArmada Cart running in your Laravel 12 application.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ^8.4 |
| Laravel | ^12.0 |

## Installation

### 1. Install via Composer

```bash
composer require aiarmada/cart
```

### 2. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=cart-config
```

Creates `config/cart.php` for customization.

### 3. Database Setup (For Database Driver)

```bash
php artisan vendor:publish --tag=cart-migrations
php artisan migrate
```

## First Cart Operation

```php
use AIArmada\Cart\Facades\Cart;

// Add an item
Cart::add(
    id: 'laptop-001',
    name: 'MacBook Pro 16"',
    price: 2499.00,
    quantity: 1,
    attributes: ['color' => 'Space Gray']
);

// Check totals
echo Cart::count();            // 1
echo Cart::total()->format();  // "$2,499.00"
```

## Configuration

### Storage Driver

```php
// config/cart.php
'storage' => env('CART_STORAGE', 'session'),
```

Options: `session`, `cache`, `database`

### Default Currency

```php
'money' => [
    'default_currency' => 'MYR',
],
```

### Auto-Migration on Login

```php
'migration' => [
    'auto_migrate_on_login' => true,
    'merge_strategy' => 'add_quantities',
],
```

## Common Operations

### Adding Items

```php
// Single item
Cart::add('product-1', 'T-Shirt', 29.99, 2, ['size' => 'L']);

// Multiple items
Cart::add([
    ['id' => 'product-1', 'name' => 'T-Shirt', 'price' => 29.99, 'quantity' => 1],
    ['id' => 'product-2', 'name' => 'Jeans', 'price' => 79.99, 'quantity' => 1],
]);
```

### Updating Items

```php
Cart::update('product-1', ['quantity' => ['value' => 3]]);
Cart::update('product-1', ['price' => 24.99]);
```

### Removing Items

```php
Cart::remove('product-1');
Cart::clear();
```

### Totals

```php
$total = Cart::total();
echo $total->format();     // "$129.60"
echo $total->getAmount();  // 12960 (cents)

$subtotal = Cart::subtotal();
$savings = Cart::savings();
```

### Conditions

```php
Cart::addDiscount('promo', '15%');
Cart::addTax('vat', '8%');
Cart::addShipping('standard', '10.00');
```

### Multiple Instances

```php
Cart::instance('wishlist')->add('product-2', 'Monitor', 449.00);
Cart::instance('default')->count();
```

## Verification

Test in artisan tinker:

```php
use AIArmada\Cart\Facades\Cart;

Cart::clear();
Cart::add('test-1', 'Test Product', 10.00, 2);
Cart::addDiscount('test', '10%');

assert(Cart::count() === 2);
assert(Cart::total()->getAmount() === 1800); // $18.00

Cart::clear();
echo "✅ Cart working!";
```

## Next Steps

- [Cart Operations](cart-operations.md) – Complete API guide
- [Conditions](conditions.md) – Discounts, taxes, shipping
- [Storage Drivers](storage.md) – Choose the right backend
- [Configuration](configuration.md) – All options explained
