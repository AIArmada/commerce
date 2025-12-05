<x-filament-widgets::widget>
    <x-filament::section heading="AI Optimization Settings" icon="heroicon-o-sparkles">
        @if(!$enabled)
            <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded text-yellow-800 dark:text-yellow-200">
                <strong>AI optimization is currently disabled.</strong>
                <p class="text-sm">Enable it in your vouchers.php config file.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-3">
                    <h4 class="font-semibold text-gray-900 dark:text-white">Conversion Prediction</h4>
                    <div class="text-sm space-y-1">
                        <div class="flex justify-between">
                            <span class="text-gray-500">High probability threshold</span>
                            <span class="font-medium text-green-600">{{ $conversionThresholdHigh * 100 }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Low probability threshold</span>
                            <span class="font-medium text-red-600">{{ $conversionThresholdLow * 100 }}%</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <h4 class="font-semibold text-gray-900 dark:text-white">Abandonment Risk</h4>
                    <div class="text-sm space-y-1">
                        <div class="flex justify-between">
                            <span class="text-gray-500">High risk threshold</span>
                            <span class="font-medium text-orange-600">{{ $abandonmentHighRisk * 100 }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Critical risk threshold</span>
                            <span class="font-medium text-red-600">{{ $abandonmentCritical * 100 }}%</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <h4 class="font-semibold text-gray-900 dark:text-white">Discount Optimization</h4>
                    <div class="text-sm space-y-1">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Minimum ROI</span>
                            <span class="font-medium">{{ $discountMinROI }}x</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Max discount</span>
                            <span class="font-medium">{{ $discountMaxPercent }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Min match score</span>
                            <span class="font-medium">{{ $matchingMinScore * 100 }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800 rounded">
                <h5 class="font-medium text-gray-900 dark:text-white mb-2">AI Features</h5>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                        <span>Conversion Prediction</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                        <span>Abandonment Detection</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                        <span>Discount Optimization</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                        <span>Voucher Matching</span>
                    </div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
