@extends(config('checkout.views.layout', 'layouts.app'))

@section('title', __('Payment Successful'))

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden">
            {{-- Success Header --}}
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-8 text-center">
                <div class="mx-auto w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white">{{ __('Payment Successful!') }}</h1>
                <p class="text-green-100 mt-2">{{ __('Thank you for your purchase') }}</p>
            </div>

            {{-- Order Details --}}
            <div class="px-6 py-6 space-y-6">
                @if($order ?? null)
                    {{-- Order Number --}}
                    <div class="text-center pb-4 border-b border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Order Number') }}</p>
                        <p class="text-xl font-mono font-bold text-gray-900 dark:text-white">
                            {{ $order->order_number ?? $order->id }}
                        </p>
                    </div>

                    {{-- Order Summary --}}
                    @if($order->items ?? null)
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">{{ __('Order Summary') }}</h3>
                            <ul class="space-y-2">
                                @foreach($order->items as $item)
                                    <li class="flex justify-between text-sm">
                                        <span class="text-gray-700 dark:text-gray-300">
                                            {{ $item->product->name ?? $item->name ?? 'Item' }}
                                            @if($item->quantity > 1)
                                                <span class="text-gray-500">&times; {{ $item->quantity }}</span>
                                            @endif
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            {{ $item->formatted_total ?? '' }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Total --}}
                    <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Total') }}</span>
                        <span class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $formattedTotal ?? $order->formatted_total ?? '' }}
                        </span>
                    </div>

                @elseif($session ?? null)
                    {{-- Session-based display when order not yet created --}}
                    <div class="text-center py-4">
                        <p class="text-gray-600 dark:text-gray-300">
                            {{ __('Your payment has been processed successfully.') }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                            {{ __('Reference:') }} {{ $reference ?? $session->cart_id ?? $session->id }}
                        </p>
                    </div>

                    @if($formattedTotal ?? null)
                        <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-700">
                            <span class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Total Paid') }}</span>
                            <span class="text-xl font-bold text-gray-900 dark:text-white">{{ $formattedTotal }}</span>
                        </div>
                    @endif
                @else
                    {{-- Fallback display --}}
                    <div class="text-center py-4">
                        <p class="text-gray-600 dark:text-gray-300">
                            {{ __('Your payment has been processed successfully.') }}
                        </p>
                    </div>
                @endif

                {{-- Shipping Info --}}
                @if($shippingData ?? null)
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ __('Shipping To') }}</h3>
                        <address class="text-sm text-gray-700 dark:text-gray-300 not-italic">
                            <p class="font-medium">{{ $shippingData['name'] ?? '' }}</p>
                            <p>{{ $shippingData['street1'] ?? '' }}</p>
                            @if($shippingData['street2'] ?? null)
                                <p>{{ $shippingData['street2'] }}</p>
                            @endif
                            <p>
                                {{ $shippingData['postcode'] ?? '' }}
                                {{ $shippingData['city'] ?? '' }},
                                {{ $shippingData['state'] ?? '' }}
                            </p>
                            <p>{{ $shippingData['country'] ?? '' }}</p>
                        </address>
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex flex-col sm:flex-row gap-3 justify-center">
                @if($order ?? null)
                    <a href="{{ route('orders.show', $order) }}"
                       class="inline-flex items-center justify-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                        {{ __('View Order') }}
                    </a>
                @endif
                <a href="{{ url('/') }}"
                   class="inline-flex items-center justify-center px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-white font-medium rounded-lg transition-colors">
                    {{ __('Continue Shopping') }}
                </a>
            </div>
        </div>

        {{-- Help Text --}}
        <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-6">
            {{ __('A confirmation email has been sent to your email address.') }}
        </p>
    </div>
</div>
@endsection
