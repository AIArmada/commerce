` <x-shop-layout title="Shopping Cart">
    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart</h1>

        @if($cartItems->count() > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2 space-y-4">
                @foreach($cartItems as $item)
                <div class="bg-white rounded-xl shadow p-6 flex gap-6">
                    <!-- Product Image -->
                    <div class="w-24 h-24 bg-gray-100 rounded-lg flex items-center justify-center text-4xl flex-shrink-0">
                        📦
                    </div>

                    <!-- Product Details -->
                    <div class="flex-1">
                        <div class="flex justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ $item->name }}</h3>
                                <p class="text-sm text-gray-500">SKU: {{ $item->attributes['sku'] ?? 'N/A' }}</p>
                            </div>
                            <form action="{{ route('shop.cart.remove', $item->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-gray-400 hover:text-red-500">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </form>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <!-- Quantity -->
                            <form action="{{ route('shop.cart.update', $item->id) }}" method="POST" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <div class="flex items-center border rounded-lg">
                                    <button type="submit" name="quantity" value="{{ max(1, $item->quantity - 1) }}"
                                            class="px-3 py-1 text-gray-600 hover:bg-gray-100">−</button>
                                    <span class="w-12 text-center py-1">{{ $item->quantity }}</span>
                                    <button type="submit" name="quantity" value="{{ $item->quantity + 1 }}"
                                            class="px-3 py-1 text-gray-600 hover:bg-gray-100">+</button>
                                </div>
                            </form>

                            <!-- Price -->
                            <div class="text-right">
                                <p class="text-lg font-bold text-gray-900">RM {{ number_format($item->getRawSubtotal() / 100, 2) }}</p>
                                <p class="text-sm text-gray-500">RM {{ number_format($item->price / 100, 2) }} each</p>
                            </div>
                        </div>

                        <!-- Item Conditions (discounts) -->
                        @if($item->conditions && count($item->conditions) > 0)
                        <div class="mt-3 space-y-1">
                            @foreach($item->conditions as $condition)
                            <div class="flex items-center gap-2 text-sm text-green-600">
                                <span>🎫</span>
                                <span>{{ $condition->getName() }}: -RM {{ number_format(abs((float) $condition->getValue()) / 100, 2) }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach

                <!-- Continue Shopping -->
                <div class="text-center py-4">
                    <a href="{{ route('shop.products') }}" class="text-amber-600 hover:text-amber-700 font-medium">
                        ← Continue Shopping
                    </a>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow p-6 sticky top-24">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Order Summary</h2>

                    <!-- Apply Voucher -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Voucher Code</label>
                        <form action="{{ route('shop.cart.voucher') }}" method="POST">
                            @csrf
                            <div class="flex gap-2">
                                <input type="text" name="voucher_code" placeholder="Enter code" 
                                       value="{{ $appliedVoucher ?? '' }}"
                                       class="flex-1 border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                <button type="submit" 
                                        class="bg-gray-900 text-white px-4 py-2 rounded-lg font-medium hover:bg-gray-800">
                                    Apply
                                </button>
                            </div>
                        </form>
                        @if($appliedVoucher)
                        <div class="mt-2 flex items-center justify-between text-sm">
                            <span class="text-green-600">✓ Voucher applied: {{ $appliedVoucher }}</span>
                            <form action="{{ route('shop.cart.voucher.remove') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-red-500 hover:text-red-600">Remove</button>
                            </form>
                        </div>
                        @endif
                    </div>

                    <!-- Totals -->
                    <div class="space-y-3 border-t pt-4">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal ({{ $cartQuantity }} items)</span>
                            <span>RM {{ number_format($cartSubtotal / 100, 2) }}</span>
                        </div>

                        @if($appliedVoucher)
                        <div class="flex justify-between text-green-600">
                            <span>🎫 Voucher Discount</span>
                            <span>-RM {{ number_format(($cartSubtotal - $cartTotal) / 100, 2) }}</span>
                        </div>
                        @endif

                        <div class="flex justify-between text-gray-600">
                            <span>Shipping</span>
                            <span class="text-green-600">Free</span>
                        </div>

                        <hr>

                        <div class="flex justify-between text-xl font-bold text-gray-900">
                            <span>Total</span>
                            <span>RM {{ number_format($cartTotal / 100, 2) }}</span>
                        </div>
                    </div>

                    <!-- Checkout Button -->
                    <a href="{{ route('shop.checkout') }}" 
                       class="mt-6 block w-full bg-amber-500 hover:bg-amber-600 text-white text-center py-3 rounded-lg font-semibold text-lg transition">
                        Proceed to Checkout
                    </a>

                    <!-- Affiliate Info -->
                    @if(session('affiliate_code'))
                    <div class="mt-4 p-3 bg-green-50 rounded-lg text-sm">
                        <p class="text-green-800">
                            🤝 You're supporting affiliate: <strong>{{ session('affiliate_code') }}</strong>
                        </p>
                    </div>
                    @endif

                    <!-- Payment Methods -->
                    <div class="mt-6 text-center">
                        <p class="text-xs text-gray-500 mb-2">Secure payment with</p>
                        <div class="flex justify-center gap-4 text-2xl">
                            <span title="Credit Card">💳</span>
                            <span title="FPX">🏦</span>
                            <span title="E-Wallet">📱</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @else
        <!-- Empty Cart -->
        <div class="text-center py-16">
            <div class="text-8xl mb-6">🛒</div>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Your cart is empty</h2>
            <p class="text-gray-500 mb-8">Looks like you haven't added any products yet.</p>
            <a href="{{ route('shop.products') }}" 
               class="inline-block bg-amber-500 hover:bg-amber-600 text-white px-8 py-3 rounded-lg font-semibold transition">
                Start Shopping
            </a>
        </div>
        @endif

        <!-- Active Vouchers Hint -->
        @if($activeVouchers->count() > 0)
        <div class="mt-12 bg-amber-50 rounded-xl p-6">
            <h3 class="font-semibold text-amber-800 mb-4">🎉 Available Vouchers</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($activeVouchers->take(3) as $voucher)
                <div class="bg-white rounded-lg p-4 border border-amber-200">
                    <p class="font-mono font-bold text-amber-600">{{ $voucher->code }}</p>
                    <p class="text-sm text-gray-600">
                        @if($voucher->type->value === 'percentage')
                            {{ $voucher->value / 100 }}% OFF
                        @else
                            RM {{ number_format($voucher->value / 100, 2) }} OFF
                        @endif
                    </p>
                    @if($voucher->min_cart_value)
                    <p class="text-xs text-gray-500">Min. order: RM {{ number_format($voucher->min_cart_value / 100, 2) }}</p>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</x-shop-layout>
