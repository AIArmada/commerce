<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $rows = $this->getRows();
    @endphp

    <div class="space-y-6">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ \Carbon\CarbonImmutable::parse($this->dateFrom)->format('M j, Y') }} - {{ \Carbon\CarbonImmutable::parse($this->dateTo)->format('M j, Y') }}
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">{{ number_format($summary['cohorts']) }}</div>
                    <div class="text-sm text-gray-500">Cohorts</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-info-600">{{ number_format($summary['identities']) }}</div>
                    <div class="text-sm text-gray-500">Identities</div>
                </div>
            </x-filament::section>

            @foreach ($summary['windows'] as $window)
                <x-filament::section>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-success-600">{{ number_format($window['retained']) }}</div>
                        <div class="text-sm text-gray-500">Retained {{ $window['days'] }}d</div>
                        <div class="text-xs text-gray-400">Avg {{ number_format($window['avg_retention_rate'], 2) }}%</div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-medium">Cohort</th>
                            <th class="px-4 py-3 font-medium">Size</th>
                            @foreach ($summary['windows'] as $window)
                                <th class="px-4 py-3 font-medium">Retained {{ $window['days'] }}d</th>
                                <th class="px-4 py-3 font-medium">{{ $window['days'] }}d Rate</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['cohort_date'] }}</td>
                                <td class="px-4 py-3">{{ number_format($row['cohort_size']) }}</td>
                                @foreach ($row['windows'] as $window)
                                    <td class="px-4 py-3">{{ number_format($window['retained']) }}</td>
                                    <td class="px-4 py-3">{{ number_format($window['retention_rate'], 2) }}%</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + (count($summary['windows']) * 2) }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    Retention cohorts will appear here once identities accumulate enough history for repeat activity.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>