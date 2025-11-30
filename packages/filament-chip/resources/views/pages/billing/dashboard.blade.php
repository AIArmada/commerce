<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Subscriptions Overview --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Active Subscriptions') }}
            </x-slot>

            <x-slot name="headerEnd">
                @if($subscriptions->isNotEmpty())
                    <x-filament::link
                        :href="route('filament.' . config('filament-chip.billing.panel_id', 'billing') . '.pages.subscriptions')"
                        color="primary"
                    >
                        {{ __('Manage All') }}
                    </x-filament::link>
                @endif
            </x-slot>

            @if($subscriptions->isEmpty())
                <div class="text-center py-6">
                    <x-heroicon-o-credit-card class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No active subscriptions') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('You have no active subscriptions.') }}</p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($subscriptions->take(3) as $subscription)
                        <div class="py-4 first:pt-0 last:pb-0">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $subscription->type ?? __('Subscription') }}
                                    </h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        @if($subscription->onTrial())
                                            {{ __('Trial ends :date', ['date' => $subscription->trial_ends_at->format('M d, Y')]) }}
                                        @elseif($subscription->active())
                                            {{ __('Renews :date', ['date' => $subscription->next_billing_at?->format('M d, Y') ?? 'N/A']) }}
                                        @elseif($subscription->cancelled())
                                            {{ __('Cancelled') }}
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    @if($subscription->active() && !$subscription->onTrial())
                                        <x-filament::badge color="success">
                                            {{ __('Active') }}
                                        </x-filament::badge>
                                    @elseif($subscription->onTrial())
                                        <x-filament::badge color="warning">
                                            {{ __('Trial') }}
                                        </x-filament::badge>
                                    @elseif($subscription->canceled())
                                        <x-filament::badge color="danger">
                                            {{ __('Cancelled') }}
                                        </x-filament::badge>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Payment Methods Overview --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Payment Methods') }}
            </x-slot>

            <x-slot name="headerEnd">
                <x-filament::link
                    :href="route('filament.' . config('filament-chip.billing.panel_id', 'billing') . '.pages.payment-methods')"
                    color="primary"
                >
                    {{ __('Manage') }}
                </x-filament::link>
            </x-slot>

            @if($paymentMethods->isEmpty())
                <div class="text-center py-6">
                    <x-heroicon-o-credit-card class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No payment methods') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('No payment methods on file.') }}</p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($paymentMethods->take(2) as $method)
                        <div class="py-4 first:pt-0 last:pb-0 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="shrink-0">
                                    <x-heroicon-o-credit-card class="h-8 w-8 text-gray-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ ucfirst($method->cardBrand() ?? 'Card') }} •••• {{ $method->cardLastFour() ?? '****' }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('Expires :date', ['date' => ($method->cardExpMonth() ?? '00') . '/' . ($method->cardExpYear() ?? '00')]) }}
                                    </p>
                                </div>
                            </div>
                            @if($defaultPaymentMethod && $defaultPaymentMethod->chipToken() === $method->chipToken())
                                <x-filament::badge color="success" size="sm">
                                    {{ __('Default') }}
                                </x-filament::badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Recent Invoices --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Recent Invoices') }}
            </x-slot>

            <x-slot name="headerEnd">
                @if($invoices->isNotEmpty())
                    <x-filament::link
                        :href="route('filament.' . config('filament-chip.billing.panel_id', 'billing') . '.pages.invoices')"
                        color="primary"
                    >
                        {{ __('View All') }}
                    </x-filament::link>
                @endif
            </x-slot>

            @if($invoices->isEmpty())
                <div class="text-center py-6">
                    <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No invoices') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('No invoices yet.') }}</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($invoices->take(5) as $invoice)
                        <div class="flex items-center justify-between py-3 border-b border-gray-200 dark:border-gray-700 last:border-0">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $invoice->date()->format('M d, Y') }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $invoice->total() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::badge 
                                    :color="$invoice->status === 'paid' ? 'success' : 'warning'"
                                >
                                    {{ ucfirst($invoice->status ?? 'unknown') }}
                                </x-filament::badge>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
