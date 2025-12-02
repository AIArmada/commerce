<?php

declare(strict_types=1);

use App\Http\Controllers\ShopController;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// ==========================================
// CUSTOMER STOREFRONT ROUTES
// ==========================================

// Login redirect (uses Filament admin login)
Route::get('/login', fn() => redirect('/admin/login'))->name('login');

// Homepage
Route::get('/', [ShopController::class, 'home'])->name('shop.home');

// Products
Route::get('/products', [ShopController::class, 'products'])->name('shop.products');
Route::get('/products/{product:slug}', [ShopController::class, 'product'])->name('shop.product');

// Categories
Route::get('/categories', [ShopController::class, 'categories'])->name('shop.categories');

// Cart
Route::get('/cart', [ShopController::class, 'cart'])->name('shop.cart');
Route::post('/cart/add', [ShopController::class, 'addToCart'])->name('shop.cart.add');
Route::patch('/cart/{item}', [ShopController::class, 'updateCart'])->name('shop.cart.update');
Route::delete('/cart/{item}', [ShopController::class, 'removeFromCart'])->name('shop.cart.remove');

// Vouchers
Route::post('/cart/voucher', [ShopController::class, 'applyVoucher'])->name('shop.cart.voucher');
Route::post('/cart/voucher/remove', [ShopController::class, 'removeVoucher'])->name('shop.cart.voucher.remove');

// Checkout
Route::get('/checkout', [ShopController::class, 'checkout'])->name('shop.checkout');
Route::post('/checkout', [ShopController::class, 'processCheckout'])->name('shop.checkout.process');
Route::post('/checkout/buy-now', [ShopController::class, 'buyNow'])->name('shop.checkout.buy-now');

// Payment callbacks (from CHIP gateway)
Route::get('/payment/{order}/success', [ShopController::class, 'paymentSuccess'])->name('shop.payment.success');
Route::get('/payment/{order}/failed', [ShopController::class, 'paymentFailed'])->name('shop.payment.failed');
Route::get('/payment/{order}/cancelled', [ShopController::class, 'paymentCancelled'])->name('shop.payment.cancelled');

// Order success (for viewing existing completed orders)
Route::get('/order/{order}/success', [ShopController::class, 'orderSuccess'])->name('shop.order.success');

// Affiliate tracking
Route::get('/ref/{code}', [ShopController::class, 'trackAffiliate'])->name('shop.affiliate');

// Orders (accessible for demo)
Route::get('/my-orders', [ShopController::class, 'orders'])->name('shop.orders');

// Authenticated routes
Route::middleware('auth')->group(function (): void {
    Route::get('/account', [ShopController::class, 'account'])->name('shop.account');
    
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

// ==========================================
// LEGACY/BILLING ROUTES
// ==========================================

Route::get('/checkout/single/{slug}', [App\Http\Controllers\BillingController::class, 'singleChipCheckout'])->name('checkout.single');
Route::post('/checkout/single', [App\Http\Controllers\BillingController::class, 'processSingleChip'])->name('checkout.single.process');

Route::middleware('auth')->group(function () {
    Route::get('/subscribe/chip/{plan}', [App\Http\Controllers\BillingController::class, 'subscribeChip'])->name('subscribe.chip');
    Route::post('/subscribe/chip', [App\Http\Controllers\BillingController::class, 'processSubscribeChip'])->name('subscribe.chip.process');
    
    Route::get('/subscribe/stripe/{plan}', [App\Http\Controllers\BillingController::class, 'subscribeStripe'])->name('subscribe.stripe');
    Route::post('/subscribe/stripe', [App\Http\Controllers\BillingController::class, 'processSubscribeStripe'])->name('subscribe.stripe.process');
    
    Route::get('/billing/portal', [App\Http\Controllers\BillingController::class, 'portal'])->name('billing.portal');
});

Route::get('/checkout/success/{id}', function (string $id) {
    return view('billing.success', ['purchaseId' => $id]);
})->name('checkout.success');

// ==========================================
// DEMO ONLY: CHIP WEBHOOK SIMULATOR
// ==========================================
// This route simulates receiving a webhook from CHIP for demo purposes.
// In production, CHIP sends webhooks to a public URL with signature verification.
Route::post('/demo/simulate-payment/{order}', function (App\Models\Order $order) {
    // Dispatch the PurchasePaid event directly for demo
    $purchaseData = [
        'id' => $order->metadata['chip_purchase_id'] ?? 'demo-purchase-id',
        'type' => 'purchase',
        'status' => 'paid',
        'reference' => $order->order_number,
        'created_on' => time(),
        'updated_on' => time(),
        'client' => [
            'email' => $order->shipping_address['email'] ?? 'demo@example.com',
            'full_name' => $order->shipping_address['name'] ?? 'Demo Customer',
        ],
        'purchase' => [
            'currency' => 'MYR',
            'total' => $order->grand_total,
            'products' => [],
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ],
        'payment' => [
            'amount' => $order->grand_total,
            'fee_amount' => 200,
            'net_amount' => $order->grand_total - 200,
        ],
        'transaction_data' => [
            'payment_method' => 'fpx',
            'attempts' => [],
        ],
        'issuer_details' => [
            'legal_name' => 'Demo Merchant',
        ],
        'is_test' => true,
        'brand_id' => config('chip.collect.brand_id', 'demo'),
        'status_history' => [],
    ];

    $purchase = \AIArmada\Chip\DataObjects\Purchase::fromArray($purchaseData);
    
    // Dispatch the event
    \AIArmada\Chip\Events\PurchasePaid::dispatch($purchase, $purchaseData);
    
    return response()->json([
        'status' => 'ok',
        'message' => 'Payment simulated successfully',
        'order_number' => $order->order_number,
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
  ->name('demo.simulate-payment');
