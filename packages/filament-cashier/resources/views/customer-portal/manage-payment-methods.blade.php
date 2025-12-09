<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $paymentMethods = $this->getPaymentMethods();
        @endphp

        @forelse ($paymentMethods as $gateway => $methods)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ ucfirst($gateway) }} Payment Methods
                    </h3>
                </div>
                <div class="fi-section-content divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($methods as $method)
                        <div class="flex items-center justify-between p-4">
                            <div class="flex items-center gap-4">
                                <div class="rounded-lg bg-gray-100 p-3 dark:bg-gray-800">
                                    <x-filament::icon
                                        icon="heroicon-o-credit-card"
                                        class="h-6 w-6 text-gray-600 dark:text-gray-400"
                                    />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ ucfirst($method['type']) }} •••• {{ $method['last4'] }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        Expires {{ $method['expiry'] }}
                                    </p>
                                </div>
                                @if ($method['is_default'])
                                    <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">
                                        {{ __('filament-cashier::portal.payment_methods.default') }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex gap-2">
                                @unless ($method['is_default'])
                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        wire:click="setDefaultPaymentMethod('{{ $gateway }}', '{{ $method['id'] }}')"
                                    >
                                        {{ __('filament-cashier::portal.payment_methods.set_default') }}
                                    </x-filament::button>
                                @endunless

                                <x-filament::button
                                    size="sm"
                                    color="danger"
                                    wire:click="deletePaymentMethod('{{ $gateway }}', '{{ $method['id'] }}')"
                                    wire:confirm="Are you sure you want to delete this payment method?"
                                >
                                    {{ __('filament-cashier::portal.payment_methods.delete') }}
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content p-12 text-center">
                    <x-filament::icon
                        icon="heroicon-o-banknotes"
                        class="mx-auto h-12 w-12 text-gray-400"
                    />
                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('filament-cashier::portal.payment_methods.empty') }}
                    </h3>
                </div>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
