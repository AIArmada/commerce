<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <x-filament::card>
            <div class="text-sm text-gray-500">Pending Review</div>
            <div class="text-2xl font-bold">{{ number_format($pendingCount ?? 0) }}</div>
        </x-filament::card>
        
        <x-filament::card>
            <div class="text-sm text-gray-500">Critical Alerts</div>
            <div class="text-2xl font-bold text-danger-600">{{ number_format($criticalCount ?? 0) }}</div>
        </x-filament::card>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
