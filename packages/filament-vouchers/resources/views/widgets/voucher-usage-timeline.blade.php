<x-filament-widgets::widget>
    @php
        $events = $this->getTimelineEvents();
        $stats = $this->getSummaryStats();
    @endphp

    <x-filament::section
        icon="heroicon-o-clock"
        heading="Usage History"
        description="Complete timeline of voucher redemptions and activity"
    >
        {{-- Summary Stats --}}
        @if($stats['total_redemptions'] > 0)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-primary-100 dark:bg-primary-900/50 p-2">
                            <x-filament::icon
                                icon="heroicon-o-check-circle"
                                class="h-5 w-5 text-primary-600 dark:text-primary-400"
                            />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Redemptions</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $stats['total_redemptions'] }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-success-100 dark:bg-success-900/50 p-2">
                            <x-filament::icon
                                icon="heroicon-o-currency-dollar"
                                class="h-5 w-5 text-success-600 dark:text-success-400"
                            />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Savings</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $stats['total_savings'] }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-info-100 dark:bg-info-900/50 p-2">
                            <x-filament::icon
                                icon="heroicon-o-users"
                                class="h-5 w-5 text-info-600 dark:text-info-400"
                            />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Unique Customers</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $stats['unique_customers'] }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Timeline --}}
        @if($events->isNotEmpty())
            <div class="space-y-4">
                @foreach($events as $index => $event)
                    <div class="relative flex gap-4">
                        {{-- Timeline Line --}}
                        @if(!$loop->last)
                            <div class="absolute left-6 top-12 h-full w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                        @endif

                        {{-- Icon --}}
                        <div class="relative flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full border-2 border-{{ $event['color'] }}-500 bg-{{ $event['color'] }}-50 dark:bg-{{ $event['color'] }}-900/20">
                            <x-filament::icon
                                :icon="$event['icon']"
                                class="h-6 w-6 text-{{ $event['color'] }}-600 dark:text-{{ $event['color'] }}-400"
                            />
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
                            {{-- Header --}}
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $event['title'] }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $event['description'] }}
                                    </p>
                                </div>
                                <div class="flex-shrink-0 text-right">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        {{ $event['timestamp']->format('M d, Y') }}
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $event['timestamp']->format('g:i A') }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        {{ $event['timestamp_human'] }}
                                    </p>
                                </div>
                            </div>

                            {{-- Details --}}
                            <div class="mt-3 grid grid-cols-2 gap-3 border-t border-gray-100 dark:border-gray-700 pt-3">
                                @if($event['details']['cart_identifier'])
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Cart ID</p>
                                        <p class="mt-0.5 text-sm font-mono text-gray-900 dark:text-gray-100">
                                            {{ Str::limit($event['details']['cart_identifier'], 20) }}
                                        </p>
                                    </div>
                                @endif

                                @if($event['details']['order_id'])
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Order ID</p>
                                        <p class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">
                                            #{{ $event['details']['order_id'] }}
                                        </p>
                                    </div>
                                @endif

                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Channel</p>
                                    <p class="mt-0.5 text-sm capitalize text-gray-900 dark:text-gray-100">
                                        {{ $event['details']['channel'] }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Savings</p>
                                    <p class="mt-0.5 text-sm font-semibold text-success-600 dark:text-success-400">
                                        {{ $event['details']['savings'] }}
                                    </p>
                                </div>

                                @if($event['details']['cart_snapshot'])
                                    @if(isset($event['details']['cart_items_count']))
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Cart Items</p>
                                            <p class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                                                {{ $event['details']['cart_items_count'] }} items
                                            </p>
                                        </div>
                                    @endif

                                    @if(isset($event['details']['cart_total']))
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Cart Total</p>
                                            <p class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ \Akaunting\Money\Money::MYR($event['details']['cart_total'])->format() }}
                                            </p>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            {{-- Notes --}}
                            @if($event['details']['notes'])
                                <div class="mt-3 rounded-md bg-gray-50 dark:bg-gray-900/50 p-3">
                                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Notes:</p>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $event['details']['notes'] }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Empty State --}}
            <div class="text-center py-12">
                <x-filament::icon
                    icon="heroicon-o-clock"
                    class="mx-auto h-12 w-12 text-gray-400"
                />
                <h3 class="mt-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                    No Usage History Yet
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    This voucher hasn't been redeemed yet. Usage activity will appear here once customers start using this voucher.
                </p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
