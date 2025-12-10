<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'AIArmada Shop' }} - Commerce Demo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('shop.home') }}" class="flex items-center gap-2">
                        <span class="text-2xl">🛒</span>
                        <span class="font-bold text-xl text-gray-900">AIArmada Shop</span>
                    </a>
                    <div class="hidden md:flex items-center ml-10 space-x-8">
                        <a href="{{ route('shop.home') }}" class="text-gray-600 hover:text-amber-600 font-medium">Home</a>
                        <a href="{{ route('shop.products') }}" class="text-gray-600 hover:text-amber-600 font-medium">Products</a>
                        <a href="{{ route('shop.categories') }}" class="text-gray-600 hover:text-amber-600 font-medium">Categories</a>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <!-- Search -->
                    <div class="hidden md:block">
                        <form action="{{ route('shop.products') }}" method="GET" class="relative">
                            <input type="text" name="search" placeholder="Search products..." 
                                   value="{{ request('search') }}"
                                   class="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                            <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </form>
                    </div>
                    
                    <!-- Cart -->
                    <a href="{{ route('shop.cart') }}" class="relative p-2 text-gray-600 hover:text-amber-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        @php $cartCount = session('cart_count', 0); @endphp
                        @if($cartCount > 0)
                        <span class="absolute -top-1 -right-1 bg-amber-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                            {{ $cartCount }}
                        </span>
                        @endif
                    </a>
                    
                    <!-- Account -->
                    @auth
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 text-gray-600 hover:text-amber-600">
                            <span class="hidden md:inline font-medium">{{ auth()->user()->name }}</span>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 border">
                            <a href="{{ route('shop.orders') }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">My Orders</a>
                            <a href="{{ route('shop.account') }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Account</a>
                            <hr class="my-1">
                            <a href="/admin" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Admin Panel</a>
                        </div>
                    </div>
                    @else
                    <a href="/admin/login" class="text-gray-600 hover:text-amber-600 font-medium">Login</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Affiliate Banner (if applicable) -->
    @if(session('affiliate_code'))
    <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white py-2 px-4 text-center text-sm">
        🎉 You're shopping with affiliate code: <strong>{{ session('affiliate_code') }}</strong> - Support our partner!
    </div>
    @endif

    <!-- Flash Messages -->
    @if(session('success'))
    <div class="bg-green-50 border-l-4 border-green-500 p-4 max-w-7xl mx-auto mt-4">
        <div class="flex items-center">
            <svg class="h-5 w-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <p class="text-green-700">{{ session('success') }}</p>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border-l-4 border-red-500 p-4 max-w-7xl mx-auto mt-4">
        <div class="flex items-center">
            <svg class="h-5 w-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <p class="text-red-700">{{ session('error') }}</p>
        </div>
    </div>
    @endif

    <!-- Main Content -->
    <main>
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white mt-16">
        <div class="max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-2xl">🛒</span>
                        <span class="font-bold text-xl">AIArmada Shop</span>
                    </div>
                    <p class="text-gray-400 text-sm">
                        A demo showcasing the full power of AIArmada Commerce packages.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold mb-4">Shop</h3>
                    <ul class="space-y-2 text-gray-400 text-sm">
                        <li><a href="{{ route('shop.products') }}" class="hover:text-white">All Products</a></li>
                        <li><a href="{{ route('shop.categories') }}" class="hover:text-white">Categories</a></li>
                        <li><a href="#" class="hover:text-white">New Arrivals</a></li>
                        <li><a href="#" class="hover:text-white">Sale</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold mb-4">Customer Service</h3>
                    <ul class="space-y-2 text-gray-400 text-sm">
                        <li><a href="#" class="hover:text-white">Contact Us</a></li>
                        <li><a href="#" class="hover:text-white">Shipping Info</a></li>
                        <li><a href="#" class="hover:text-white">Returns</a></li>
                        <li><a href="#" class="hover:text-white">Track Order</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold mb-4">Packages Used</h3>
                    <div class="flex flex-wrap gap-2">
                        <span class="bg-amber-500/20 text-amber-400 text-xs px-2 py-1 rounded">cart</span>
                        <span class="bg-amber-500/20 text-amber-400 text-xs px-2 py-1 rounded">vouchers</span>
                        <span class="bg-amber-500/20 text-amber-400 text-xs px-2 py-1 rounded">inventory</span>
                        <span class="bg-amber-500/20 text-amber-400 text-xs px-2 py-1 rounded">affiliates</span>
                        <span class="bg-amber-500/20 text-amber-400 text-xs px-2 py-1 rounded">chip</span>
                        <span class="bg-amber-500/20 text-amber-400 text-xs px-2 py-1 rounded">jnt</span>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm">
                <p>&copy; {{ date('Y') }} AIArmada Commerce. Demo for showcase purposes.</p>
                <p class="mt-2">
                    <a href="/admin" class="text-amber-400 hover:text-amber-300">Admin Panel →</a>
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
