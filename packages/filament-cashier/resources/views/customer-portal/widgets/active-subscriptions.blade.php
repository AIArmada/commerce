<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('filament-cashier::portal.subscriptions.title') }}
        </x-slot>

        <div class="space-y-4">
            @forelse ($this->getSubscriptions() as $subscription)
                <div class="flex items-center justify-between rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-{{ $subscription->gatewayConfig()['color'] }}-50 p-2 dark:bg-{{ $subscription->gatewayConfig()['color'] }}-400/10">
                            <x-filament::icon
                                :icon="$subscription->gatewayConfig()['icon']"
                                class="h-5 w-5 text-{{ $subscription->gatewayConfig()['color'] }}-600 dark:text-{{ $subscription->gatewayConfig()['color'] }}-400"
                            />
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">
                                {{ ucfirst($subscription->type) }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ $subscription->billingCycle() }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-900 dark:text-white">
                            {{ $subscription->formattedAmount() }}
                        </p>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-{{ $subscription->status->color() }}-50 text-{{ $subscription->status->color() }}-700 dark:bg-{{ $subscription->status->color() }}-400/10 dark:text-{{ $subscription->status->color() }}-400">
                            {{ $subscription->status->label() }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-gray-500">
                    {{ __('filament-cashier::portal.subscriptions.empty') }}
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
