<x-filament-panels::page>
    {{ $this->form }}

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
        @if(isset($reportData['summary']))
            <x-filament::card>
                <div class="text-sm text-gray-500">Total Conversions</div>
                <div class="text-2xl font-bold">{{ number_format($reportData['summary']['conversions'] ?? 0) }}</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500">Total Revenue</div>
                @if(($reportData['summary']['revenue_minor'] ?? null) === null)
                    <div class="text-2xl font-bold">—</div>
                    <div class="text-xs text-gray-400">Missing exchange rate; see breakdown</div>
                @else
                    <div class="text-2xl font-bold">{{ \AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor($reportData['summary']['revenue_minor'], $reportData['summary']['currency'] ?? config('affiliates.currency.default', 'MYR')) }}</div>
                    @if(! empty($reportData['summary']['converted']))
                        <div class="text-xs text-gray-400">Converted to {{ $reportData['summary']['currency'] }}@if(! empty($reportData['summary']['conversion']['as_of'])) · rates as of {{ $reportData['summary']['conversion']['as_of'] }}@endif</div>
                    @endif
                @endif
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500">Total Commission</div>
                @if(($reportData['summary']['commission_minor'] ?? null) === null)
                    <div class="text-2xl font-bold">—</div>
                    <div class="text-xs text-gray-400">Missing exchange rate; see breakdown</div>
                @else
                    <div class="text-2xl font-bold">{{ \AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor($reportData['summary']['commission_minor'], $reportData['summary']['currency'] ?? config('affiliates.currency.default', 'MYR')) }}</div>
                    @if(! empty($reportData['summary']['converted']))
                        <div class="text-xs text-gray-400">Converted to {{ $reportData['summary']['currency'] }}@if(! empty($reportData['summary']['conversion']['as_of'])) · rates as of {{ $reportData['summary']['conversion']['as_of'] }}@endif</div>
                    @endif
                @endif
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500">Attributions</div>
                <div class="text-2xl font-bold">{{ number_format($reportData['summary']['attributions'] ?? 0) }}</div>
            </x-filament::card>
        @endif
    </div>

    @if(! empty($reportData['summary']['by_currency'] ?? []) && count($reportData['summary']['by_currency']) > 1)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Totals by Currency</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-4">Currency</th>
                            <th class="text-right py-2 px-4">Conversions</th>
                            <th class="text-right py-2 px-4">Revenue</th>
                            <th class="text-right py-2 px-4">Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['summary']['by_currency'] as $currency => $money)
                            <tr class="border-b">
                                <td class="py-2 px-4">{{ $currency }}</td>
                                <td class="text-right py-2 px-4">{{ number_format($money['conversions'] ?? 0) }}</td>
                                <td class="text-right py-2 px-4">{{ \AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor($money['revenue_minor'] ?? 0, $currency) }}</td>
                                <td class="text-right py-2 px-4">{{ \AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor($money['commission_minor'] ?? 0, $currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if(isset($reportData['top_affiliates']) && count($reportData['top_affiliates']) > 0)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Top Performing Affiliates</x-slot>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-4">Affiliate</th>
                            <th class="text-left py-2 px-4">Currency</th>
                            <th class="text-right py-2 px-4">Conversions</th>
                            <th class="text-right py-2 px-4">Revenue</th>
                            <th class="text-right py-2 px-4">Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['top_affiliates'] as $affiliate)
                            <tr class="border-b">
                                <td class="py-2 px-4">{{ $affiliate['name'] ?? 'Unknown' }}</td>
                                <td class="py-2 px-4">{{ $affiliate['currency'] ?? config('affiliates.currency.default', 'MYR') }}</td>
                                <td class="text-right py-2 px-4">{{ number_format($affiliate['conversions'] ?? 0) }}</td>
                                <td class="text-right py-2 px-4">{{ \AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor($affiliate['revenue_minor'] ?? 0, $affiliate['currency'] ?? config('affiliates.currency.default', 'MYR')) }}</td>
                                <td class="text-right py-2 px-4">{{ \AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor($affiliate['commission_minor'] ?? 0, $affiliate['currency'] ?? config('affiliates.currency.default', 'MYR')) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if(isset($reportData['conversion_trend']) && count($reportData['conversion_trend']) > 0)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Conversion Trend</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-4">Date</th>
                            <th class="text-left py-2 px-4">Currency</th>
                            <th class="text-right py-2 px-4">Conversions</th>
                            <th class="text-right py-2 px-4">Revenue</th>
                            <th class="text-right py-2 px-4">Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['conversion_trend'] as $point)
                            <tr class="border-b">
                                <td class="py-2 px-4">{{ $point['date'] ?? '—' }}</td>
                                <td class="py-2 px-4">{{ $point['currency'] ?? config('affiliates.currency.default', 'MYR') }}</td>
                                <td class="text-right py-2 px-4">{{ number_format($point['conversions'] ?? 0) }}</td>
                                <td class="text-right py-2 px-4">{{ \AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor($point['revenue_minor'] ?? 0, $point['currency'] ?? config('affiliates.currency.default', 'MYR')) }}</td>
                                <td class="text-right py-2 px-4">{{ \AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor($point['commission_minor'] ?? 0, $point['currency'] ?? config('affiliates.currency.default', 'MYR')) }}</td>
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
