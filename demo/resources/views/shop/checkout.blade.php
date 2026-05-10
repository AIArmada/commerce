<x-shop-layout title="Checkout">
    @php
        $states = [
            'Johor',
            'Kedah',
            'Kelantan',
            'Melaka',
            'Negeri Sembilan',
            'Pahang',
            'Perak',
            'Perlis',
            'Pulau Pinang',
            'Sabah',
            'Sarawak',
            'Selangor',
            'Terengganu',
            'Kuala Lumpur',
            'Labuan',
            'Putrajaya',
        ];
        $selectedPaymentMethod = old('payment_method', 'fpx');
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>

        <form action="{{ route('shop.checkout.process') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Checkout Form -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Contact Information -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Contact Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" required
                                       value="{{ auth()->user()?->email ?? old('email') }}"
                                       class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="tel" name="phone" required
                                       value="{{ old('phone') }}"
                                       placeholder="+60123456789"
                                       class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping Address</h2>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                    <input type="text" name="first_name" required
                                           value="{{ old('first_name') }}"
                                           class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                    <input type="text" name="last_name" required
                                           value="{{ old('last_name') }}"
                                           class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                                <input type="text" name="line1" required
                                    value="{{ old('line1') }}"
                                       placeholder="Street address"
                                       class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 2 (Optional)</label>
                                <input type="text" name="line2"
                                    value="{{ old('line2') }}"
                                       placeholder="Apartment, suite, unit, etc."
                                       class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                    <input type="text" name="city" required
                                           value="{{ old('city') }}"
                                           class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                    <select name="state" required
                                            class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                        <option value="">Select</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state }}" @selected(old('state', 'Selangor') === $state)>{{ $state }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                                    <input type="text" name="postcode" required
                                           value="{{ old('postcode', '50000') }}"
                                           class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Method -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping Method</h2>
                        <div class="space-y-3">
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer {{ $selectedShippingMethod === 'jnt_standard' ? 'border-amber-500 bg-amber-50' : 'hover:border-amber-300' }}">
                                <input type="radio" name="shipping_method" value="jnt_standard" {{ $selectedShippingMethod === 'jnt_standard' ? 'checked' : '' }}
                                       class="text-amber-500 focus:ring-amber-500">
                                <div class="ml-4 flex-1">
                                    <div class="flex justify-between">
                                        <span class="font-medium text-gray-900">J&T Standard</span>
                                        <span class="font-medium text-gray-900">RM 8.00</span>
                                    </div>
                                    <p class="text-sm text-gray-500">3-5 business days</p>
                                </div>
                            </label>
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer {{ $selectedShippingMethod === 'jnt_express' ? 'border-amber-500 bg-amber-50' : 'hover:border-amber-300' }}">
                                <input type="radio" name="shipping_method" value="jnt_express" {{ $selectedShippingMethod === 'jnt_express' ? 'checked' : '' }}
                                       class="text-amber-500 focus:ring-amber-500">
                                <div class="ml-4 flex-1">
                                    <div class="flex justify-between">
                                        <span class="font-medium text-gray-900">J&T Express</span>
                                        <span class="font-medium text-gray-900">RM 15.00</span>
                                    </div>
                                    <p class="text-sm text-gray-500">1-2 business days</p>
                                </div>
                            </label>
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer {{ $selectedShippingMethod === 'free' ? 'border-amber-500 bg-amber-50' : 'hover:border-amber-300' }}">
                                <input type="radio" name="shipping_method" value="free"
                                       {{ $selectedShippingMethod === 'free' && $subtotal >= 10000 ? 'checked' : '' }}
                                       class="text-amber-500 focus:ring-amber-500"
                                       {{ $subtotal >= 10000 ? '' : 'disabled' }}>
                                <div class="ml-4 flex-1">
                                    <div class="flex justify-between">
                                        <span class="font-medium text-gray-900 {{ $subtotal < 10000 ? 'text-gray-400' : '' }}">Free Shipping</span>
                                        <span class="font-medium text-green-600">FREE</span>
                                    </div>
                                    <p class="text-sm {{ $subtotal < 10000 ? 'text-red-500' : 'text-gray-500' }}">
                                        {{ $subtotal >= 10000 ? 'Available for orders over RM100' : 'Spend RM'.number_format((10000 - $subtotal) / 100, 2).' more to qualify' }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Payment Method</h2>
                        <p class="text-sm text-gray-600 mb-4">Powered by CHIP - Malaysia's trusted payment gateway</p>
                        <div class="space-y-3">
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer {{ $selectedPaymentMethod === 'fpx' ? 'border-amber-500 bg-amber-50' : 'hover:border-amber-300' }}">
                                <input type="radio" name="payment_method" value="fpx" {{ $selectedPaymentMethod === 'fpx' ? 'checked' : '' }}
                                       class="text-amber-500 focus:ring-amber-500">
                                <div class="ml-4 flex items-center gap-3">
                                    <span class="text-2xl">🏦</span>
                                    <div>
                                        <span class="font-medium text-gray-900">Online Banking (FPX)</span>
                                        <p class="text-sm text-gray-500">Pay directly from your bank</p>
                                    </div>
                                </div>
                            </label>
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer {{ $selectedPaymentMethod === 'card' ? 'border-amber-500 bg-amber-50' : 'hover:border-amber-300' }}">
                                <input type="radio" name="payment_method" value="card" {{ $selectedPaymentMethod === 'card' ? 'checked' : '' }}
                                       class="text-amber-500 focus:ring-amber-500">
                                <div class="ml-4 flex items-center gap-3">
                                    <span class="text-2xl">💳</span>
                                    <div>
                                        <span class="font-medium text-gray-900">Credit/Debit Card</span>
                                        <p class="text-sm text-gray-500">Visa, Mastercard, AMEX</p>
                                    </div>
                                </div>
                            </label>
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer {{ $selectedPaymentMethod === 'ewallet' ? 'border-amber-500 bg-amber-50' : 'hover:border-amber-300' }}">
                                <input type="radio" name="payment_method" value="ewallet" {{ $selectedPaymentMethod === 'ewallet' ? 'checked' : '' }}
                                       class="text-amber-500 focus:ring-amber-500">
                                <div class="ml-4 flex items-center gap-3">
                                    <span class="text-2xl">📱</span>
                                    <div>
                                        <span class="font-medium text-gray-900">E-Wallet</span>
                                        <p class="text-sm text-gray-500">Touch 'n Go, GrabPay, Boost</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Order Notes (Optional)</h2>
                        <textarea name="notes" rows="3" 
                                  placeholder="Special instructions for your order..."
                                  class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow p-6 sticky top-24">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Order Summary</h2>

                        <!-- Cart Items -->
                        <div class="space-y-4 max-h-64 overflow-y-auto mb-4">
                            @foreach($items as $item)
                            <div class="flex gap-3">
                                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-xl">📦</div>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 text-sm line-clamp-1">{{ $item->name }}</p>
                                    <p class="text-sm text-gray-500">Qty: {{ $item->quantity }}</p>
                                </div>
                                <p class="font-medium text-gray-900 text-sm">RM {{ number_format($item->getRawSubtotal() / 100, 2) }}</p>
                            </div>
                            @endforeach
                        </div>

                        <hr class="my-4">

                        <!-- Totals -->
                        <div class="space-y-3">
                            <div class="flex justify-between text-gray-600">
                                <span>Subtotal</span>
                                <span>RM {{ number_format($subtotalWithoutConditions / 100, 2) }}</span>
                            </div>

                            @foreach($conditionBreakdown['conditions'] as $condition)
                            @php $conditionValue = (int) ($condition['calculated_value'] ?? 0); @endphp
                            <div class="flex justify-between {{ $conditionValue < 0 ? 'text-green-600' : 'text-gray-600' }}">
                                @php
                                    $conditionName = (string) ($condition['name'] ?? 'Condition');

                                    $displayConditionName = match (true) {
                                        str_starts_with($conditionName, 'voucher_') => 'Voucher Discount',
                                        str_starts_with($conditionName, 'affiliate_') => 'Affiliate Discount',
                                        default => $conditionName,
                                    };
                                @endphp
                                <span>{{ $displayConditionName }}</span>
                                <span>
                                    @if($conditionValue < 0)
                                        -RM {{ number_format(abs($conditionValue) / 100, 2) }}
                                    @else
                                        RM {{ number_format($conditionValue / 100, 2) }}
                                    @endif
                                </span>
                            </div>
                            @endforeach

                            <div class="flex justify-between text-gray-600" id="shipping-cost">
                                <span>Shipping</span>
                                <span id="shipping-cost-amount">{{ ($shippingSummaries[$selectedShippingMethod]['cost'] ?? 0) === 0 ? 'FREE' : 'RM '.number_format(($shippingSummaries[$selectedShippingMethod]['cost'] ?? 0) / 100, 2) }}</span>
                            </div>

                            <div class="flex justify-between text-gray-600" id="estimated-tax-row">
                                <span>Estimated Tax</span>
                                <span id="estimated-tax-amount">RM {{ number_format($estimatedTax / 100, 2) }}</span>
                            </div>

                            <hr>

                            <div class="flex justify-between text-xl font-bold text-gray-900">
                                <span>Total</span>
                                <span id="order-total">RM {{ number_format($estimatedGrandTotal / 100, 2) }}</span>
                            </div>
                        </div>

                        <!-- Applied Voucher -->
                        @if(session('applied_voucher'))
                        <div class="mt-4 p-3 bg-green-50 rounded-lg">
                            <p class="text-sm text-green-700">
                                ✓ Voucher <strong>{{ session('applied_voucher') }}</strong> applied
                            </p>
                        </div>
                        @endif

                        <!-- Affiliate -->
                        @if(session('affiliate_code'))
                        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                            <p class="text-sm text-blue-700">
                                🤝 Affiliate: <strong>{{ session('affiliate_code') }}</strong>
                            </p>
                        </div>
                        @endif

                        <!-- Submit -->
                        <button type="submit" 
                                class="mt-6 w-full bg-amber-500 hover:bg-amber-600 text-white py-3 rounded-lg font-semibold text-lg transition">
                            Place Order
                        </button>

                        <!-- Security Badge -->
                        <div class="mt-4 text-center text-xs text-gray-500">
                            <p>🔒 Secure checkout powered by CHIP</p>
                            <p class="mt-1">Your payment is protected</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        const shippingSummaries = @json($shippingSummaries);
        const shippingCostRow = document.getElementById('shipping-cost');
        const shippingCostAmount = document.getElementById('shipping-cost-amount');
        const estimatedTaxAmount = document.getElementById('estimated-tax-amount');
        const totalAmount = document.getElementById('order-total');

        const formatMoney = (cents) => `RM ${Number(cents / 100).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;

        const updateSummary = (shippingMethod) => {
            const summary = shippingSummaries[shippingMethod] ?? shippingSummaries.jnt_standard;

            if (summary.cost === 0) {
                shippingCostAmount.textContent = 'FREE';
                shippingCostRow.classList.add('text-green-600');
            } else {
                shippingCostAmount.textContent = formatMoney(summary.cost);
                shippingCostRow.classList.remove('text-green-600');
            }

            estimatedTaxAmount.textContent = formatMoney(summary.tax);
            totalAmount.textContent = formatMoney(summary.grand_total);
        };

        document.querySelectorAll('input[name="shipping_method"]').forEach((radio) => {
            radio.addEventListener('change', function () {
                updateSummary(this.value);
            });
        });

        updateSummary(document.querySelector('input[name="shipping_method"]:checked')?.value ?? 'jnt_standard');
    </script>
</x-shop-layout>
