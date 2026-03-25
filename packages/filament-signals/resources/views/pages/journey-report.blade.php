<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
    @endphp

    <div class="space-y-6">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ \Carbon\CarbonImmutable::parse($this->dateFrom)->format('M j, Y') }} - {{ \Carbon\CarbonImmutable::parse($this->dateTo)->format('M j, Y') }}
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">{{ number_format($summary['sessions']) }}</div>
                    <div class="text-sm text-gray-500">Sessions</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-info-600">{{ number_format($summary['unique_entry_paths']) }}</div>
                    <div class="text-sm text-gray-500">Entry Paths</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600">{{ number_format($summary['unique_exit_paths']) }}</div>
                    <div class="text-sm text-gray-500">Exit Paths</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-warning-600">{{ number_format($summary['bounced_sessions']) }}</div>
                    <div class="text-sm text-gray-500">Bounced Sessions</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($summary['avg_duration_seconds'], 0) }}s</div>
                    <div class="text-sm text-gray-500">Avg Duration</div>
                </div>
            </x-filament::section>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>