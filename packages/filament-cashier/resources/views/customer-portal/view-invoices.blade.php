<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Invoice
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Gateway
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Amount
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Status
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @forelse ($this->getInvoices() as $invoice)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $invoice['number'] }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ ucfirst($invoice['gateway']) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    {{ $invoice['amount'] }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $invoice['date'] }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    @php
                                        $statusColor = match($invoice['status']) {
                                            'paid' => 'success',
                                            'open' => 'warning',
                                            default => 'gray',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-{{ $statusColor }}-50 text-{{ $statusColor }}-700 ring-1 ring-inset ring-{{ $statusColor }}-600/20 dark:bg-{{ $statusColor }}-400/10 dark:text-{{ $statusColor }}-400 dark:ring-{{ $statusColor }}-400/30">
                                        {{ __('filament-cashier::portal.invoices.status.' . $invoice['status']) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    @if ($invoice['download_url'])
                                        <a
                                            href="{{ $invoice['download_url'] }}"
                                            target="_blank"
                                            class="text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                        >
                                            {{ __('filament-cashier::portal.invoices.download') }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <x-filament::icon
                                        icon="heroicon-o-document-text"
                                        class="mx-auto h-12 w-12 text-gray-400"
                                    />
                                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ __('filament-cashier::portal.invoices.empty') }}
                                    </h3>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
