# Identifiers & Migration

Managing cart identifiers for guest and authenticated users, plus cart migration strategies.

## Default Identifiers

### Session-Based (Guests)

```php
// Automatic session ID
$identifier = Cart::getIdentifier(); // "sess_abc123..."
```

### Authenticated Users

```php
// After login, bind to user
Cart::setIdentifier("user-{$user->id}");
```

## Guest to User Migration

When users log in, migrate their guest cart:

### Basic Migration

```php
// LoginController or Listener
public function authenticated(Request $request, User $user): void
{
    $guestIdentifier = session()->getId();
    $userIdentifier = "user-{$user->id}";
    
    Cart::migrate($guestIdentifier, $userIdentifier);
}
```

### Auth Event Listener

```php
// EventServiceProvider
protected $listen = [
    \Illuminate\Auth\Events\Login::class => [
        \App\Listeners\MigrateGuestCart::class,
    ],
];

// Listener
class MigrateGuestCart
{
    public function handle(Login $event): void
    {
        $guestId = session()->getId();
        $userId = "user-{$event->user->id}";
        
        if (Cart::exists($guestId)) {
            Cart::migrate($guestId, $userId);
        }
    }
}
```

## Migration Strategies

### Replace (Default)

Overwrites user cart with guest cart:

```php
Cart::migrate($guestId, $userId, 'replace');
```

### Merge

Combines items, summing quantities:

```php
Cart::migrate($guestId, $userId, 'merge');

// Guest: 2x SKU-123
// User:  1x SKU-123
// Result: 3x SKU-123
```

### Keep

Keeps user cart, discards guest cart:

```php
Cart::migrate($guestId, $userId, 'keep');
```

## Migration with Conflict Resolution

```php
Cart::migrate($guestId, $userId, 'merge', function ($guestItem, $userItem) {
    // Custom merge logic
    return [
        'quantity' => max($guestItem->getQuantity(), $userItem->getQuantity()),
        'metadata' => array_merge(
            $userItem->getMetadata(),
            $guestItem->getMetadata()
        ),
    ];
});
```

## Multiple Cart Instances

Migrate specific instances:

```php
// Migrate wishlist
Cart::instance('wishlist')->migrate($guestId, $userId, 'merge');

// Migrate saved for later
Cart::instance('saved')->migrate($guestId, $userId, 'merge');
```

## Identifier Patterns

### By User Type

```php
// Customer
Cart::setIdentifier("customer-{$customer->id}");

// Admin (separate cart)
Cart::setIdentifier("admin-{$admin->id}");

// Guest
Cart::setIdentifier("guest-" . session()->getId());
```

### By Session Context

```php
// POS terminal
Cart::setIdentifier("pos-terminal-{$terminalId}");

// Phone order
Cart::setIdentifier("phone-order-{$operatorId}-{$timestamp}");
```

### Multi-tenant

```php
Cart::setIdentifier("tenant-{$tenantId}-user-{$userId}");
```

## Persistence Across Devices

With database storage, users access same cart on any device:

```php
// config/cart.php
'storage' => [
    'driver' => 'database',
],

// User logs in anywhere
Cart::setIdentifier("user-{$user->id}");
// Same cart content everywhere
```

## Cart Recovery

### By Email (Pre-purchase)

```php
// Store email with guest cart
Cart::setMetadata('email', $request->email);
Cart::setMetadata('guest_id', session()->getId());

// Later, recover by email
$guestId = CartMetadata::where('email', $email)->value('identifier');
if ($guestId) {
    Cart::setIdentifier($guestId);
}
```

### Abandoned Cart Link

```php
// Generate recovery link
$token = Str::random(32);
Cart::setMetadata('recovery_token', $token);

$recoveryUrl = route('cart.recover', ['token' => $token]);

// Recovery route
Route::get('/cart/recover/{token}', function ($token) {
    $identifier = CartMetadata::where('recovery_token', $token)
        ->value('identifier');
    
    if ($identifier) {
        Cart::setIdentifier($identifier);
        return redirect('/cart');
    }
    
    return abort(404);
});
```

## Middleware for Cart Binding

```php
class BindCartIdentifier
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            Cart::setIdentifier("user-" . auth()->id());
        } else {
            Cart::setIdentifier("guest-" . session()->getId());
        }
        
        return $next($request);
    }
}
```

## Testing

```php
it('migrates guest cart to user on login', function () {
    $guestId = 'guest-123';
    $userId = 'user-1';
    
    Cart::setIdentifier($guestId);
    Cart::add('sku-1', 'Product', Money::MYR(1000), 2);
    
    Cart::migrate($guestId, $userId);
    
    Cart::setIdentifier($userId);
    expect(Cart::count())->toBe(2);
    
    Cart::setIdentifier($guestId);
    expect(Cart::count())->toBe(0);
});

it('merges carts on migration', function () {
    Cart::setIdentifier('guest-1');
    Cart::add('sku-1', 'Product', Money::MYR(1000), 2);
    
    Cart::setIdentifier('user-1');
    Cart::add('sku-1', 'Product', Money::MYR(1000), 3);
    
    Cart::migrate('guest-1', 'user-1', 'merge');
    
    expect(Cart::get('sku-1')->getQuantity())->toBe(5);
});
```

## Next Steps

- [Storage](storage.md) – Storage driver selection
- [Concurrency](concurrency.md) – Conflict resolution
