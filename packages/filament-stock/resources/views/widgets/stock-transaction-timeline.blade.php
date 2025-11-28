<x-filament-widgets::widget>
    @php
        $events = $this->getTimelineEvents();
        $stats = $this->getSummaryStats();
    @endphp

    <x-filament::section
        icon="heroicon-o-clock"
        heading="Stock History"
        description="Timeline of stock movements and transactions"
    >
        {{-- Summary Stats --}}
        @if($stats['total_transactions'] > 0)
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-primary-100 dark:bg-primary-900/50 p-2">
                            <x-filament::icon
                                icon="heroicon-o-document-text"
                                class="h-5 w-5 text-primary-600 dark:text-primary-400"
                            />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Transactions</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $stats['total_transactions'] }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-success-100 dark:bg-success-900/50 p-2">
                            <x-filament::icon
                                icon="heroicon-o-arrow-up"
                                class="h-5 w-5 text-success-600 dark:text-success-400"
                            />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total In</p>
                            <p class="text-2xl font-bold text-success-600 dark:text-success-400">
                                +{{ number_format($stats['total_in']) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-danger-100 dark:bg-danger-900/50 p-2">
                            <x-filament::icon
                                icon="heroicon-o-arrow-down"
                                class="h-5 w-5 text-danger-600 dark:text-danger-400"
                            />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Out</p>
                            <p class="text-2xl font-bold text-danger-600 dark:text-danger-400">
                                -{{ number_format($stats['total_out']) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-info-100 dark:bg-info-900/50 p-2">
                            <x-filament::icon
                                icon="heroicon-o-scale"
                                class="h-5 w-5 text-info-600 dark:text-info-400"
                            />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Net Change</p>
                            <p class="text-2xl font-bold {{ $stats['net_change'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ $stats['net_change'] >= 0 ? '+' : '' }}{{ number_format($stats['net_change']) }}
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
                                    <h3 class="text-base font-semibold {{ $event['type'] === 'in' ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
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
                            <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 border-t border-gray-100 dark:border-gray-700 pt-3">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Quantity</p>
                                    <p class="mt-0.5 text-sm font-semibold {{ $event['type'] === 'in' ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                        {{ $event['type'] === 'in' ? '+' : '-' }}{{ $event['details']['quantity'] }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Reason</p>
                                    <p class="mt-0.5 text-sm capitalize text-gray-900 dark:text-gray-100">
                                        {{ $event['details']['reason'] ?? 'Unknown' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Performed By</p>
                                    <p class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $event['details']['user'] }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Item Type</p>
                                    <p class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $event['details']['stockable_type'] }}
                                    </p>
                                </div>
                            </div>

                            {{-- Note --}}
                            @if($event['details']['note'])
                                <div class="mt-3 rounded-md bg-gray-50 dark:bg-gray-900/50 p-3">
                                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Note:</p>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $event['details']['note'] }}
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
                    icon="heroicon-o-cube"
                    class="mx-auto h-12 w-12 text-gray-400"
                />
                <h3 class="mt-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                    No Stock History Yet
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Stock transactions will appear here once items are added or removed from inventory.
                </p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
