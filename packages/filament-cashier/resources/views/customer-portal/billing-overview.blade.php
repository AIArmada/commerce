<x-filament-panels::page>
    <div class="space-y-6">
        <div class="text-center py-8">
            <x-filament::icon
                icon="heroicon-o-user-circle"
                class="mx-auto h-16 w-16 text-primary-500"
            />
            <h2 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">
                {{ __('filament-cashier::portal.overview.welcome') }}
            </h2>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                {{ auth()->user()->name ?? auth()->user()->email }}
            </p>
        </div>
    </div>
</x-filament-panels::page>
