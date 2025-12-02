<x-shop-layout title="Categories">
    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8 text-center">Shop by Category</h1>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @foreach($categories as $category)
            <a href="{{ route('shop.products', ['category' => $category->slug]) }}" 
               class="group bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition">
                <div class="aspect-square bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                    <span class="text-7xl group-hover:scale-110 transition">
                        @switch($category->name)
                            @case('Electronics') 📱 @break
                            @case('Fashion') 👗 @break
                            @case('Home & Living') 🏠 @break
                            @case('Sports') ⚽ @break
                            @case('Beauty') 💄 @break
                            @case('Books') 📚 @break
                            @case('Toys') 🎮 @break
                            @case('Food') 🍔 @break
                            @default 📦
                        @endswitch
                    </span>
                </div>
                <div class="p-6 text-center">
                    <h2 class="text-xl font-semibold text-gray-900 group-hover:text-amber-600 transition mb-2">
                        {{ $category->name }}
                    </h2>
                    <p class="text-gray-500">{{ $category->products_count ?? 0 }} products</p>
                    @if($category->description)
                    <p class="text-sm text-gray-400 mt-2 line-clamp-2">{{ $category->description }}</p>
                    @endif
                </div>
            </a>
            @endforeach
        </div>

        @if($categories->isEmpty())
        <div class="text-center py-16">
            <div class="text-6xl mb-4">📁</div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No categories yet</h3>
            <p class="text-gray-500">Check back soon!</p>
        </div>
        @endif
    </div>
</x-shop-layout>
