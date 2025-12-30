<x-shop-layout title="My Orders">
    <div class="max-w-6xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Order History</h1>

        @if($orders->isEmpty())
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <div class="text-6xl mb-4">📦</div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">No Orders Yet</h2>
            <p class="text-gray-600 mb-6">You haven't placed any orders yet. Start shopping to see your orders here!</p>
            <a href="{{ route('shop.products') }}" 
               class="inline-block bg-amber-500 hover:bg-amber-600 text-white px-8 py-3 rounded-lg font-semibold transition">
                Browse Products
            </a>
        </div>
        @else
        <div class="space-y-6">
            @foreach($orders as $order)
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Order Header -->
                <div class="bg-gray-50 px-6 py-4 border-b flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Order Number</p>
                            <p class="font-mono font-bold text-gray-900">{{ $order->order_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Date</p>
                            <p class="font-medium text-gray-900">{{ $order->created_at->format('d M Y, h:i A') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Total</p>
                            <p class="font-bold text-gray-900">RM {{ number_format($order->grand_total / 100, 2) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <!-- Order Status -->
                        <span class="px-3 py-1 rounded-full text-sm font-medium 
                            {{ (string) $order->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                            {{ (string) $order->status === 'pending_payment' ? 'bg-amber-100 text-amber-800' : '' }}
                            {{ (string) $order->status === 'processing' ? 'bg-blue-100 text-blue-800' : '' }}
                            {{ (string) $order->status === 'shipped' ? 'bg-purple-100 text-purple-800' : '' }}
                            {{ (string) $order->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}">
                            {{ $order->status->label() }}
                        </span>
                        <!-- Payment Status -->
                        <span class="px-3 py-1 rounded-full text-sm font-medium 
                            {{ $order->paid_at !== null ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $order->paid_at !== null ? 'Paid' : 'Pending' }}
                        </span>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="p-6">
                    <div class="space-y-4">
                        @foreach($order->items as $item)
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center text-2xl">
                                📦
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">{{ $item->name }}</p>
                                <p class="text-sm text-gray-500">
                                    Qty: {{ $item->quantity }} × RM {{ number_format($item->unit_price / 100, 2) }}
                                </p>
                            </div>
                            <p class="font-medium text-gray-900">RM {{ number_format($item->total / 100, 2) }}</p>
                        </div>
                        @endforeach
                    </div>

                    <!-- Order Summary -->
                    <div class="mt-6 pt-4 border-t">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                @if($order->shippingAddress)
                                <p><strong>Ship to:</strong> {{ $order->shippingAddress->getFullName() }}</p>
                                <p>{{ $order->shippingAddress->city }}, {{ $order->shippingAddress->state }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500">
                                    <span>Subtotal: RM {{ number_format($order->subtotal / 100, 2) }}</span>
                                    @if($order->discount_total > 0)
                                    <span class="text-green-600"> • Discount: -RM {{ number_format($order->discount_total / 100, 2) }}</span>
                                    @endif
                                    <span> • Shipping: {{ $order->shipping_total > 0 ? 'RM '.number_format($order->shipping_total / 100, 2) : 'Free' }}</span>
                                    @if($order->tax_total > 0)
                                    <span> • Tax: RM {{ number_format($order->tax_total / 100, 2) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Actions -->
                    <div class="mt-4 pt-4 border-t flex justify-end gap-4">
                        <a href="{{ route('shop.order.success', $order) }}" 
                           class="text-amber-600 hover:text-amber-700 font-medium text-sm">
                            View Details →
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $orders->links() }}
        </div>
        @endif

        <!-- Demo Note -->
        <div class="mt-8 p-4 bg-blue-50 rounded-lg">
            <p class="text-sm text-blue-700">
                <strong>🎭 Demo Note:</strong> This page shows all orders for demonstration purposes. 
                In production, users would only see their own orders after logging in.
            </p>
        </div>
    </div>
</x-shop-layout>
