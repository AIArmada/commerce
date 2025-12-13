<x-filament-panels::page>
    <div class="mb-6">
        <x-filament::section>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('Pending Approvals') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Documents awaiting your review and approval.') }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-warning-600">
                        {{ $this->getPendingApprovalsCount() }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Pending') }}
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
