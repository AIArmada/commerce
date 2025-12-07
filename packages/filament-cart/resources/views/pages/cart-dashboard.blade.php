<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Stats --}}
        @livewire(\AIArmada\FilamentCart\Widgets\CartStatsOverviewWidget::class)
        
        {{-- Abandoned Carts Table --}}
        <div class="mt-6">
            @livewire(\AIArmada\FilamentCart\Widgets\AbandonedCartsWidget::class)
        </div>
    </div>
</x-filament-panels::page>
