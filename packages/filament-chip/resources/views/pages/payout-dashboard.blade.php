<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Key Metrics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $metrics['total_payouts'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Total Payouts') }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-success-600 dark:text-success-400">
                        MYR {{ number_format($metrics['completed_amount'] ?? 0, 2) }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Completed Amount') }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">
                        {{ $metrics['pending_count'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Pending') }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">
                        {{ $metrics['failed_count'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Failed') }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $metrics['active_accounts'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Active Accounts') }}
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Payout Status Breakdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Payout Status (:period Days)', ['period' => $period]) }}
                </x-slot>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-success-50 dark:bg-success-900/20 rounded-lg">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="h-5 w-5 text-success-600" />
                            <span class="text-sm font-medium text-success-800 dark:text-success-200">{{ __('Completed') }}</span>
                        </div>
                        <span class="text-lg font-bold text-success-600">
                            MYR {{ number_format($metrics['completed_amount'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-3 bg-warning-50 dark:bg-warning-900/20 rounded-lg">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-clock class="h-5 w-5 text-warning-600" />
                            <span class="text-sm font-medium text-warning-800 dark:text-warning-200">{{ __('Pending') }}</span>
                        </div>
                        <span class="text-lg font-bold text-warning-600">{{ $metrics['pending_count'] ?? 0 }}</span>
                    </div>

                    <div class="flex items-center justify-between p-3 bg-danger-50 dark:bg-danger-900/20 rounded-lg">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-x-circle class="h-5 w-5 text-danger-600" />
                            <span class="text-sm font-medium text-danger-800 dark:text-danger-200">{{ __('Failed/Cancelled') }}</span>
                        </div>
                        <span class="text-lg font-bold text-danger-600">{{ $metrics['failed_count'] ?? 0 }}</span>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Quick Actions') }}
                </x-slot>

                <div class="grid grid-cols-2 gap-4">
                    <a href="{{ route('filament.admin.resources.send-instructions.create') }}" 
                       class="flex flex-col items-center justify-center p-6 bg-primary-50 dark:bg-primary-900/20 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/40 transition">
                        <x-heroicon-o-banknotes class="h-8 w-8 text-primary-600 mb-2" />
                        <span class="text-sm font-medium text-primary-800 dark:text-primary-200">{{ __('New Payout') }}</span>
                    </a>

                    <a href="{{ route('filament.admin.resources.bank-accounts.create') }}" 
                       class="flex flex-col items-center justify-center p-6 bg-info-50 dark:bg-info-900/20 rounded-lg hover:bg-info-100 dark:hover:bg-info-900/40 transition">
                        <x-heroicon-o-building-library class="h-8 w-8 text-info-600 mb-2" />
                        <span class="text-sm font-medium text-info-800 dark:text-info-200">{{ __('Add Account') }}</span>
                    </a>

                    <a href="{{ route('filament.admin.resources.send-instructions.index') }}" 
                       class="flex flex-col items-center justify-center p-6 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-queue-list class="h-8 w-8 text-gray-600 mb-2" />
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('View Payouts') }}</span>
                    </a>

                    <a href="{{ route('filament.admin.resources.bank-accounts.index') }}" 
                       class="flex flex-col items-center justify-center p-6 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-list-bullet class="h-8 w-8 text-gray-600 mb-2" />
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('View Accounts') }}</span>
                    </a>
                </div>
            </x-filament::section>
        </div>

        {{-- Info Section --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('About CHIP Send') }}
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    CHIP Send enables you to disburse funds to verified bank accounts in Malaysia. 
                    Use this dashboard to monitor payout activity, manage bank accounts, and track disbursement performance.
                </p>
                <div class="mt-4 flex flex-wrap gap-4">
                    <div class="flex items-center gap-2 text-sm">
                        <x-heroicon-o-shield-check class="h-4 w-4 text-success-600" />
                        <span class="text-gray-600 dark:text-gray-400">Bank account verification required</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <x-heroicon-o-clock class="h-4 w-4 text-warning-600" />
                        <span class="text-gray-600 dark:text-gray-400">Same-day processing available</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <x-heroicon-o-bell-alert class="h-4 w-4 text-info-600" />
                        <span class="text-gray-600 dark:text-gray-400">Webhook notifications</span>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
