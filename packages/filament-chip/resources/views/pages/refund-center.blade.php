<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Refund Guidelines --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                {{ __('Refund Guidelines') }}
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-success-50 dark:bg-success-900/20 rounded-lg">
                        <h4 class="font-medium text-success-800 dark:text-success-200 flex items-center gap-2">
                            <x-heroicon-o-check-circle class="h-5 w-5" />
                            {{ __('Full Refund') }}
                        </h4>
                        <p class="text-sm text-success-700 dark:text-success-300 mt-2">
                            Refunds the entire purchase amount. Use when the customer requests a complete refund or order cancellation.
                        </p>
                    </div>
                    <div class="p-4 bg-warning-50 dark:bg-warning-900/20 rounded-lg">
                        <h4 class="font-medium text-warning-800 dark:text-warning-200 flex items-center gap-2">
                            <x-heroicon-o-minus-circle class="h-5 w-5" />
                            {{ __('Partial Refund') }}
                        </h4>
                        <p class="text-sm text-warning-700 dark:text-warning-300 mt-2">
                            Refunds a specific amount. Use for partial returns, price adjustments, or promotional credits.
                        </p>
                    </div>
                </div>
                <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <h4 class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <x-heroicon-o-information-circle class="h-5 w-5 text-info-600" />
                        {{ __('Important Notes') }}
                    </h4>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                        <li>• Refunds are processed through CHIP and may take 3-7 business days to appear in customer accounts.</li>
                        <li>• Refunded amounts cannot be recovered once processed.</li>
                        <li>• Processing fees may not be refundable depending on payment method.</li>
                        <li>• Keep records of refund reasons for accounting purposes.</li>
                    </ul>
                </div>
            </div>
        </x-filament::section>

        {{-- Refundable Purchases Table --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Refundable Purchases') }}
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
