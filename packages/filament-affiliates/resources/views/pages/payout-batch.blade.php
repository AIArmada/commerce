<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-filament::card>
            <div class="text-sm text-gray-500">Pending Payouts</div>
            <div class="text-2xl font-bold">{{ number_format($pendingCount ?? 0) }}</div>
        </x-filament::card>
        
        @foreach(($pendingByCurrency ?? []) as $currency)
            <x-filament::card>
                <div class="text-sm text-gray-500">{{ $currency->currency }} Pending</div>
                <div class="text-2xl font-bold">{{ \Illuminate\Support\Number::currency($currency->total / 100, $currency->currency) }}</div>
                <div class="text-xs text-gray-400">{{ $currency->count }} payouts</div>
            </x-filament::card>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
