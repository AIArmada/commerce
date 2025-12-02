@extends('layouts.app')

@section('content')
<div class="container mx-auto py-8">
    <h1 class="text-3xl font-bold mb-8">Products</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($products as $product)
        <div class="bg-white border rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-2">{{ $product->name }}</h2>
            <p class="text-gray-600 mb-4">{{ $product->description }}</p>
            <div class="text-2xl font-bold text-green-600 mb-4">
                RM{{ number_format($product->price / 100, 2) }}
                @if($product->compare_at_price)
                <span class="line-through text-gray-400">RM{{ number_format($product->compare_at_price / 100, 2) }}</span>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ $product->slug }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">View</a>
                <a href="/checkout/single/{{ $product->slug }}" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Buy Now</a>
            </div>
            <p class="text-sm text-gray-500 mt-2">Stock: {{ $product->stock_quantity }}</p>
        </div>
        @endforeach
    </div>
    
    {{ $products->links() }}
</div>
@endsection