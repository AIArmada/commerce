<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Gateway Health Status --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('filament-cashier::gateway.management.health_title') }}
            </x-slot>
            <x-slot name="description">
                {{ __('filament-cashier::gateway.management.health_description') }}
            </x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach($this->getGatewayHealth() as $gateway)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <x-filament::icon
                                    :icon="$gateway['icon']"
                                    class="w-6 h-6 text-{{ $gateway['color'] }}-500"
                                />
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white">
                                        {{ $gateway['label'] }}
                                    </h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('filament-cashier::gateway.management.last_check') }}: {{ $gateway['lastCheck'] }}
                                    </p>
                                </div>
                            </div>

                            <x-filament::badge :color="$gateway['statusColor']">
                                {{ __('filament-cashier::gateway.health.' . $gateway['status']) }}
                            </x-filament::badge>
                        </div>

                        @if($gateway['message'])
                            <p class="mt-2 text-sm text-{{ $gateway['statusColor'] }}-600 dark:text-{{ $gateway['statusColor'] }}-400">
                                {{ $gateway['message'] }}
                            </p>
                        @endif

                        @if($gateway['gateway'] === $this->getDefaultGateway())
                            <div class="mt-3">
                                <x-filament::badge color="primary" icon="heroicon-o-star">
                                    {{ __('filament-cashier::gateway.management.default_gateway') }}
                                </x-filament::badge>
                            </div>
                        @endif
                    </div>
                @endforeach

                @if($this->getGatewayHealth()->isEmpty())
                    <div class="col-span-2 text-center py-8">
                        <x-filament::icon
                            icon="heroicon-o-exclamation-triangle"
                            class="w-12 h-12 mx-auto text-warning-500"
                        />
                        <h3 class="mt-2 font-semibold text-gray-900 dark:text-white">
                            {{ __('filament-cashier::gateway.setup.no_gateway_title') }}
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ __('filament-cashier::gateway.setup.no_gateway_description') }}
                        </p>
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Gateway Configuration --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('filament-cashier::gateway.management.config_title') }}
            </x-slot>
            <x-slot name="description">
                {{ __('filament-cashier::gateway.management.config_description') }}
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <h4>{{ __('filament-cashier::gateway.management.stripe_title') }}</h4>
                <ul>
                    <li><code>STRIPE_KEY</code> - {{ __('filament-cashier::gateway.management.stripe_key_desc') }}</li>
                    <li><code>STRIPE_SECRET</code> - {{ __('filament-cashier::gateway.management.stripe_secret_desc') }}</li>
                    <li><code>STRIPE_WEBHOOK_SECRET</code> - {{ __('filament-cashier::gateway.management.stripe_webhook_desc') }}</li>
                </ul>

                <h4>{{ __('filament-cashier::gateway.management.chip_title') }}</h4>
                <ul>
                    <li><code>CHIP_BRAND_ID</code> - {{ __('filament-cashier::gateway.management.chip_brand_desc') }}</li>
                    <li><code>CHIP_API_KEY</code> - {{ __('filament-cashier::gateway.management.chip_api_desc') }}</li>
                    <li><code>CHIP_WEBHOOK_TOKEN</code> - {{ __('filament-cashier::gateway.management.chip_webhook_desc') }}</li>
                </ul>
            </div>
        </x-filament::section>

        {{-- Gateway Features Comparison --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                {{ __('filament-cashier::gateway.management.features_title') }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left p-2">{{ __('filament-cashier::gateway.management.feature') }}</th>
                            <th class="text-center p-2">Stripe</th>
                            <th class="text-center p-2">CHIP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @php
                            $features = [
                                ['name' => 'Subscriptions', 'stripe' => true, 'chip' => true],
                                ['name' => 'Invoices', 'stripe' => true, 'chip' => true],
                                ['name' => 'Multiple Payment Methods', 'stripe' => true, 'chip' => true],
                                ['name' => 'Webhooks', 'stripe' => true, 'chip' => true],
                                ['name' => 'Trial Periods', 'stripe' => true, 'chip' => true],
                                ['name' => 'Metered Billing', 'stripe' => true, 'chip' => false],
                                ['name' => 'Proration', 'stripe' => true, 'chip' => false],
                                ['name' => 'DuitNow QR', 'stripe' => false, 'chip' => true],
                                ['name' => 'FPX', 'stripe' => false, 'chip' => true],
                            ];
                        @endphp
                        @foreach($features as $feature)
                            <tr>
                                <td class="p-2">{{ $feature['name'] }}</td>
                                <td class="text-center p-2">
                                    @if($feature['stripe'])
                                        <x-filament::icon icon="heroicon-o-check-circle" class="w-5 h-5 mx-auto text-success-500" />
                                    @else
                                        <x-filament::icon icon="heroicon-o-x-circle" class="w-5 h-5 mx-auto text-gray-400" />
                                    @endif
                                </td>
                                <td class="text-center p-2">
                                    @if($feature['chip'])
                                        <x-filament::icon icon="heroicon-o-check-circle" class="w-5 h-5 mx-auto text-success-500" />
                                    @else
                                        <x-filament::icon icon="heroicon-o-x-circle" class="w-5 h-5 mx-auto text-gray-400" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
