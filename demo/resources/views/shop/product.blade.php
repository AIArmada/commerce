<x-shop-layout :title="$product->name">
    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="mb-6">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                <li><a href="{{ route('shop.home') }}" class="hover:text-amber-600">Home</a></li>
                <li>/</li>
                <li><a href="{{ route('shop.products') }}" class="hover:text-amber-600">Products</a></li>
                @if($product->category)
                <li>/</li>
                <li><a href="{{ route('shop.products', ['category' => $product->category->slug]) }}" class="hover:text-amber-600">{{ $product->category->name }}</a></li>
                @endif
                <li>/</li>
                <li class="text-gray-900 font-medium">{{ $product->name }}</li>
            </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Product Image -->
            <div class="bg-gray-100 rounded-2xl aspect-square flex items-center justify-center relative">
                <span class="text-9xl">
                    @if(str_contains(strtolower($product->name), 'phone') || str_contains(strtolower($product->name), 'iphone'))
                        📱
                    @elseif(str_contains(strtolower($product->name), 'laptop') || str_contains(strtolower($product->name), 'macbook'))
                        💻
                    @elseif(str_contains(strtolower($product->name), 'watch'))
                        ⌚
                    @elseif(str_contains(strtolower($product->name), 'airpod') || str_contains(strtolower($product->name), 'headphone'))
                        🎧
                    @elseif(str_contains(strtolower($product->name), 'dress') || str_contains(strtolower($product->name), 'shirt'))
                        👗
                    @elseif(str_contains(strtolower($product->name), 'chair') || str_contains(strtolower($product->name), 'lamp'))
                        🪑
                    @elseif(str_contains(strtolower($product->name), 'yoga') || str_contains(strtolower($product->name), 'fitness'))
                        🧘
                    @elseif(str_contains(strtolower($product->name), 'skin') || str_contains(strtolower($product->name), 'serum'))
                        🧴
                    @else
                        📦
                    @endif
                </span>
                @if($product->compare_at_price && $product->compare_at_price > $product->price)
                <span class="absolute top-4 left-4 bg-red-500 text-white text-sm font-bold px-3 py-1 rounded">
                    {{ round((1 - $product->price / $product->compare_at_price) * 100) }}% OFF
                </span>
                @endif
            </div>

            <!-- Product Details -->
            <div>
                <div class="mb-4">
                    @if($product->category)
                    <a href="{{ route('shop.products', ['category' => $product->category->slug]) }}" 
                       class="text-amber-600 text-sm font-medium hover:text-amber-700">
                        {{ $product->category->name }}
                    </a>
                    @endif
                </div>

                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $product->name }}</h1>

                <!-- Price -->
                <div class="flex items-baseline gap-4 mb-6">
                    <span class="text-4xl font-bold text-amber-600">RM {{ number_format($product->price / 100, 2) }}</span>
                    @if($product->compare_at_price && $product->compare_at_price > $product->price)
                    <span class="text-xl text-gray-400 line-through">RM {{ number_format($product->compare_at_price / 100, 2) }}</span>
                    <span class="text-sm bg-red-100 text-red-700 px-2 py-1 rounded font-medium">
                        Save RM {{ number_format(($product->compare_at_price - $product->price) / 100, 2) }}
                    </span>
                    @endif
                </div>

                <!-- Stock Status -->
                <div class="flex items-center gap-4 mb-6 p-4 rounded-lg {{ $product->isInStock() ? 'bg-green-50' : 'bg-red-50' }}">
                    @if($product->isInStock())
                    <svg class="h-6 w-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="font-semibold text-green-800">In Stock</p>
                        <p class="text-sm text-green-600">{{ $product->stock_quantity }} units available</p>
                    </div>
                    @else
                    <svg class="h-6 w-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="font-semibold text-red-800">Out of Stock</p>
                        <p class="text-sm text-red-600">This product is currently unavailable</p>
                    </div>
                    @endif
                </div>

                <!-- Description -->
                @if($product->description)
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-900 mb-2">Description</h3>
                    <p class="text-gray-600 leading-relaxed">{{ $product->description }}</p>
                </div>
                @endif

                <!-- Product Info -->
                <div class="border-t border-b py-4 mb-6">
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        @if($product->sku)
                        <div>
                            <dt class="text-gray-500">SKU</dt>
                            <dd class="font-medium text-gray-900">{{ $product->sku }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-gray-500">Category</dt>
                            <dd class="font-medium text-gray-900">{{ $product->category?->name ?? 'Uncategorized' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Currency</dt>
                            <dd class="font-medium text-gray-900">{{ $product->currency ?? 'MYR' }}</dd>
                        </div>
                        @if($product->track_stock)
                        <div>
                            <dt class="text-gray-500">Stock Tracking</dt>
                            <dd class="font-medium text-green-600">Enabled</dd>
                        </div>
                        @endif
                    </dl>
                </div>

                <!-- Add to Cart -->
                <form action="{{ route('shop.cart.add') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    
                    <div class="flex items-center gap-4">
                        <label for="quantity" class="font-medium text-gray-900">Quantity:</label>
                        <div class="flex items-center border rounded-lg">
                            <button type="button" onclick="decrementQty()" 
                                    class="px-4 py-2 text-gray-600 hover:bg-gray-100">−</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" 
                                   max="{{ $product->stock_quantity }}"
                                   class="w-16 text-center border-0 focus:ring-0">
                            <button type="button" onclick="incrementQty({{ $product->stock_quantity }})" 
                                    class="px-4 py-2 text-gray-600 hover:bg-gray-100">+</button>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" 
                                {{ $product->isOutOfStock() ? 'disabled' : '' }}
                                class="flex-1 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white py-3 rounded-lg font-semibold text-lg transition">
                            {{ $product->isOutOfStock() ? 'Out of Stock' : 'Add to Cart' }}
                        </button>
                        <button type="button" 
                                class="px-4 py-3 border-2 border-gray-300 rounded-lg hover:border-amber-500 transition">
                            <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                    </div>
                </form>

                <!-- Buy Now -->
                @if($product->isInStock())
                <form action="{{ route('shop.checkout.buy-now') }}" method="POST" class="mt-4">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" 
                            class="w-full bg-gray-900 hover:bg-gray-800 text-white py-3 rounded-lg font-semibold text-lg transition">
                        Buy Now
                    </button>
                </form>
                @endif

                <!-- Trust Badges -->
                <div class="mt-8 grid grid-cols-3 gap-4 text-center text-sm">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl mb-1">🚚</div>
                        <p class="font-medium text-gray-900">Free Shipping</p>
                        <p class="text-gray-500">Orders over RM100</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl mb-1">🔒</div>
                        <p class="font-medium text-gray-900">Secure Payment</p>
                        <p class="text-gray-500">CHIP Gateway</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl mb-1">↩️</div>
                        <p class="font-medium text-gray-900">Easy Returns</p>
                        <p class="text-gray-500">30-day policy</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        @if($relatedProducts->count() > 0)
        <section class="mt-16">
            <h2 class="text-2xl font-bold text-gray-900 mb-8">Related Products</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                @foreach($relatedProducts as $related)
                <div class="group bg-white border rounded-2xl overflow-hidden hover:shadow-lg transition">
                    <a href="{{ route('shop.product', $related) }}" class="block">
                        <div class="aspect-square bg-gray-100 flex items-center justify-center text-5xl">
                            📦
                        </div>
                    </a>
                    <div class="p-4">
                        <h3 class="font-semibold text-gray-900 group-hover:text-amber-600 transition mb-2 line-clamp-1">
                            <a href="{{ route('shop.product', $related) }}">{{ $related->name }}</a>
                        </h3>
                        <p class="text-lg font-bold text-amber-600">RM {{ number_format($related->price / 100, 2) }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </section>
        @endif
    </div>

    <script>
        function incrementQty(max) {
            const input = document.getElementById('quantity');
            const current = parseInt(input.value);
            if (current < max) {
                input.value = current + 1;
            }
        }
        
        function decrementQty() {
            const input = document.getElementById('quantity');
            const current = parseInt(input.value);
            if (current > 1) {
                input.value = current - 1;
            }
        }
    </script>
</x-shop-layout>
