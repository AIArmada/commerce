# Clear vs Destroy: Implementation Guide

## Overview

This document clarifies the distinction between `clear()` and `destroy()` methods in the Cart package.

## Key Differences

### `clear()` - Reset Cart Content
**Purpose:** Remove all content from the cart while preserving the cart structure in storage.

**What it does:**
- ✅ Removes all items
- ✅ Removes all conditions
- ✅ Removes all metadata
- ✅ Preserves the cart entity in storage
- ✅ Cart can be refilled immediately after clearing
- ✅ Dispatches `CartCleared` event
- ⚡ Optimized: Clears everything in a single storage operation

**Use case:** An admin wants to manually clear a cart and refill it from scratch while maintaining the same cart identifier and instance.

**Signature:**
```php
public function clear(?string $identifier = null, ?string $instance = null): bool
```

**Example:**
```php
// Clear current cart
$cart->clear();

// Clear specific cart
$cart->clear('user-123', 'default');

// After clearing, cart can be immediately refilled
$cart->add('product-1', 'Product 1', 100.00, 1);
```

---

### `destroy()` - Delete Cart Completely
**Purpose:** Permanently delete the cart from storage.

**What it does:**
- ❌ Completely removes the cart entity from storage
- ❌ Cart no longer exists after destruction
- ❌ Must recreate cart to use it again
- ✅ Dispatches `CartDestroyed` event

**Use case:** Permanently remove a cart when it's no longer needed (e.g., after order completion, session expiration, user logout).

**Signature:**
```php
public function destroy(?string $identifier = null, ?string $instance = null): void
```

**Example:**
```php
// Destroy current cart
$cart->destroy();

// Destroy specific cart
$cart->destroy('user-123', 'wishlist');

// After destroying, cart no longer exists
$cart->exists(); // false
```

---

## Parameter Handling

Both methods accept optional parameters:
- **`$identifier`**: Cart identifier (defaults to current if `null`)
- **`$instance`**: Instance name (defaults to current if `null`)

```php
// Use current identifier and instance
$cart->clear();
$cart->destroy();

// Specify identifier and instance
$cart->clear('user-123', 'default');
$cart->destroy('user-123', 'wishlist');
```

---

## Events

### CartCleared Event
Dispatched when `clear()` is called.

**Properties:**
- `cart`: The Cart instance that was cleared

**Example:**
```php
use AIArmada\Cart\Events\CartCleared;
use Illuminate\Support\Facades\Event;

Event::listen(CartCleared::class, function (CartCleared $event) {
    logger('Cart cleared', [
        'identifier' => $event->cart->getIdentifier(),
        'instance' => $event->cart->instance(),
    ]);
});
```

### CartDestroyed Event
Dispatched when `destroy()` is called.

**Properties:**
- `identifier`: The cart identifier that was destroyed
- `instance`: The cart instance name that was destroyed

**Example:**
```php
use AIArmada\Cart\Events\CartDestroyed;
use Illuminate\Support\Facades\Event;

Event::listen(CartDestroyed::class, function (CartDestroyed $event) {
    logger('Cart destroyed', [
        'identifier' => $event->identifier,
        'instance' => $event->instance,
    ]);
    
    // Clean up related resources
    Cache::forget("cart-analytics-{$event->identifier}");
});
```

---

## Storage Behavior

### Database Storage
With database storage, the distinction is clear:
- `clear()`: Cart record remains with empty items/conditions/metadata
- `destroy()`: Cart record is deleted from the database

### Session Storage
With session storage:
- `clear()`: Puts empty arrays for items/conditions, clears metadata
- `destroy()`: Removes cart keys from session entirely

**Note:** Session storage's `has()` method checks for content presence, so a cleared cart may not "exist" according to `has()` until new content is added. This is a limitation of session storage compared to database storage.

---

## Complete Workflow Example

```php
use AIArmada\Cart\Facades\Cart;

// 1. Add items to cart
Cart::add('product-1', 'Product 1', 100.00, 2);
Cart::addMetadata('notes', 'Customer requested gift wrap');
Cart::addCondition(new CartCondition('TAX', 'Tax', 0.1));

Cart::exists(); // true
Cart::isEmpty(); // false
Cart::getMetadata('notes'); // 'Customer requested gift wrap'

// 2. Clear cart (preserves cart structure)
Cart::clear();

Cart::exists(); // depends on storage (DB: true, Session: false until refilled)
Cart::isEmpty(); // true
Cart::getMetadata('notes'); // null

// 3. Refill cart after clearing
Cart::add('product-2', 'Product 2', 50.00, 1);
Cart::exists(); // true
Cart::isEmpty(); // false

// 4. Destroy cart completely
Cart::destroy();

Cart::exists(); // false
Cart::isEmpty(); // true

// 5. Cart must be recreated (happens automatically on first add)
Cart::add('product-3', 'Product 3', 75.00, 1);
Cart::exists(); // true (new cart created)
```

---

## Testing

Tests are provided to verify the distinction:

**Feature Tests:**
- `tests/src/Cart/Feature/Core/CartInstancesTest.php` - Tests `clear()` vs `destroy()` behavior
- `tests/src/Cart/Feature/Events/CartClearedEventTest.php` - Tests `CartCleared` event
- `tests/src/Cart/Feature/Events/CartDestroyedEventTest.php` - Tests `CartDestroyed` event

**Unit Tests:**
- `tests/src/Cart/Unit/Events/CartLifecycleEventsTest.php` - Tests event data structures

---

## Migration Guide

If you were previously using `clear()` to permanently remove carts, update your code:

```php
// ❌ Old approach (if you wanted permanent deletion)
$cart->clear();

// ✅ New approach (for permanent deletion)
$cart->destroy();

// ✅ Use clear() if you want to reset content but keep cart
$cart->clear();
$cart->add('new-item', 'New Item', 50.00, 1);
```

---

## Summary

| Action | Items | Conditions | Metadata | Cart Entity | Event |
|--------|-------|------------|----------|-------------|-------|
| `clear()` | ❌ Removed | ❌ Removed | ❌ Removed | ✅ Preserved | `CartCleared` |
| `destroy()` | ❌ Deleted | ❌ Deleted | ❌ Deleted | ❌ Deleted | `CartDestroyed` |

**Key Takeaway:**
- Use `clear()` to reset a cart's content while keeping the cart structure
- Use `destroy()` to permanently delete a cart from storage
