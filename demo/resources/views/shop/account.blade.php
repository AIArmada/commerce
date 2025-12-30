<x-shop-layout title="My Account">
    <div class="max-w-6xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">My Account</h1>
            <p class="text-gray-600 mt-1">Manage your account and view your order history</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Profile Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-8 text-center">
                        <div class="w-24 h-24 bg-white rounded-full mx-auto flex items-center justify-center text-4xl shadow-lg">
                            👤
                        </div>
                        <h2 class="text-xl font-bold text-white mt-4">{{ $user->name }}</h2>
                        <p class="text-amber-100">{{ $user->email }}</p>
                    </div>
                    
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center gap-3 text-gray-600">
                                <span class="text-xl">📧</span>
                                <div>
                                    <p class="text-xs text-gray-400">Email</p>
                                    <p class="font-medium text-gray-900">{{ $user->email }}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3 text-gray-600">
                                <span class="text-xl">📅</span>
                                <div>
                                    <p class="text-xs text-gray-400">Member Since</p>
                                    <p class="font-medium text-gray-900">{{ $user->created_at->format('d M Y') }}</p>
                                </div>
                            </div>

                            @if(method_exists($user, 'affiliate') && $user->affiliate)
                            <div class="flex items-center gap-3 text-gray-600">
                                <span class="text-xl">🤝</span>
                                <div>
                                    <p class="text-xs text-gray-400">Affiliate Status</p>
                                    <p class="font-medium text-green-600">Active Affiliate</p>
                                </div>
                            </div>
                            @endif
                        </div>

                        <div class="mt-6 pt-6 border-t">
                            <a href="{{ url('/admin/logout') }}" 
                               class="block w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition">
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
                    <h3 class="font-bold text-gray-900 mb-4">Quick Stats</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-4 bg-amber-50 rounded-lg">
                            <p class="text-2xl font-bold text-amber-600">{{ $recentOrders->count() }}</p>
                            <p class="text-xs text-gray-500">Recent Orders</p>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <p class="text-2xl font-bold text-green-600">
                                {{ $recentOrders->filter(fn ($order) => $order->paid_at !== null)->count() }}
                            </p>
                            <p class="text-xs text-gray-500">Paid Orders</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Recent Orders -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="font-bold text-gray-900">Recent Orders</h3>
                        <a href="{{ route('shop.orders') }}" class="text-amber-600 hover:text-amber-700 text-sm font-medium">
                            View All →
                        </a>
                    </div>

                    @if($recentOrders->isEmpty())
                    <div class="p-12 text-center">
                        <div class="text-5xl mb-4">📦</div>
                        <h4 class="text-lg font-bold text-gray-900 mb-2">No Orders Yet</h4>
                        <p class="text-gray-600 mb-6">Start shopping to see your orders here!</p>
                        <a href="{{ route('shop.products') }}" 
                           class="inline-block bg-amber-500 hover:bg-amber-600 text-white px-6 py-2 rounded-lg font-medium transition">
                            Browse Products
                        </a>
                    </div>
                    @else
                    <div class="divide-y">
                        @foreach($recentOrders as $order)
                        <div class="p-6 hover:bg-gray-50 transition">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-4">
                                    <span class="font-mono font-bold text-gray-900">{{ $order->order_number }}</span>
                                    <span class="text-sm text-gray-500">{{ $order->created_at->format('d M Y') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium 
                                        {{ (string) $order->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ (string) $order->status === 'pending_payment' ? 'bg-amber-100 text-amber-800' : '' }}
                                        {{ (string) $order->status === 'processing' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ (string) $order->status === 'shipped' ? 'bg-purple-100 text-purple-800' : '' }}
                                        {{ (string) $order->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}">
                                        {{ $order->status->label() }}
                                    </span>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium 
                                        {{ $order->paid_at !== null ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $order->paid_at !== null ? 'Paid' : 'Pending' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-500">
                                    {{ $order->items->count() }} item(s)
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="font-bold text-gray-900">RM {{ number_format($order->grand_total / 100, 2) }}</span>
                                    <a href="{{ route('shop.order.success', $order) }}" 
                                       class="text-amber-600 hover:text-amber-700 text-sm font-medium">
                                        Details →
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="{{ route('shop.products') }}" 
                           class="flex flex-col items-center p-4 bg-gray-50 hover:bg-amber-50 rounded-lg transition group">
                            <span class="text-3xl mb-2">🛍️</span>
                            <span class="text-sm font-medium text-gray-700 group-hover:text-amber-700">Shop</span>
                        </a>
                        <a href="{{ route('shop.orders') }}" 
                           class="flex flex-col items-center p-4 bg-gray-50 hover:bg-amber-50 rounded-lg transition group">
                            <span class="text-3xl mb-2">📦</span>
                            <span class="text-sm font-medium text-gray-700 group-hover:text-amber-700">Orders</span>
                        </a>
                        <a href="{{ route('shop.cart') }}" 
                           class="flex flex-col items-center p-4 bg-gray-50 hover:bg-amber-50 rounded-lg transition group">
                            <span class="text-3xl mb-2">🛒</span>
                            <span class="text-sm font-medium text-gray-700 group-hover:text-amber-700">Cart</span>
                        </a>
                        <a href="{{ route('shop.categories') }}" 
                           class="flex flex-col items-center p-4 bg-gray-50 hover:bg-amber-50 rounded-lg transition group">
                            <span class="text-3xl mb-2">📂</span>
                            <span class="text-sm font-medium text-gray-700 group-hover:text-amber-700">Categories</span>
                        </a>
                    </div>
                </div>

                <!-- Demo Packages Info -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6">
                    <h3 class="font-bold text-gray-900 mb-3">🎭 Packages Powering Your Account</h3>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 shadow-sm">cart</span>
                        <span class="px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 shadow-sm">vouchers</span>
                        <span class="px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 shadow-sm">stock</span>
                        <span class="px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 shadow-sm">affiliates</span>
                        <span class="px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 shadow-sm">chip</span>
                        <span class="px-3 py-1 bg-white rounded-full text-sm font-medium text-gray-700 shadow-sm">jnt</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-3">
                        This account page demonstrates user management with order tracking powered by AIArmada Commerce packages.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-shop-layout>
