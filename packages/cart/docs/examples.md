# 🎯 Real-World Examples

Complete, working code examples for common cart scenarios.

---

## Table of Contents

- [E-commerce Checkout](#e-commerce-checkout)
- [Guest to User Migration](#guest-to-user-migration)
- [Multi-Currency Carts](#multi-currency-carts)
- [Subscription Products](#subscription-products)
- [Bundle Discounts](#bundle-discounts)
- [Flash Sales](#flash-sales)
- [Abandoned Cart Recovery](#abandoned-cart-recovery)
- [Wishlist to Cart](#wishlist-to-cart)
- [Product Comparison](#product-comparison)
- [Gift Cards & Promo Codes](#gift-cards--promo-codes)
- [Loyalty Points](#loyalty-points)
- [International Shipping](#international-shipping)
- [Tax Calculations](#tax-calculations)
- [Inventory Reservations](#inventory-reservations)
- [B2B Pricing Tiers](#b2b-pricing-tiers)
- [API Integration](#api-integration)
- [Webhooks](#webhooks)
- [Testing Examples](#testing-examples)

---

## E-commerce Checkout

Complete checkout flow with validation, inventory checks, and order creation.

### Controller

```php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use AIArmada\Cart\Facades\Cart;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shipping_address' => 'required|array',
            'billing_address' => 'required|array',
            'payment_method' => 'required|in:credit_card,paypal',
        ]);

        // Validate cart not empty
        if (Cart::isEmpty()) {
            return back()->withErrors(['cart' => 'Your cart is empty']);
        }

        // Validate inventory
        foreach (Cart::items() as $item) {
            $product = Product::find($item->id);
            
            if (! $product || $product->stock < $item->quantity) {
                return back()->withErrors([
                    'inventory' => "Insufficient stock for {$item->name}",
                ]);
            }
        }

        // Create order in transaction
        return DB::transaction(function () use ($validated) {
            // Create order
            $order = Order::create([
                'user_id' => auth()->id(),
                'subtotal' => Cart::subtotal()->getAmount(),
                'tax' => Cart::tax()->getAmount(),
                'total' => Cart::total()->getAmount(),
                'currency' => 'USD',
                'shipping_address' => $validated['shipping_address'],
                'billing_address' => $validated['billing_address'],
                'payment_method' => $validated['payment_method'],
            ]);

            // Create order items
            foreach (Cart::items() as $item) {
                $order->items()->create([
                    'product_id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price->getAmount(),
                    'quantity' => $item->quantity,
                    'attributes' => $item->attributes,
                ]);

                // Decrement stock
                Product::find($item->id)->decrement('stock', $item->quantity);
            }

            // Clear cart
            Cart::clear();

            return redirect()->route('orders.show', $order)
                ->with('success', 'Order placed successfully!');
        });
    }
}
```

---

## Guest to User Migration

Migrate guest cart when user logs in.

### Event Listener

```php
namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Services\MigrationService;

class MigrateGuestCart
{
    public function __construct(
        private MigrationService $migration
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        
        // Get session identifier (guest cart)
        $sessionId = session()->getId();
        $guestIdentifier = "cart_session_{$sessionId}";
        
        // Get user identifier
        $userIdentifier = "cart_user_{$user->id}";

        // Migrate all instances (default, wishlist, compare)
        $this->migration->migrateAll(
            fromIdentifier: $guestIdentifier,
            toIdentifier: $userIdentifier,
            strategy: 'add_quantities' // Merge quantities
        );

        // Log for debugging
        logger()->info('Cart migrated on login', [
            'user_id' => $user->id,
            'from' => $guestIdentifier,
            'to' => $userIdentifier,
            'items' => Cart::count(),
        ]);
    }
}
```

### Register Listener

```php
// app/Providers/AppServiceProvider.php

use Illuminate\Auth\Events\Login;
use App\Listeners\MigrateGuestCart;

public function boot(): void
{
    Event::listen(Login::class, MigrateGuestCart::class);
}
```

---

## Multi-Currency Carts

Handle multiple currencies with conversion.

### Service

```php
namespace App\Services;

use Akaunting\Money\Money;
use Illuminate\Support\Facades\Cache;
use AIArmada\Cart\Facades\Cart;

class CurrencyService
{
    private array $rates = [
        'USD' => 1.0,
        'EUR' => 0.85,
        'GBP' => 0.73,
        'MYR' => 4.18,
    ];

    public function switchCurrency(string $currency): void
    {
        // Get current cart items
        $items = Cart::items();
        
        if ($items->isEmpty()) {
            session(['currency' => $currency]);
            return;
        }

        // Convert each item's price
        foreach ($items as $item) {
            $convertedPrice = $this->convert(
                amount: $item->price,
                to: $currency
            );

            Cart::update($item->id, [
                'price' => $convertedPrice,
            ]);
        }

        session(['currency' => $currency]);
    }

    public function convert(Money $amount, string $to): Money
    {
        $from = $amount->getCurrency()->getCurrency();
        
        if ($from === $to) {
            return $amount;
        }

        // Convert to USD first, then to target currency
        $usdAmount = $amount->getAmount() / $this->rates[$from];
        $targetAmount = $usdAmount * $this->rates[$to];

        return new Money($targetAmount, $to);
    }

    public function getCurrentCurrency(): string
    {
        return session('currency', 'USD');
    }
}
```

### Controller

```php
namespace App\Http\Controllers;

use App\Services\CurrencyService;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function switch(Request $request, CurrencyService $currency)
    {
        $validated = $request->validate([
            'currency' => 'required|in:USD,EUR,GBP,MYR',
        ]);

        $currency->switchCurrency($validated['currency']);

        return back()->with('success', 'Currency updated');
    }
}
```

---

## Subscription Products

Handle recurring subscription items.

### Adding Subscription

```php
namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use AIArmada\Cart\Facades\Cart;
use Akaunting\Money\Money;

class SubscriptionController extends Controller
{
    public function addToCart(SubscriptionPlan $plan)
    {
        Cart::instance('subscriptions')->add(
            id: "subscription_{$plan->id}",
            name: $plan->name,
            price: Money::USD($plan->price_cents),
            quantity: 1,
            attributes: [
                'plan_id' => $plan->id,
                'billing_cycle' => $plan->billing_cycle, // 'monthly', 'yearly'
                'is_subscription' => true,
                'trial_days' => $plan->trial_days,
                'features' => $plan->features,
            ]
        );

        return redirect()->route('subscriptions.cart')
            ->with('success', "Added {$plan->name} to subscriptions");
    }

    public function checkout()
    {
        $subscriptions = Cart::instance('subscriptions')->items();

        if ($subscriptions->isEmpty()) {
            return redirect()->route('subscriptions.index')
                ->withErrors(['cart' => 'No subscriptions selected']);
        }

        // Create subscription records
        foreach ($subscriptions as $item) {
            auth()->user()->subscriptions()->create([
                'plan_id' => $item->attributes['plan_id'],
                'price' => $item->price->getAmount(),
                'billing_cycle' => $item->attributes['billing_cycle'],
                'trial_ends_at' => $item->attributes['trial_days'] 
                    ? now()->addDays($item->attributes['trial_days'])
                    : null,
                'status' => 'active',
            ]);
        }

        Cart::instance('subscriptions')->clear();

        return redirect()->route('subscriptions.manage')
            ->with('success', 'Subscriptions activated!');
    }
}
```

---

## Bundle Discounts

Apply discounts when buying product bundles.

### Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use AIArmada\Cart\Data\CartCondition;
use AIArmada\Cart\Facades\Cart;

class ApplyBundleDiscounts
{
    private array $bundles = [
        'tech_bundle' => [
            'products' => [1, 2, 3], // Product IDs
            'discount' => '20%',
            'name' => 'Tech Bundle Discount',
        ],
        'fashion_bundle' => [
            'products' => [10, 11, 12],
            'discount' => '-15.00',
            'name' => 'Fashion Bundle Discount',
        ],
    ];

    public function handle(Request $request, Closure $next)
    {
        foreach ($this->bundles as $bundleId => $bundle) {
            if ($this->hasBundleProducts($bundle['products'])) {
                $this->applyBundleDiscount($bundleId, $bundle);
            } else {
                $this->removeBundleDiscount($bundleId);
            }
        }

        return $next($request);
    }

    private function hasBundleProducts(array $productIds): bool
    {
        $cartProductIds = Cart::items()->pluck('id')->toArray();
        
        foreach ($productIds as $productId) {
            if (! in_array($productId, $cartProductIds)) {
                return false;
            }
        }

        return true;
    }

    private function applyBundleDiscount(string $bundleId, array $bundle): void
    {
        if (Cart::getCondition($bundle['name'])) {
            return; // Already applied
        }

        Cart::addCondition(
            CartCondition::make([
                'name' => $bundle['name'],
                'type' => 'discount',
                'target' => 'total',
                'value' => $bundle['discount'],
                'attributes' => [
                    'bundle_id' => $bundleId,
                    'auto_applied' => true,
                ],
            ])
        );
    }

    private function removeBundleDiscount(string $bundleId): void
    {
        $conditions = Cart::getConditions();
        
        $bundleCondition = $conditions->first(function ($condition) use ($bundleId) {
            return ($condition->attributes['bundle_id'] ?? null) === $bundleId;
        });

        if ($bundleCondition) {
            Cart::removeCondition($bundleCondition->name);
        }
    }
}
```

### Register Middleware

```php
// bootstrap/app.php

->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\ApplyBundleDiscounts::class,
    ]);
})
```

---

## Flash Sales

Time-limited discounts with countdown.

### Service

```php
namespace App\Services;

use Carbon\Carbon;
use AIArmada\Cart\Data\CartCondition;
use AIArmada\Cart\Facades\Cart;

class FlashSaleService
{
    public function applyFlashSale(): void
    {
        $sale = $this->getActiveFlashSale();

        if (! $sale) {
            $this->removeFlashSale();
            return;
        }

        Cart::addCondition(
            CartCondition::make([
                'name' => 'Flash Sale',
                'type' => 'discount',
                'target' => 'total',
                'value' => "-{$sale['discount']}%",
                'attributes' => [
                    'ends_at' => $sale['ends_at']->toIso8601String(),
                    'description' => $sale['description'],
                ],
            ])
        );
    }

    private function getActiveFlashSale(): ?array
    {
        // In production, fetch from database
        $sales = [
            [
                'name' => 'Weekend Flash Sale',
                'discount' => 25,
                'starts_at' => Carbon::parse('2025-10-08 00:00:00'),
                'ends_at' => Carbon::parse('2025-10-10 23:59:59'),
                'description' => '25% off everything!',
            ],
        ];

        foreach ($sales as $sale) {
            if (now()->between($sale['starts_at'], $sale['ends_at'])) {
                return $sale;
            }
        }

        return null;
    }

    private function removeFlashSale(): void
    {
        Cart::removeCondition('Flash Sale');
    }

    public function getTimeRemaining(): ?array
    {
        $condition = Cart::getCondition('Flash Sale');

        if (! $condition) {
            return null;
        }

        $endsAt = Carbon::parse($condition->attributes['ends_at']);
        $diff = now()->diff($endsAt);

        return [
            'hours' => $diff->h,
            'minutes' => $diff->i,
            'seconds' => $diff->s,
            'ends_at' => $endsAt->toDateTimeString(),
        ];
    }
}
```

### Blade Component

```blade
{{-- resources/views/components/flash-sale-timer.blade.php --}}

@php
    $flashSale = app(\App\Services\FlashSaleService::class);
    $time = $flashSale->getTimeRemaining();
@endphp

@if($time)
    <div class="flash-sale-banner bg-red-600 text-white p-4 text-center">
        <div class="font-bold text-lg">⚡ Flash Sale Active!</div>
        <div class="text-sm">
            Ends in: 
            <span class="font-mono">{{ $time['hours'] }}h {{ $time['minutes'] }}m {{ $time['seconds'] }}s</span>
        </div>
    </div>

    <script>
        // Countdown timer
        setInterval(() => {
            window.location.reload();
        }, 60000); // Refresh every minute
    </script>
@endif
```

---

## Abandoned Cart Recovery

Send email reminders for abandoned carts.

### Command

```php
namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AbandonedCartReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAbandonedCartEmails extends Command
{
    protected $signature = 'cart:abandoned {--hours=24}';
    protected $description = 'Send emails for abandoned carts';

    public function handle(): int
    {
        $hours = $this->option('hours');
        $cutoff = now()->subHours($hours);

        // Query carts from database (assuming database driver)
        $abandonedCarts = DB::table('carts')
            ->where('updated_at', '<', $cutoff)
            ->where('updated_at', '>', now()->subDays(7)) // Not too old
            ->whereNotNull('user_id')
            ->get();

        foreach ($abandonedCarts as $cart) {
            $user = User::find($cart->user_id);
            
            if (! $user || $user->notified_abandoned_cart_at?->isAfter($cutoff)) {
                continue; // Skip if already notified recently
            }

            $user->notify(new AbandonedCartReminder($cart));
            $user->update(['notified_abandoned_cart_at' => now()]);

            $this->info("Sent reminder to {$user->email}");
        }

        $this->info("Processed {$abandonedCarts->count()} abandoned carts");

        return self::SUCCESS;
    }
}
```

### Notification

```php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbandonedCartReminder extends Notification
{
    use Queueable;

    public function __construct(
        private object $cart
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $cartData = json_decode($this->cart->data, true);
        $itemCount = count($cartData['items'] ?? []);

        return (new MailMessage)
            ->subject('You left items in your cart!')
            ->greeting("Hi {$notifiable->name},")
            ->line("You have {$itemCount} item(s) waiting in your cart.")
            ->action('Complete Your Purchase', route('cart.index'))
            ->line('Complete your order today and enjoy your items!');
    }
}
```

### Schedule

```php
// routes/console.php

use Illuminate\Support\Facades\Schedule;

Schedule::command('cart:abandoned --hours=24')
    ->dailyAt('09:00');
```

---

## Wishlist to Cart

Move items from wishlist to cart.

### Controller

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use AIArmada\Cart\Facades\Cart;

class WishlistController extends Controller
{
    public function moveToCart(Request $request)
    {
        $itemId = $request->input('item_id');

        // Get item from wishlist
        $item = Cart::instance('wishlist')->get($itemId);

        if (! $item) {
            return back()->withErrors(['item' => 'Item not found in wishlist']);
        }

        // Add to main cart
        Cart::instance('default')->add(
            id: $item->id,
            name: $item->name,
            price: $item->price,
            quantity: 1,
            attributes: $item->attributes
        );

        // Remove from wishlist
        Cart::instance('wishlist')->remove($itemId);

        return back()->with('success', 'Item moved to cart');
    }

    public function moveAllToCart()
    {
        $wishlistItems = Cart::instance('wishlist')->items();

        if ($wishlistItems->isEmpty()) {
            return back()->withErrors(['wishlist' => 'Wishlist is empty']);
        }

        foreach ($wishlistItems as $item) {
            Cart::instance('default')->add(
                id: $item->id,
                name: $item->name,
                price: $item->price,
                quantity: 1,
                attributes: $item->attributes
            );
        }

        Cart::instance('wishlist')->clear();

        return redirect()->route('cart.index')
            ->with('success', "Moved {$wishlistItems->count()} items to cart");
    }
}
```

---

## Product Comparison

Compare products across instances.

### Controller

```php
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use AIArmada\Cart\Facades\Cart;

class ComparisonController extends Controller
{
    public function index()
    {
        $items = Cart::instance('compare')->items();

        $products = Product::with(['attributes', 'reviews'])
            ->whereIn('id', $items->pluck('id'))
            ->get();

        return view('comparison.index', [
            'products' => $products,
            'items' => $items,
        ]);
    }

    public function add(Product $product)
    {
        // Limit to 4 products
        if (Cart::instance('compare')->count() >= 4) {
            return back()->withErrors([
                'compare' => 'You can only compare up to 4 products',
            ]);
        }

        Cart::instance('compare')->add(
            id: $product->id,
            name: $product->name,
            price: $product->price,
            quantity: 1,
            attributes: [
                'image' => $product->image,
                'category' => $product->category->name,
                'specifications' => $product->specifications,
            ]
        );

        return back()->with('success', 'Product added to comparison');
    }

    public function remove(Request $request)
    {
        Cart::instance('compare')->remove($request->input('product_id'));

        return back()->with('success', 'Product removed from comparison');
    }

    public function clear()
    {
        Cart::instance('compare')->clear();

        return redirect()->route('products.index')
            ->with('success', 'Comparison cleared');
    }
}
```

---

## Gift Cards & Promo Codes

Apply and validate promo codes.

### Service

```php
namespace App\Services;

use App\Models\PromoCode;
use Akaunting\Money\Money;
use AIArmada\Cart\Data\CartCondition;
use AIArmada\Cart\Facades\Cart;

class PromoCodeService
{
    public function apply(string $code): array
    {
        $promo = PromoCode::where('code', strtoupper($code))
            ->where('active', true)
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->first();

        if (! $promo) {
            return ['success' => false, 'message' => 'Invalid promo code'];
        }

        // Check usage limit
        if ($promo->usage_limit && $promo->usage_count >= $promo->usage_limit) {
            return ['success' => false, 'message' => 'Promo code limit reached'];
        }

        // Check minimum purchase
        if ($promo->minimum_purchase && Cart::subtotal()->getAmount() < $promo->minimum_purchase) {
            $min = Money::USD($promo->minimum_purchase)->format();
            return ['success' => false, 'message' => "Minimum purchase of {$min} required"];
        }

        // Apply promo
        Cart::addCondition(
            CartCondition::make([
                'name' => "Promo: {$promo->code}",
                'type' => 'discount',
                'target' => 'total',
                'value' => $promo->type === 'percentage' 
                    ? "-{$promo->value}%" 
                    : "-{$promo->value}",
                'attributes' => [
                    'promo_id' => $promo->id,
                    'code' => $promo->code,
                ],
            ])
        );

        // Store in metadata
        Cart::setMetadata([
            'promo_code' => $promo->code,
            'promo_applied_at' => now()->toDateTimeString(),
        ]);

        return ['success' => true, 'message' => 'Promo code applied!'];
    }

    public function remove(): void
    {
        $conditions = Cart::getConditions();

        foreach ($conditions as $condition) {
            if (isset($condition->attributes['promo_id'])) {
                Cart::removeCondition($condition->name);
            }
        }

        Cart::removeMetadata('promo_code');
        Cart::removeMetadata('promo_applied_at');
    }
}
```

### Controller

```php
namespace App\Http\Controllers;

use App\Services\PromoCodeService;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    public function apply(Request $request, PromoCodeService $promo)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $result = $promo->apply($validated['code']);

        if (! $result['success']) {
            return back()->withErrors(['promo' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function remove(PromoCodeService $promo)
    {
        $promo->remove();

        return back()->with('success', 'Promo code removed');
    }
}
```

---

## Loyalty Points

Earn and redeem loyalty points.

### Service

```php
namespace App\Services;

use Akaunting\Money\Money;
use AIArmada\Cart\Data\CartCondition;
use AIArmada\Cart\Facades\Cart;

class LoyaltyPointsService
{
    private int $pointsPerDollar = 10;
    private int $pointsValue = 100; // 100 points = $1

    public function calculateEarnings(): int
    {
        $total = Cart::total()->getAmount();
        return (int) floor($total / 100 * $this->pointsPerDollar);
    }

    public function redeem(int $points): array
    {
        $user = auth()->user();

        if ($user->loyalty_points < $points) {
            return ['success' => false, 'message' => 'Insufficient points'];
        }

        // Calculate discount
        $discountCents = (int) floor($points / $this->pointsValue * 100);
        $discount = Money::USD($discountCents);

        // Can't exceed cart total
        if ($discount->greaterThan(Cart::total())) {
            return ['success' => false, 'message' => 'Points exceed cart total'];
        }

        // Apply points discount
        Cart::addCondition(
            CartCondition::make([
                'name' => 'Loyalty Points',
                'type' => 'discount',
                'target' => 'total',
                'value' => "-{$discountCents}",
                'attributes' => [
                    'points_redeemed' => $points,
                ],
            ])
        );

        Cart::setMetadata([
            'points_redeemed' => $points,
        ]);

        return [
            'success' => true,
            'message' => "Redeemed {$points} points for {$discount->format()}",
        ];
    }

    public function removeRedemption(): void
    {
        Cart::removeCondition('Loyalty Points');
        Cart::removeMetadata('points_redeemed');
    }
}
```

---

## International Shipping

Calculate shipping by region.

### Service

```php
namespace App\Services;

use Akaunting\Money\Money;
use AIArmada\Cart\Data\CartCondition;
use AIArmada\Cart\Facades\Cart;

class ShippingService
{
    private array $rates = [
        'US' => [
            'standard' => 500,  // $5.00
            'express' => 1500,  // $15.00
            'free_threshold' => 5000, // Free over $50
        ],
        'EU' => [
            'standard' => 800,
            'express' => 2000,
            'free_threshold' => 7500,
        ],
        'ASIA' => [
            'standard' => 1000,
            'express' => 2500,
            'free_threshold' => 10000,
        ],
    ];

    public function calculate(string $region, string $method = 'standard'): void
    {
        if (! isset($this->rates[$region])) {
            return;
        }

        $rate = $this->rates[$region];
        $subtotal = Cart::subtotal()->getAmount();

        // Free shipping if threshold met
        if ($subtotal >= $rate['free_threshold']) {
            $this->applyShipping($region, $method, 0, true);
            return;
        }

        $cost = $rate[$method] ?? $rate['standard'];
        $this->applyShipping($region, $method, $cost, false);
    }

    private function applyShipping(
        string $region,
        string $method,
        int $cost,
        bool $free
    ): void {
        // Remove existing shipping
        Cart::removeCondition('Shipping');

        if ($free) {
            Cart::addCondition(
                CartCondition::make([
                    'name' => 'Shipping',
                    'type' => 'shipping',
                    'target' => 'total',
                    'value' => '0',
                    'attributes' => [
                        'region' => $region,
                        'method' => $method,
                        'free' => true,
                    ],
                ])
            );
            return;
        }

        Cart::addCondition(
            CartCondition::make([
                'name' => 'Shipping',
                'type' => 'shipping',
                'target' => 'total',
                'value' => "+{$cost}",
                'attributes' => [
                    'region' => $region,
                    'method' => $method,
                    'cost_cents' => $cost,
                ],
            ])
        );
    }
}
```

---

## Tax Calculations

Regional tax rates.

### Service

```php
namespace App\Services;

use AIArmada\Cart\Data\CartCondition;
use AIArmada\Cart\Facades\Cart;

class TaxService
{
    private array $rates = [
        'US' => [
            'CA' => 7.25,  // California
            'NY' => 8.52,  // New York
            'TX' => 6.25,  // Texas
        ],
        'EU' => [
            'DE' => 19.0,  // Germany
            'FR' => 20.0,  // France
            'IT' => 22.0,  // Italy
        ],
    ];

    public function calculate(string $country, string $state): void
    {
        // Remove existing tax
        $this->removeTax();

        $rate = $this->rates[$country][$state] ?? 0;

        if ($rate === 0) {
            return;
        }

        Cart::addCondition(
            CartCondition::make([
                'name' => "{$state} Tax",
                'type' => 'tax',
                'target' => 'subtotal',
                'value' => "+{$rate}%",
                'order' => 1,
                'attributes' => [
                    'country' => $country,
                    'state' => $state,
                    'rate' => $rate,
                ],
            ])
        );
    }

    public function removeTax(): void
    {
        $conditions = Cart::getConditionsByType('tax');

        foreach ($conditions as $condition) {
            Cart::removeCondition($condition->name);
        }
    }

    public function calculateCompoundTax(string $country, string $state): void
    {
        // Example: Canada with GST + PST
        if ($country === 'CA') {
            $gst = 5.0;  // Federal GST
            $pst = $this->getPstRate($state);

            // GST on subtotal
            Cart::addCondition(
                CartCondition::make([
                    'name' => 'GST',
                    'type' => 'tax',
                    'target' => 'subtotal',
                    'value' => "+{$gst}%",
                    'order' => 1,
                ])
            );

            // PST on subtotal
            if ($pst > 0) {
                Cart::addCondition(
                    CartCondition::make([
                        'name' => 'PST',
                        'type' => 'tax',
                        'target' => 'subtotal',
                        'value' => "+{$pst}%",
                        'order' => 2,
                    ])
                );
            }
        }
    }

    private function getPstRate(string $province): float
    {
        return match ($province) {
            'BC' => 7.0,
            'SK' => 6.0,
            'MB' => 7.0,
            'QC' => 9.975,
            default => 0.0,
        };
    }
}
```

---

## Inventory Reservations

Reserve stock during checkout with optimistic locking.

### Service

```php
namespace App\Services;

use App\Models\InventoryReservation;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use AIArmada\Cart\Exceptions\CartConflictException;
use AIArmada\Cart\Facades\Cart;

class InventoryService
{
    public function reserve(string $sessionId, int $ttlMinutes = 15): bool
    {
        return DB::transaction(function () use ($sessionId, $ttlMinutes) {
            foreach (Cart::items() as $item) {
                $product = Product::lockForUpdate()->find($item->id);

                if (! $product) {
                    throw new \Exception("Product {$item->id} not found");
                }

                // Check available stock (current - reserved)
                $reserved = InventoryReservation::where('product_id', $product->id)
                    ->where('expires_at', '>', now())
                    ->sum('quantity');

                $available = $product->stock - $reserved;

                if ($available < $item->quantity) {
                    throw new \Exception("Insufficient stock for {$product->name}");
                }

                // Create or update reservation
                InventoryReservation::updateOrCreate(
                    [
                        'session_id' => $sessionId,
                        'product_id' => $product->id,
                    ],
                    [
                        'quantity' => $item->quantity,
                        'expires_at' => now()->addMinutes($ttlMinutes),
                    ]
                );
            }

            return true;
        });
    }

    public function release(string $sessionId): void
    {
        InventoryReservation::where('session_id', $sessionId)->delete();
    }

    public function confirm(string $sessionId): void
    {
        DB::transaction(function () use ($sessionId) {
            $reservations = InventoryReservation::where('session_id', $sessionId)->get();

            foreach ($reservations as $reservation) {
                $product = Product::find($reservation->product_id);
                $product->decrement('stock', $reservation->quantity);
            }

            $this->release($sessionId);
        });
    }

    public function cleanupExpired(): int
    {
        return InventoryReservation::where('expires_at', '<', now())->delete();
    }
}
```

### Schedule Cleanup

```php
// routes/console.php

Schedule::call(function () {
    app(\App\Services\InventoryService::class)->cleanupExpired();
})->everyFiveMinutes();
```

---

## B2B Pricing Tiers

Different pricing for business customers.

### Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use AIArmada\Cart\Facades\Cart;
use Akaunting\Money\Money;

class ApplyB2BPricing
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (! $user || $user->account_type !== 'business') {
            return $next($request);
        }

        // Get tier discount
        $discount = $this->getTierDiscount($user->tier);

        if ($discount === 0) {
            return $next($request);
        }

        // Apply discount to each item
        foreach (Cart::items() as $item) {
            $originalPrice = $item->price;
            $discountedPrice = $originalPrice->multiply(1 - $discount / 100);

            Cart::update($item->id, [
                'price' => $discountedPrice,
                'attributes' => array_merge($item->attributes, [
                    'original_price' => $originalPrice->getAmount(),
                    'b2b_discount' => $discount,
                    'pricing_tier' => $user->tier,
                ]),
            ]);
        }

        return $next($request);
    }

    private function getTierDiscount(string $tier): int
    {
        return match ($tier) {
            'bronze' => 5,
            'silver' => 10,
            'gold' => 15,
            'platinum' => 20,
            default => 0,
        };
    }
}
```

---

## Payment Gateway Integration

### Storing Cart UUID with Payment

```php
namespace App\Services;

use AIArmada\Cart\Facades\Cart;
use App\Models\Payment;
use Illuminate\Support\Str;

class PaymentService
{
    public function createPayment(array $paymentData): Payment
    {
        // Get cart UUID before creating payment
        $cartUuid = Cart::getId();
        
        if (!$cartUuid) {
            throw new \Exception('Cart must be persisted before creating payment');
        }
        
        // Create payment record
        $payment = Payment::create([
            'reference' => Str::uuid(),
            'cart_id' => $cartUuid,  // Store cart UUID
            'amount' => Cart::total()->getAmount(),
            'currency' => Cart::currency(),
            'status' => 'pending',
            'gateway' => $paymentData['gateway'],
            'metadata' => [
                'items_count' => Cart::count(),
                'cart_identifier' => Cart::getIdentifier(),
            ],
        ]);
        
        return $payment;
    }
}
```

### Processing Webhook with Cart UUID

```php
namespace App\Http\Controllers\Webhooks;

use AIArmada\Cart\Facades\Cart;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Verify webhook signature here...
        
        $paymentId = $request->input('payment_id');
        $status = $request->input('status');
        
        $payment = Payment::where('reference', $paymentId)->firstOrFail();
        
        if ($status === 'paid') {
            return $this->handleSuccessfulPayment($payment);
        }
        
        return response()->json(['status' => 'received']);
    }
    
    protected function handleSuccessfulPayment(Payment $payment)
    {
        return DB::transaction(function () use ($payment) {
            // Load cart by UUID
            $cart = Cart::getById($payment->cart_id);
            
            if (!$cart) {
                throw new \Exception("Cart not found: {$payment->cart_id}");
            }
            
            // Create order from cart
            $order = Order::create([
                'user_id' => $cart->getIdentifier(),
                'payment_id' => $payment->id,
                'cart_id' => $payment->cart_id,
                'subtotal' => $cart->subtotal()->getAmount(),
                'total' => $cart->total()->getAmount(),
                'currency' => $cart->currency(),
                'status' => 'confirmed',
            ]);
            
            // Create order items from cart
            foreach ($cart->getItems() as $item) {
                $order->items()->create([
                    'product_id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price->getAmount(),
                    'quantity' => $item->quantity,
                    'attributes' => $item->attributes->toArray(),
                ]);
            }
            
            // Update payment
            $payment->update([
                'status' => 'completed',
                'order_id' => $order->id,
            ]);
            
            // Clear the cart
            $cart->clear();
            
            return response()->json([
                'status' => 'success',
                'order_id' => $order->id,
            ]);
        });
    }
}
```

### Abandoned Cart Recovery with UUID

```php
namespace App\Console\Commands;

use AIArmada\Cart\Facades\Cart;
use App\Mail\AbandonedCartEmail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendAbandonedCartEmails extends Command
{
    protected $signature = 'cart:send-abandoned-emails';
    
    protected $description = 'Send emails for abandoned carts';
    
    public function handle()
    {
        // Find carts abandoned for 24+ hours
        $abandonedCarts = DB::table('carts')
            ->where('updated_at', '<', now()->subHours(24))
            ->whereNotNull('identifier')
            ->get();
        
        foreach ($abandonedCarts as $snapshot) {
            // Load cart by UUID
            $cart = Cart::getById($snapshot->id);
            
            if (!$cart || $cart->isEmpty()) {
                continue;
            }
            
            // Try to find user by identifier
            $userId = str_replace(['user:', 'guest:'], '', $snapshot->identifier);
            $user = User::find($userId);
            
            if (!$user || !$user->email) {
                continue;
            }
            
            // Send email with cart details
            Mail::to($user->email)->send(
                new AbandonedCartEmail($cart, $user)
            );
            
            $this->info("Sent abandoned cart email to {$user->email}");
        }
        
        return Command::SUCCESS;
    }
}
```

---

## Additional Resources

- [Cart Operations](cart-operations.md)
- [Conditions](conditions.md)
- [Money & Currency](money-and-currency.md)
- [Identifiers & Migration](identifiers-and-migration.md)
- [API Reference](api-reference.md)
