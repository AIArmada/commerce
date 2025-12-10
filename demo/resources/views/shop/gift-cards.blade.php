<x-shop-layout title="Gift Cards">
    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500 py-16">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <div class="text-6xl mb-4">🎁</div>
            <h1 class="text-4xl font-bold text-white mb-4">Gift Cards</h1>
            <p class="text-xl text-white/80">The perfect gift for any occasion</p>
        </div>
    </section>

    <!-- Gift Card Options -->
    <section class="max-w-6xl mx-auto px-4 py-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">Choose Your Gift Card Amount</h2>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12">
            @foreach([50, 100, 200, 500] as $amount)
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition group cursor-pointer">
                <div class="aspect-video bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                    <span class="text-4xl font-bold text-white">RM {{ $amount }}</span>
                </div>
                <div class="p-4 text-center">
                    <button class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg font-medium transition">
                        Buy Now
                    </button>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Custom Amount -->
        <div class="max-w-md mx-auto bg-white rounded-2xl shadow-lg p-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 text-center">Custom Amount</h3>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label for="custom_amount" class="sr-only">Amount</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">RM</span>
                        <input type="number" 
                               id="custom_amount" 
                               placeholder="Enter amount"
                               min="10"
                               max="5000"
                               class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>
                </div>
                <button class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-medium transition">
                    Add
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-2 text-center">Min RM10 • Max RM5,000</p>
        </div>
    </section>

    <!-- Check Balance -->
    <section class="bg-gray-50 py-12">
        <div class="max-w-4xl mx-auto px-4">
            <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">Check Gift Card Balance</h2>
            
            <form action="{{ route('shop.gift-cards.check') }}" method="GET" class="bg-white rounded-2xl shadow-lg p-8 max-w-xl mx-auto">
                <div class="mb-4">
                    <label for="gift_card_code" class="block text-sm font-medium text-gray-700 mb-2">
                        Gift Card Code
                    </label>
                    <input type="text" 
                           name="code" 
                           id="gift_card_code"
                           value="{{ request('code') }}"
                           placeholder="e.g., GC-MALL-2024-ABCD"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 font-mono">
                </div>
                <button type="submit" 
                        class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-lg font-semibold transition">
                    Check Balance
                </button>
            </form>

            @if(isset($giftCard))
            <!-- Gift Card Result -->
            <div class="mt-8 bg-white rounded-2xl shadow-lg overflow-hidden max-w-xl mx-auto">
                <div class="bg-gradient-to-r from-purple-500 to-pink-500 p-6 text-white">
                    <p class="text-sm text-purple-200">Gift Card Code</p>
                    <p class="text-xl font-bold font-mono">{{ $giftCard->code }}</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Current Balance</p>
                            <p class="text-3xl font-bold text-green-600">RM {{ number_format($giftCard->current_balance / 100, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                @switch($giftCard->status->value)
                                    @case('active') bg-green-100 text-green-800 @break
                                    @case('exhausted') bg-gray-100 text-gray-800 @break
                                    @case('expired') bg-red-100 text-red-800 @break
                                    @case('suspended') bg-yellow-100 text-yellow-800 @break
                                    @default bg-gray-100 text-gray-800
                                @endswitch
                            ">
                                {{ ucfirst($giftCard->status->value) }}
                            </span>
                        </div>
                    </div>
                    
                    @if($giftCard->initial_balance != $giftCard->current_balance)
                    <div class="mt-4 pt-4 border-t">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Initial Value</span>
                            <span class="text-gray-900">RM {{ number_format($giftCard->initial_balance / 100, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            <span class="text-gray-500">Amount Used</span>
                            <span class="text-gray-900">RM {{ number_format(($giftCard->initial_balance - $giftCard->current_balance) / 100, 2) }}</span>
                        </div>
                        <div class="mt-3">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-purple-600 h-2 rounded-full" style="width: {{ $giftCard->balance_utilization }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">{{ number_format($giftCard->balance_utilization, 1) }}% used</p>
                        </div>
                    </div>
                    @endif
                    
                    @if($giftCard->expires_at)
                    <div class="mt-4 pt-4 border-t">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">Expires:</span>
                            <span class="text-sm font-medium {{ $giftCard->expires_at->isPast() ? 'text-red-600' : 'text-gray-900' }}">
                                {{ $giftCard->expires_at->format('M d, Y') }}
                            </span>
                            @if($giftCard->expires_at->isFuture() && $giftCard->expires_at->diffInDays(now()) <= 30)
                            <span class="text-xs text-yellow-600 font-medium">⚠️ Expiring soon!</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @elseif(request('code'))
            <!-- No Results -->
            <div class="mt-8 bg-white rounded-2xl shadow-lg p-12 text-center max-w-xl mx-auto">
                <div class="text-6xl mb-4">🔍</div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Gift card not found</h3>
                <p class="text-gray-600">We couldn't find a gift card with code "{{ request('code') }}"</p>
            </div>
            @endif
        </div>
    </section>

    <!-- Available Gift Cards (Demo) -->
    @if(isset($availableCards) && $availableCards->count() > 0)
    <section class="max-w-6xl mx-auto px-4 py-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">🎁 Demo Gift Cards</h2>
        <p class="text-center text-gray-600 mb-8">Try these gift card codes in checkout to see the integration!</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($availableCards as $card)
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-br 
                    @switch($card->type->value)
                        @case('standard') from-blue-500 to-indigo-500 @break
                        @case('promotional') from-green-500 to-emerald-500 @break
                        @case('reward') from-yellow-500 to-orange-500 @break
                        @case('corporate') from-gray-600 to-gray-800 @break
                        @default from-purple-500 to-pink-500
                    @endswitch
                p-6 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs text-white/70">{{ ucfirst($card->type->value) }} Gift Card</p>
                            <p class="font-mono text-sm mt-1 bg-white/20 px-2 py-1 rounded">{{ $card->code }}</p>
                        </div>
                        <span class="text-3xl">
                            @switch($card->type->value)
                                @case('standard') 💳 @break
                                @case('promotional') 🎉 @break
                                @case('reward') ⭐ @break
                                @case('corporate') 🏢 @break
                                @default 🎁
                            @endswitch
                        </span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500">Balance</p>
                            <p class="text-xl font-bold text-green-600">RM {{ number_format($card->current_balance / 100, 2) }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                            @switch($card->status->value)
                                @case('active') bg-green-100 text-green-800 @break
                                @case('exhausted') bg-gray-100 text-gray-800 @break
                                @default bg-gray-100 text-gray-800
                            @endswitch
                        ">
                            {{ ucfirst($card->status->value) }}
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <!-- Features -->
    <section class="bg-gray-900 text-white py-16">
        <div class="max-w-6xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Why Choose Our Gift Cards?</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="text-5xl mb-4">✉️</div>
                    <h3 class="text-xl font-semibold mb-2">Instant Delivery</h3>
                    <p class="text-gray-400">Sent directly to your inbox within seconds</p>
                </div>
                <div class="text-center">
                    <div class="text-5xl mb-4">🔒</div>
                    <h3 class="text-xl font-semibold mb-2">Secure & Safe</h3>
                    <p class="text-gray-400">Protected with unique codes and PIN verification</p>
                </div>
                <div class="text-center">
                    <div class="text-5xl mb-4">♾️</div>
                    <h3 class="text-xl font-semibold mb-2">No Expiry Hassle</h3>
                    <p class="text-gray-400">Use anytime within the validity period</p>
                </div>
            </div>
        </div>
    </section>
</x-shop-layout>
