<x-shop-layout title="Payment Successful">
    <div class="max-w-3xl mx-auto px-4 py-16 sm:px-6 lg:px-8 text-center">
        <!-- Success Icon -->
        <div class="mx-auto w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-8">
            <svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="text-3xl font-bold text-gray-900 mb-4">Payment Successful!</h1>
        <p class="text-xl text-gray-600 mb-2">Thank you for your purchase.</p>
        <p class="text-gray-500 mb-8">Order Number: <span class="font-mono font-bold text-gray-900">{{ $order->order_number }}</span></p>

        @if($order->payment_status !== 'paid')
        <!-- Processing Notice (only shown while waiting for webhook) -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-8">
            <div class="flex items-center justify-center gap-3 mb-3">
                <svg class="animate-spin h-5 w-5 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="font-semibold text-amber-700">Processing Payment Confirmation...</span>
            </div>
            <p class="text-sm text-amber-600">
                We're waiting for payment confirmation from CHIP. This usually takes a few seconds.
                Your order will be updated automatically once payment is confirmed.
            </p>
        </div>
        @else
        <!-- Payment Confirmed Notice -->
        <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-8">
            <div class="flex items-center justify-center gap-3 mb-3">
                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-semibold text-green-700">Payment Confirmed!</span>
            </div>
            <p class="text-sm text-green-600">
                Your payment has been confirmed and your order is being processed.
            </p>
        </div>
        @endif

        <!-- Order Summary Card -->
        <div class="bg-white rounded-2xl shadow-lg p-8 text-left mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Order Summary</h2>

            <!-- Status -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-6">
                <div>
                    <p class="text-sm text-gray-600">Order Status</p>
                    <p class="font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Payment Status</p>
                    <p class="font-semibold {{ $order->payment_status === 'paid' ? 'text-green-600' : 'text-amber-600' }}">
                        {{ ucfirst($order->payment_status) }}
                    </p>
                </div>
            </div>

            <!-- Items -->
            <div class="space-y-4 mb-6">
                @foreach($order->items as $item)
                <div class="flex justify-between items-center py-3 border-b">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-xl">📦</div>
                        <div>
                            <p class="font-medium text-gray-900">{{ $item->name }}</p>
                            <p class="text-sm text-gray-500">Qty: {{ $item->quantity }}</p>
                        </div>
                    </div>
                    <p class="font-medium text-gray-900">RM {{ number_format($item->total_price / 100, 2) }}</p>
                </div>
                @endforeach
            </div>

            <!-- Totals -->
            <div class="space-y-2 border-t pt-4">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span>RM {{ number_format($order->subtotal / 100, 2) }}</span>
                </div>
                @if($order->discount_total > 0)
                <div class="flex justify-between text-green-600">
                    <span>Discount</span>
                    <span>-RM {{ number_format($order->discount_total / 100, 2) }}</span>
                </div>
                @endif
                <div class="flex justify-between text-gray-600">
                    <span>Shipping</span>
                    <span>{{ $order->shipping_total > 0 ? 'RM '.number_format($order->shipping_total / 100, 2) : 'Free' }}</span>
                </div>
                <div class="flex justify-between text-xl font-bold text-gray-900 pt-2 border-t">
                    <span>Total</span>
                    <span>RM {{ number_format($order->grand_total / 100, 2) }}</span>
                </div>
            </div>

            <!-- Shipping Info -->
            <div class="mt-6 pt-6 border-t">
                <h3 class="font-semibold text-gray-900 mb-3">Shipping Address</h3>
                <div class="text-gray-600">
                    <p>{{ $order->shipping_address['name'] ?? '' }}</p>
                    <p>{{ $order->shipping_address['address_line_1'] ?? '' }}</p>
                    @if(!empty($order->shipping_address['address_line_2']))
                    <p>{{ $order->shipping_address['address_line_2'] }}</p>
                    @endif
                    <p>{{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['state'] ?? '' }} {{ $order->shipping_address['postcode'] ?? '' }}</p>
                </div>
            </div>

            <!-- CHIP Payment Info -->
            @if($order->metadata['chip_purchase_id'] ?? null)
            <div class="mt-6 pt-6 border-t">
                <div class="p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-gray-600">CHIP Transaction ID</p>
                    <p class="font-mono text-sm text-blue-700">{{ $order->metadata['chip_purchase_id'] }}</p>
                </div>
            </div>
            @endif
        </div>

        <!-- What's Next -->
        <div class="bg-gray-50 rounded-2xl p-8 mb-8">
            <h3 class="font-bold text-gray-900 mb-4">What's Next?</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                <div>
                    <div class="text-3xl mb-2">📧</div>
                    <p class="font-medium text-gray-900">Confirmation Email</p>
                    <p class="text-sm text-gray-500">Check your inbox for order details</p>
                </div>
                <div>
                    <div class="text-3xl mb-2">📦</div>
                    <p class="font-medium text-gray-900">Order Processing</p>
                    <p class="text-sm text-gray-500">We're preparing your order</p>
                </div>
                <div>
                    <div class="text-3xl mb-2">🚚</div>
                    <p class="font-medium text-gray-900">Shipping</p>
                    <p class="text-sm text-gray-500">J&T will deliver to you</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('shop.products') }}" 
               class="bg-amber-500 hover:bg-amber-600 text-white px-8 py-3 rounded-lg font-semibold transition">
                Continue Shopping
            </a>
            <a href="{{ route('shop.orders') }}" 
               class="border-2 border-gray-300 hover:border-gray-400 text-gray-700 px-8 py-3 rounded-lg font-semibold transition">
                View All Orders
            </a>
        </div>

        <!-- Demo Note -->
        <div class="mt-12 p-4 bg-green-50 rounded-lg">
            <p class="text-sm text-green-700">
                <strong>✅ CHIP Payment Gateway:</strong> Payment was processed through the CHIP sandbox environment.
                In production, this would use real payment credentials.
            </p>
        </div>
    </div>

    @push('scripts')
    <script>
        // Auto-refresh to check for webhook confirmation
        let checkCount = 0;
        const maxChecks = 30; // Stop after 30 checks (60 seconds)
        
        function checkPaymentStatus() {
            checkCount++;
            if (checkCount >= maxChecks) return;
            
            fetch(window.location.href, { method: 'HEAD' })
                .then(() => {
                    // Refresh the page to show updated status
                    if (checkCount % 5 === 0) { // Refresh every 10 seconds
                        window.location.reload();
                    }
                });
        }
        
        // Check every 2 seconds
        setInterval(checkPaymentStatus, 2000);
    </script>
    @endpush
</x-shop-layout>
