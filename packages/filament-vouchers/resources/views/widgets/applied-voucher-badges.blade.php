<x-filament-widgets::widget>
    <div class="space-y-4">
        @php
            $vouchers = $this->getAppliedVouchers();
        @endphp

        @if(count($vouchers) > 0)
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-ticket"
                        class="h-5 w-5 text-gray-400"
                    />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Applied Vouchers:
                    </span>
                </div>

                @foreach($vouchers as $voucher)
                    @php
                        $badgeColor = match($voucher['status']) {
                            'active' => 'success',
                            'expiring_soon' => 'warning',
                            'low_uses' => 'warning',
                            'expired' => 'danger',
                            'limit_reached' => 'danger',
                            default => 'gray',
                        };
                        
                        $statusIcon = match($voucher['status']) {
                            'expiring_soon' => 'heroicon-o-clock',
                            'low_uses' => 'heroicon-o-exclamation-triangle',
                            'expired' => 'heroicon-o-x-circle',
                            'limit_reached' => 'heroicon-o-x-circle',
                            default => 'heroicon-o-check-circle',
                        };
                    @endphp

                    <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 shadow-sm">
                        <div class="flex items-center gap-2">
                            <x-filament::badge :color="$badgeColor">
                                {{ $voucher['code'] }}
                            </x-filament::badge>
                            
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $voucher['discount_text'] }}
                            </span>

                            @if($voucher['status'] !== 'active')
                                <x-filament::icon
                                    :icon="$statusIcon"
                                    class="h-4 w-4"
                                    :class="$badgeColor === 'warning' ? 'text-warning-500' : 'text-danger-500'"
                                />
                            @endif
                        </div>

                        <button
                            wire:click="removeVoucherAction('{{ $voucher['code'] }}')"
                            type="button"
                            class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300 transition-colors"
                            title="Remove voucher"
                        >
                            <x-filament::icon
                                icon="heroicon-o-x-mark"
                                class="h-4 w-4"
                            />
                        </button>
                    </div>
                @endforeach
            </div>

            @if(collect($vouchers)->contains('status', 'expiring_soon'))
                <div class="rounded-lg border border-warning-200 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 p-3">
                    <div class="flex items-center gap-2">
                        <x-filament::icon
                            icon="heroicon-o-clock"
                            class="h-5 w-5 text-warning-500"
                        />
                        <span class="text-sm text-warning-700 dark:text-warning-400">
                            Some vouchers are expiring soon. Use them before they expire!
                        </span>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-filament-widgets::widget>
