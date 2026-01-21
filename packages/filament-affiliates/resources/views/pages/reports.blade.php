<x-filament-panels::page>
    {{ $this->form }}

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
        @if(isset($reportData['summary']))
            <x-filament::card>
                <div class="text-sm text-gray-500">Total Conversions</div>
                <div class="text-2xl font-bold">{{ number_format($reportData['summary']['total_conversions'] ?? 0) }}</div>
            </x-filament::card>
            
            <x-filament::card>
                <div class="text-sm text-gray-500">Total Revenue</div>
                <div class="text-2xl font-bold">{{ \Illuminate\Support\Number::currency(($reportData['summary']['total_revenue_minor'] ?? 0) / 100) }}</div>
            </x-filament::card>
            
            <x-filament::card>
                <div class="text-sm text-gray-500">Total Commission</div>
                <div class="text-2xl font-bold">{{ \Illuminate\Support\Number::currency(($reportData['summary']['total_commission_minor'] ?? 0) / 100) }}</div>
            </x-filament::card>
            
            <x-filament::card>
                <div class="text-sm text-gray-500">Active Affiliates</div>
                <div class="text-2xl font-bold">{{ number_format($reportData['summary']['active_affiliates'] ?? 0) }}</div>
            </x-filament::card>
        @endif
    </div>

    @if(isset($reportData['top_affiliates']) && count($reportData['top_affiliates']) > 0)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Top Performing Affiliates</x-slot>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-4">Affiliate</th>
                            <th class="text-right py-2 px-4">Conversions</th>
                            <th class="text-right py-2 px-4">Revenue</th>
                            <th class="text-right py-2 px-4">Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['top_affiliates'] as $affiliate)
                            <tr class="border-b">
                                <td class="py-2 px-4">{{ $affiliate['name'] ?? 'Unknown' }}</td>
                                <td class="text-right py-2 px-4">{{ number_format($affiliate['conversions'] ?? 0) }}</td>
                                <td class="text-right py-2 px-4">{{ \Illuminate\Support\Number::currency(($affiliate['revenue_minor'] ?? 0) / 100) }}</td>
                                <td class="text-right py-2 px-4">{{ \Illuminate\Support\Number::currency(($affiliate['commission_minor'] ?? 0) / 100) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if(isset($reportData['traffic_sources']) && count($reportData['traffic_sources']) > 0)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Traffic Sources</x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($reportData['traffic_sources']['sources'] ?? [] as $source => $count)
                    <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded">
                        <span>{{ $source ?: 'Direct' }}</span>
                        <span class="font-medium">{{ number_format((int) $count) }}</span>
                    </div>
                @endforeach
            </div>

            @if(! empty($reportData['traffic_sources']['campaigns'] ?? []))
                <div class="mt-6">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-2">Campaigns</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($reportData['traffic_sources']['campaigns'] as $campaign => $count)
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded">
                                <span>{{ $campaign ?: 'Unknown' }}</span>
                                <span class="font-medium">{{ number_format((int) $count) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
