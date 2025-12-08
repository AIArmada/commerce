<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-3 p-4 bg-warning-50 dark:bg-warning-900/20 border-l-4 border-warning-500">
            <x-filament::icon 
                icon="heroicon-o-user-circle" 
                class="w-6 h-6 text-warning-500"
            />
            <div class="flex-1">
                <h3 class="text-sm font-semibold text-warning-900 dark:text-warning-100">
                    Super Admin Context
                </h3>
                <p class="text-xs text-warning-700 dark:text-warning-300 mt-1">
                    Current Roles: <strong>{{ $this->getCurrentRoleContext() }}</strong>
                </p>
                <p class="text-xs text-warning-600 dark:text-warning-400 mt-0.5">
                    You have unrestricted access. Use responsibly.
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
