<x-shop-layout title="Payment Cancelled">
    <div class="max-w-2xl mx-auto px-4 py-16 sm:px-6 lg:px-8 text-center">
        <!-- Cancelled Icon -->
        <div class="mx-auto w-24 h-24 bg-gray-200 rounded-full flex items-center justify-center mb-8">
            <svg class="h-12 w-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>

        <h1 class="text-3xl font-bold text-gray-900 mb-4">Payment Cancelled</h1>
        <p class="text-xl text-gray-600 mb-2">Your payment was cancelled.</p>
        <p class="text-gray-500 mb-8">Order Number: <span class="font-mono font-bold text-gray-900">{{ $order->order_number }}</span></p>

        <!-- Info Card -->
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-8 text-left">
            <h3 class="font-semibold text-gray-800 mb-3">No charges were made</h3>
            <p class="text-gray-600">
                You cancelled the payment process before it was completed. 
                No charges have been made to your card or account.
                Your items are still saved and you can complete your purchase anytime.
            </p>
        </div>

        <!-- Order Summary -->
        <div class="bg-white rounded-2xl shadow-lg p-8 text-left mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Your Order Was:</h2>

            <!-- Status -->
            <div class="flex items-center justify-between p-4 bg-gray-100 rounded-lg mb-6">
                <div>
                    <p class="text-sm text-gray-600">Order Status</p>
                    <p class="font-semibold text-gray-600">Cancelled</p>
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
                    <span class="font-medium text-gray-900">RM {{ number_format($item->total / 100, 2) }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8">
            <a href="{{ route('shop.checkout') }}" 
               class="bg-amber-500 hover:bg-amber-600 text-white px-8 py-3 rounded-lg font-semibold transition">
                Complete Your Order
            </a>
            <a href="{{ route('shop.cart') }}" 
               class="border-2 border-gray-300 hover:border-gray-400 text-gray-700 px-8 py-3 rounded-lg font-semibold transition">
                Review Cart
            </a>
            <a href="{{ route('shop.products') }}" 
               class="border-2 border-gray-300 hover:border-gray-400 text-gray-700 px-8 py-3 rounded-lg font-semibold transition">
                Continue Shopping
            </a>
        </div>

        <!-- Reason Section -->
        <div class="bg-amber-50 rounded-xl p-6">
            <h3 class="font-semibold text-amber-800 mb-3">Changed your mind?</h3>
            <p class="text-amber-700">
                That's okay! Your cart items are still saved. Take your time to browse our products 
                and come back when you're ready to complete your purchase.
            </p>
        </div>

        <!-- Demo Note -->
        <div class="mt-8 p-4 bg-blue-50 rounded-lg">
            <p class="text-sm text-blue-700">
                <strong>🎭 Demo Note:</strong> This page is shown when a user clicks "Cancel" on the CHIP payment page.
            </p>
        </div>
    </div>
</x-shop-layout>
