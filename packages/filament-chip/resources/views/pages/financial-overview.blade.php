<x-filament-panels::page>
    <div class="space-y-6">
        @if($hasError)
            <x-filament::section>
                <div class="flex items-center gap-4 p-4 bg-danger-50 dark:bg-danger-900/20 rounded-lg">
                    <x-heroicon-o-exclamation-triangle class="h-8 w-8 text-danger-600" />
                    <div>
                        <h3 class="text-lg font-medium text-danger-800 dark:text-danger-200">{{ __('Unable to fetch financial data') }}</h3>
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $errorMessage }}</p>
                    </div>
                </div>
            </x-filament::section>
        @else
            {{-- Balance Summary --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-filament::section>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-success-600 dark:text-success-400">
                            MYR {{ number_format($balance['available'] / 100, 2) }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Available Balance') }}
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">
                            MYR {{ number_format($balance['pending'] / 100, 2) }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Pending Balance') }}
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-info-600 dark:text-info-400">
                            MYR {{ number_format($balance['reserved'] / 100, 2) }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Reserved') }}
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                            MYR {{ number_format($balance['total'] / 100, 2) }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Total Balance') }}
                        </div>
                    </div>
                </x-filament::section>
            </div>

            {{-- Monthly Turnover --}}
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('This Month\'s Turnover') }}
                </x-slot>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 bg-success-50 dark:bg-success-900/20 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <x-heroicon-o-arrow-trending-up class="h-5 w-5 text-success-600" />
                            <span class="text-sm font-medium text-success-800 dark:text-success-200">{{ __('Income') }}</span>
                        </div>
                        <div class="text-2xl font-bold text-success-600">
                            MYR {{ number_format($turnover['income'] / 100, 2) }}
                        </div>
                    </div>

                    <div class="p-4 bg-danger-50 dark:bg-danger-900/20 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <x-heroicon-o-receipt-percent class="h-5 w-5 text-danger-600" />
                            <span class="text-sm font-medium text-danger-800 dark:text-danger-200">{{ __('Fees') }}</span>
                        </div>
                        <div class="text-2xl font-bold text-danger-600">
                            MYR {{ number_format($turnover['fees'] / 100, 2) }}
                        </div>
                    </div>

                    <div class="p-4 bg-warning-50 dark:bg-warning-900/20 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <x-heroicon-o-arrow-uturn-left class="h-5 w-5 text-warning-600" />
                            <span class="text-sm font-medium text-warning-800 dark:text-warning-200">{{ __('Refunds') }}</span>
                        </div>
                        <div class="text-2xl font-bold text-warning-600">
                            MYR {{ number_format($turnover['refunds'] / 100, 2) }}
                        </div>
                    </div>

                    <div class="p-4 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <x-heroicon-o-banknotes class="h-5 w-5 text-primary-600" />
                            <span class="text-sm font-medium text-primary-800 dark:text-primary-200">{{ __('Net') }}</span>
                        </div>
                        <div class="text-2xl font-bold text-primary-600">
                            MYR {{ number_format($turnover['net'] / 100, 2) }}
                        </div>
                    </div>
                </div>
            </x-filament::section>

            {{-- Quick Links --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <x-filament::section>
                    <x-slot name="heading">
                        {{ __('Financial Reports') }}
                    </x-slot>

                    <div class="space-y-3">
                        <a href="{{ route('filament.admin.resources.company-statements.index') }}" 
                           class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <div class="flex items-center gap-3">
                                <x-heroicon-o-document-text class="h-6 w-6 text-gray-600" />
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ __('Company Statements') }}</div>
                                    <div class="text-sm text-gray-500">{{ __('View and download financial statements') }}</div>
                                </div>
                            </div>
                            <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400" />
                        </a>

                        <a href="{{ route('filament.admin.resources.purchases.index') }}" 
                           class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <div class="flex items-center gap-3">
                                <x-heroicon-o-shopping-cart class="h-6 w-6 text-gray-600" />
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ __('Purchases') }}</div>
                                    <div class="text-sm text-gray-500">{{ __('View all CHIP Collect transactions') }}</div>
                                </div>
                            </div>
                            <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400" />
                        </a>

                        <a href="{{ route('filament.admin.resources.send-instructions.index') }}" 
                           class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <div class="flex items-center gap-3">
                                <x-heroicon-o-banknotes class="h-6 w-6 text-gray-600" />
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ __('Payouts') }}</div>
                                    <div class="text-sm text-gray-500">{{ __('View all CHIP Send disbursements') }}</div>
                                </div>
                            </div>
                            <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400" />
                        </a>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">
                        {{ __('Financial Summary') }}
                    </x-slot>

                    <div class="prose dark:prose-invert max-w-none">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            This page provides an overview of your CHIP account's financial status. 
                            The balance information shows funds available for withdrawal, pending settlements, 
                            and any reserved amounts for potential refunds or disputes.
                        </p>
                        <div class="mt-4 space-y-2">
                            <div class="flex items-center gap-2 text-sm">
                                <x-heroicon-o-check-circle class="h-4 w-4 text-success-600" />
                                <span class="text-gray-600 dark:text-gray-400"><strong>Available:</strong> Funds ready for payout</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <x-heroicon-o-clock class="h-4 w-4 text-warning-600" />
                                <span class="text-gray-600 dark:text-gray-400"><strong>Pending:</strong> Funds awaiting settlement</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <x-heroicon-o-shield-exclamation class="h-4 w-4 text-info-600" />
                                <span class="text-gray-600 dark:text-gray-400"><strong>Reserved:</strong> Held for refunds/disputes</span>
                            </div>
                        </div>
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
