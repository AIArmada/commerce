<x-shop-layout title="Track Your Order">
    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-blue-600 to-indigo-700 py-16">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <div class="text-6xl mb-4">📦</div>
            <h1 class="text-4xl font-bold text-white mb-4">Track Your Order</h1>
            <p class="text-xl text-white/80">Enter your tracking number to see real-time delivery updates</p>
        </div>
    </section>

    <!-- Tracking Form -->
    <section class="max-w-4xl mx-auto px-4 py-12">
        <form action="{{ route('shop.tracking.search') }}" method="GET" class="bg-white rounded-2xl shadow-lg p-8">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="tracking_number" class="block text-sm font-medium text-gray-700 mb-2">
                        Tracking Number or Order ID
                    </label>
                    <input type="text" 
                           name="tracking_number" 
                           id="tracking_number"
                           value="{{ request('tracking_number') }}"
                           placeholder="e.g., JT630002864925 or ORD-ABCD1234"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg">
                </div>
                <div class="flex items-end">
                    <button type="submit" 
                            class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-semibold text-lg transition">
                        Track Now
                    </button>
                </div>
            </div>
        </form>

        @if(isset($shipment))
        <!-- Tracking Result -->
        <div class="mt-8 bg-white rounded-2xl shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-white">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <p class="text-sm text-blue-200">Tracking Number</p>
                        <p class="text-2xl font-bold font-mono">{{ $shipment->tracking_number }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold
                            @switch($shipment->status)
                                @case('DELIVERED')
                                    bg-green-500 text-white
                                    @break
                                @case('ON_DELIVERY')
                                    bg-yellow-500 text-black
                                    @break
                                @case('PROBLEM')
                                    bg-red-500 text-white
                                    @break
                                @default
                                    bg-blue-500 text-white
                            @endswitch
                        ">
                            @switch($shipment->status)
                                @case('DELIVERED') ✓ Delivered @break
                                @case('ON_DELIVERY') 🚚 Out for Delivery @break
                                @case('ARRIVED') 📍 At Destination Hub @break
                                @case('DEPARTED') 🛫 In Transit @break
                                @case('PICKUP') 📦 Picked Up @break
                                @case('PROBLEM') ⚠️ Issue Detected @break
                                @default 🕐 {{ $shipment->status }}
                            @endswitch
                        </span>
                    </div>
                </div>
            </div>

            <!-- Shipment Details -->
            <div class="p-6 border-b">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">📤 Sender</h3>
                        <p class="text-gray-600">{{ $shipment->sender['name'] ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-500">{{ $shipment->sender['city'] ?? '' }}, {{ $shipment->sender['state'] ?? '' }}</p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">📥 Recipient</h3>
                        <p class="text-gray-600">{{ $shipment->receiver['name'] ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-500">{{ $shipment->receiver['city'] ?? '' }}, {{ $shipment->receiver['state'] ?? '' }}</p>
                    </div>
                </div>
            </div>

            @if($shipment->has_problem && $shipment->remark)
            <!-- Problem Alert -->
            <div class="bg-red-50 border-l-4 border-red-500 p-4 m-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <span class="text-2xl">⚠️</span>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Delivery Issue</h3>
                        <p class="mt-1 text-sm text-red-700">{{ $shipment->remark }}</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Tracking Timeline -->
            <div class="p-6">
                <h3 class="font-semibold text-gray-900 mb-6">📍 Tracking History</h3>
                
                @if($shipment->trackingEvents->count() > 0)
                <div class="space-y-4">
                    @foreach($shipment->trackingEvents->sortByDesc('scan_time') as $event)
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <div class="w-4 h-4 rounded-full 
                                @if($loop->first)
                                    bg-blue-600
                                @else
                                    bg-gray-300
                                @endif
                            "></div>
                            @if(!$loop->last)
                            <div class="w-0.5 h-full bg-gray-200 min-h-[40px]"></div>
                            @endif
                        </div>
                        <div class="pb-4">
                            <p class="font-medium text-gray-900">{{ $event->description }}</p>
                            <p class="text-sm text-gray-600">{{ $event->location }}</p>
                            <p class="text-xs text-gray-400">{{ $event->scan_time->format('M d, Y - h:i A') }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-gray-500 text-center py-8">No tracking events yet. Check back soon!</p>
                @endif
            </div>
        </div>
        @elseif(request('tracking_number'))
        <!-- No Results -->
        <div class="mt-8 bg-white rounded-2xl shadow-lg p-12 text-center">
            <div class="text-6xl mb-4">🔍</div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No shipment found</h3>
            <p class="text-gray-600">We couldn't find a shipment with tracking number "{{ request('tracking_number') }}"</p>
            <p class="text-sm text-gray-500 mt-2">Please check the number and try again.</p>
        </div>
        @endif
    </section>

    <!-- Recent Shipments (Demo) -->
    @if(isset($recentShipments) && $recentShipments->count() > 0)
    <section class="max-w-4xl mx-auto px-4 pb-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">📦 Recent Shipments (Demo)</h2>
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destination</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($recentShipments as $shipment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <span class="font-mono text-sm">{{ $shipment->tracking_number }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $shipment->receiver['city'] ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @switch($shipment->status)
                                    @case('DELIVERED') bg-green-100 text-green-800 @break
                                    @case('ON_DELIVERY') bg-yellow-100 text-yellow-800 @break
                                    @case('PROBLEM') bg-red-100 text-red-800 @break
                                    @default bg-blue-100 text-blue-800
                                @endswitch
                            ">
                                {{ $shipment->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('shop.tracking.search', ['tracking_number' => $shipment->tracking_number]) }}" 
                               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Track →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif
</x-shop-layout>
