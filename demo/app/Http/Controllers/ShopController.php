<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Models\Voucher;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ShopController extends Controller
{
    /**
     * Homepage with featured products and categories.
     */
    public function home(): View
    {
        $categories = Category::withCount('products')->get();

        $featuredProducts = Product::with('category')
            ->where('is_active', true)
            ->inRandomOrder()
            ->take(8)
            ->get();

        $activeVouchers = Voucher::where('status', VoucherStatus::Active)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->take(3)
            ->get();

        return view('shop.home', compact('categories', 'featuredProducts', 'activeVouchers'));
    }

    /**
     * Products listing with filters.
     */
    public function products(Request $request): View
    {
        $categories = Category::withCount('products')->get();
        $currentCategory = null;

        $query = Product::with('category')->where('is_active', true);

        // Category filter
        if ($request->filled('category')) {
            $currentCategory = Category::where('slug', $request->category)->first();
            if ($currentCategory) {
                $query->where('category_id', $currentCategory->id);
            }
        }

        // Search filter
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%')
                    ->orWhere('sku', 'like', '%'.$request->search.'%');
            });
        }

        // Price filter
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (int) $request->min_price * 100);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (int) $request->max_price * 100);
        }

        // In stock filter
        if ($request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        // Sorting
        $sort = $request->get('sort', 'newest');
        $query = match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name' => $query->orderBy('name', 'asc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $products = $query->paginate(12);

        return view('shop.products', compact('products', 'categories', 'currentCategory'));
    }

    /**
     * Categories listing.
     */
    public function categories(): View
    {
        $categories = Category::withCount('products')->get();

        return view('shop.categories', compact('categories'));
    }

    /**
     * Single product page.
     */
    public function product(Product $product): View
    {
        $product->load('category');

        $relatedProducts = Product::with('category')
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->inRandomOrder()
            ->take(4)
            ->get();

        return view('shop.product', compact('product', 'relatedProducts'));
    }

    /**
     * Shopping cart page.
     */
    public function cart(): View
    {
        $cartItems = Cart::getItems();
        $cartTotal = Cart::isEmpty() ? 0 : Cart::getRawTotal();
        $cartSubtotal = Cart::isEmpty() ? 0 : Cart::getRawSubtotalWithoutConditions();
        $cartQuantity = Cart::getTotalQuantity();
        $appliedVoucher = session('applied_voucher');

        $activeVouchers = Voucher::where('status', VoucherStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->take(3)
            ->get();

        return view('shop.cart', compact('cartItems', 'cartTotal', 'cartSubtotal', 'cartQuantity', 'activeVouchers', 'appliedVoucher'));
    }

    /**
     * Add item to cart.
     */
    public function addToCart(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);

        if ($product->isOutOfStock()) {
            return back()->with('error', 'Sorry, this product is out of stock.');
        }

        if ($request->quantity > $product->stock_quantity) {
            return back()->with('error', 'Requested quantity exceeds available stock.');
        }

        Cart::add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => $request->quantity,
            'attributes' => [
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'slug' => $product->slug,
            ],
        ]);

        session(['cart_count' => Cart::getTotalQuantity()]);

        return back()->with('success', "{$product->name} added to cart!");
    }

    /**
     * Update cart item quantity.
     */
    public function updateCart(Request $request, string $itemId): RedirectResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        Cart::update($itemId, [
            'quantity' => [
                'relative' => false,
                'value' => $request->quantity,
            ],
        ]);

        session(['cart_count' => Cart::getTotalQuantity()]);

        return back()->with('success', 'Cart updated.');
    }

    /**
     * Remove item from cart.
     */
    public function removeFromCart(string $itemId): RedirectResponse
    {
        Cart::remove($itemId);

        session(['cart_count' => Cart::getTotalQuantity()]);

        return back()->with('success', 'Item removed from cart.');
    }

    /**
     * Apply voucher to cart.
     */
    public function applyVoucher(Request $request): RedirectResponse
    {
        $request->validate([
            'voucher_code' => 'required|string',
        ]);

        try {
            Cart::applyVoucher($request->voucher_code);
            session(['applied_voucher' => mb_strtoupper($request->voucher_code)]);

            return back()->with('success', 'Voucher '.mb_strtoupper($request->voucher_code).' applied!');
        } catch (\AIArmada\Vouchers\Exceptions\InvalidVoucherException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove voucher from cart.
     */
    public function removeVoucher(): RedirectResponse
    {
        $appliedVoucher = session('applied_voucher');

        if ($appliedVoucher) {
            Cart::removeVoucher($appliedVoucher);
        }

        session()->forget('applied_voucher');

        return back()->with('success', 'Voucher removed.');
    }

    /**
     * Checkout page.
     */
    public function checkout(): View|RedirectResponse
    {
        if (Cart::isEmpty()) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty.');
        }

        $items = Cart::getItems();
        $subtotal = Cart::getRawSubtotal();
        $total = Cart::getRawTotal();
        $conditions = Cart::getConditions();

        return view('shop.checkout', compact('items', 'subtotal', 'total', 'conditions'));
    }

    /**
     * Process checkout and create order, then redirect to CHIP payment.
     */
    public function processCheckout(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'phone' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'address_line_1' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'postcode' => 'required|string',
            'shipping_method' => 'required|in:jnt_standard,jnt_express,free',
            'payment_method' => 'required|in:fpx,card,ewallet',
        ]);

        if (Cart::isEmpty()) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty.');
        }

        // Calculate shipping cost
        $shippingCost = match ($request->shipping_method) {
            'jnt_express' => 1500,
            'free' => 0,
            default => 800,
        };

        // Calculate discount properly (subtotal without conditions - subtotal with conditions)
        $subtotalWithoutConditions = (int) Cart::getRawSubtotalWithoutConditions();
        $subtotalWithConditions = (int) Cart::getRawSubtotal();
        $discountTotal = max(0, $subtotalWithoutConditions - $subtotalWithConditions);

        // Create order with pending_payment status
        $order = Order::create([
            'order_number' => 'ORD-'.mb_strtoupper(Str::random(8)),
            'user_id' => auth()->id(),
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'subtotal' => $subtotalWithConditions,
            'discount_total' => $discountTotal,
            'tax_total' => 0,
            'shipping_total' => $shippingCost,
            'grand_total' => $subtotalWithConditions + $shippingCost,
            'currency' => 'MYR',
            'shipping_address' => [
                'name' => $request->first_name.' '.$request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address_line_1' => $request->address_line_1,
                'address_line_2' => $request->address_line_2,
                'city' => $request->city,
                'state' => $request->state,
                'postcode' => $request->postcode,
                'country' => 'Malaysia',
            ],
            'metadata' => [
                'shipping_method' => $request->shipping_method,
                'payment_method' => $request->payment_method,
                'affiliate_code' => session('affiliate_code'),
            ],
            'notes' => $request->notes,
            'voucher_code' => session('applied_voucher'),
        ]);

        // Create order items (don't deduct stock yet - wait for payment)
        foreach (Cart::getItems() as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->id,
                'name' => $item->name,
                'sku' => $item->attributes['sku'] ?? null,
                'quantity' => $item->quantity,
                'unit_price' => (int) $item->price,
                'total_price' => (int) $item->getRawSubtotal(),
            ]);
        }

        // Store cart data in session for potential recovery
        session([
            'pending_order_id' => $order->id,
            'pending_affiliate_code' => session('affiliate_code'),
            'pending_voucher_code' => session('applied_voucher'),
        ]);

        // Create CHIP purchase
        try {
            $purchase = Chip::purchase()
                ->currency('MYR')
                ->reference($order->order_number)
                ->customer(
                    email: $request->email,
                    fullName: $request->first_name.' '.$request->last_name,
                    phone: $request->phone,
                    country: 'MY'
                )
                ->billingAddress(
                    streetAddress: $request->address_line_1,
                    city: $request->city,
                    zipCode: $request->postcode,
                    state: $request->state,
                    country: 'MY'
                );

            // Add order items as products
            foreach ($order->items as $item) {
                $purchase->addProduct(
                    name: $item->name,
                    price: $item->unit_price,
                    quantity: $item->quantity
                );
            }

            // Add shipping as a product if applicable
            if ($shippingCost > 0) {
                $purchase->addProduct(
                    name: 'Shipping ('.ucfirst(str_replace('_', ' ', $request->shipping_method)).')',
                    price: $shippingCost,
                    quantity: 1
                );
            }

            // Apply discount using CHIP's total_discount_override
            if ($order->discount_total > 0) {
                $purchase->discount($order->discount_total);
            }

            // Set redirect URLs
            $purchase->redirects(
                successUrl: route('shop.payment.success', $order),
                failureUrl: route('shop.payment.failed', $order),
                cancelUrl: route('shop.payment.cancelled', $order)
            );

            // Set webhook URL
            $purchase->webhook(Chip::webhookUrl());

            // Store order metadata
            $purchase->metadata([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'affiliate_code' => session('affiliate_code'),
            ]);

            // Create the purchase and get the checkout URL
            $chipPurchase = $purchase->create();

            // Store CHIP purchase ID in order metadata
            $order->update([
                'metadata' => array_merge($order->metadata ?? [], [
                    'chip_purchase_id' => $chipPurchase->id,
                ]),
            ]);

            // Redirect to CHIP payment page
            return redirect()->away($chipPurchase->getCheckoutUrl());

        } catch (Exception $e) {
            // Log the error
            Log::error('CHIP payment creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            // Mark order as failed
            $order->update(['status' => 'payment_failed']);

            return redirect()->route('shop.checkout')
                ->with('error', 'Payment initialization failed. Please try again. Error: '.$e->getMessage());
        }
    }

    /**
     * Handle successful payment redirect from CHIP.
     */
    public function paymentSuccess(Order $order): View
    {
        // Clear cart and session
        Cart::clear();
        Cart::clearConditions();
        session()->forget(['cart_count', 'applied_voucher', 'pending_order_id', 'pending_affiliate_code', 'pending_voucher_code']);

        // For demo: Simulate webhook if payment is still pending
        // In production, CHIP sends the webhook to a public URL automatically
        if ($order->payment_status === 'pending' && $order->metadata['chip_purchase_id'] ?? null) {
            $this->simulatePaymentWebhook($order);
            $order->refresh(); // Reload to get updated status
        }

        $order->load('items');

        return view('shop.payment-success', compact('order'));
    }

    /**
     * Handle failed payment redirect from CHIP.
     */
    public function paymentFailed(Order $order): View
    {
        $order->update(['status' => 'payment_failed']);

        return view('shop.payment-failed', compact('order'));
    }

    /**
     * Handle cancelled payment redirect from CHIP.
     */
    public function paymentCancelled(Order $order): View
    {
        $order->update(['status' => 'cancelled']);

        return view('shop.payment-cancelled', compact('order'));
    }

    /**
     * Order success page (for viewing existing completed orders).
     */
    public function orderSuccess(Order $order): View
    {
        $order->load('items');

        return view('shop.order-success', compact('order'));
    }

    /**
     * Track affiliate from URL.
     */
    public function trackAffiliate(Request $request, string $code): RedirectResponse
    {
        $affiliate = Affiliate::where('code', mb_strtoupper($code))
            ->where('status', 'active')
            ->first();

        if ($affiliate) {
            session(['affiliate_code' => $affiliate->code]);

            // Track click
            $affiliate->increment('total_clicks');
        }

        return redirect()->route('shop.home');
    }

    /**
     * Buy now - direct checkout.
     */
    public function buyNow(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);

        if ($product->isOutOfStock()) {
            return back()->with('error', 'Sorry, this product is out of stock.');
        }

        // Clear cart and add single item
        Cart::clear();
        Cart::clearConditions();

        Cart::add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => $request->quantity,
            'attributes' => [
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'slug' => $product->slug,
            ],
        ]);

        session(['cart_count' => Cart::getTotalQuantity()]);

        return redirect()->route('shop.checkout');
    }

    /**
     * My orders page.
     */
    public function orders(): View
    {
        // For demo purposes, show all recent orders if not authenticated
        if (auth()->check()) {
            $orders = Order::where('user_id', auth()->id())
                ->with('items')
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            // Show recent orders for demo
            $orders = Order::with('items')
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }

        return view('shop.orders', compact('orders'));
    }

    /**
     * Account page.
     */
    public function account(): View
    {
        $user = auth()->user();
        $recentOrders = Order::with('items')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('shop.account', compact('user', 'recentOrders'));
    }

    /**
     * Simulate CHIP payment webhook for demo purposes.
     * In production, CHIP sends webhooks to a public URL with signature verification.
     */
    private function simulatePaymentWebhook(Order $order): void
    {
        $address = $order->shipping_address ?? [];
        $streetAddress = $address['address_line_1'] ?? $address['address'] ?? '';

        \AIArmada\Chip\Testing\WebhookSimulator::paid()
            ->purchaseId($order->metadata['chip_purchase_id'])
            ->reference($order->order_number)
            ->amount($order->grand_total)
            ->customer(
                $address['email'] ?? 'demo@example.com',
                $address['name'] ?? 'Demo Customer',
                $address['phone'] ?? '+60123456789'
            )
            ->with([
                'client' => [
                    'street_address' => $streetAddress,
                    'city' => $address['city'] ?? '',
                    'state' => $address['state'] ?? '',
                    'zip_code' => $address['postcode'] ?? '',
                    'country' => 'MY',
                    'shipping_street_address' => $streetAddress,
                    'shipping_city' => $address['city'] ?? '',
                    'shipping_state' => $address['state'] ?? '',
                    'shipping_zip_code' => $address['postcode'] ?? '',
                    'shipping_country' => 'MY',
                ],
                'purchase' => [
                    'total' => $order->grand_total,
                    'currency' => 'MYR',
                    'metadata' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ],
                    'products' => $order->items->map(fn ($item) => [
                        'name' => $item->name,
                        'price' => $item->unit_price,
                        'quantity' => (string) $item->quantity,
                        'category' => 'product',
                        'discount' => 0,
                        'tax_percent' => '0.00',
                    ])->toArray(),
                    'subtotal_override' => $order->subtotal,
                    'total_discount_override' => $order->discount_total,
                    'shipping_options' => $order->shipping_total > 0 ? [
                        ['amount' => $order->shipping_total, 'title' => 'Shipping'],
                    ] : [],
                ],
            ])
            ->fpx()
            ->isTest()
            ->dispatch();
    }
}
