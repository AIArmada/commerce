# 🔄 Identifiers & Cart Migration

> **Seamlessly transition carts from guest to authenticated users—comprehensive guide to cart identifiers, migration strategies, and edge cases.**

Cart identifiers track who owns which cart. When users transition from browsing as guests to logging in, the cart must migrate gracefully. This guide covers identifier architecture, migration triggers, merge strategies, and troubleshooting.

## 📋 Table of Contents

- [Identifier Architecture](#-identifier-architecture)
- [Guest vs Authenticated Flows](#-guest-vs-authenticated-flows)
- [Migration Triggers](#-migration-triggers)
- [Merge Strategies](#-merge-strategies)
- [Edge Cases](#-edge-cases)
- [Testing Migrations](#-testing-migrations)
- [Debugging Tools](#-debugging-tools)
- [Performance Considerations](#-performance-considerations)
- [Advanced Patterns](#-advanced-patterns)
- [Troubleshooting](#-troubleshooting)

---

## 🏗️ Identifier Architecture

### How Identifiers Work

Identifiers uniquely track cart ownership across sessions:

```
┌─────────────────┐
│  Guest User     │
│  Session: abc   │ → Identifier: "guest:abc"
└─────────────────┘

       ↓ (User logs in)

┌─────────────────┐
│  Auth User      │
│  ID: 123        │ → Identifier: "user:123"
└─────────────────┘
```

The cart internally swaps the identifier from `guest:session` to `user:id` during login.

### Identifier Format

```php
// Guest identifiers (session-based)
"guest:{session_id}"  // e.g., "guest:a1b2c3d4e5f6"

// Authenticated identifiers (user-based)
"user:{user_id}"      // e.g., "user:123"

// Custom identifiers (advanced)
"team:{team_id}"      // e.g., "team:456" (multi-tenant)
"device:{uuid}"       // e.g., "device:abcd-1234" (mobile apps)
```

### Configuration

```php
// config/cart.php
return [
    'identifiers' => [
        'guest_prefix' => 'guest',       // Prefix for guest carts
        'user_prefix' => 'user',         // Prefix for authenticated carts
        'separator' => ':',              // Separator between prefix and ID
    ],
    
    'migration' => [
        'enabled' => true,               // Enable automatic migration
        'strategy' => 'add_quantities',  // Default merge strategy
        'clear_guest_after' => true,     // Clear guest cart after merge
    ],
];
```

---

## 👤 Guest vs Authenticated Flows

### Guest Flow

```php
// Visitor arrives → session starts → cart created
Session::start(); // Laravel handles this automatically

Cart::add('product-1', 'Product 1', Money::MYR(1000), 1);

// Cart stored with identifier: "guest:{session_id}"
```

**Storage Representation:**
```
Identifier: guest:abc123
Instance: default
Items: [
    {id: "product-1", name: "Product 1", price: 1000, quantity: 1}
]
```

### Authenticated Flow

```php
// User logs in
Auth::login($user);

// Cart::setIdentifier() is called automatically via Login event listener
// Identifier changes: "guest:abc123" → "user:123"

Cart::add('product-2', 'Product 2', Money::MYR(2000), 1);

// Cart stored with identifier: "user:123"
```

**Storage Representation:**
```
Identifier: user:123
Instance: default
Items: [
    {id: "product-1", name: "Product 1", price: 1000, quantity: 1}, // Migrated
    {id: "product-2", name: "Product 2", price: 2000, quantity: 1}  // Added after login
]
```

### Flow Diagram

```
┌──────────────────────────────────────────────────────────────┐
│                    Cart Lifecycle                             │
└──────────────────────────────────────────────────────────────┘

   [Guest Arrives]
         │
         ↓
   [Session Created]
         │
         ↓
   [Add Items to Cart]   → Identifier: guest:abc123
         │
         ↓
   [User Logs In] ────────────┐
         │                     │
         ↓                     ↓
   [Trigger Migration]   [Merge Strategy Applied]
         │                     │
         ↓                     ↓
   [Identifier Swapped]  [Guest Cart Merged into User Cart]
         │
         ↓
   [Identifier: user:123]
         │
         ↓
   [Continue Shopping]
```

---

## ⚡ Migration Triggers

### Automatic Migration (Login Event)

The cart automatically migrates when Laravel's `Illuminate\Auth\Events\Login` event fires:

```php
// app/Listeners/MigrateGuestCart.php
namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use AIArmada\Cart\Facades\Cart;

class MigrateGuestCart
{
    public function handle(Login $event): void
    {
        if (!config('cart.migration.enabled')) {
            return;
        }
        
        $guestIdentifier = 'guest:' . session()->getId();
        $userIdentifier = 'user:' . $event->user->id;
        
        // Swap identifier (merges automatically based on strategy)
        Cart::setIdentifier($userIdentifier, $guestIdentifier);
    }
}

// Register listener in EventServiceProvider
protected $listen = [
    Login::class => [
        MigrateGuestCart::class,
    ],
];
```

### Manual Migration

```php
// Manually trigger migration (useful for custom auth flows)
$guestId = 'guest:' . session()->getId();
$userId = 'user:' . auth()->id();

Cart::setIdentifier($userId, $guestId);
```

### Multiple Instances

```php
// Migrate all instances (default, wishlist, saved)
foreach (['default', 'wishlist', 'saved'] as $instance) {
    Cart::setInstance($instance);
    Cart::setIdentifier("user:{$userId}", "guest:{$sessionId}");
}
```

---

## 🔀 Merge Strategies

When a user logs in with items already in their authenticated cart, the package must decide how to merge the guest cart. Four strategies are available:

### 1. Add Quantities (Default)

**Strategy:** `add_quantities`

Merge item quantities when the same product exists in both carts.

```php
// config/cart.php
'migration' => [
    'strategy' => 'add_quantities',
],
```

**Example:**

**Guest Cart (before login):**
```
Product A: quantity 2
Product B: quantity 1
```

**User Cart (existing):**
```
Product A: quantity 3
Product C: quantity 1
```

**Result (after login):**
```
Product A: quantity 5  // 2 + 3 = 5
Product B: quantity 1  // From guest
Product C: quantity 1  // From user
```

**Use Case:** Best for B2C e-commerce where users expect quantities to accumulate.

### 2. Keep Highest Quantity

**Strategy:** `keep_highest_quantity`

Keep the higher quantity when the same product exists in both carts.

```php
'migration' => [
    'strategy' => 'keep_highest_quantity',
],
```

**Example:**

**Guest Cart:**
```
Product A: quantity 2
```

**User Cart:**
```
Product A: quantity 5
```

**Result:**
```
Product A: quantity 5  // Kept higher (5 > 2)
```

**Use Case:** Prevents accidental over-purchasing, useful for limited-stock items.

### 3. Keep User Cart

**Strategy:** `keep_user_cart`

Discard guest cart entirely, keep only the authenticated user's cart.

```php
'migration' => [
    'strategy' => 'keep_user_cart',
],
```

**Example:**

**Guest Cart:**
```
Product A: quantity 2
Product B: quantity 1
```

**User Cart:**
```
Product C: quantity 3
```

**Result:**
```
Product C: quantity 3  // Guest cart discarded
```

**Use Case:** B2B scenarios where the user's saved cart is authoritative.

### 4. Replace with Guest Cart

**Strategy:** `replace_with_guest`

Replace user's existing cart with the guest cart.

```php
'migration' => [
    'strategy' => 'replace_with_guest',
],
```

**Example:**

**Guest Cart:**
```
Product A: quantity 2
```

**User Cart:**
```
Product C: quantity 3
```

**Result:**
```
Product A: quantity 2  // User cart discarded
```

**Use Case:** When the guest session is more recent/important (e.g., "Continue where you left off").

### Implementing Custom Strategies

```php
// app/Services/CustomCartMigration.php
namespace App\Services;

use AIArmada\Cart\CartItem;
use AIArmada\Cart\Collections\CartCollection;

class CustomCartMigration
{
    public function merge(CartCollection $guestItems, CartCollection $userItems): CartCollection
    {
        // Custom logic: prioritize guest items but limit quantity to 10
        $merged = $userItems->keyBy('id');
        
        foreach ($guestItems as $guestItem) {
            if ($merged->has($guestItem->id)) {
                $userItem = $merged->get($guestItem->id);
                $newQuantity = min($userItem->quantity + $guestItem->quantity, 10);
                $merged->put($guestItem->id, new CartItem(
                    $guestItem->id,
                    $guestItem->name,
                    $guestItem->price,
                    $newQuantity,
                    $guestItem->attributes
                ));
            } else {
                $merged->put($guestItem->id, $guestItem);
            }
        }
        
        return $merged;
    }
}

// Use custom strategy
$guestItems = Cart::setIdentifier($guestId)->all();
$userItems = Cart::setIdentifier($userId)->all();

$merged = app(CustomCartMigration::class)->merge($guestItems, $userItems);

Cart::setIdentifier($userId)->clear();
foreach ($merged as $item) {
    Cart::add($item->id, $item->name, $item->price, $item->quantity, $item->attributes);
}
```

---

## 🚨 Edge Cases

### 1. Multiple Devices (Same User)

**Problem:** User adds items on mobile, then logs in on desktop.

**Solution:** Database storage driver + `add_quantities` strategy.

```php
// Mobile session (guest cart)
Cart::setIdentifier('guest:mobile123')->add('A', 'Product A', Money::MYR(1000), 2);

// Desktop login (merge carts)
Cart::setIdentifier('user:123', 'guest:mobile123'); // Merges mobile cart into user:123

// User continues on desktop
Cart::add('B', 'Product B', Money::MYR(2000), 1);

// Later, user opens mobile app again → same user:123 identifier → sees combined cart
```

### 2. Logout/Login Cycles

**Problem:** User logs out, adds items as guest, then logs back in.

**Solution:** Migration happens again, merging new guest cart.

```php
// User logs out
Auth::logout();
// Identifier becomes: guest:{new_session_id}

Cart::add('C', 'Product C', Money::MYR(500), 1);

// User logs back in
Auth::login($user); // Triggers migration again
// Guest cart (with Product C) merges into user:123
```

### 3. Expired Sessions

**Problem:** Guest session expires before login.

**Solution:** Session driver auto-clears; database/cache drivers persist longer.

```php
// Guest adds items (session driver)
Cart::add('D', 'Product D', Money::MYR(1000), 1);

// 2 hours later → session expired → guest cart lost

// User logs in → no guest cart to migrate (fresh start)
```

**Mitigation:** Use database/cache drivers for longer persistence:

```php
// config/cart.php
'storage' => [
    'driver' => 'database', // Persist beyond session expiry
],

'cache' => [
    'ttl' => 604800, // 7 days
],
```

### 4. Concurrent Logins (Race Condition)

**Problem:** User logs in on two devices simultaneously.

**Solution:** Optimistic locking in database driver prevents conflicts.

```php
// Device 1: Login triggers migration
Cart::setIdentifier('user:123', 'guest:abc');

// Device 2: Login triggers migration (0.1s later)
Cart::setIdentifier('user:123', 'guest:def'); // CartConflictException if database driver

// Retry with exponential backoff
retry(3, function () use ($userId, $guestId) {
    Cart::setIdentifier($userId, $guestId);
}, 100);
```

### 5. Same Product, Different Attributes

**Problem:** Guest cart has "T-Shirt (Red, M)", user cart has "T-Shirt (Blue, L)".

**Solution:** Items are distinct (different `id`), both preserved.

```php
// Guest cart
Cart::add('tshirt-red-m', 'T-Shirt', Money::MYR(3000), 1, ['color' => 'Red', 'size' => 'M']);

// User cart
Cart::add('tshirt-blue-l', 'T-Shirt', Money::MYR(3000), 1, ['color' => 'Blue', 'size' => 'L']);

// After login → both items in cart (distinct IDs)
```

**Tip:** Ensure product IDs include variant info (e.g., `product-{id}-{variant}`).

---

## 🧪 Testing Migrations

### Basic Migration Test

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

it('migrates guest cart to user cart on login', function () {
    // Guest adds items
    Cart::add('product-1', 'Product 1', Money::MYR(1000), 2);
    
    expect(Cart::countItems())->toBe(2);
    
    // User logs in
    $user = User::factory()->create();
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);
    
    // Cart migrated to user
    Cart::setIdentifier("user:{$user->id}");
    expect(Cart::countItems())->toBe(2);
    expect(Cart::get('product-1')->quantity)->toBe(2);
});
```

### Test Merge Strategy: Add Quantities

```php
it('adds quantities when merging guest and user carts', function () {
    config(['cart.migration.strategy' => 'add_quantities']);
    
    $user = User::factory()->create();
    
    // User cart (existing)
    Cart::setIdentifier("user:{$user->id}");
    Cart::add('product-1', 'Product 1', Money::MYR(1000), 3);
    
    // Guest cart (new session)
    $guestId = 'guest:abc123';
    Cart::setIdentifier($guestId);
    Cart::add('product-1', 'Product 1', Money::MYR(1000), 2);
    
    // Login triggers migration
    Cart::setIdentifier("user:{$user->id}", $guestId);
    
    // Quantity should be 3 + 2 = 5
    expect(Cart::get('product-1')->quantity)->toBe(5);
});
```

### Test Merge Strategy: Keep Highest Quantity

```php
it('keeps highest quantity when merging', function () {
    config(['cart.migration.strategy' => 'keep_highest_quantity']);
    
    $user = User::factory()->create();
    
    Cart::setIdentifier("user:{$user->id}");
    Cart::add('product-1', 'Product 1', Money::MYR(1000), 5);
    
    $guestId = 'guest:xyz789';
    Cart::setIdentifier($guestId);
    Cart::add('product-1', 'Product 1', Money::MYR(1000), 2);
    
    Cart::setIdentifier("user:{$user->id}", $guestId);
    
    expect(Cart::get('product-1')->quantity)->toBe(5); // Kept higher
});
```

### Test Multiple Instances

```php
it('migrates all cart instances on login', function () {
    $user = User::factory()->create();
    $guestId = 'guest:multi123';
    
    // Guest adds to default cart
    Cart::setIdentifier($guestId)->setInstance('default');
    Cart::add('A', 'Product A', Money::MYR(1000), 1);
    
    // Guest adds to wishlist
    Cart::setInstance('wishlist');
    Cart::add('B', 'Product B', Money::MYR(2000), 1);
    
    // Migrate both instances
    foreach (['default', 'wishlist'] as $instance) {
        Cart::setInstance($instance);
        Cart::setIdentifier("user:{$user->id}", $guestId);
    }
    
    // Verify both migrated
    Cart::setIdentifier("user:{$user->id}");
    Cart::setInstance('default');
    expect(Cart::has('A'))->toBeTrue();
    
    Cart::setInstance('wishlist');
    expect(Cart::has('B'))->toBeTrue();
});
```

---

## 🔧 Debugging Tools

### Log Migrations

```php
// app/Listeners/MigrateGuestCart.php
public function handle(Login $event): void
{
    $guestId = 'guest:' . session()->getId();
    $userId = 'user:' . $event->user->id;
    
    Log::info('Cart migration started', [
        'from' => $guestId,
        'to' => $userId,
        'strategy' => config('cart.migration.strategy'),
    ]);
    
    Cart::setIdentifier($userId, $guestId);
    
    Log::info('Cart migration completed', [
        'user_id' => $event->user->id,
        'item_count' => Cart::countItems(),
        'total' => Cart::total()->getAmount(),
    ]);
}
```

### Inspect Identifiers

```php
// Artisan command to inspect cart by identifier
php artisan cart:inspect guest:abc123

// Output:
// Identifier: guest:abc123
// Instance: default
// Items: 3
// Total: MYR 45.00
// Metadata: {"promo_code": "SAVE10"}
```

### Dump Cart State

```php
// Debug helper
Cart::setIdentifier('user:123');
dd([
    'identifier' => Cart::getIdentifier(),
    'instance' => Cart::getInstance(),
    'items' => Cart::all()->toArray(),
    'total' => Cart::total()->format(),
]);
```

---

## ⚡ Performance Considerations

### Lazy Loading

```php
// ❌ BAD: Loads cart immediately
$cart = Cart::setIdentifier("user:{$userId}");
$cart->all(); // Triggers DB/cache read

// ✅ GOOD: Only load when needed
$cart = Cart::setIdentifier("user:{$userId}");
if ($user->wants_to_checkout) {
    $items = $cart->all();
}
```

### Batch Migrations

```php
// Migrate multiple users in a job (e.g., after importing users)
Queue::bulk(
    User::whereNull('cart_migrated_at')->chunk(100)->map(function ($users) {
        return new MigrateUserCartsJob($users);
    })
);
```

### Cache Warming

```php
// Pre-load user cart on login (before redirect)
public function handle(Login $event): void
{
    Cart::setIdentifier("user:{$event->user->id}");
    
    // Warm cache
    Cache::remember("cart:user:{$event->user->id}", 3600, function () {
        return Cart::all()->toArray();
    });
}
```

---

## 🎯 Advanced Patterns

### Loading Carts by UUID

When integrating with payment systems, orders, or webhooks, you often need to load a cart by its database UUID:

```php
// In payment callback handler
public function handlePaymentCallback(Request $request)
{
    $cartUuid = $request->input('cart_id');
    
    // Load cart by UUID
    $cart = Cart::getById($cartUuid);
    
    if (!$cart) {
        throw new Exception('Cart not found');
    }
    
    // Process the cart
    $total = $cart->total();
    $items = $cart->getItems();
    
    // Create order from cart
    $order = Order::createFromCart($cart);
}
```

**Common Use Cases:**

```php
// 1. Payment Gateway Integration
$payment = Payment::create([
    'cart_id' => Cart::getId(), // Store UUID
    'amount' => Cart::total()->getAmount(),
]);

// Later, when webhook arrives...
$cart = Cart::getById($payment->cart_id);

// 2. Order Processing
$order->cart_id = Cart::getId();
$order->save();

// Retrieve cart when processing
$cart = Cart::getById($order->cart_id);

// 3. Abandoned Cart Recovery
$abandonedCarts = DB::table('carts')
    ->where('updated_at', '<', now()->subHours(24))
    ->get();

foreach ($abandonedCarts as $snapshot) {
    $cart = Cart::getById($snapshot->id);
    if ($cart && $cart->count() > 0) {
        Mail::to($snapshot->user_email)
            ->send(new AbandonedCartEmail($cart));
    }
}
```

### Multi-Tenant Migration

```php
// Migrate cart to team (B2B SaaS)
$user = auth()->user();
$team = $user->currentTeam;

Cart::setIdentifier("team:{$team->id}", "user:{$user->id}");
```

### Conditional Migration

```php
// Only migrate if user has fewer than 5 items
public function handle(Login $event): void
{
    $userId = "user:{$event->user->id}";
    
    Cart::setIdentifier($userId);
    
    if (Cart::countItems() < 5) {
        $guestId = 'guest:' . session()->getId();
        Cart::setIdentifier($userId, $guestId);
    } else {
        Log::info('Skipped migration: user cart full');
    }
}
```

### Migration with Notifications

```php
// Notify user of merged items
public function handle(Login $event): void
{
    $guestId = 'guest:' . session()->getId();
    $userId = 'user:' . $event->user->id;
    
    $guestItems = Cart::setIdentifier($guestId)->all();
    
    if ($guestItems->isNotEmpty()) {
        Cart::setIdentifier($userId, $guestId);
        
        $event->user->notify(new CartMergedNotification($guestItems->count()));
    }
}
```

---

## 🐛 Troubleshooting

### Issue 1: Cart Not Migrating

**Symptoms:** Items disappear after login.

**Solutions:**

1. **Check listener registered:**
```php
// EventServiceProvider.php
protected $listen = [
    Login::class => [
        MigrateGuestCart::class,
    ],
];

// Then:
php artisan event:cache
```

2. **Verify migration enabled:**
```php
// config/cart.php
'migration' => [
    'enabled' => true,
],
```

3. **Check session ID:**
```php
Log::info('Session ID on login', ['session_id' => session()->getId()]);
```

### Issue 2: Duplicate Items After Login

**Symptoms:** Same product appears twice.

**Solutions:**

1. **Use consistent product IDs:**
```php
// ❌ BAD: Different IDs for same product
Cart::add('prod-123', ...); // Guest
Cart::add('product-123', ...); // User (won't merge!)

// ✅ GOOD: Same ID
Cart::add('product-123', ...); // Both use this
```

2. **Check merge strategy:**
```php
config(['cart.migration.strategy' => 'add_quantities']);
```

### Issue 3: Lost Items After Session Expiry

**Symptoms:** Guest cart empty after 2 hours.

**Solutions:**

1. **Use database/cache driver:**
```php
// config/cart.php
'storage' => [
    'driver' => 'database', // Not 'session'
],
```

2. **Increase cache TTL:**
```php
'cache' => [
    'ttl' => 604800, // 7 days
],
```

### Issue 4: Race Conditions on Concurrent Logins

**Symptoms:** `CartConflictException` thrown.

**Solutions:**

1. **Retry with backoff:**
```php
retry(3, function () use ($userId, $guestId) {
    Cart::setIdentifier($userId, $guestId);
}, 100);
```

2. **Use pessimistic locking:**
```php
// config/cart.php
'database' => [
    'locking' => 'pessimistic',
],
```

---

## 📚 Related Documentation

- **[Storage Drivers](storage.md)** – Choosing the right driver for migrations
- **[Events](events.md)** – Listen to migration events
- **[Testing](testing.md)** – Testing migration scenarios
- **[Concurrency & Retry](concurrency-and-retry.md)** – Handling race conditions

---

**Next Steps:**
- [Configure merge strategy](#merge-strategies)
- [Test migration flows](#testing-migrations)
- [Handle edge cases](#edge-cases)
