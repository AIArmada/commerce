<x-filament-panels::page>
    <div class="space-y-6">
        @if($paymentMethods->isEmpty())
            <x-filament::section>
                <x-filament-panels::placeholder>
                    <x-slot name="icon">
                        <x-heroicon-o-credit-card class="h-12 w-12" />
                    </x-slot>

                    <x-slot name="heading">
                        {{ __('No payment methods') }}
                    </x-slot>

                    <x-slot name="description">
                        {{ __('You have not added any payment methods yet.') }}
                    </x-slot>

                    <x-slot name="actions">
                        @if($this->getAddPaymentMethodUrl() !== '#')
                            <x-filament::button
                                tag="a"
                                :href="$this->getAddPaymentMethodUrl()"
                                color="primary"
                            >
                                {{ __('Add Payment Method') }}
                            </x-filament::button>
                        @endif
                    </x-slot>
                </x-filament-panels::placeholder>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Your Payment Methods') }}
                </x-slot>

                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($paymentMethods as $method)
                        <div class="py-4 first:pt-0 last:pb-0">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="shrink-0">
                                        @php
                                            $brand = strtolower($method->card_brand ?? 'card');
                                        @endphp
                                        @switch($brand)
                                            @case('visa')
                                                <div class="w-12 h-8 bg-blue-600 rounded flex items-center justify-center text-white text-xs font-bold">
                                                    VISA
                                                </div>
                                                @break
                                            @case('mastercard')
                                                <div class="w-12 h-8 bg-red-600 rounded flex items-center justify-center text-white text-xs font-bold">
                                                    MC
                                                </div>
                                                @break
                                            @case('amex')
                                                <div class="w-12 h-8 bg-blue-800 rounded flex items-center justify-center text-white text-xs font-bold">
                                                    AMEX
                                                </div>
                                                @break
                                            @default
                                                <x-heroicon-o-credit-card class="h-8 w-12 text-gray-400" />
                                        @endswitch
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $this->formatCardBrand($method->card_brand ?? 'Card') }} •••• {{ $method->card_last_four ?? '****' }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('Expires :month/:year', ['month' => str_pad($method->card_exp_month ?? '00', 2, '0', STR_PAD_LEFT), 'year' => substr($method->card_exp_year ?? '00', -2)]) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($defaultPaymentMethod && $defaultPaymentMethod->chip_token === $method->chip_token)
                                        <x-filament::badge color="success" size="sm">
                                            {{ __('Default') }}
                                        </x-filament::badge>
                                    @else
                                        <x-filament::button
                                            color="gray"
                                            size="xs"
                                            wire:click="setAsDefault('{{ $method->chip_token }}')"
                                        >
                                            {{ __('Set as Default') }}
                                        </x-filament::button>
                                    @endif
                                    
                                    <x-filament::button
                                        color="danger"
                                        size="xs"
                                        wire:click="deletePaymentMethod('{{ $method->chip_token }}')"
                                        wire:confirm="{{ __('Are you sure you want to delete this payment method?') }}"
                                    >
                                        {{ __('Delete') }}
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
