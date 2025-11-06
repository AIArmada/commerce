# Getting Started

Get AIArmada Cart running in your Laravel 12 application in under 5 minutes. This guide covers installation, configuration, and your first cart operations.

## 📋 Requirements

| Requirement | Version | Notes |
|-------------|---------|-------|
| **PHP** | ^8.4 | Constructor property promotion, enums, attributes |
| **Laravel** | ^12.0 | Requires Laravel 12's streamlined structure |
| **Database** | Any | Optional, only needed for database storage driver |

No additional PHP extensions required beyond Laravel's defaults.

## ⚡ Quick Install

### Step 1: Install via Composer

```bash
composer require aiarmada/cart
```

Laravel automatically discovers the service provider—no manual registration needed.

### Step 2: Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=cart-config
```

This creates `config/cart.php`. You can skip this step if you're happy with the defaults.

### Step 3: Database Setup (Optional)

Only required if using the database storage driver:

```bash
# Publish migrations
php artisan vendor:publish --tag=cart-migrations

# Run migrations
php artisan migrate
```

The migration creates a `carts` table with:
- `identifier` and `instance` columns (compound key)
- `items`, `conditions`, `metadata` JSON columns
- `version` for optimistic locking
- Timestamps for tracking

## 🎉 Your First Cart

Let's verify everything works:

```php
use AIArmada\Cart\Facades\Cart;

// Add an item
Cart::add(
    id: 'laptop-001',
    name: 'MacBook Pro 16"',
    price: 2499.00,
    quantity: 1,
    attributes: [
        'sku' => 'MBP16-2024',
        'color' => 'Space Gray',
        'warranty' => '1 year',
    ]
);

// Check the cart
echo "Items: " . Cart::count() . "\n";
echo "Total: " . Cart::total()->format() . "\n";

// Output:
// Items: 1
// Total: $2,499.00
```

✅ If you see the output, you're ready to go!

## 🏗️ Basic Configuration

### Choose Your Storage Driver

Edit `config/cart.php` (or use environment variables):

```php
// config/cart.php
return [
    'storage' => env('CART_STORAGE', 'session'),
];
```

```bash
# .env
CART_STORAGE=session  # or cache, or database
```

**When to use each:**
- `session` – Single-device carts, quick prototypes
- `cache` – Multi-server deployments, fast access
- `database` – Cross-device carts, analytics, high traffic

See [Storage Drivers](storage.md) for detailed comparison.

### Configure Currency

```php
// config/cart.php
'money' => [
    'default_currency' => 'MYR', // Change to your currency
],
```

All prices will use this currency. See [Money & Currency](money-and-currency.md) for multi-currency strategies.

### Enable Auto-Migration

Automatically migrate guest carts when users log in:

```php
// config/cart.php
'migration' => [
    'auto_migrate_on_login' => true,
    'merge_strategy' => 'add_quantities',
],
```

See [User Migration](identifiers-and-migration.md) for merge strategy options.

## 🚀 Common Operations

### Adding Items

```php
// Single item
Cart::add('product-1', 'T-Shirt', 29.99, 2, ['size' => 'L']);

// Multiple items at once
Cart::add([
    ['id' => 'product-1', 'name' => 'T-Shirt', 'price' => 29.99, 'quantity' => 1],
    ['id' => 'product-2', 'name' => 'Jeans', 'price' => 79.99, 'quantity' => 1],
]);
```

### Updating Items

```php
// Update quantity (absolute)
Cart::update('product-1', [
    'quantity' => ['value' => 3],
]);

// Update quantity (relative)
Cart::update('product-1', [
    'quantity' => 1, // Adds 1 to current quantity
]);

// Update multiple properties
Cart::update('product-1', [
    'price' => 24.99,
    'name' => 'T-Shirt (Sale)',
    'attributes' => ['size' => 'XL'],
]);
```

### Removing Items

```php
// Remove specific item
Cart::remove('product-1');

// Clear entire cart
Cart::clear();

// Check if empty
if (Cart::isEmpty()) {
    echo "Cart is empty";
}
```

### Getting Totals

```php
// Cart total (with conditions applied)
$total = Cart::total();
echo $total->format();      // "$129.60"
echo $total->getAmount();   // 129.60

// Subtotal (before total-level conditions)
$subtotal = Cart::subtotal()->format(); // "$120.00"

// Item count
$quantity = Cart::count();      // Total quantity: 5
$items = Cart::countItems();    // Unique items: 3

// Savings (if discounts applied)
$saved = Cart::savings()->format(); // "$15.00"
```

### Working with Items

```php
// Get specific item
$item = Cart::get('product-1');
echo $item->name;                    // "T-Shirt"
echo $item->quantity;                // 2
echo $item->getSubtotal()->format(); // "$59.98"

// Get all items
$items = Cart::getItems();
foreach ($items as $item) {
    echo "{$item->name}: {$item->getSubtotal()->format()}\n";
}

// Check if item exists
if (Cart::has('product-1')) {
    echo "Item exists";
}
```

### Applying Discounts & Taxes

```php
// Percentage discount
Cart::addDiscount('summer-sale', '20%');

// Fixed discount
Cart::addDiscount('coupon-code', '-10.00');

// Tax
Cart::addTax('vat', '8%');

// Shipping
Cart::addShipping('standard', '5.00', 'standard', [
    'eta' => '3-5 business days',
]);

// Remove shipping
Cart::removeShipping();

// Get applied shipping
$shipping = Cart::getShipping();
```

See [Conditions & Pricing](conditions.md) for advanced condition usage.

## 🎯 Multiple Instances

Use different cart instances for different purposes:

```php
// Shopping cart (default instance)
Cart::add('product-1', 'Laptop', 999.00);

// Wishlist
Cart::instance('wishlist')
    ->add('product-2', 'Monitor', 449.00);

// Quote basket
Cart::instance('quote')
    ->add('product-3', 'Keyboard', 129.00);

// Check counts
Cart::instance('default')->count();   // 1
Cart::instance('wishlist')->count();  // 1
Cart::instance('quote')->count();     // 1

// Switch back to default
Cart::instance('default');
```

Each instance maintains independent state. See [Cart Operations](cart-operations.md) for more on instances.

## 🧪 Verify Installation

Run this test in `artisan tinker` or a feature test:

```php
use AIArmada\Cart\Facades\Cart;

// Clear any existing data
Cart::clear();

// Add test items
Cart::add('test-1', 'Test Product A', 10.00, 2);
Cart::add('test-2', 'Test Product B', 15.00, 1);

// Apply discount
Cart::addDiscount('test-discount', '10%');

// Verify results
assert(Cart::count() === 3, 'Total quantity should be 3');
assert(Cart::countItems() === 2, 'Unique items should be 2');
assert(Cart::subtotal()->getAmount() === 35.00, 'Subtotal should be 35.00');
assert(Cart::total()->getAmount() === 31.50, 'Total should be 31.50');

echo "✅ All tests passed!\n";

// Clean up
Cart::clear();
```

## 🔧 Common Configuration Tweaks

### Increase Item Limits

```php
// config/cart.php
'limits' => [
    'max_items' => 1000,           // Max line items per cart
    'max_item_quantity' => 10000,  // Max quantity per item
    'max_data_size_bytes' => 1048576, // Max total payload (1MB)
],
```

### Disable Events

```php
// config/cart.php
'events' => false, // Disable event dispatching
```

Useful for performance-critical batch operations.

### Configure Database Locking

```php
// config/cart.php
'database' => [
    'lock_for_update' => true, // Enable pessimistic locking
],
```

See [Concurrency Control](concurrency-and-retry.md) for details.

## 📊 Storage Driver Setup

### Using Cache Driver

```php
// config/cart.php
'storage' => 'cache',
'cache' => [
    'prefix' => 'cart',
    'ttl' => 86400, // 24 hours
],
```

```bash
# .env
CACHE_STORE=redis
CART_CACHE_TTL=86400
```

### Using Database Driver

```php
// config/cart.php
'storage' => 'database',
'database' => [
    'table' => 'carts',
    'lock_for_update' => false,
],
```

Remember to run migrations:

```bash
php artisan migrate
```

See [Storage Drivers](storage.md) for detailed setup instructions.

## 🎓 Next Steps

Now that you're set up, continue your journey:

1. **[Architecture Overview](architecture.md)** – Understand how the cart works internally
2. **[Cart Operations](cart-operations.md)** – Master the complete API
3. **[Conditions & Pricing](conditions.md)** – Build complex pricing rules
4. **[Quick Examples](examples.md)** – See common patterns and recipes

## 🆘 Troubleshooting

### Cart Returns Empty After Restart

**Problem:** Using session driver and server restarted.  
**Solution:** Sessions are ephemeral. Use cache or database driver for persistence.

### "Call to undefined log channel"

**Problem:** Metrics logging to undefined channel.  
**Solution:** Configure `CART_METRICS_LOG_CHANNEL` in `.env` or leave unset to use default logger.

### Totals Always Return Zero

**Problem:** Not calling `->getAmount()` or `->format()` on Money object.  
**Solution:** Money objects require method calls to retrieve values:

```php
// ❌ Wrong
$total = Cart::total(); // Returns Money object
echo $total; // Doesn't display correctly

// ✅ Correct
echo Cart::total()->format();   // "$99.00"
echo Cart::total()->getAmount(); // 99.00
```

### Database Conflicts After Deploy

**Problem:** Missing `version` column or indices.  
**Solution:** Run migrations:

```bash
php artisan migrate
```

More solutions in [Troubleshooting](troubleshooting.md).

## ✅ Installation Checklist

- [ ] Composer install completed
- [ ] Configuration published (if customizing)
- [ ] Storage driver chosen and configured
- [ ] Database migrated (if using database driver)
- [ ] Default currency configured
- [ ] Test cart operation verified
- [ ] Environment-specific settings in `.env`

**Ready to build?** Continue to [Cart Operations](cart-operations.md) →

