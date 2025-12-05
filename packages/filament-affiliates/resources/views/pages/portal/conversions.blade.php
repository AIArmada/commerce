<x-filament-panels::page>
    @if(!$hasAffiliate)
        <x-filament::section>
            <div class="text-center py-8">
                <x-heroicon-o-user-plus class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('No Affiliate Account') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('You do not have an affiliate account yet.') }}</p>
            </div>
        </x-filament::section>
    @else
        <div class="space-y-6">
            {{-- Stats Summary --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-filament::section>
                    <div class="text-center">
                        <x-heroicon-o-chart-bar class="mx-auto h-8 w-8 text-primary-500" />
                        <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ number_format($totalConversions) }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total Conversions') }}</p>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-center">
                        <x-heroicon-o-currency-dollar class="mx-auto h-8 w-8 text-success-500" />
                        <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $this->formatAmount($totalEarnings) }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Approved Earnings') }}</p>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-center">
                        <x-heroicon-o-clock class="mx-auto h-8 w-8 text-warning-500" />
                        <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $this->formatAmount($pendingEarnings) }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Pending Earnings') }}</p>
                    </div>
                </x-filament::section>
            </div>

            {{-- Conversions Table --}}
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Conversion History') }}
                </x-slot>

                {{ $this->table }}
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
