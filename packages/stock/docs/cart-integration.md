# Cart Integration

When installed alongside `aiarmada/cart`, the stock package provides seamless integration for managing inventory during the shopping and checkout process.

## Automatic Integration

The integration is automatic when both packages are installed. No additional setup required.

What happens automatically:
1. `CartManager` is extended with `CartManagerWithStock`
2. Cart events trigger stock reservation releases
3. Payment success events trigger stock deductions

## CartManagerWithStock

The cart manager gains additional stock-aware methods:

### Reserve All Stock

Reserve stock for all cart items before checkout:

```php
use AIArmada\Cart\Facades\Cart;

// Reserve for 30 minutes (default)
$results = Cart::reserveAllStock();

// Reserve for custom duration
$results = Cart::reserveAllStock(60); // 60 minutes

// Results: ['item-id-1' => true, 'item-id-2' => false]
```

Returns an array mapping item IDs to success status.

### Release All Stock

Release all reservations for the current cart:

```php
Cart::releaseAllStock();
```

Called automatically when:
- Cart is cleared (`CartCleared` event)
- Cart is destroyed (`CartDestroyed` event)

### Commit Stock

Convert reservations to actual stock deductions after payment:

```php
// With order reference
Cart::commitStock('order-123');

// Without reference
Cart::commitStock();
```

### Validate Stock

Check if all cart items have sufficient stock:

```php
$validation = Cart::validateStock();

if ($validation['available']) {
    // All items have sufficient stock
} else {
    // Handle issues
    foreach ($validation['issues'] as $itemId => $issue) {
        echo "{$issue['name']}: ";
        echo "requested {$issue['requested']}, ";
        echo "only {$issue['available']} available";
    }
}
```

## Product Model Setup

Your product should implement both cart and stock interfaces:

```php
use AIArmada\Cart\Contracts\BuyableInterface;
use AIArmada\Cart\Concerns\Buyable;
use AIArmada\Stock\Contracts\StockableInterface;
use AIArmada\Stock\Traits\HasStock;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements BuyableInterface, StockableInterface
{
    use Buyable, HasStock;
    
    public function getBuyableStock(): ?int
    {
        // Return available stock (minus reservations)
        return $this->getAvailableStock();
    }
    
    public function canBePurchased(?int $quantity = null): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        return $quantity === null || $this->hasAvailableStock($quantity);
    }
}
```

## Checkout Flow

Recommended checkout implementation:

```php
class CheckoutController
{
    public function showCheckout()
    {
        // 1. Validate stock availability
        $validation = Cart::validateStock();
        
        if (!$validation['available']) {
            return back()->withErrors([
                'stock' => 'Some items are no longer available',
                'issues' => $validation['issues'],
            ]);
        }
        
        // 2. Reserve stock for checkout
        $reserved = Cart::reserveAllStock(30);
        
        $failed = array_filter($reserved, fn($success) => !$success);
        if (!empty($failed)) {
            return back()->withErrors([
                'stock' => 'Could not reserve all items'
            ]);
        }
        
        return view('checkout.payment');
    }
    
    public function processPayment(Request $request)
    {
        // Process payment...
        $payment = PaymentGateway::charge($amount);
        
        if ($payment->successful()) {
            // Create order
            $order = Order::create([...]);
            
            // Commit stock (or let PaymentSucceeded event handle it)
            Cart::commitStock($order->id);
            
            return redirect()->route('order.success', $order);
        }
        
        // Payment failed - reservations expire automatically
        // Or release immediately:
        Cart::releaseAllStock();
        
        return back()->withErrors(['payment' => 'Payment failed']);
    }
}
```

## Event Listeners

The package registers these listeners automatically:

### ReleaseStockOnCartClear

Releases reservations when:
- `CartCleared` event is dispatched
- `CartDestroyed` event is dispatched

### DeductStockOnPaymentSuccess

Deducts stock when payment events are dispatched:
- `AIArmada\CashierChip\Events\PaymentSucceeded`
- `AIArmada\Cashier\Events\PaymentSucceeded`
- Custom events from config

## StockCondition

A cart condition for validating stock:

```php
use AIArmada\Stock\Cart\StockCondition;

// Create from cart items
$condition = StockCondition::fromCartItems(
    $cartId,
    $cart->getItems(),
    $reservationService
);

if ($condition->hasIssues()) {
    $issues = $condition->getIssues();
    // Handle stock problems
}
```

## Disabling Integration

Disable cart integration via config:

```php
// config/stock.php
'cart' => [
    'enabled' => false,
],
```

Or via environment:

```env
STOCK_CART_INTEGRATION=false
```

## Standalone Usage

Without cart package, stock management works independently:

```php
use AIArmada\Stock\Facades\Stock;
use AIArmada\Stock\Facades\StockReservations;

// Direct stock operations
Stock::addStock($product, 100, 'restock');
Stock::removeStock($product, 5, 'sale');

// Manual reservation management
StockReservations::reserve($product, 5, 'custom-ref', 30);
StockReservations::release($product, 'custom-ref');
StockReservations::commitReservations('custom-ref', 'order-id');
```

## Database Schema

The integration adds a `stock_reservations` table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `stockable_type` | string | Polymorphic type |
| `stockable_id` | UUID | Polymorphic ID |
| `cart_id` | string | Cart identifier |
| `quantity` | integer | Reserved quantity |
| `expires_at` | timestamp | Reservation expiry |
| `created_at` | timestamp | Created timestamp |
| `updated_at` | timestamp | Updated timestamp |

Unique constraint on `(stockable_type, stockable_id, cart_id)` ensures one reservation per product per cart.
