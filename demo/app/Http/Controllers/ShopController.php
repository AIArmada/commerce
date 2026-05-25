<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Checkout\Facades\Checkout;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Testing\WebhookSimulator;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart as CartSnapshot;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Pricing\Services\PriceCalculator;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Shipping\Cart\ShippingCondition;
use AIArmada\Signals\Support\Browser\SignalsBrowserContextManager;
use AIArmada\Tax\Services\TaxCalculator;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\VoucherStatus;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class ShopController extends Controller
{
    /**
     * Homepage with featured products and categories.
     */
    public function home(): View
    {
        $owner = OwnerContext::resolve();

        $categories = Category::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->withCount('products')
            ->get();

        $featuredProducts = Product::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with('categories')
            ->where('status', ProductStatus::Active)
            ->inRandomOrder()
            ->take(8)
            ->get();

        $activeVouchers = Voucher::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status', VoucherStatus::normalize(Active::class))
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
        $owner = OwnerContext::resolve();

        $categories = Category::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->withCount('products')
            ->get();
        $currentCategory = null;

        $query = Product::query()
            ->when(
                $owner,
                fn ($builder) => $builder->forOwner($owner),
                fn ($builder) => $builder->whereRaw('1 = 0'),
            )
            ->with('categories')
            ->where('status', ProductStatus::Active);

        // Category filter
        if ($request->filled('category')) {
            $currentCategory = Category::query()
                ->when(
                    $owner,
                    fn ($builder) => $builder->forOwner($owner),
                    fn ($builder) => $builder->whereRaw('1 = 0'),
                )
                ->where('slug', $request->category)
                ->first();
            if ($currentCategory) {
                $query->whereHas('categories', fn ($q) => $q->whereKey($currentCategory->id));
            }
        }

        // Search filter
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        // Price filter
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (int) $request->min_price * 100);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (int) $request->max_price * 100);
        }

        // In-stock filtering is handled by the Inventory package in admin.

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
        $owner = OwnerContext::resolve();

        $categories = Category::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->withCount('products')
            ->get();

        return view('shop.categories', compact('categories'));
    }

    /**
     * Single product page.
     */
    public function product(Product $product): View
    {
        $this->ensureProductAccessible($product);

        $product->load('categories');

        $primaryCategoryId = $product->categories->first()?->getKey();

        $owner = OwnerContext::resolve();

        $relatedProducts = Product::query()
            ->when(
                $owner,
                fn ($builder) => $builder->forOwner($owner),
                fn ($builder) => $builder->whereRaw('1 = 0'),
            )
            ->with('categories')
            ->where('status', ProductStatus::Active)
            ->where('id', '!=', $product->id)
            ->when($primaryCategoryId, fn ($q) => $q->whereHas('categories', fn ($sub) => $sub->whereKey($primaryCategoryId)))
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
        $cartSubtotal = Cart::isEmpty() ? 0 : Cart::getRawSubtotalWithoutConditions();
        $cartDiscountedSubtotal = Cart::isEmpty() ? 0 : Cart::getRawSubtotal();
        $cartQuantity = Cart::getTotalQuantity();

        $appliedVoucher = session('applied_voucher');
        $appliedVouchers = Cart::getAppliedVoucherCodes();
        $conditionBreakdown = Cart::getConditions()->toDetailedArray($cartSubtotal);

        $owner = OwnerContext::resolve();

        $activeVouchers = Voucher::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status', VoucherStatus::normalize(Active::class))
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

        return view('shop.cart', compact(
            'cartItems',
            'cartSubtotal',
            'cartDiscountedSubtotal',
            'cartQuantity',
            'activeVouchers',
            'appliedVoucher',
            'appliedVouchers',
            'conditionBreakdown',
        ));
    }

    /**
     * Add item to cart.
     */
    public function addToCart(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id' => ['required', 'string'],
            'quantity' => 'required|integer|min:1',
        ]);

        $owner = OwnerContext::resolve();

        $product = Product::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->whereKey($request->product_id)
            ->firstOrFail();

        if (! $product->isPurchasable()) {
            return back()->with('error', 'Sorry, this product is not available for purchase.');
        }

        $quantity = max(1, (int) $request->quantity);

        /** @var PriceCalculator $priceCalculator */
        $priceCalculator = app(PriceCalculator::class);

        $priceResult = $priceCalculator->calculate($product, $quantity, [
            'currency' => 'MYR',
        ]);

        Cart::add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $priceResult->finalPrice,
            'quantity' => $quantity,
            'associated_model' => $product,
            'attributes' => [
                'sku' => $product->sku,
                'category' => $product->categories->first()?->name,
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

        $quantity = max(1, (int) $request->quantity);

        $owner = OwnerContext::resolve();

        $product = Product::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->whereKey($itemId)
            ->first();

        $unitPrice = null;

        if ($product !== null) {
            /** @var PriceCalculator $priceCalculator */
            $priceCalculator = app(PriceCalculator::class);

            $priceResult = $priceCalculator->calculate($product, $quantity, [
                'currency' => 'MYR',
            ]);

            $unitPrice = $priceResult->finalPrice;
        }

        Cart::update($itemId, [
            'quantity' => [
                'relative' => false,
                'value' => $quantity,
            ],
            ...($unitPrice !== null ? ['price' => $unitPrice] : []),
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

            return back()->with('success', 'Voucher ' . mb_strtoupper($request->voucher_code) . ' applied!');
        } catch (InvalidVoucherException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove voucher from cart.
     */
    public function removeVoucher(Request $request): RedirectResponse
    {
        $voucherCode = $request->input('voucher_code');

        if ($voucherCode) {
            Cart::removeVoucher($voucherCode);

            // Also clean up session if it matches (for backwards compatibility/simplicity)
            $appliedInSession = session('applied_voucher');
            if ($appliedInSession === mb_strtoupper((string) $voucherCode)) {
                session()->forget('applied_voucher');
            }

            return back()->with('success', 'Voucher ' . mb_strtoupper((string) $voucherCode) . ' removed.');
        }

        // Fallback for when no code provided (shouldn't happen with new UI)
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
    public function checkout(): View | RedirectResponse
    {
        if (Cart::isEmpty()) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty.');
        }

        $this->removeDemoShippingCondition();

        // Mark cart as checkout started for recovery tracking
        $identifier = Cart::getIdentifier();
        $snapshot = CartSnapshot::query()->forOwner()->where('identifier', $identifier)->first();

        if ($snapshot !== null) {
            $snapshot->markCheckoutStarted();
        }

        $items = Cart::getItems();
        $subtotalWithoutConditions = Cart::getRawSubtotalWithoutConditions();
        $subtotal = Cart::getRawSubtotal();
        $conditions = Cart::getConditions();
        $conditionBreakdown = $conditions->toDetailedArray($subtotalWithoutConditions);

        $selectedShippingMethod = old('shipping_method', 'jnt_standard');

        if (! is_string($selectedShippingMethod) || ! array_key_exists($selectedShippingMethod, $this->shippingMethodCosts())) {
            $selectedShippingMethod = 'jnt_standard';
        }

        if ($selectedShippingMethod === 'free' && $subtotal < 10_000) {
            $selectedShippingMethod = 'jnt_standard';
        }

        $selectedState = old('state');
        $selectedState = is_string($selectedState) && $selectedState !== '' ? $selectedState : 'Selangor';

        $selectedPostcode = old('postcode');
        $selectedPostcode = is_string($selectedPostcode) && $selectedPostcode !== '' ? $selectedPostcode : '50000';

        $shippingSummaries = $this->calculateCheckoutSummaries(
            subtotalAfterConditions: $subtotal,
            state: $selectedState,
            postcode: $selectedPostcode,
        );

        $selectedShippingSummary = $shippingSummaries[$selectedShippingMethod] ?? $shippingSummaries['jnt_standard'];
        $estimatedTax = $selectedShippingSummary['tax'];
        $estimatedGrandTotal = $selectedShippingSummary['grand_total'];

        return view('shop.checkout', compact(
            'items',
            'subtotalWithoutConditions',
            'subtotal',
            'conditions',
            'conditionBreakdown',
            'selectedShippingMethod',
            'shippingSummaries',
            'estimatedTax',
            'estimatedGrandTotal',
        ));
    }

    /**
     * Process checkout through the Checkout package and redirect to the active payment gateway.
     */
    public function processCheckout(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'phone' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'line1' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'postcode' => 'required|string',
            'shipping_method' => 'required|in:jnt_standard,jnt_express,free',
            'payment_method' => 'required|in:fpx,card,ewallet',
        ]);

        if (Cart::isEmpty()) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty.');
        }

        if ($request->shipping_method === 'free' && (int) Cart::getRawSubtotal() < 10_000) {
            return redirect()->route('shop.checkout')
                ->withErrors([
                    'shipping_method' => 'Free shipping is only available for orders over RM 100.00.',
                ])
                ->withInput();
        }

        $cartId = Cart::getId();

        if (! is_string($cartId) || $cartId === '') {
            return redirect()->route('shop.checkout')
                ->with('error', 'Unable to resolve the active cart for checkout.')
                ->withInput();
        }

        $this->applySelectedShippingToCart($request->shipping_method);

        try {
            $checkoutSession = Checkout::startCheckout($cartId);
            $experimentContext = \experiment()?->toArray();
            $growthVisitorId = $this->resolveGrowthVisitorId($request);
            $billingData = $this->buildCheckoutBillingData($request);
            $billingMetadata = is_array(data_get($billingData, 'metadata')) ? $billingData['metadata'] : [];

            if ($growthVisitorId !== null) {
                $billingMetadata['growth_visitor_id'] = $growthVisitorId;
            }

            if ($experimentContext !== null) {
                $billingMetadata['experiment_contexts'] = [$experimentContext];
            }

            if ($billingMetadata !== []) {
                $billingData['metadata'] = $billingMetadata;
            }

            $paymentData = array_merge($checkoutSession->payment_data ?? [], [
                'requested_payment_method' => (string) $request->payment_method,
                'storefront' => 'demo',
            ]);

            if ($growthVisitorId !== null) {
                $paymentData['growth_visitor_id'] = $growthVisitorId;
            }

            if ($experimentContext !== null) {
                $paymentData['experiment_contexts'] = [$experimentContext];
            }

            $checkoutSession->update([
                'billing_data' => $billingData,
                'shipping_data' => $this->buildCheckoutShippingData($request),
                'selected_shipping_method' => $request->shipping_method,
                'selected_payment_gateway' => $this->determineCheckoutPaymentGateway(),
                'payment_data' => $paymentData,
            ]);

            $result = Checkout::processCheckout($checkoutSession->fresh());

            if ($result->requiresRedirect() && is_string($result->redirectUrl)) {
                return redirect()->to($result->redirectUrl);
            }

            if ($result->success && is_string($result->orderId) && $result->orderId !== '') {
                return redirect()->route('shop.order.success', ['order' => $result->orderId])
                    ->with('success', 'Checkout completed successfully.');
            }

            return redirect()->route('shop.checkout')
                ->withErrors($result->errors !== [] ? $result->errors : ['checkout' => $result->message ?? 'Checkout failed.'])
                ->withInput();
        } catch (Throwable $exception) {
            Log::error('Demo checkout failed', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return redirect()->route('shop.checkout')
                ->with('error', 'Checkout failed: ' . $exception->getMessage())
                ->withInput();
        }
    }

    /**
     * Handle successful payment redirect from CHIP.
     */
    public function paymentSuccess(Order $order): View
    {
        $this->ensureOrderAccessible($order);

        // Clear cart and session
        Cart::clear();
        Cart::clearConditions();
        session()->forget(['cart_count', 'applied_voucher', 'pending_order_id', 'pending_affiliate_code', 'pending_voucher_code']);

        // For demo: Simulate webhook if payment is still pending
        // In production, CHIP sends the webhook to a public URL automatically
        if ((string) $order->status === 'pending_payment' && ($order->metadata['chip_purchase_id'] ?? null)) {
            $this->simulatePaymentWebhook($order);
            $order->refresh(); // Reload to get updated status
        }

        $order->load('items', 'shippingAddress', 'billingAddress');

        return view('shop.payment-success', compact('order'));
    }

    /**
     * Handle failed payment redirect from CHIP.
     */
    public function paymentFailed(Order $order): View
    {
        $this->ensureOrderAccessible($order);

        $order->update(['status' => 'payment_failed']);

        return view('shop.payment-failed', compact('order'));
    }

    /**
     * Handle cancelled payment redirect from CHIP.
     */
    public function paymentCancelled(Order $order): View
    {
        $this->ensureOrderAccessible($order);

        $order->update(['status' => 'canceled']);

        return view('shop.payment-cancelled', compact('order'));
    }

    /**
     * Order success page (for viewing existing completed orders).
     */
    public function orderSuccess(Order $order): View
    {
        $this->ensureOrderAccessible($order);

        $order->load('items', 'shippingAddress', 'billingAddress');

        return view('shop.order-success', compact('order'));
    }

    /**
     * Track affiliate from URL.
     */
    public function trackAffiliate(Request $request, string $code): RedirectResponse
    {
        $owner = OwnerContext::resolve();

        $affiliate = Affiliate::where('code', mb_strtoupper($code))
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
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
            'product_id' => ['required', 'string'],
            'quantity' => 'required|integer|min:1',
        ]);

        $owner = OwnerContext::resolve();

        $product = Product::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->whereKey($request->product_id)
            ->firstOrFail();

        if (! $product->isInStock()) {
            return back()->with('error', 'Sorry, this product is out of stock.');
        }

        $quantity = max(1, (int) $request->quantity);

        /** @var PriceCalculator $priceCalculator */
        $priceCalculator = app(PriceCalculator::class);

        $priceResult = $priceCalculator->calculate($product, $quantity, [
            'currency' => 'MYR',
        ]);

        // Clear cart and add single item
        Cart::clear();
        Cart::clearConditions();

        Cart::add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $priceResult->finalPrice,
            'quantity' => $quantity,
            'associated_model' => $product,
            'attributes' => [
                'sku' => $product->sku,
                'category' => $product->categories->first()?->name,
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
        $owner = OwnerContext::resolve();

        $orders = Order::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with('items', 'shippingAddress')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('shop.orders', compact('orders'));
    }

    /**
     * Account page.
     */
    public function account(): View | RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('shop.home')->with('error', 'Please sign in to view your account.');
        }

        $user = Auth::user();
        $owner = OwnerContext::resolve();

        $recentOrders = Order::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with('items', 'shippingAddress')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('shop.account', compact('user', 'recentOrders'));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheckoutBillingData(Request $request): array
    {
        return [
            'first_name' => (string) $request->first_name,
            'last_name' => (string) $request->last_name,
            'name' => mb_trim((string) $request->first_name . ' ' . (string) $request->last_name),
            'email' => (string) $request->email,
            'phone' => (string) $request->phone,
            'line1' => (string) $request->line1,
            'line2' => $request->filled('line2') ? (string) $request->line2 : null,
            'city' => (string) $request->city,
            'state' => (string) $request->state,
            'postcode' => (string) $request->postcode,
            'country' => 'MY',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheckoutShippingData(Request $request): array
    {
        return $this->buildCheckoutBillingData($request);
    }

    private function resolveGrowthVisitorId(Request $request): ?string
    {
        if ($request->hasSession()) {
            $stickyVisitorId = $request->session()->get('demo_growth_checkout_subject');

            if (is_scalar($stickyVisitorId)) {
                $normalizedStickyVisitorId = mb_trim((string) $stickyVisitorId);

                if ($normalizedStickyVisitorId !== '') {
                    return $normalizedStickyVisitorId;
                }
            }
        }

        try {
            /** @var SignalsBrowserContextManager $browserContextManager */
            $browserContextManager = app(SignalsBrowserContextManager::class);
            $browserContext = $browserContextManager->current($request) ?? $browserContextManager->resolveOrCreate($request);

            if ($browserContext->visitorId !== '') {
                return $browserContext->visitorId;
            }
        } catch (Throwable) {
        }

        $configuredVisitorId = $request->cookie(
            (string) config('signals.integrations.browser.identifiers.visitor_cookie_name', 'sig_vid'),
        );

        if (is_scalar($configuredVisitorId)) {
            $normalizedConfiguredVisitorId = mb_trim((string) $configuredVisitorId);

            if ($normalizedConfiguredVisitorId !== '') {
                return $normalizedConfiguredVisitorId;
            }
        }

        $cartIdentifier = Cart::getIdentifier();

        if ($cartIdentifier !== '') {
            return $cartIdentifier;
        }

        if (! $request->hasSession()) {
            return null;
        }

        $sessionId = $request->session()->getId();

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    private function determineCheckoutPaymentGateway(): string
    {
        $chipApiKey = config('chip.collect.api_key');
        $chipBrandId = config('chip.collect.brand_id');

        return is_string($chipApiKey) && $chipApiKey !== '' && is_string($chipBrandId) && $chipBrandId !== ''
            ? 'chip'
            : 'demo';
    }

    private function applySelectedShippingToCart(string $shippingMethod): void
    {
        $this->removeDemoShippingCondition();

        $attributes = match ($shippingMethod) {
            'jnt_express' => [
                'carrier' => 'jnt',
                'service' => 'express',
                'estimated_days' => 2,
                'currency' => 'MYR',
            ],
            'free' => [
                'carrier' => 'promotion',
                'service' => 'free',
                'estimated_days' => 4,
                'currency' => 'MYR',
            ],
            default => [
                'carrier' => 'jnt',
                'service' => 'standard',
                'estimated_days' => 4,
                'currency' => 'MYR',
            ],
        };

        $condition = new ShippingCondition(
            name: $this->demoShippingConditionName(),
            type: 'shipping',
            value: $this->shippingCostForMethod($shippingMethod),
            attributes: $attributes,
        );

        Cart::addCondition($condition->asCartCondition());
    }

    private function removeDemoShippingCondition(): void
    {
        Cart::removeCondition($this->demoShippingConditionName());
    }

    private function demoShippingConditionName(): string
    {
        return 'demo_checkout_shipping';
    }

    private function ensureOrderAccessible(Order $order): void
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            abort(404);
        }

        if ($order->owner_type === null || $order->owner_id === null) {
            abort(404);
        }

        if (
            $order->owner_type !== $owner->getMorphClass()
            || (string) $order->owner_id !== (string) $owner->getKey()
        ) {
            abort(404);
        }
    }

    private function ensureProductAccessible(Product $product): void
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            abort(404);
        }

        if ($product->owner_type === null || $product->owner_id === null) {
            abort(404);
        }

        if (
            $product->owner_type !== $owner->getMorphClass()
            || (string) $product->owner_id !== (string) $owner->getKey()
        ) {
            abort(404);
        }
    }

    /**
     * Order tracking page.
     */
    public function tracking(Request $request): View
    {
        $shipment = null;
        $recentShipments = null;

        $owner = OwnerContext::resolve();

        // Search for shipment if query provided
        $search = $request->get('tracking_number', $request->get('q', ''));

        if (is_string($search) && $search !== '') {
            $query = mb_strtoupper($search);

            $shipment = JntOrder::query()
                ->when(
                    $owner,
                    fn ($builder) => $builder->forOwner($owner),
                    fn ($builder) => $builder->whereRaw('1 = 0'),
                )
                ->where(function ($builder) use ($query): void {
                    $builder->where('tracking_number', $query)
                        ->orWhere('order_id', $query);
                })
                ->with('trackingEvents')
                ->first();
        }

        // Get recent shipments for display
        $recentShipments = JntOrder::query()
            ->when(
                $owner,
                fn ($builder) => $builder->forOwner($owner),
                fn ($builder) => $builder->whereRaw('1 = 0'),
            )
            ->whereNotNull('tracking_number')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('shop.tracking', compact('shipment', 'recentShipments'));
    }

    /**
     * Track shipment search (redirect).
     */
    public function trackingSearch(Request $request): RedirectResponse
    {
        $q = $request->get('tracking_number', '');

        return redirect()->route('shop.tracking', ['tracking_number' => $q]);
    }

    /**
     * Simulate CHIP payment webhook for demo purposes.
     * In production, CHIP sends webhooks to a public URL with signature verification.
     */
    private function simulatePaymentWebhook(Order $order): void
    {
        $purchaseId = $order->metadata['chip_purchase_id'] ?? null;

        if (is_string($purchaseId) && $purchaseId !== '' && ! str_starts_with($purchaseId, 'demo-')) {
            try {
                $purchase = Chip::getPurchase($purchaseId);
                $payload = [
                    ...$purchase->toArray(),
                    'event_type' => 'purchase.paid',
                ];

                WebhookReceived::dispatch('purchase.paid', $payload, $purchase);
                PurchasePaid::dispatch($purchase, $payload);

                return;
            } catch (Exception $exception) {
                Log::warning('Falling back to simulated CHIP webhook after purchase sync failed.', [
                    'order_id' => $order->id,
                    'chip_purchase_id' => $purchaseId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $shipping = $order->shippingAddress;
        $streetAddress = $shipping?->line1 ?? '';

        $simulator = WebhookSimulator::paid()
            ->purchaseId((string) $purchaseId)
            ->reference($order->order_number)
            ->amount($order->grand_total)
            ->customer(
                $shipping?->email ?? 'demo@example.com',
                $shipping?->getFullName() ?? 'Demo Customer',
                $shipping?->phone ?? '+60123456789'
            )
            ->with([
                'client' => [
                    'street_address' => $streetAddress,
                    'city' => $shipping?->city ?? '',
                    'state' => $shipping?->state ?? '',
                    'zip_code' => $shipping?->postcode ?? '',
                    'country' => 'MY',
                    'shipping_street_address' => $streetAddress,
                    'shipping_city' => $shipping?->city ?? '',
                    'shipping_state' => $shipping?->state ?? '',
                    'shipping_zip_code' => $shipping?->postcode ?? '',
                    'shipping_country' => 'MY',
                ],
                'purchase' => [
                    'total' => $order->grand_total,
                    'currency' => 'MYR',
                    'metadata' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ],
                    'products' => [
                        ...$order->items
                            ->flatMap(fn (OrderItem $item): array => $this->buildChipProductLines($item))
                            ->map(fn (array $productLine): array => [
                                'name' => $productLine['name'],
                                'price' => $productLine['price'],
                                'quantity' => (string) $productLine['quantity'],
                                'category' => $productLine['category'],
                                'discount' => $productLine['discount'],
                                'tax_percent' => '0.00',
                            ])
                            ->values()
                            ->all(),
                        ...($order->shipping_total > 0 ? [[
                            'name' => 'Shipping (' . $this->shippingMethodLabel((string) ($order->metadata['shipping_method'] ?? 'jnt_standard')) . ')',
                            'price' => $order->shipping_total,
                            'quantity' => '1',
                            'category' => 'shipping',
                            'discount' => 0,
                            'tax_percent' => '0.00',
                        ]] : []),
                        ...($order->tax_total > 0 ? [[
                            'name' => 'Sales Tax',
                            'price' => $order->tax_total,
                            'quantity' => '1',
                            'category' => 'tax',
                            'discount' => 0,
                            'tax_percent' => '0.00',
                        ]] : []),
                    ],
                ],
            ])
            ->fpx()
            ->isTest();

        $simulator->dispatch();
    }

    /**
     * @return array<string, array{cost: int, tax: int, grand_total: int, label: string}>
     */
    private function calculateCheckoutSummaries(int $subtotalAfterConditions, string $state, string $postcode): array
    {
        /** @var TaxCalculator $taxCalculator */
        $taxCalculator = app(TaxCalculator::class);

        $taxContext = [
            'shipping_address' => [
                'country' => 'MY',
                'state' => $state,
                'postcode' => $postcode,
            ],
        ];

        $itemsTax = 0;

        try {
            $itemsTax = $taxCalculator
                ->calculateTax(max(0, $subtotalAfterConditions), 'standard', null, $taxContext)
                ->taxAmount;
        } catch (QueryException $exception) {
            Log::warning('Checkout tax estimate skipped due to missing DB tables.', [
                'message' => $exception->getMessage(),
            ]);
        }

        $summaries = [];

        foreach ($this->shippingMethodCosts() as $method => $cost) {
            $shippingTax = 0;

            try {
                $shippingTax = $taxCalculator
                    ->calculateShippingTax($cost, null, $taxContext)
                    ->taxAmount;
            } catch (QueryException $exception) {
                Log::warning('Checkout shipping tax estimate skipped due to missing DB tables.', [
                    'message' => $exception->getMessage(),
                    'shipping_method' => $method,
                ]);
            }

            $summaries[$method] = [
                'cost' => $cost,
                'tax' => $itemsTax + $shippingTax,
                'grand_total' => max(0, $subtotalAfterConditions) + $cost + $itemsTax + $shippingTax,
                'label' => $this->shippingMethodLabel($method),
            ];
        }

        return $summaries;
    }

    /**
     * @return array<int, array{name: string, price: int, quantity: int, discount: int, category: string}>
     */
    private function buildChipProductLines(OrderItem $item): array
    {
        $lines = [];

        foreach ($this->splitCentsAcrossQuantity($item->discount_amount, $item->quantity) as $unitDiscount) {
            $lines[] = [
                'name' => $item->name,
                'price' => $item->unit_price,
                'quantity' => 1,
                'discount' => $unitDiscount,
                'category' => 'product',
            ];
        }

        return $lines;
    }

    /**
     * @return array<int, int>
     */
    private function splitCentsAcrossQuantity(int $amount, int $quantity): array
    {
        $quantity = max(1, $quantity);
        $amount = max(0, $amount);
        $baseAmount = intdiv($amount, $quantity);
        $remainder = $amount % $quantity;
        $parts = array_fill(0, $quantity, $baseAmount);

        for ($index = $quantity - $remainder; $index < $quantity; $index++) {
            if ($index >= 0 && array_key_exists($index, $parts)) {
                $parts[$index]++;
            }
        }

        return $parts;
    }

    /**
     * @return array<string, int>
     */
    private function shippingMethodCosts(): array
    {
        return [
            'jnt_standard' => 800,
            'jnt_express' => 1500,
            'free' => 0,
        ];
    }

    private function shippingCostForMethod(string $shippingMethod): int
    {
        return $this->shippingMethodCosts()[$shippingMethod] ?? 800;
    }

    private function shippingMethodLabel(string $shippingMethod): string
    {
        return match ($shippingMethod) {
            'jnt_express' => 'J&T Express',
            'free' => 'Free Shipping',
            default => 'J&T Standard',
        };
    }
}
