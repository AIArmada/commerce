@extends(config('checkout.views.layout', 'layouts.app'))

@section('title', __('Payment Failed'))

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12">
    <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden">
            {{-- Error Header --}}
            <div class="bg-gradient-to-r from-red-500 to-rose-600 px-6 py-8 text-center">
                <div class="mx-auto w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white">{{ __('Payment Failed') }}</h1>
                <p class="text-red-100 mt-2">{{ __('Something went wrong with your payment') }}</p>
            </div>

            {{-- Error Details --}}
            <div class="px-6 py-6 space-y-4">
                @if($error ?? null)
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <p class="text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
                    </div>
                @endif

                <p class="text-gray-600 dark:text-gray-300 text-center">
                    {{ __('Your payment could not be processed. Please try again or use a different payment method.') }}
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
                   class="inline-flex items-center justify-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
                    {{ __('Try Again') }}
                </a>
                <a href="{{ url('/cart') }}"
                   class="inline-flex items-center justify-center px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-white font-medium rounded-lg transition-colors">
                    {{ __('Back to Cart') }}
                </a>
            </div>
        </div>

        {{-- Help Text --}}
        <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-6">
            {{ __('If the problem persists, please contact our support team.') }}
        </p>
    </div>
</div>
@endsection
