<x-shop-layout title="Payment Failed">
    <div class="max-w-2xl mx-auto px-4 py-16 sm:px-6 lg:px-8 text-center">
        <!-- Error Icon -->
        <div class="mx-auto w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mb-8">
            <svg class="h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>

        <h1 class="text-3xl font-bold text-gray-900 mb-4">Payment Failed</h1>
        <p class="text-xl text-gray-600 mb-2">We couldn't process your payment.</p>
        <p class="text-gray-500 mb-8">Order Number: <span class="font-mono font-bold text-gray-900">{{ $order->order_number }}</span></p>

        <!-- Error Card -->
        <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8 text-left">
            <h3 class="font-semibold text-red-800 mb-3">What happened?</h3>
            <p class="text-red-700 mb-4">
                Your payment could not be processed. This could be due to:
            </p>
            <ul class="list-disc list-inside text-red-700 space-y-1">
                <li>Insufficient funds</li>
                <li>Card declined by your bank</li>
                <li>Network or connectivity issues</li>
                <li>Incorrect card details</li>
            </ul>
        </div>

        <!-- Order Summary -->
        <div class="bg-white rounded-2xl shadow-lg p-8 text-left mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Order Summary</h2>

            <!-- Status -->
            <div class="flex items-center justify-between p-4 bg-red-50 rounded-lg mb-6">
                <div>
                    <p class="text-sm text-gray-600">Order Status</p>
                    <p class="font-semibold text-red-600">Payment Failed</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Total Amount</p>
                    <p class="font-semibold text-gray-900">RM {{ number_format($order->grand_total / 100, 2) }}</p>
                </div>
            </div>

            <!-- Items Preview -->
            <div class="space-y-3">
                @foreach($order->items as $item)
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                    <span class="text-gray-700">{{ $item->name }} × {{ $item->quantity }}</span>
                    <span class="font-medium text-gray-900">RM {{ number_format($item->total_price / 100, 2) }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8">
            <a href="{{ route('shop.checkout') }}" 
               class="bg-amber-500 hover:bg-amber-600 text-white px-8 py-3 rounded-lg font-semibold transition">
                Try Again
            </a>
            <a href="{{ route('shop.cart') }}" 
               class="border-2 border-gray-300 hover:border-gray-400 text-gray-700 px-8 py-3 rounded-lg font-semibold transition">
                Back to Cart
            </a>
            <a href="{{ route('shop.products') }}" 
               class="border-2 border-gray-300 hover:border-gray-400 text-gray-700 px-8 py-3 rounded-lg font-semibold transition">
                Continue Shopping
            </a>
        </div>

        <!-- Help Section -->
        <div class="bg-gray-50 rounded-xl p-6">
            <h3 class="font-semibold text-gray-900 mb-3">Need Help?</h3>
            <p class="text-gray-600 mb-4">
                If you continue to experience issues, please contact our support team.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center text-sm">
                <a href="mailto:support@example.com" class="text-amber-600 hover:text-amber-700 font-medium">
                    📧 support@example.com
                </a>
                <a href="tel:+60123456789" class="text-amber-600 hover:text-amber-700 font-medium">
                    📞 +60 12-345 6789
                </a>
            </div>
        </div>

        <!-- Demo Note -->
        <div class="mt-8 p-4 bg-blue-50 rounded-lg">
            <p class="text-sm text-blue-700">
                <strong>🎭 Demo Note:</strong> In the CHIP sandbox, you can use test card 4242 4242 4242 4242 to complete payment successfully.
            </p>
        </div>
    </div>
</x-shop-layout>
