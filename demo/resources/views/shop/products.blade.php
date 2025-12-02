<x-shop-layout title="Products">
    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="mb-6">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                <li><a href="{{ route('shop.home') }}" class="hover:text-amber-600">Home</a></li>
                <li>/</li>
                <li class="text-gray-900 font-medium">Products</li>
                @if($currentCategory)
                <li>/</li>
                <li class="text-gray-900 font-medium">{{ $currentCategory->name }}</li>
                @endif
            </ol>
        </nav>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar Filters -->
            <aside class="lg:w-64 flex-shrink-0">
                <div class="bg-white rounded-xl shadow p-6 sticky top-24">
                    <h3 class="font-semibold text-gray-900 mb-4">Categories</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="{{ route('shop.products') }}" 
                               class="block py-1 {{ !$currentCategory ? 'text-amber-600 font-medium' : 'text-gray-600 hover:text-amber-600' }}">
                                All Products
                            </a>
                        </li>
                        @foreach($categories as $category)
                        <li>
                            <a href="{{ route('shop.products', ['category' => $category->slug]) }}" 
                               class="block py-1 {{ $currentCategory?->id === $category->id ? 'text-amber-600 font-medium' : 'text-gray-600 hover:text-amber-600' }}">
                                {{ $category->name }}
                                <span class="text-gray-400 text-sm">({{ $category->products_count ?? 0 }})</span>
                            </a>
                        </li>
                        @endforeach
                    </ul>

                    <hr class="my-6">

                    <h3 class="font-semibold text-gray-900 mb-4">Price Range</h3>
                    <form action="{{ route('shop.products') }}" method="GET">
                        @if($currentCategory)
                        <input type="hidden" name="category" value="{{ $currentCategory->slug }}">
                        @endif
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm text-gray-600">Min (RM)</label>
                                <input type="number" name="min_price" value="{{ request('min_price') }}" 
                                       class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="0">
                            </div>
                            <div>
                                <label class="text-sm text-gray-600">Max (RM)</label>
                                <input type="number" name="max_price" value="{{ request('max_price') }}" 
                                       class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="10000">
                            </div>
                            <button type="submit" 
                                    class="w-full bg-gray-900 text-white py-2 rounded-lg text-sm font-medium hover:bg-gray-800">
                                Apply Filter
                            </button>
                        </div>
                    </form>

                    <hr class="my-6">

                    <h3 class="font-semibold text-gray-900 mb-4">Availability</h3>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="in_stock" 
                               {{ request('in_stock') ? 'checked' : '' }}
                               onchange="this.form.submit()"
                               class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        <span class="text-gray-600">In Stock Only</span>
                    </label>
                </div>
            </aside>

            <!-- Products Grid -->
            <div class="flex-1">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            {{ $currentCategory ? $currentCategory->name : 'All Products' }}
                        </h1>
                        <p class="text-gray-500">{{ $products->total() }} products found</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="text-sm text-gray-600">Sort by:</label>
                        <select onchange="window.location.href = this.value" 
                                class="border rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            <option value="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" 
                                    {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>Newest</option>
                            <option value="{{ request()->fullUrlWithQuery(['sort' => 'price_asc']) }}"
                                    {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                            <option value="{{ request()->fullUrlWithQuery(['sort' => 'price_desc']) }}"
                                    {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                            <option value="{{ request()->fullUrlWithQuery(['sort' => 'name']) }}"
                                    {{ request('sort') === 'name' ? 'selected' : '' }}>Name: A-Z</option>
                        </select>
                    </div>
                </div>

                <!-- Products -->
                @if($products->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($products as $product)
                    <div class="group bg-white border rounded-2xl overflow-hidden hover:shadow-lg transition">
                        <a href="{{ route('shop.product', $product) }}" class="block">
                            <div class="relative aspect-square bg-gray-100">
                                <div class="absolute inset-0 flex items-center justify-center text-6xl">
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
                                </div>
                                @if($product->compare_at_price && $product->compare_at_price > $product->price)
                                <span class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">
                                    {{ round((1 - $product->price / $product->compare_at_price) * 100) }}% OFF
                                </span>
                                @endif
                                @if($product->isOutOfStock())
                                <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                    <span class="bg-white text-gray-900 px-4 py-2 rounded-lg font-semibold">Out of Stock</span>
                                </div>
                                @endif
                            </div>
                        </a>
                        <div class="p-4">
                            <p class="text-xs text-gray-500 mb-1">{{ $product->category?->name ?? 'Uncategorized' }}</p>
                            <h3 class="font-semibold text-gray-900 group-hover:text-amber-600 transition mb-2">
                                <a href="{{ route('shop.product', $product) }}">{{ $product->name }}</a>
                            </h3>
                            @if($product->description)
                            <p class="text-sm text-gray-500 mb-3 line-clamp-2">{{ Str::limit($product->description, 80) }}</p>
                            @endif
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg font-bold text-amber-600">RM {{ number_format($product->price / 100, 2) }}</span>
                                @if($product->compare_at_price && $product->compare_at_price > $product->price)
                                <span class="text-sm text-gray-400 line-through">RM {{ number_format($product->compare_at_price / 100, 2) }}</span>
                                @endif
                            </div>
                            <div class="flex items-center justify-between text-xs mb-3">
                                <span class="{{ $product->isInStock() ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $product->isInStock() ? '✓ In Stock' : '✗ Out of Stock' }}
                                </span>
                                @if($product->sku)
                                <span class="text-gray-400">SKU: {{ $product->sku }}</span>
                                @endif
                            </div>
                            <form action="{{ route('shop.cart.add') }}" method="POST">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" 
                                        {{ $product->isOutOfStock() ? 'disabled' : '' }}
                                        class="w-full bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white py-2 rounded-lg font-medium transition">
                                    Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-8">
                    {{ $products->withQueryString()->links() }}
                </div>
                @else
                <div class="text-center py-16">
                    <div class="text-6xl mb-4">🔍</div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No products found</h3>
                    <p class="text-gray-500 mb-6">Try adjusting your filters or search terms.</p>
                    <a href="{{ route('shop.products') }}" class="text-amber-600 hover:text-amber-700 font-medium">
                        ← Clear all filters
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-shop-layout>
