<x-filament-panels::page>
    <div class="space-y-6">
        @if($invoices->isEmpty())
            <x-filament::section>
                <x-filament-panels::placeholder>
                    <x-slot name="icon">
                        <x-heroicon-o-document-text class="h-12 w-12" />
                    </x-slot>

                    <x-slot name="heading">
                        {{ __('No invoices') }}
                    </x-slot>

                    <x-slot name="description">
                        {{ __('You have no billing history yet.') }}
                    </x-slot>
                </x-filament-panels::placeholder>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Billing History') }}
                </x-slot>

                <div class="overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th scope="col" class="py-3.5 pl-0 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ __('Date') }}
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ __('Invoice #') }}
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ __('Amount') }}
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ __('Status') }}
                                </th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-0 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-0 pr-3 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $invoice->date()->format('M d, Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $invoice->number ?? $invoice->id }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $invoice->total() }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm">
                                        <x-filament::badge :color="$this->getStatusColor($invoice->status ?? 'unknown')">
                                            {{ $this->formatInvoiceStatus($invoice->status ?? 'unknown') }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-0 text-right text-sm">
                                        <x-filament::button
                                            tag="a"
                                            :href="route('filament.' . config('filament-chip.billing.panel_id', 'billing') . '.pages.invoices') . '?download=' . $invoice->id"
                                            color="gray"
                                            size="xs"
                                            icon="heroicon-o-arrow-down-tray"
                                            wire:click="downloadInvoice('{{ $invoice->id }}')"
                                        >
                                            {{ __('Download') }}
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
