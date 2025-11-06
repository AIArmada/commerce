<x-filament-widgets::widget>
    @php
        $suggestions = $this->getEligibleVouchers();
    @endphp

    @if($suggestions->isNotEmpty())
        <x-filament::section
            icon="heroicon-o-sparkles"
            heading="Suggested Vouchers"
            description="Save more with these available vouchers"
        >
            <div class="space-y-3">
                @foreach($suggestions as $suggestion)
                    @php
                        $voucher = $suggestion['voucher'];
                        $typeColor = match($voucher->type->value) {
                            'percentage' => 'success',
                            'fixed' => 'info',
                            'free_shipping' => 'warning',
                            default => 'gray',
                        };
                    @endphp

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 space-y-2">
                                <div class="flex items-center gap-3">
                                    <x-filament::badge
                                        :color="$typeColor"
                                        size="lg"
                                        class="font-mono"
                                    >
                                        {{ $voucher->code }}
                                    </x-filament::badge>

                                    <div class="flex items-center gap-1.5 text-lg font-bold text-success-600 dark:text-success-400">
                                        <x-filament::icon
                                            icon="heroicon-o-arrow-trending-down"
                                            class="h-5 w-5"
                                        />
                                        <span>{{ $suggestion['savings_text'] }}</span>
                                    </div>
                                </div>

                                @if($voucher->description)
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $voucher->description }}
                                    </p>
                                @endif

                                <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-1">
                                        <x-filament::icon
                                            icon="heroicon-o-information-circle"
                                            class="h-3.5 w-3.5"
                                        />
                                        <span>{{ $suggestion['recommendation'] }}</span>
                                    </div>

                                    @if($voucher->end_date)
                                        <div class="flex items-center gap-1">
                                            <x-filament::icon
                                                icon="heroicon-o-clock"
                                                class="h-3.5 w-3.5"
                                            />
                                            <span>Expires {{ $voucher->end_date->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex-shrink-0">
                                <x-filament::button
                                    wire:click="applySuggestion('{{ $voucher->code }}')"
                                    color="success"
                                    icon="heroicon-o-plus-circle"
                                    size="sm"
                                >
                                    Apply
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 rounded-lg bg-info-50 dark:bg-info-950/50 border border-info-200 dark:border-info-800 p-3">
                <div class="flex items-start gap-2 text-sm text-info-700 dark:text-info-400">
                    <x-filament::icon
                        icon="heroicon-o-light-bulb"
                        class="h-5 w-5 flex-shrink-0 mt-0.5"
                    />
                    <div>
                        <p class="font-medium">Pro Tip</p>
                        <p class="text-xs mt-1">
                            These vouchers are automatically sorted by potential savings.
                            The top recommendation will save you the most money!
                        </p>
                    </div>
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section
            icon="heroicon-o-ticket"
            icon-color="gray"
        >
            <div class="text-center py-8">
                <x-filament::icon
                    icon="heroicon-o-ticket"
                    class="mx-auto h-12 w-12 text-gray-400"
                />
                <h3 class="mt-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                    No Vouchers Available
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    There are no eligible vouchers for your cart at this time.
                </p>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
