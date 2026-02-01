@extends(config('checkout.views.layout', 'layouts.app'))

@section('title', __('Payment Cancelled'))

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12">
    <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden">
            {{-- Cancelled Header --}}
            <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-8 text-center">
                <div class="mx-auto w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white">{{ __('Payment Cancelled') }}</h1>
                <p class="text-amber-100 mt-2">{{ __('Your payment was not completed') }}</p>
            </div>

            {{-- Message --}}
            <div class="px-6 py-6 space-y-4">
                <p class="text-gray-600 dark:text-gray-300 text-center">
                    {{ __('You cancelled the payment process. Your cart items are still saved and you can complete the checkout whenever you\'re ready.') }}
                </p>

                @if($reference ?? null)
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center">
                        {{ __('Reference:') }} {{ $reference }}
                    </p>
                @endif
            </div>

            {{-- Actions --}}
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ url('/checkout') }}"
                   class="inline-flex items-center justify-center px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg transition-colors">
                    {{ __('Complete Checkout') }}
                </a>
                <a href="{{ url('/') }}"
                   class="inline-flex items-center justify-center px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-white font-medium rounded-lg transition-colors">
                    {{ __('Continue Shopping') }}
                </a>
            </div>
        </div>

        {{-- Reassurance Text --}}
        <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-6">
            {{ __('No charges were made. Your payment information was not saved.') }}
        </p>
    </div>
</div>
@endsection
