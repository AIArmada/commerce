<x-filament-panels::page>
    @if($this->campaign === null)
        <x-filament::section>
            <div class="text-center py-8">
                <x-heroicon-o-beaker class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No A/B Test Campaign Selected</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Select a campaign with A/B testing enabled to view analysis.
                </p>
            </div>
        </x-filament::section>
    @else
        {{-- Campaign Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-4">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ $this->campaign->name }}
                </h2>
                <x-filament::badge :color="match($this->campaign->status->value) {
                    'draft' => 'gray',
                    'scheduled' => 'info',
                    'active' => 'success',
                    'paused' => 'warning',
                    'completed' => 'primary',
                    'cancelled' => 'danger',
                    default => 'gray',
                }">
                    {{ $this->campaign->status->label() }}
                </x-filament::badge>
                @if($this->campaign->ab_winner_variant)
                    <x-filament::badge color="success">
                        Winner: {{ $this->campaign->ab_winner_variant }}
                    </x-filament::badge>
                @endif
            </div>
        </div>

        {{-- Summary Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($this->analysisData['totalImpressions'] ?? 0) }}
                    </p>
                    <p class="text-sm text-gray-500">Total Impressions</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($this->analysisData['totalConversions'] ?? 0) }}
                    </p>
                    <p class="text-sm text-gray-500">Total Conversions</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $this->analysisData['totalRevenue'] ?? 'MYR 0.00' }}
                    </p>
                    <p class="text-sm text-gray-500">Total Revenue</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    @if($this->analysisData['hasEnoughData'] ?? false)
                        <x-filament::badge color="success" size="lg">
                            Sufficient Data
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="warning" size="lg">
                            Need More Data
                        </x-filament::badge>
                    @endif
                    <p class="text-sm text-gray-500 mt-1">Min 30 samples/variant</p>
                </div>
            </x-filament::section>
        </div>

        {{-- Variant Comparison Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($this->analysisData['variants'] ?? [] as $code => $data)
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="font-bold">Variant {{ $code }}</span>
                                <span class="text-gray-500">{{ $data['variant']->name }}</span>
                            </span>
                            <div class="flex gap-2">
                                @if($data['variant']->is_control)
                                    <x-filament::badge color="gray">Control</x-filament::badge>
                                @endif
                                @if($code === ($this->analysisData['suggestedWinner'] ?? null))
                                    <x-filament::badge color="success">Suggested Winner</x-filament::badge>
                                @endif
                            </div>
                        </div>
                    </x-slot>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <p class="text-sm text-gray-500">Sample Size</p>
                            <p class="text-xl font-bold">{{ number_format($data['sample_size']) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Conversions</p>
                            <p class="text-xl font-bold">{{ number_format($data['conversions']) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Conversion Rate</p>
                            <p class="text-xl font-bold">{{ number_format($data['conversion_rate'] * 100, 2) }}%</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <p class="text-sm text-gray-500">Revenue</p>
                            <p class="text-lg font-semibold text-green-600">{{ $data['revenue'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Discount</p>
                            <p class="text-lg font-semibold text-red-600">{{ $data['discount'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Avg Order Value</p>
                            <p class="text-lg font-semibold">{{ $data['aov'] }}</p>
                        </div>
                    </div>

                    @if($data['comparison'] !== null)
                        <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <p class="text-sm font-medium mb-2">Comparison vs Control</p>
                            <div class="flex gap-4 text-sm">
                                <span class="{{ $data['comparison']['conversion_lift'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $data['comparison']['conversion_lift'] > 0 ? '+' : '' }}{{ number_format($data['comparison']['conversion_lift'], 1) }}% conversion
                                </span>
                                <span class="{{ $data['comparison']['revenue_lift'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $data['comparison']['revenue_lift'] > 0 ? '+' : '' }}{{ number_format($data['comparison']['revenue_lift'], 1) }}% revenue
                                </span>
                            </div>

                            @if($data['significance'] !== null)
                                <div class="mt-2">
                                    @if($data['significance']['significant'])
                                        <x-filament::badge color="success" size="sm">
                                            Statistically Significant (p={{ number_format($data['significance']['p_value'], 4) }})
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray" size="sm">
                                            Not Significant (p={{ number_format($data['significance']['p_value'], 4) }})
                                        </x-filament::badge>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        @if(empty($this->analysisData['variants']))
            <x-filament::section>
                <div class="text-center py-8">
                    <x-heroicon-o-chart-bar class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No Variants Found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Add variants to this campaign to start A/B testing.
                    </p>
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
