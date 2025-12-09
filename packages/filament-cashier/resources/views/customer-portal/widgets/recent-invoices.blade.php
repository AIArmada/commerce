<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('filament-cashier::portal.invoices.title') }}
        </x-slot>

        <div class="space-y-3">
            @forelse ($this->getRecentInvoices() as $invoice)
                <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $invoice['amount'] }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $invoice['date'] }}
                        </p>
                    </div>
                    @php
                        $statusColor = match($invoice['status']) {
                            'paid' => 'success',
                            'open' => 'warning',
                            default => 'gray',
                        };
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-{{ $statusColor }}-50 text-{{ $statusColor }}-700 dark:bg-{{ $statusColor }}-400/10 dark:text-{{ $statusColor }}-400">
                        {{ __('filament-cashier::portal.invoices.status.' . $invoice['status']) }}
                    </span>
                </div>
            @empty
                <div class="text-center py-4 text-gray-500">
                    {{ __('filament-cashier::portal.invoices.empty') }}
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
