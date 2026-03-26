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
                    <div class="text-2xl font-bold text-info-600">{{ number_format($summary['browsers']) }}</div>
                    <div class="text-sm text-gray-500">Browsers</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600">{{ number_format($summary['operating_systems']) }}</div>
                    <div class="text-sm text-gray-500">Operating Systems</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-warning-600">{{ number_format($summary['brands']) }}</div>
                    <div class="text-sm text-gray-500">Device Brands</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-danger-600">{{ number_format($summary['bots']) }}</div>
                    <div class="text-sm text-gray-500">Bots</div>
                </div>
            </x-filament::section>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button
                :color="$this->activeTab === 'device_type' ? 'primary' : 'gray'"
                wire:click="setTab('device_type')"
                size="sm"
            >
                Devices
            </x-filament::button>

            <x-filament::button
                :color="$this->activeTab === 'browser' ? 'primary' : 'gray'"
                wire:click="setTab('browser')"
                size="sm"
            >
                Browsers
            </x-filament::button>

            <x-filament::button
                :color="$this->activeTab === 'os' ? 'primary' : 'gray'"
                wire:click="setTab('os')"
                size="sm"
            >
                Operating Systems
            </x-filament::button>

            <x-filament::button
                :color="$this->activeTab === 'brand_model' ? 'primary' : 'gray'"
                wire:click="setTab('brand_model')"
                size="sm"
            >
                Brands
            </x-filament::button>

            <div class="ml-auto">
                <x-filament::button
                    :color="$this->includeBots ? 'danger' : 'gray'"
                    wire:click="toggleBots"
                    size="sm"
                    outlined
                >
                    {{ $this->includeBots ? 'Exclude bots' : 'Include bots' }}
                </x-filament::button>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
