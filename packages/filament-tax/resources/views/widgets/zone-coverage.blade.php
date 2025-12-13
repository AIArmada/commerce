<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Tax Zone Coverage
        </x-slot>

        <x-slot name="description">
            Overview of all configured tax zones and their rates
        </x-slot>

        @if($zones->isEmpty())
            <div class="flex flex-col items-center justify-center p-6 text-center">
                <x-heroicon-o-globe-alt class="h-12 w-12 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No tax zones configured</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a tax zone.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($zones as $zone)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 {{ $zone['is_default'] ? 'bg-primary-50 dark:bg-primary-950 border-primary-200 dark:border-primary-700' : 'bg-white dark:bg-gray-800' }}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-medium text-gray-900 dark:text-white">
                                        {{ $zone['name'] }}
                                    </h4>
                                    @if($zone['is_default'])
                                        <x-filament::badge color="primary" size="sm">
                                            Default
                                        </x-filament::badge>
                                    @endif
                                    <x-filament::badge color="gray" size="sm">
                                        {{ $zone['type'] }}
                                    </x-filament::badge>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Code: {{ $zone['code'] }} | Priority: {{ $zone['priority'] }}
                                </p>
                            </div>
                            <div class="text-right text-sm text-gray-600 dark:text-gray-400">
                                @if(!empty($zone['countries']))
                                    <span class="font-medium">Countries:</span>
                                    {{ implode(', ', array_slice($zone['countries'], 0, 5)) }}
                                    @if(count($zone['countries']) > 5)
                                        <span class="text-gray-400">+{{ count($zone['countries']) - 5 }} more</span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        @if(count($zone['rates']) > 0)
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                                @foreach($zone['rates'] as $rate)
                                    <div class="rounded bg-gray-50 dark:bg-gray-700 p-2 text-sm">
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-500 dark:text-gray-400">{{ $rate['class'] }}</span>
                                            @if($rate['is_compound'])
                                                <x-heroicon-s-arrow-path class="h-3 w-3 text-warning-500" title="Compound" />
                                            @endif
                                        </div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $rate['rate'] }}
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500 truncate">
                                            {{ $rate['name'] }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-3 text-sm text-gray-500 dark:text-gray-400 italic">
                                No rates configured for this zone
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
