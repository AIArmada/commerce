<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('filament-cashier::portal.payment_methods.title') }}
        </x-slot>

        <div class="space-y-3">
            @forelse ($this->getPaymentMethods() as $gateway => $method)
                <div class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <x-filament::icon
                        icon="heroicon-o-credit-card"
                        class="h-5 w-5 text-gray-500"
                    />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ ucfirst($method['type']) }} •••• {{ $method['last4'] }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ ucfirst($gateway) }}
                        </p>
                    </div>
                    @if ($method['is_default'])
                        <span class="text-xs text-success-600 dark:text-success-400">
                            {{ __('filament-cashier::portal.payment_methods.default') }}
                        </span>
                    @endif
                </div>
            @empty
                <div class="text-center py-4 text-gray-500">
                    {{ __('filament-cashier::portal.payment_methods.empty') }}
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
