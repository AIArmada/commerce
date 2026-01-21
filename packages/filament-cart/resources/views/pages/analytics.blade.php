<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Date range info --}}
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                <span class="font-medium">Period:</span>
                {{ \Illuminate\Support\Carbon::parse($this->dateFrom)->format('M j, Y') }}
                -
                {{ \Illuminate\Support\Carbon::parse($this->dateTo)->format('M j, Y') }}
                <span class="text-xs ml-2">({{ \Illuminate\Support\Carbon::parse($this->dateFrom)->diffInDays(\Illuminate\Support\Carbon::parse($this->dateTo)) + 1 }} days)</span>
            </div>
        </div>

        {{-- Stats overview is in header widgets --}}

        {{-- Main content grid --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Conversion Funnel --}}
            @livewire(\AIArmada\FilamentCart\Widgets\ConversionFunnelWidget::class, ['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo, 'interval' => $this->interval], key('conversion-funnel-'.$this->dateFrom.'-'.$this->dateTo.'-'.$this->interval))

            {{-- Value Trends Chart --}}
            @livewire(\AIArmada\FilamentCart\Widgets\ValueTrendChartWidget::class, ['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo, 'interval' => $this->interval], key('value-trend-'.$this->dateFrom.'-'.$this->dateTo.'-'.$this->interval))
        </div>

        {{-- Abandonment Analysis --}}
        @if(config('filament-cart.features.abandonment_tracking', true))
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-1">
                @livewire(\AIArmada\FilamentCart\Widgets\AbandonmentAnalysisWidget::class, ['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo, 'interval' => $this->interval], key('abandonment-'.$this->dateFrom.'-'.$this->dateTo.'-'.$this->interval))
            </div>
        @endif

        {{-- Recovery Performance --}}
        @if(config('filament-cart.features.recovery', true))
            @livewire(\AIArmada\FilamentCart\Widgets\RecoveryPerformanceWidget::class, ['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo, 'interval' => $this->interval], key('recovery-'.$this->dateFrom.'-'.$this->dateTo.'-'.$this->interval))
        @endif
    </div>
</x-filament-panels::page>
